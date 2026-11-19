<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Listeners;

use CraftCms\Cms\View\Events\TemplateGlobalsResolving;
use JustinHoltWeb\Dipstick\Readiness;

/**
 * `dipstick` global for Twig and Blade alike (Craft feeds template globals to
 * both): `dipstick.readiness()` / `$dipstick->readiness()`, admins only.
 */
class RegisterTemplateApi
{
    public function handle(TemplateGlobalsResolving $event): void
    {
        $event->globals['dipstick'] = app(Readiness::class);
    }
}
