---
title: How it works
slug: how-it-works
order: 20
summary: The static scan, the sandboxed runtime probe, and what each can and can't see.
---

> Dipstick is for **Craft 6**, which is **in beta**. Use caution on production.

Under PHP-FPM every request starts with a fresh PHP process, so anything a plugin keeps in a static property
or a singleton is thrown away at the end. Laravel Octane (FrankenPHP, Swoole, RoadRunner) boots the app once
and serves many requests from it. Whatever survives one request is still there for the next — including who
was signed in and which site they were on.

## The static scan

Dipstick parses every PHP file in `app/`, `modules/`, `routes/` and each installed plugin's package (not its
dependencies), plus Blade templates, with `nikic/php-parser`. Nothing is included, autoloaded or run.

Constructor captures are judged by how the class is bound: a class bound with `scoped()` or `#[Scoped]` is
rebuilt per request and is fine; one bound with `singleton()` or `#[Singleton]` is critical; a controller is
critical too, because Laravel keeps controller instances on their routes. Writes in `boot()` and
`register()` are skipped: they run once per worker, which is what they mean.

Blade is checked inside `@php` blocks and `{{ }}` echoes, lifted out without compiling the template (compiling
would run every directive handler plugins registered). Twig templates are counted, not checked: compiled Twig
can't keep state between requests.

## The runtime probe

The probe runs as `php artisan craft:dipstick:probe` in its own process. It:

1. starts a database transaction and fakes mail, notifications, queues, job dispatch and Laravel's HTTP client,
   with array cache and session stores;
2. for each round, sends each site a simulated request from a different visitor (an admin, a guest, another
   user), non-primary sites first: sets the request, site and user, builds every singleton your app and plugins
   bind, optionally renders the home page through the full HTTP stack, then resets scoped instances and auth
   guards as Octane does;
3. after each request, looks through every audited service and static property (without calling their code)
   for a request, user or site that belongs to an earlier request;
4. rolls everything back.

What it can't stop: code that writes files, or talks to the network without Laravel's HTTP client, while a page
renders. Turn off **Render pages in the probe** if your home page does that.

What it can't see: code that only runs on pages other than the home page, in queue jobs, or after a form post.
The static scan covers those.
