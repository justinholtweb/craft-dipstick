<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Probe;

use ArrayObject;
use Closure;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\User\Contracts\CraftUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use ReflectionClass;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

/**
 * Looks through what an object (or a static property) holds for a request,
 * a user or a site, without calling any of its code.
 *
 * Properties are read with `get_mangled_object_vars()`, which doesn't run
 * `__get()` or property hooks, and objects that haven't been initialised yet
 * (lazy objects) are left alone. Only objects of classes being audited, plus
 * plain arrays, collections and `stdClass`, are looked inside: a plugin that
 * holds Craft's own `Sites` service isn't blamed for what `Sites` holds.
 */
final class StateWalker
{
    public const string REQUEST = 'request';

    public const string USER = 'user';

    public const string SITE = 'site';

    private int $visited = 0;

    /** @var array<int, true> */
    private array $seen = [];

    /**
     * @param  Closure(string): bool  $owns  Whether a class is being audited (so worth looking inside).
     */
    public function __construct(
        private readonly Closure $owns,
        private readonly int $maxDepth = 4,
        private readonly int $maxNodes = 5000,
    ) {}

    public static function kindOf(mixed $value): ?string
    {
        return match (true) {
            $value instanceof Request => self::REQUEST,
            $value instanceof CraftUser, $value instanceof Authenticatable => self::USER,
            $value instanceof Site => self::SITE,
            default => null,
        };
    }

    /**
     * @return list<array{path: string, kind: string, value: object, direct: bool}>
     *                                                                              `direct` is false when the path goes through an array or collection (a per-site or per-user map).
     */
    public function find(mixed $root, string $label): array
    {
        $this->visited = 0;
        $this->seen = [];
        $found = [];
        $this->walk($root, $label, 0, true, $found);

        return $found;
    }

    /**
     * @param  list<array{path: string, kind: string, value: object, direct: bool}>  $found
     */
    private function walk(mixed $value, string $path, int $depth, bool $direct, array &$found): void
    {
        if (++$this->visited > $this->maxNodes || $depth > $this->maxDepth) {
            return;
        }

        if (is_array($value)) {
            $i = 0;

            foreach ($value as $key => $item) {
                if (++$i > 200) {
                    break;
                }

                if (is_array($item) || is_object($item)) {
                    $this->walk($item, $path.'['.self::key($key).']', $depth + 1, false, $found);
                }
            }

            return;
        }

        if (! is_object($value)) {
            return;
        }

        if (($kind = self::kindOf($value)) !== null) {
            $found[] = ['path' => $path, 'kind' => $kind, 'value' => $value, 'direct' => $direct];

            return;
        }

        $id = spl_object_id($value);

        if (isset($this->seen[$id]) || $value instanceof Closure || new ReflectionClass($value)->isUninitializedLazyObject($value)) {
            return;
        }

        $this->seen[$id] = true;

        if ($value instanceof Collection || $value instanceof ArrayObject) {
            $this->walk($value instanceof Collection ? $value->all() : $value->getArrayCopy(), $path, $depth, false, $found);

            return;
        }

        if (! $value instanceof stdClass && ! ($this->owns)($value::class)) {
            return;
        }

        foreach (get_mangled_object_vars($value) as $name => $item) {
            if (is_array($item) || is_object($item)) {
                $this->walk($item, $path.'->'.self::property((string) $name), $depth + 1, $direct, $found);
            }
        }
    }

    private static function property(string $mangled): string
    {
        $at = strrpos($mangled, "\0");

        return $at === false ? $mangled : substr($mangled, $at + 1);
    }

    private static function key(int|string $key): string
    {
        $key = (string) $key;

        return preg_match('/^[\w.\-]{1,40}$/', $key) ? $key : '…';
    }
}
