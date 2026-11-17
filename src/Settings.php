<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick;

use CraftCms\Cms\Plugin\PluginSettings;

/**
 * Blank-able properties are nullable: Laravel's ConvertEmptyStringsToNull middleware
 * turns an empty field into null before Craft assigns it.
 */
class Settings extends PluginSettings
{
    /** Scan installed plugins. Include vendor plugins, not just the app's own modules. */
    public bool $scanVendor = true;
}
