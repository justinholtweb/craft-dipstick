<?php

namespace Leaky;

use CraftCms\Cms\Plugin\Plugin;
use Illuminate\Support\Facades\Event;

class LeakyPlugin extends Plugin
{
    public function boot(): void
    {
        $this->app->scoped(ScopedThing::class);
        $this->app->singleton(BoundSingleton::class);
        Event::listen('x', function () {
            self::$flag = true;
        });
    }

    private function helper(): void
    {
        Event::listen('y', fn () => null);
    }

    private static bool $flag = false;
}
