<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick;

use CraftCms\Cms\Cp\Icons;
use CraftCms\Cms\Cp\IconSets;
use CraftCms\Cms\Plugin\PluginSettings;

/**
 * Blank-able properties are nullable: Laravel's ConvertEmptyStringsToNull middleware
 * turns an empty field into null before Craft assigns it.
 *
 * The PHP binary the probe runs with isn't a setting (an admin setting that picks
 * an executable is a foothold): set `DIPSTICK_PHP_BINARY` in the environment.
 */
class Settings extends PluginSettings
{
    /** Scan the app's own code: `app/`, `modules/` and `routes/`. */
    public bool $scanApp = true;

    /** Scan installed plugins, not just the app's own code. */
    public bool $scanVendor = true;

    /** Scan site templates (Blade is checked, Twig is counted). */
    public bool $scanTemplates = true;

    /** Paths to leave out, one glob per line, as reports show them (`app/Legacy/*`, `acme/craft-thing/src/Old.php`). */
    public ?string $excludePaths = null;

    /** Fingerprints of findings or evidence rows to ignore, one per line (a `#` starts a comment). */
    public ?string $ignore = null;

    /** Run the runtime probe as part of an audit. */
    public bool $probeEnabled = true;

    /** Render each site's home page during the probe (sandboxed: database rolled back, mail/queue/HTTP faked). */
    public bool $probeRender = true;

    /** Simulated requests per site. */
    public int $probeRequests = 3;

    /**
     * Classes the probe must never build (config file only), e.g. a service that
     * connects somewhere when constructed.
     *
     * @var list<string>|null
     */
    public ?array $probeSkip = [];

    /** Audits kept in the database. */
    public int $keepScans = 10;

    /** Show Dipstick in the sidebar (admins only). */
    public bool $showInCpNav = true;

    /** What the sidebar link says. Null uses “Dipstick”. */
    public ?string $cpNavLabel = null;

    /** A Craft icon name or `set:name` icon. Null uses Dipstick's own icon. */
    public ?string $cpNavIcon = null;

    /** @return list<string> */
    public function excludeList(): array
    {
        return self::lines($this->excludePaths);
    }

    /** @return list<string> */
    public function ignoreList(): array
    {
        return array_values(array_filter(self::lines($this->ignore), fn (string $f) => (bool) preg_match('/^[0-9a-f]{12}$/D', $f)));
    }

    /** @return list<string> */
    public function skipList(): array
    {
        return array_values(array_filter(array_map(fn ($c) => ltrim(trim((string) $c), '\\'), $this->probeSkip ?? []), fn (string $c) => $c !== ''));
    }

    public function requests(): int
    {
        return max(2, min(10, $this->probeRequests));
    }

    public function navLabel(): ?string
    {
        $label = trim((string) $this->cpNavLabel);

        return $label === '' ? null : $label;
    }

    public function navIcon(): ?string
    {
        $icon = trim((string) $this->cpNavIcon);

        if ($icon === '') {
            return null;
        }

        if ($custom = IconSets::parse($icon)) {
            $svg = rescue(fn () => app(IconSets::class)->getIconSetByHandle($custom[0])?->getSvg($custom[1]), null, false);

            return is_string($svg) && stripos($svg, '<svg') !== false ? $icon : null;
        }

        return preg_match('/^[\da-z\-]+$/D', $icon) && is_file(Icons::resolveIconPath($icon)) ? $icon : null;
    }

    /** @return list<string> Non-empty lines, `#` comments removed. */
    private static function lines(?string $value): array
    {
        $lines = array_map(fn (string $l) => trim((string) preg_replace('/#.*$/', '', $l)), preg_split('/\R/', (string) $value) ?: []);

        return array_values(array_filter($lines, fn (string $l) => $l !== ''));
    }

    /**
     * @return array<string, list<string>>
     */
    public function getRules(): array
    {
        return [
            'excludePaths' => ['nullable', 'string', 'max:10000'],
            'ignore' => ['nullable', 'string', 'max:20000'],
            'probeRequests' => ['integer', 'min:2', 'max:10'],
            'keepScans' => ['integer', 'min:1', 'max:100'],
            'probeSkip' => ['nullable', 'array'],
            'cpNavLabel' => ['nullable', 'string', 'max:100'],
            'cpNavIcon' => ['nullable', 'string', 'max:100', 'regex:/^([a-zA-Z][\w\-]*:)?[\w\-]+$/'],
        ];
    }
}
