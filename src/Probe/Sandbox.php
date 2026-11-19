<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Probe;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * Keeps the probe's simulated requests from changing anything.
 *
 * The probe runs in its own short-lived PHP process (`craft:dipstick:probe`),
 * never in a web or queue worker, and inside it:
 *
 * - every database write happens in a transaction that's rolled back;
 * - mail, notifications, queued jobs, dispatched jobs and outgoing HTTP
 *   (Laravel's client) are faked, so nothing is sent or run;
 * - the cache and session are in-memory arrays, and broadcasting is off.
 *
 * What it can't stop: code that writes files, or talks to the network
 * without Laravel's HTTP client, while a page renders. Turn page rendering
 * off in the settings if a site's home page does that.
 */
final class Sandbox
{
    private bool $entered = false;

    public function enter(): void
    {
        if ($this->entered) {
            return;
        }

        config([
            'cache.default' => 'array',
            'session.driver' => 'array',
            'mail.default' => 'array',
            'broadcasting.default' => 'null',
            'queue.default' => 'null',
        ]);

        foreach (['cache', 'cache.store', 'session', 'session.store'] as $service) {
            app()->forgetInstance($service);
            Facade::clearResolvedInstance($service);
        }

        Cache::setDefaultDriver('array');
        Mail::fake();
        Notification::fake();
        Queue::fake();
        Bus::fake();
        Http::fake();
        Http::preventStrayRequests();

        DB::beginTransaction();
        $this->entered = true;
    }

    public function leave(): void
    {
        if (! $this->entered) {
            return;
        }

        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        $this->entered = false;
    }
}
