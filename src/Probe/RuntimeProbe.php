<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Probe;

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\View\TemplateMode;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use JustinHoltWeb\Dipstick\Findings\Finding;
use JustinHoltWeb\Dipstick\Findings\Rules;
use JustinHoltWeb\Dipstick\Findings\Severity;
use JustinHoltWeb\Dipstick\Scan\Source;
use JustinHoltWeb\Dipstick\Scan\SourceLocator;
use JustinHoltWeb\Dipstick\Scan\StaticScanner;
use JustinHoltWeb\Dipstick\Settings;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Throwable;

use function CraftCms\Cms\craftAuth;

/**
 * The runtime probe: one application, many simulated requests, as an Octane
 * worker would serve them.
 *
 * For each round, every site gets a request from a different visitor (an
 * admin, a guest, another user). Before each request the probe sets the
 * request, site and user the way Octane does; it optionally renders the
 * site's home page through the HTTP kernel; afterwards it flushes scoped
 * instances and auth guards, as Octane does. Then it looks through every
 * audited service in the container, and every audited class's static
 * properties, for a request, user or site that belongs to an earlier request.
 *
 * Runs only inside the {@see Sandbox}, in its own process.
 */
final class RuntimeProbe
{
    public const string HEADER = 'X-Dipstick-Probe';

    private const string SELF = 'JustinHoltWeb\\Dipstick\\';

    /** @var list<Source> */
    private array $sources = [];

    /** @var array<string, string> Abstract => exception class, for bindings that couldn't be built. */
    private array $unresolvable = [];

    /** @var list<array{rule: string, site: string, target: string, path: string, source: string, row: array<string, scalar|null>}> */
    private array $leaks = [];

    /** @var array<string, array<string, int>> Holder path => value key => request number it first appeared in. */
    private array $firstSeen = [];

    /** @var array<int, array{site: Site, user: int}> Request number => its context. */
    private array $contexts = [];

    /** @var array<string, list<array{count: int|null, hash: string}>> */
    private array $statics = [];

    /** @var array<string, list<int>> Site handle => memory after each request. */
    private array $memory = [];

    /** @var array<string, string> Site handle => exception class from rendering. */
    private array $renderErrors = [];

    private int $requestNumber = 0;

    private ?StateWalker $walker = null;

    public function __construct(
        private readonly Application $app,
        private readonly Settings $settings,
        private readonly SourceLocator $locator,
    ) {}

    /**
     * @return array{findings: list<Finding>, meta: array<string, scalar|null>}
     */
    public function run(?int $requests = null, ?bool $render = null): array
    {
        $requests ??= $this->settings->requests();
        $render ??= $this->settings->probeRender;
        $this->sources = $this->locator->codeSources();
        $this->walker = new StateWalker($this->owns(...));
        $sites = $this->orderedSites();
        $users = $this->users();
        $sandbox = new Sandbox;
        $sandbox->enter();

        try {
            $candidates = $this->candidates();

            for ($round = 0; $round < $requests; $round++) {
                foreach ($sites as $i => $site) {
                    $user = $users[($round + $i) % count($users)];
                    $this->simulate($site, $user, $candidates, $render);
                }
            }
        } finally {
            $sandbox->leave();
        }

        return [
            'findings' => $this->findings($sites, $requests, $render),
            'meta' => [
                'sites' => count($sites),
                'requests' => $this->requestNumber,
                'rendered' => $render,
                'services' => count($this->instances()),
                'unresolvable' => count($this->unresolvable),
            ],
        ];
    }

    /**
     * Enabled sites, a non-primary one first, so a service that only ever
     * holds the primary site isn't mistaken for one that captured it.
     *
     * @return list<Site>
     */
    private function orderedSites(): array
    {
        $sites = Sites::getAllSites(false)->values()->all();
        usort($sites, fn (Site $a, Site $b) => [(int) $a->primary, $a->sortOrder] <=> [(int) $b->primary, $b->sortOrder]);

        return $sites;
    }

    /**
     * Visitors for the simulated requests: an admin, a guest and a non-admin
     * user, where they exist. Only read, never changed.
     *
     * @return list<User|null>
     */
    private function users(): array
    {
        $admin = User::find()->admin(true)->status(User::STATUS_ACTIVE)->orderBy('elements.id')->one();
        $other = User::find()->admin(false)->status(User::STATUS_ACTIVE)->orderBy('elements.id')->one();
        $users = [];

        if ($admin instanceof User) {
            $users[] = $admin;
        }

        $users[] = null;

        if ($other instanceof User) {
            $users[] = $other;
        }

        return $users;
    }

    /**
     * Shared bindings of audited classes, plus singletons the static scan found.
     *
     * @return list<string>
     */
    private function candidates(): array
    {
        $skip = array_flip($this->settings->skipList());
        $candidates = [];

        foreach ($this->app->getBindings() as $abstract => $binding) {
            if (($binding['shared'] ?? false) && $this->owns($abstract)) {
                $candidates[] = $abstract;
            }
        }

        $scanner = new StaticScanner;
        $scanner->configure($this->settings->excludeList())->scan($this->sources);
        $candidates = [...$candidates, ...array_filter($scanner->singletons, $this->owns(...))];

        return array_values(array_filter(array_unique($candidates), fn (string $c) => ! isset($skip[$c])));
    }

    /** @param  list<string>  $candidates */
    private function simulate(Site $site, ?User $user, array $candidates, bool $render): void
    {
        $n = ++$this->requestNumber;
        $this->contexts[$n] = ['site' => $site, 'user' => (int) $user?->id];

        $request = Request::create($site->getBaseUrl() ?: (string) config('app.url'), 'GET');
        $request->headers->set(self::HEADER, (string) $n);
        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');
        Sites::setCurrentSite($site);
        $user !== null ? craftAuth()->setUser($user) : craftAuth()->forgetUser();

        // Services built during this request see this request's site and user.
        foreach ($candidates as $abstract) {
            if (! isset($this->unresolvable[$abstract]) && ! $this->app->resolved($abstract)) {
                try {
                    $this->app->make($abstract);
                } catch (Throwable $e) {
                    $this->unresolvable[$abstract] = $e::class;
                }
            }
        }

        if ($render) {
            $this->render($site, $request);
        }

        // What Octane does between requests.
        $this->app->forgetScopedInstances();
        $this->app->make('auth')->forgetGuards();
        Facade::clearResolvedInstance('auth');

        $this->inspect($n);

        if ($render) {
            gc_collect_cycles();
            $this->memory[$site->handle][] = memory_get_usage();
        }
    }

    private function render(Site $site, Request $request): void
    {
        $kernel = $this->app->make(Kernel::class);

        try {
            $response = TemplateMode::with(TemplateMode::Site, fn () => $kernel->handle($request));
            $kernel->terminate($request, $response);

            if ($response->getStatusCode() >= 500) {
                $exception = property_exists($response, 'exception') ? $response->exception : null;
                $this->renderErrors[$site->handle] ??= is_object($exception) ? $exception::class : 'HTTP '.$response->getStatusCode();
            }
        } catch (Throwable $e) {
            $this->renderErrors[$site->handle] ??= $e::class;
        }
    }

    /** Looks for request, user and site values carried into request `$n`. */
    private function inspect(int $n): void
    {
        assert($this->walker !== null);

        foreach ($this->instances() as $label => $object) {
            foreach ($this->walker->find($object, $label) as $found) {
                $this->judge($n, $found, $object::class);
            }
        }

        foreach ($this->staticProperties() as $class => $properties) {
            foreach ($properties as $name => $value) {
                $label = $class.'::$'.$name;
                $this->track($label, $value);

                foreach ($this->walker->find($value, $label) as $found) {
                    $this->judge($n, $found, $class);
                }
            }
        }
    }

    /**
     * @param  array{path: string, kind: string, value: object, direct: bool}  $found
     */
    private function judge(int $n, array $found, string $class): void
    {
        $context = $this->contexts[$n];
        $value = $found['value'];

        if ($found['kind'] === StateWalker::REQUEST) {
            assert($value instanceof SymfonyRequest);
            $from = (int) $value->headers->get(self::HEADER, '0');

            if ($from !== $n) {
                $this->leak('probe-request-leak', $n, $found['path'], $class, $from > 0
                    ? ['from' => $from, 'fromSite' => $this->contexts[$from]['site']->handle]
                    : ['from' => null, 'fromSite' => null]);
            }

            return;
        }

        // A user or site inside an array is usually a map keyed by ID, which is fine.
        if (! $found['direct']) {
            return;
        }

        $key = $found['kind'] === StateWalker::SITE
            ? (int) ($value instanceof Site ? $value->id : 0)
            : (int) (method_exists($value, 'getAuthIdentifier') ? $value->getAuthIdentifier() : 0);
        $current = $found['kind'] === StateWalker::SITE ? (int) $context['site']->id : $context['user'];
        $first = $this->firstSeen[$found['path']]['k'.$key] ??= $n;
        $capturedFrom = $found['kind'] === StateWalker::SITE ? (int) $this->contexts[$first]['site']->id : $this->contexts[$first]['user'];

        // Captured: it first appeared during a request whose site/user it is, and it's still here for another.
        if ($capturedFrom === $key && $key !== $current) {
            $this->leak($found['kind'] === StateWalker::SITE ? 'probe-site-leak' : 'probe-user-leak', $n, $found['path'], $class, [
                'from' => $first,
                'fromSite' => $this->contexts[$first]['site']->handle,
                'held' => $found['kind'] === StateWalker::SITE ? ($value instanceof Site ? $value->handle : null) : ($key > 0 ? 'user #'.$key : 'guest'),
                'current' => $found['kind'] === StateWalker::SITE ? $context['site']->handle : ($current > 0 ? 'user #'.$current : 'guest'),
            ]);
        }
    }

    /** @param  array<string, scalar|null>  $detail */
    private function leak(string $rule, int $n, string $path, string $class, array $detail): void
    {
        [$source, $file] = $this->locate($class);
        $target = (string) preg_replace('/^([^\[\->]+(?:->|::\$)[^\[\->]+).*$/', '$1', $path);

        $this->leaks[] = [
            'rule' => $rule,
            'site' => $this->contexts[$n]['site']->handle,
            'target' => $target,
            'path' => $path,
            'source' => $source,
            'row' => ['site' => $this->contexts[$n]['site']->handle, 'request' => $n, 'holder' => $path, 'file' => $file, ...$detail],
        ];
    }

    /** Shape of a static property after each request, to spot growth and churn. */
    private function track(string $label, mixed $value): void
    {
        $count = is_array($value) || $value instanceof \Countable ? count($value) : null;
        $hash = match (true) {
            is_array($value) => md5(json_encode(array_map(fn ($v) => is_scalar($v) || $v === null ? $v : get_debug_type($v).(is_object($v) ? '#'.spl_object_id($v) : ''), $value)) ?: ''),
            is_object($value) => $value::class.'#'.spl_object_id($value),
            default => md5(var_export($value, true)),
        };

        $this->statics[$label][] = ['count' => $count, 'hash' => $hash];
    }

    /**
     * Resolved shared instances of audited classes, keyed by a label.
     *
     * @return array<string, object>
     */
    private function instances(): array
    {
        $instances = new ReflectionProperty($this->app, 'instances')->getValue($this->app);
        $out = [];

        foreach (is_array($instances) ? $instances : [] as $abstract => $object) {
            if (is_object($object) && $this->owns($object::class)) {
                $out[$object::class] = $object;
            }
        }

        ksort($out);

        return $out;
    }

    /**
     * Initialised static properties declared by loaded, audited classes.
     *
     * @return array<string, array<string, mixed>>
     */
    private function staticProperties(): array
    {
        $out = [];

        foreach (get_declared_classes() as $class) {
            if (! $this->owns($class)) {
                continue;
            }

            foreach (new ReflectionClass($class)->getProperties(ReflectionProperty::IS_STATIC) as $property) {
                if ($property->getDeclaringClass()->getName() === $class && $property->isInitialized()) {
                    $out[$class][$property->getName()] = $property->getValue();
                }
            }
        }

        return $out;
    }

    private function owns(string $class): bool
    {
        if (str_starts_with(ltrim($class, '\\'), self::SELF)) {
            return false;
        }

        foreach ($this->sources as $source) {
            if ($source->ownsClass($class)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{string, string|null} Source name and displayed file for a loaded class. */
    private function locate(string $class): array
    {
        $file = class_exists($class, false) ? new ReflectionClass($class)->getFileName() : false;

        foreach ($this->sources as $source) {
            if ($source->ownsClass($class)) {
                return [$source->name, $file === false ? null : $source->display((string) realpath($file))];
            }
        }

        return ['app', null];
    }

    /**
     * @param  list<Site>  $sites
     * @return list<Finding>
     */
    private function findings(array $sites, int $rounds, bool $render): array
    {
        $findings = [];
        $grouped = [];

        foreach ($this->leaks as $leak) {
            $key = $leak['rule'].'|'.$leak['target'];
            $grouped[$key] ??= ['leak' => $leak, 'rows' => [], 'sites' => []];
            $grouped[$key]['rows'][] = $leak['row'];
            $grouped[$key]['sites'][$leak['site']] = true;
        }

        $leakySites = [];

        foreach ($grouped as $group) {
            $leak = $group['leak'];
            $leakySites += $group['sites'];
            $findings[] = new Finding(
                rule: $leak['rule'],
                severity: Rules::severity($leak['rule']),
                kind: 'runtime',
                source: $leak['source'],
                site: $leak['site'],
                target: $leak['target'],
                evidence: array_slice($group['rows'], 0, StaticScanner::MAX_EVIDENCE),
                occurrences: count($group['rows']),
            );
        }

        foreach ($this->statics as $label => $history) {
            if (count($history) < 3) {
                continue;
            }

            $counts = array_column($history, 'count');
            $class = explode('::', $label)[0];
            [$source, $file] = $this->locate($class);
            $grows = ! in_array(null, $counts, true) && self::increasing(array_map(intval(...), $counts));
            $changes = count(array_unique(array_column(array_slice($history, 1), 'hash'))) === count($history) - 1;

            if ($grows) {
                $findings[] = new Finding('probe-static-growth', Severity::Warning, 'runtime', $source, target: $label,
                    evidence: [['holder' => $label, 'file' => $file, 'sizes' => implode(' → ', $counts)]]);
            } elseif ($changes && ! isset($grouped['probe-request-leak|'.$label])) {
                $findings[] = new Finding('probe-static-mutation', Severity::Notice, 'runtime', $source, target: $label,
                    evidence: [['holder' => $label, 'file' => $file, 'requests' => count($history)]]);
            }
        }

        foreach ($this->memory as $handle => $samples) {
            $grew = array_slice($samples, 1);

            if (count($grew) >= 2 && self::increasing($grew) && ($perRequest = (int) ((end($grew) - $grew[0]) / (count($grew) - 1))) > 256 * 1024) {
                $findings[] = new Finding('probe-memory-growth', Severity::Warning, 'runtime', 'app', site: $handle,
                    params: ['perRequest' => self::bytes($perRequest)],
                    evidence: [['site' => $handle, 'samples' => implode(' → ', array_map(self::bytes(...), $samples))]]);
            }
        }

        foreach ($this->renderErrors as $handle => $exception) {
            $findings[] = new Finding('probe-render-error', Severity::Notice, 'runtime', 'app', site: $handle, params: ['exception' => $exception]);
        }

        $bySource = [];

        foreach ($this->unresolvable as $abstract => $exception) {
            $bySource[$this->locate($abstract)[0]][] = ['service' => $abstract, 'error' => $exception];
        }

        foreach ($bySource as $source => $rows) {
            $findings[] = new Finding('probe-unresolvable', Severity::Notice, 'runtime', $source, evidence: $rows, occurrences: count($rows));
        }

        foreach ($sites as $site) {
            if (! isset($leakySites[$site->handle])) {
                $findings[] = new Finding('probe-clean', Severity::Pass, 'runtime', 'app', site: $site->handle, params: ['requests' => $rounds]);
            }
        }

        return $findings;
    }

    /** @param  list<int>  $values */
    private static function increasing(array $values): bool
    {
        for ($i = 1, $n = count($values); $i < $n; $i++) {
            if ($values[$i] <= $values[$i - 1]) {
                return false;
            }
        }

        return count($values) > 1;
    }

    private static function bytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : round($bytes / 1024).' KB';
    }
}
