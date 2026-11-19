<?php

// Dipstick harness fixture (copied to app/Providers/DipstickFixtureProvider.php by tests/Harness/check.sh, removed after).
// Binds the leaky singleton, and on every handled request records it (static growth). On the probe's
// simulated requests (X-Dipstick-Probe header) it also tries side effects the sandbox must swallow:
// a database row and an email.

namespace App\Providers;

use App\Dipstick\LeakyContext;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class DipstickFixtureProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LeakyContext::class);
    }

    public function boot(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $e): void {
            if (! $e->request->headers->has('X-Dipstick-Probe')) {
                return;
            }

            LeakyContext::remember($e->request);
            DB::table('dipstick_scans')->insert(['status' => 'sandbox', 'trigger' => 'cli', 'dateCreated' => Carbon::now(), 'dateUpdated' => Carbon::now(), 'uid' => (string) Str::uuid()]);
            Mail::raw('Sent from a simulated request.', fn ($m) => $m->to('sandbox@dipstick.test')->subject('dipstick-sandbox'));
        });
    }
}
