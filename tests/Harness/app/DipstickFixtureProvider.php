<?php

// Dipstick harness fixture (copied to app/Providers/DipstickFixtureProvider.php by tests/Harness/check.sh, removed after).

namespace App\Providers;

use App\Dipstick\LeakyContext;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DipstickFixtureProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LeakyContext::class);
    }

    public function boot(): void
    {
        Event::listen(RequestHandled::class, fn (RequestHandled $e) => LeakyContext::remember($e->request));
    }
}
