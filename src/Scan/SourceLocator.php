<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Scan;

use Composer\InstalledVersions;
use CraftCms\Cms\Support\Facades\Plugins;
use JustinHoltWeb\Dipstick\Settings;
use Throwable;

/**
 * What an audit covers, from the settings: the app (`app/`, `modules/`,
 * `routes/`), each installed plugin's package, and the site templates.
 */
class SourceLocator
{
    public function __construct(private readonly Settings $settings) {}

    /** @return list<Source> */
    public function sources(): array
    {
        $sources = [];

        if ($this->settings->scanApp && ($app = $this->app()) !== null) {
            $sources[] = $app;
        }

        if ($this->settings->scanVendor) {
            $sources = [...$sources, ...$this->plugins()];
        }

        if ($this->settings->scanTemplates && ($templates = $this->templates()) !== null) {
            $sources[] = $templates;
        }

        return $sources;
    }

    /**
     * Sources with PHP classes (what the runtime probe looks at).
     *
     * @return list<Source>
     */
    public function codeSources(): array
    {
        return array_values(array_filter($this->sources(), fn (Source $s) => $s->kind !== Source::KIND_TEMPLATES));
    }

    private function app(): ?Source
    {
        $root = realpath(base_path());

        if ($root === false) {
            return null;
        }

        $paths = array_values(array_filter(array_map(fn (string $d) => $root.'/'.$d, ['app', 'modules', 'routes']), is_dir(...)));

        if ($paths === []) {
            return null;
        }

        $namespaces = array_keys($this->psr4($root));

        return new Source('app', Source::KIND_APP, $root, '', $paths, $namespaces !== [] ? $namespaces : ['App\\']);
    }

    /** @return list<Source> */
    private function plugins(): array
    {
        $sources = [];

        foreach (Plugins::getAllPlugins() as $plugin) {
            $package = $plugin->packageName;

            if ($package === null || ! preg_match('/^[a-z0-9_.\-]+\/[a-z0-9_.\-]+$/D', $package)) {
                continue;
            }

            try {
                $installed = InstalledVersions::isInstalled($package) ? InstalledVersions::getInstallPath($package) : null;
            } catch (Throwable) {
                $installed = null;
            }

            $root = $installed !== null ? realpath($installed) : false;

            if ($root === false) {
                continue;
            }

            $psr4 = $this->psr4($root);
            $paths = array_values(array_unique(array_filter([...array_values($psr4), $root.'/resources', $root.'/routes'], is_dir(...))));

            if ($paths !== []) {
                $sources[$package] = new Source($package, Source::KIND_PLUGIN, $root, $package.'/', $paths, array_keys($psr4));
            }
        }

        ksort($sources);

        return array_values($sources);
    }

    private function templates(): ?Source
    {
        $root = realpath(base_path());

        if ($root === false) {
            return null;
        }

        $paths = array_values(array_filter([$root.'/resources/views', $root.'/templates'], is_dir(...)));

        return $paths === [] ? null : new Source('templates', Source::KIND_TEMPLATES, $root, '', $paths);
    }

    /**
     * PSR-4 prefixes from a package's composer.json, mapped to directories inside it.
     *
     * @return array<string, string>
     */
    private function psr4(string $root): array
    {
        $json = @file_get_contents($root.'/composer.json');
        $config = $json === false ? null : json_decode($json, true);
        $map = [];

        foreach ((is_array($config) ? ($config['autoload']['psr-4'] ?? []) : []) as $prefix => $dirs) {
            foreach ((array) $dirs as $dir) {
                $path = realpath($root.'/'.trim((string) $dir, '/'));

                // Only directories inside the package: `../` can't widen the scan.
                if ($path !== false && is_dir($path) && str_starts_with($path.'/', $root.'/') && is_string($prefix)) {
                    $map[$prefix] = $path;
                }
            }
        }

        return $map;
    }
}
