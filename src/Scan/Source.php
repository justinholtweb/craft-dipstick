<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Scan;

/**
 * Something Dipstick scans: the app's own code, an installed plugin's package,
 * or the site templates.
 *
 * Files are shown relative to the project (`app/Services/Cart.php`) or, for a
 * package outside it, as `<package>/<path in package>`, never as an absolute path.
 */
final class Source
{
    public const string KIND_APP = 'app';

    public const string KIND_PLUGIN = 'plugin';

    public const string KIND_TEMPLATES = 'templates';

    /**
     * @param  string  $name  `app`, `templates` or the Composer package name.
     * @param  string  $root  Absolute directory files are shown relative to.
     * @param  string  $displayPrefix  Put in front of the relative path (`vendor/package/`), or ''.
     * @param  list<string>  $paths  Absolute directories (or files) to scan.
     * @param  list<string>  $namespaces  PSR-4 prefixes the source owns, e.g. `App\`.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $kind,
        public readonly string $root,
        public readonly string $displayPrefix,
        public readonly array $paths,
        public readonly array $namespaces = [],
    ) {}

    /** The file as shown in reports, or null if it isn't under this source's root. */
    public function display(string $file): ?string
    {
        $root = rtrim($this->root, '/').'/';

        if (! str_starts_with($file, $root)) {
            return null;
        }

        return $this->displayPrefix.substr($file, strlen($root));
    }

    public function ownsClass(string $class): bool
    {
        $class = ltrim($class, '\\');

        foreach ($this->namespaces as $prefix) {
            if ($prefix !== '' && str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
