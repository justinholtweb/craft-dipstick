<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick;

use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Cms\Gql\GqlQueries;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Ui\Controls\IconPicker;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\View\Events\TemplateGlobalsResolving;
use Override;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Dipstick — check the engine before you run Craft on Octane. Finds state that
 * leaks between requests in the app, installed plugins and templates, with a
 * static scan and a sandboxed runtime probe. Admins only.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;

    protected array $events = [
        GqlSchemaComponentsResolving::class => Listeners\RegisterGqlComponents::class,
        TemplateGlobalsResolving::class => Listeners\RegisterTemplateApi::class,
    ];

    protected array $commands = [
        Commands\ScanCommand::class,
        Commands\ProbeCommand::class,
    ];

    public function boot(): void
    {
        $this->app->bind(Settings::class, fn (): Settings => $this->getSettings());
        // Rebuilt per request (and per job): nothing here holds request state anyway.
        $this->app->scoped(Audit\Scans::class);
        $this->app->scoped(Readiness::class);
        $this->hasCpSection = $this->getSettings()->showInCpNav;
        $this->app->make(GqlQueries::class)->register(Gql\ReadinessQuery::class);
    }

    /** The sidebar link, for admins only. */
    #[Override]
    public function getCpNavItem(): NavItem|array|null
    {
        if (! currentUser()?->isAdmin()) {
            return null;
        }

        $settings = $this->getSettings();

        return new NavItem()
            ->label($settings->navLabel() ?? $this->name)
            ->href('dipstick')
            ->icon($settings->navIcon() ?? $this->cpNavIconPath());
    }

    #[Override]
    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        return Ui::make([
            Field::make(t('Scan the app’s code', category: 'dipstick'), Lightswitch::make('scanApp'))
                ->instructions(t('`app/`, `modules/` and `routes/`.', category: 'dipstick')),
            Field::make(t('Scan installed plugins', category: 'dipstick'), Lightswitch::make('scanVendor'))
                ->instructions(t('Each installed plugin’s own code (its Composer package), not its dependencies.', category: 'dipstick')),
            Field::make(t('Scan templates', category: 'dipstick'), Lightswitch::make('scanTemplates'))
                ->instructions(t('Blade templates are checked; Twig templates can’t keep state between requests, so they’re only counted.', category: 'dipstick')),
            Field::make(t('Leave out', category: 'dipstick'), Textarea::make('excludePaths')->rows(3)->monospace()->placeholder("app/Legacy/*\nacme/craft-thing/src/Old.php"))
                ->instructions(t('Paths to skip, one per line, as the report shows them. `*` matches anything.', category: 'dipstick')),
            Field::make(t('Ignore', category: 'dipstick'), Textarea::make('ignore')->rows(3)->monospace()->placeholder('3f9a1c2b7d4e  # cache is reset per request'))
                ->instructions(t('Fingerprints of findings (or single rows of evidence) you’ve checked and accept, one per line. A `#` starts a comment. You can also put `@dipstick-ignore` in a comment on, or just above, a line of code.', category: 'dipstick')),
            Field::make(t('Run the runtime probe', category: 'dipstick'), Lightswitch::make('probeEnabled'))
                ->instructions(t('After the static scan, simulate several requests per site in one process, as an Octane worker would, and look for state carried between them. Runs in its own short-lived process with the database rolled back and mail, queues and HTTP faked.', category: 'dipstick')),
            Field::make(t('Render pages in the probe', category: 'dipstick'), Lightswitch::make('probeRender'))
                ->instructions(t('Render each site’s home page through the full request stack. Catches more, but runs your templates and plugins’ request code. Turn off if the home page writes files or calls services outside Laravel’s HTTP client.', category: 'dipstick')),
            Field::make(t('Requests per site', category: 'dipstick'), Number::make('probeRequests')->min(2)->max(10))
                ->instructions(t('How many simulated requests each site gets, rotating between an admin, a guest and another user.', category: 'dipstick')),
            Field::make(t('Audits to keep', category: 'dipstick'), Number::make('keepScans')->min(1)->max(100)),
            Field::make(t('Show in the sidebar', category: 'dipstick'), Lightswitch::make('showInCpNav'))
                ->instructions(t('Only admins see it.', category: 'dipstick')),
            Field::make(t('Sidebar label', category: 'dipstick'), Text::make('cpNavLabel')->placeholder('Dipstick')),
            Field::make(t('Sidebar icon', category: 'dipstick'), IconPicker::make('cpNavIcon')->sets('*'))
                ->instructions(t('Leave blank for Dipstick’s own icon.', category: 'dipstick')),
        ]);
    }
}
