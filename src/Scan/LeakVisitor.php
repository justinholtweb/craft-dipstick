<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Scan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

/**
 * Walks one file's syntax tree (names already resolved) and records the
 * patterns that leak state between requests in a long-lived worker.
 *
 * It never loads, includes or runs the code it reads.
 *
 * Constructor captures are recorded per class rather than reported straight
 * away: whether they matter depends on how the class is bound, which can be
 * declared in another file. {@see StaticScanner} joins them up.
 */
final class LeakVisitor extends NodeVisitorAbstract
{
    /** Types whose instances belong to one request. */
    public const array REQUEST_TYPES = [
        'Illuminate\Http\Request' => 'request',
        'Symfony\Component\HttpFoundation\Request' => 'request',
        'Illuminate\Contracts\Session\Session' => 'request',
        'Illuminate\Session\Store' => 'request',
        'Illuminate\Contracts\Auth\Authenticatable' => 'user',
        'CraftCms\Cms\User\Elements\User' => 'user',
        'CraftCms\Cms\User\Contracts\CraftUser' => 'user',
        'CraftCms\Cms\Site\Data\Site' => 'site',
    ];

    private const array SUPERGLOBALS = ['_GET', '_POST', '_REQUEST', '_COOKIE', '_FILES', '_SESSION', '_SERVER'];

    private const array NATIVE_RESPONSE = ['header', 'header_remove', 'setcookie', 'setrawcookie', 'http_response_code', 'session_start', 'session_regenerate_id', 'session_destroy', 'session_write_close', 'session_id', 'session_unset'];

    /** Functions that change the whole process; the value is the minimum argument count that makes it a change. */
    private const array PROCESS_STATE = ['date_default_timezone_set' => 1, 'setlocale' => 1, 'ini_set' => 2, 'ini_alter' => 2, 'set_error_handler' => 1, 'set_exception_handler' => 1, 'putenv' => 1, 'chdir' => 1, 'umask' => 1, 'error_reporting' => 1, 'mb_internal_encoding' => 1, 'set_include_path' => 1, 'mt_srand' => 1, 'srand' => 1];

    /** @var list<array{rule: string, line: int, symbol: string, target: string|null}> */
    public array $hits = [];

    /** @var array<string, array{line: int, singleton: bool, scoped: bool, controller: bool, captures: list<array{kind: string, line: int}>}> */
    public array $classes = [];

    /** @var array<string, string> Class => `singleton` or `scoped`, from `->singleton(X::class)` style bindings. */
    public array $bindings = [];

    /** @var list<array{node: Stmt\ClassLike|null, name: string|null}> */
    private array $classStack = [];

    /** @var list<Node\FunctionLike> */
    private array $functionStack = [];

    private NodeFinder $finder;

    /**
     * @param  bool  $template  Top-level code runs on every render (templates), not once at boot.
     */
    public function __construct(private readonly bool $template = false)
    {
        $this->finder = new NodeFinder;
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Stmt\ClassLike) {
            $name = $node->namespacedName?->toString();
            $this->classStack[] = ['node' => $node, 'name' => $name];

            if ($name !== null && $node instanceof Stmt\Class_) {
                $this->registerClass($node, $name);
            }

            return null;
        }

        if ($node instanceof Node\FunctionLike) {
            $this->functionStack[] = $node;

            if ($node instanceof Stmt\ClassMethod && $node->name->toLowerString() === '__construct') {
                $this->recordConstructorCaptures($node);
            }

            return null;
        }

        match (true) {
            $node instanceof Expr\Assign, $node instanceof Expr\AssignOp, $node instanceof Expr\AssignRef => $this->checkWrite($node->var, $node->expr, $node),
            $node instanceof Expr\PreInc, $node instanceof Expr\PostInc, $node instanceof Expr\PreDec, $node instanceof Expr\PostDec => $this->checkWrite($node->var, null, $node),
            $node instanceof Stmt\Static_ => $this->hit('static-variable', $node),
            $node instanceof Stmt\Global_ => $this->hit('global-state', $node),
            $node instanceof Expr\Variable => $this->checkVariable($node),
            $node instanceof Expr\Exit_ => $this->inConsole() ? null : $this->hit('worker-exit', $node),
            $node instanceof Expr\FuncCall => $this->checkFunctionCall($node),
            $node instanceof Expr\StaticCall, $node instanceof Expr\MethodCall => $this->checkCall($node),
            default => null,
        };

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Stmt\ClassLike) {
            array_pop($this->classStack);
        } elseif ($node instanceof Node\FunctionLike) {
            array_pop($this->functionStack);
        }

        return null;
    }

    /**
     * What kind of per-request state an expression reads, if any: `request`,
     * `user` or `site`.
     */
    public function requestState(Node $expr): ?string
    {
        $kind = null;

        $this->finder->findFirst($expr, function (Node $n) use (&$kind): bool {
            $kind = $this->stateOf($n);

            return $kind !== null;
        });

        return $kind;
    }

    private function stateOf(Node $n): ?string
    {
        if ($n instanceof Expr\FuncCall && $n->name instanceof Name) {
            $function = strtolower($n->name->getLast());

            return match ($function) {
                'request', 'session', 'cookie' => 'request',
                'currentuser', 'currentuserelement' => 'user',
                'app', 'resolve' => $this->namesRequest($n->args[0] ?? null) ? 'request' : null,
                default => null,
            };
        }

        if (($n instanceof Expr\MethodCall || $n instanceof Expr\NullsafeMethodCall) && $n->name instanceof Node\Identifier) {
            $method = $n->name->toLowerString();

            if ($method === 'getcurrentsite') {
                return 'site';
            }

            if (in_array($method, ['user', 'id'], true) && $n->var instanceof Expr\FuncCall && $n->var->name instanceof Name
                && in_array(strtolower($n->var->name->getLast()), ['auth', 'craftauth'], true)) {
                return 'user';
            }

            if ($method === 'make' && $this->namesRequest($n->args[0] ?? null)) {
                return 'request';
            }

            return null;
        }

        if ($n instanceof Expr\StaticCall && $n->class instanceof Name && $n->name instanceof Node\Identifier) {
            $class = ltrim($n->class->toString(), '\\');
            $method = $n->name->toLowerString();
            $facade = str_starts_with($class, 'Illuminate\Support\Facades\\') ? substr($class, 27) : (str_contains($class, '\\') ? null : $class);

            return match (true) {
                $facade === 'Auth' && in_array($method, ['user', 'id', 'check', 'guest'], true) => 'user',
                $facade === 'Request', $facade === 'Session' => 'request',
                $facade === 'Cookie' && $method === 'get' => 'request',
                $method === 'getcurrentsite' => 'site',
                $class === 'CraftCms\Cms\Cp\RequestedSite' => 'site',
                default => null,
            };
        }

        return null;
    }

    private function namesRequest(mixed $arg): bool
    {
        if (! $arg instanceof Node\Arg) {
            return false;
        }

        $value = $arg->value;

        if ($value instanceof Node\Scalar\String_) {
            return in_array($value->value, ['request', 'session', 'session.store'], true);
        }

        return $value instanceof Expr\ClassConstFetch && $value->class instanceof Name
            && (self::REQUEST_TYPES[ltrim($value->class->toString(), '\\')] ?? null) === 'request';
    }

    private function registerClass(Stmt\Class_ $node, string $name): void
    {
        $attributes = [];

        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $attributes[] = ltrim($attr->name->toString(), '\\');
            }
        }

        $parent = $node->extends?->toString() ?? '';

        $this->classes[$name] = [
            'line' => $node->getStartLine(),
            'singleton' => in_array('Illuminate\Container\Attributes\Singleton', $attributes, true),
            'scoped' => in_array('Illuminate\Container\Attributes\Scoped', $attributes, true),
            'controller' => str_ends_with($name, 'Controller') || str_ends_with($parent, 'Controller'),
            'captures' => [],
        ];
    }

    /** Request, user or site values a constructor keeps on the object. */
    private function recordConstructorCaptures(Stmt\ClassMethod $method): void
    {
        $class = end($this->classStack);
        $name = $class === false ? null : $class['name'];

        if ($name === null || ! isset($this->classes[$name])) {
            return;
        }

        $assignedParams = [];
        $captures = [];

        foreach ($this->finder->findInstanceOf($method->stmts ?? [], Expr\Assign::class) as $assign) {
            if (! $this->isThisProperty($assign->var)) {
                continue;
            }

            if ($assign->expr instanceof Expr\Variable && is_string($assign->expr->name)) {
                $assignedParams[$assign->expr->name] = $assign->getStartLine();
            } elseif (($kind = $this->requestState($assign->expr)) !== null) {
                $captures[] = ['kind' => $kind, 'line' => $assign->getStartLine()];
            }
        }

        foreach ($method->params as $param) {
            $kind = $this->requestType($param->type);
            $var = $param->var instanceof Expr\Variable && is_string($param->var->name) ? $param->var->name : null;

            if ($kind !== null && ($param->flags !== 0 || ($var !== null && isset($assignedParams[$var])))) {
                $captures[] = ['kind' => $kind, 'line' => $param->getStartLine()];
            }
        }

        $this->classes[$name]['captures'] = $captures;
    }

    private function requestType(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $names = $type instanceof Name ? [$type] : $this->finder->findInstanceOf($type, Name::class);

        foreach ($names as $name) {
            if (isset(self::REQUEST_TYPES[ltrim($name->toString(), '\\')])) {
                return self::REQUEST_TYPES[ltrim($name->toString(), '\\')];
            }
        }

        return null;
    }

    private function isThisProperty(Expr $var): bool
    {
        while ($var instanceof Expr\ArrayDimFetch) {
            $var = $var->var;
        }

        return ($var instanceof Expr\PropertyFetch || $var instanceof Expr\NullsafePropertyFetch)
            && $var->var instanceof Expr\Variable && $var->var->name === 'this';
    }

    private function checkWrite(Expr $var, ?Expr $value, Node $node): void
    {
        $isArray = false;
        $base = $var;

        while ($base instanceof Expr\ArrayDimFetch) {
            $isArray = true;
            $base = $base->var;
        }

        if ($base instanceof Expr\Variable && $base->name === 'GLOBALS') {
            $this->hit('global-state', $node);

            return;
        }

        if (! $base instanceof Expr\StaticPropertyFetch || $this->inBoot()) {
            return;
        }

        $target = $this->staticTarget($base);

        if ($value !== null && $this->requestState($value) !== null) {
            $this->hit('static-request-state', $node, $target);
        } else {
            $this->hit($isArray ? 'static-array-growth' : 'static-property-write', $node, $target);
        }
    }

    private function checkVariable(Expr\Variable $node): void
    {
        if (is_string($node->name) && in_array($node->name, self::SUPERGLOBALS, true) && ! $this->inConsole() && ! $this->inBoot()) {
            $this->hit('superglobal-access', $node);
        }
    }

    private function checkFunctionCall(Expr\FuncCall $node): void
    {
        if (! $node->name instanceof Name) {
            return;
        }

        $function = strtolower($node->name->getLast());

        if (in_array($function, ['array_push', 'array_unshift', 'array_splice'], true)
            && ($first = $node->args[0] ?? null) instanceof Node\Arg && $first->value instanceof Expr\StaticPropertyFetch && ! $this->inBoot()) {
            $this->hit('static-array-growth', $node, $this->staticTarget($first->value));

            return;
        }

        if ($this->inConsole()) {
            return;
        }

        if (in_array($function, self::NATIVE_RESPONSE, true)) {
            $this->hit('native-response', $node);
        } elseif (isset(self::PROCESS_STATE[$function]) && count($node->args) >= self::PROCESS_STATE[$function] && ! $this->inBoot()) {
            $this->hit('process-state', $node);
        } elseif ($function === 'register_shutdown_function' && ! $this->inBoot()) {
            $this->hit('shutdown-function', $node);
        } elseif ($function === 'define' && $this->functionStack !== [] && ! $this->guardedByDefined($node)) {
            $this->hit('runtime-define', $node);
        }
    }

    private function checkCall(Expr\StaticCall|Expr\MethodCall $node): void
    {
        if (! $node->name instanceof Node\Identifier) {
            return;
        }

        $method = $node->name->toLowerString();

        // Bindings: `$this->app->singleton(Foo::class)`, `App::scoped(Foo::class, …)`.
        if (in_array($method, ['singleton', 'singletonif', 'instance', 'scoped', 'scopedif'], true)
            && ($first = $node->args[0] ?? null) instanceof Node\Arg
            && $first->value instanceof Expr\ClassConstFetch && $first->value->class instanceof Name
            && $first->value->name instanceof Node\Identifier && $first->value->name->toLowerString() === 'class') {
            $class = ltrim($first->value->class->toString(), '\\');
            $this->bindings[$class] = str_starts_with($method, 'scoped') ? 'scoped' : ($this->bindings[$class] ?? 'singleton');

            return;
        }

        if ($node instanceof Expr\StaticCall && $node->class instanceof Name && in_array($method, ['listen', 'subscribe'], true)
            && in_array(ltrim($node->class->toString(), '\\'), ['Illuminate\Support\Facades\Event', 'Event'], true)
            && ! $this->inBoot() && ! $this->inProvider() && ! $this->inMethod('subscribe')) {
            $this->hit('listener-in-request', $node);
        }
    }

    private function staticTarget(Expr\StaticPropertyFetch $fetch): string
    {
        $class = $fetch->class instanceof Name ? $fetch->class->toString() : '?';

        if (in_array(strtolower($class), ['self', 'static', 'parent'], true)) {
            $class = $this->currentClass() ?? $class;
        }

        $property = $fetch->name instanceof Node\VarLikeIdentifier ? $fetch->name->toString() : '?';

        return ltrim($class, '\\').'::$'.$property;
    }

    private function guardedByDefined(Node $node): bool
    {
        $parent = $node->getAttribute('parent');

        while ($parent instanceof Node && ! $parent instanceof Node\FunctionLike) {
            if (($parent instanceof Stmt\If_ || $parent instanceof Expr\BinaryOp || $parent instanceof Expr\Ternary)
                && $this->finder->findFirst($parent instanceof Stmt\If_ ? $parent->cond : $parent, fn (Node $n) => $n instanceof Expr\FuncCall && $n->name instanceof Name && strtolower($n->name->getLast()) === 'defined') !== null) {
                return true;
            }

            $parent = $parent->getAttribute('parent');
        }

        return false;
    }

    /**
     * Boot code runs once per worker: `boot()`, `register()`, `bootstrap()` (and
     * `boot*`/`register*` helpers) and, outside templates, top-level code. A
     * closure defined in boot runs later, per request, so it doesn't count.
     */
    private function inBoot(): bool
    {
        $function = end($this->functionStack);

        if ($function === false) {
            return ! $this->template;
        }

        return $function instanceof Stmt\ClassMethod
            && (bool) preg_match('/^(boot|register|bootstrap|booted|booting|configure)/i', $function->name->toString());
    }

    private function inMethod(string $name): bool
    {
        $function = end($this->functionStack);

        return $function instanceof Stmt\ClassMethod && $function->name->toLowerString() === strtolower($name);
    }

    private function inConsole(): bool
    {
        $class = end($this->classStack);

        if ($class === false || ! $class['node'] instanceof Stmt\Class_) {
            return false;
        }

        $parent = $class['node']->extends?->toString() ?? '';

        return str_ends_with((string) $class['name'], 'Command') || str_ends_with($parent, 'Command');
    }

    private function inProvider(): bool
    {
        $class = end($this->classStack);

        if ($class === false || ! $class['node'] instanceof Stmt\Class_) {
            return false;
        }

        $parent = $class['node']->extends?->toString() ?? '';

        return str_ends_with($parent, 'ServiceProvider') || str_ends_with($parent, '\Plugin') || $parent === 'Plugin';
    }

    private function currentClass(): ?string
    {
        $class = end($this->classStack);

        return $class === false ? null : $class['name'];
    }

    /** `Class::method`, `function()` or `(top level)`, for evidence rows. */
    private function symbol(): string
    {
        $class = $this->currentClass();
        $method = null;

        foreach (array_reverse($this->functionStack) as $function) {
            if ($function instanceof Stmt\ClassMethod || $function instanceof Stmt\Function_) {
                $method = $function->name->toString();
                break;
            }
        }

        return match (true) {
            $class !== null && $method !== null => $class.'::'.$method.'()',
            $class !== null => $class,
            $method !== null => $method.'()',
            default => '(top level)',
        };
    }

    private function hit(string $rule, Node $node, ?string $target = null): void
    {
        $this->hits[] = ['rule' => $rule, 'line' => $node->getStartLine(), 'symbol' => $this->symbol(), 'target' => $target];
    }
}
