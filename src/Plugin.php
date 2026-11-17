<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick;

use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use Override;

use function CraftCms\Cms\t;

/**
 * Dipstick — Check the engine before you run Craft on Octane — finds state that leaks between requests in plugins and modules.
 *
 * See PLAN.md for the build phases.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;

    #[Override]
    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        return Ui::make([
            Field::make(t('Scan installed plugins', category: 'dipstick'), Lightswitch::make('scanVendor'))
                ->instructions(t('Include vendor plugins, not just the app\'s own modules.', category: 'dipstick')),
        ]);
    }
}
