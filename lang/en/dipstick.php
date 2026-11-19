<?php

// Source strings are English: this file maps each to itself so translators can copy it.

return [
    'A static property is set from the request, user or site ({count})' => 'A static property is set from the request, user or site ({count})',
    'In {source}, a value taken from the current request, signed-in user or current site is written to a static property.' => 'In {source}, a value taken from the current request, signed-in user or current site is written to a static property.',
    'Under Octane one PHP process serves many requests. A static property keeps its value between them, so the next visitor gets the previous visitor’s request, user or site: someone else’s account details, the wrong language, the wrong site’s content.' => 'Under Octane one PHP process serves many requests. A static property keeps its value between them, so the next visitor gets the previous visitor’s request, user or site: someone else’s account details, the wrong language, the wrong site’s content.',
    'Don’t keep per-request values in static properties. Read them where you need them, or keep them on a service bound with `scoped()`, which Octane throws away after every request:

```php
$this->app->scoped(CurrentShopper::class);
```

If it has to be static, reset it at the start of every request:

```php
Event::listen(\\Laravel\\Octane\\Events\\RequestReceived::class, fn () => Cart::$current = null);
```' => 'Don’t keep per-request values in static properties. Read them where you need them, or keep them on a service bound with `scoped()`, which Octane throws away after every request:

```php
$this->app->scoped(CurrentShopper::class);
```

If it has to be static, reset it at the start of every request:

```php
Event::listen(\\Laravel\\Octane\\Events\\RequestReceived::class, fn () => Cart::$current = null);
```',
    'Singletons capture the request, user or site when they’re built ({count})' => 'Singletons capture the request, user or site when they’re built ({count})',
    'In {source}, a class bound as a singleton takes the request, the signed-in user or the current site in its constructor and keeps it.' => 'In {source}, a class bound as a singleton takes the request, the signed-in user or the current site in its constructor and keeps it.',
    'A singleton is built once per Octane worker and then shared by every request that worker serves. Whatever it captured while serving the first request stays with it: later visitors see the first visitor’s user, site or request data.' => 'A singleton is built once per Octane worker and then shared by every request that worker serves. Whatever it captured while serving the first request stays with it: later visitors see the first visitor’s user, site or request data.',
    'Bind the class with `scoped()` instead of `singleton()` (or swap `#[Singleton]` for `#[Scoped]`), so it’s rebuilt for each request:

```php
$this->app->scoped(Basket::class);
```

Or keep it a singleton and ask for the current value when you need it, instead of in the constructor:

```php
public function owner(): ?User
{
    return currentUserElement();
}
```' => 'Bind the class with `scoped()` instead of `singleton()` (or swap `#[Singleton]` for `#[Scoped]`), so it’s rebuilt for each request:

```php
$this->app->scoped(Basket::class);
```

Or keep it a singleton and ask for the current value when you need it, instead of in the constructor:

```php
public function owner(): ?User
{
    return currentUserElement();
}
```',
    'Controllers capture the request, user or site in their constructor ({count})' => 'Controllers capture the request, user or site in their constructor ({count})',
    'In {source}, a controller takes the request, the signed-in user or the current site in its constructor.' => 'In {source}, a controller takes the request, the signed-in user or the current site in its constructor.',
    'Laravel keeps the controller instance on its route. Under Octane routes live for the life of the worker, so the constructor runs once and every later request reuses what the first one captured.' => 'Laravel keeps the controller instance on its route. Under Octane routes live for the life of the worker, so the constructor runs once and every later request reuses what the first one captured.',
    'Take the request in the action instead of the constructor; Laravel injects it per call:

```php
public function show(Request $request, string $slug)
{
    $user = $request->user();
}
```' => 'Take the request in the action instead of the constructor; Laravel injects it per call:

```php
public function show(Request $request, string $slug)
{
    $user = $request->user();
}
```',
    'Classes keep the request, user or site from their constructor ({count})' => 'Classes keep the request, user or site from their constructor ({count})',
    'In {source}, classes store the request, signed-in user or current site when they’re built. That’s fine while they’re built per request; it leaks if they’re ever bound as a singleton.' => 'In {source}, classes store the request, signed-in user or current site when they’re built. That’s fine while they’re built per request; it leaks if they’re ever bound as a singleton.',
    'Harmless as long as each request builds its own instance. If one of these is later bound as a singleton, or cached on a route, it starts handing one visitor’s data to the next.' => 'Harmless as long as each request builds its own instance. If one of these is later bound as a singleton, or cached on a route, it starts handing one visitor’s data to the next.',
    'Prefer passing the request, user or site to the method that needs it. If the class is resolved from the container, bind it with `scoped()` so Octane rebuilds it per request.' => 'Prefer passing the request, user or site to the method that needs it. If the class is resolved from the container, bind it with `scoped()` so Octane rebuilds it per request.',
    'Static arrays that grow with every request ({count})' => 'Static arrays that grow with every request ({count})',
    'In {source}, values are appended to, or added by key to, a static array.' => 'In {source}, values are appended to, or added by key to, a static array.',
    'Under Octane a static array is never emptied between requests. A cache keyed by URL, user or element grows until the worker runs out of memory or is recycled, and entries from one request are served to the next.' => 'Under Octane a static array is never emptied between requests. A cache keyed by URL, user or element grows until the worker runs out of memory or is recycled, and entries from one request are served to the next.',
    'Keep the cache on a `scoped()` service, or use Laravel’s `once()`, which Octane flushes after each request:

```php
return once(fn () => $this->expensiveLookup($id));
```

If a static cache is intended, cap its size, and reset it in a `RequestReceived` listener.' => 'Keep the cache on a `scoped()` service, or use Laravel’s `once()`, which Octane flushes after each request:

```php
return once(fn () => $this->expensiveLookup($id));
```

If a static cache is intended, cap its size, and reset it in a `RequestReceived` listener.',
    'Static properties written while handling requests ({count})' => 'Static properties written while handling requests ({count})',
    'In {source}, static properties are written outside of boot code.' => 'In {source}, static properties are written outside of boot code.',
    'A static property outlives the request under Octane. Flags, counters and memoised values set while serving one request are still set for the next.' => 'A static property outlives the request under Octane. Flags, counters and memoised values set while serving one request are still set for the next.',
    'Move the value onto a `scoped()` service, or reset it at the start of every request:

```php
Event::listen(\\Laravel\\Octane\\Events\\RequestReceived::class, fn () => Thing::$value = null);
```

Writes in `boot()` / `register()` are skipped: they run once per worker, which is what they mean.' => 'Move the value onto a `scoped()` service, or reset it at the start of every request:

```php
Event::listen(\\Laravel\\Octane\\Events\\RequestReceived::class, fn () => Thing::$value = null);
```

Writes in `boot()` / `register()` are skipped: they run once per worker, which is what they mean.',
    'Function-level `static` variables ({count})' => 'Function-level `static` variables ({count})',
    'In {source}, functions or methods declare `static $var`, which keeps its value across calls.' => 'In {source}, functions or methods declare `static $var`, which keeps its value across calls.',
    'A `static` variable lives as long as the PHP process. Under Octane that’s every request a worker serves, so the first request’s value is reused by all later ones.' => 'A `static` variable lives as long as the PHP process. Under Octane that’s every request a worker serves, so the first request’s value is reused by all later ones.',
    'Replace the `static` variable with `once()` (flushed by Octane after each request) or a property on a `scoped()` service:

```php
return once(fn () => $this->load());
```' => 'Replace the `static` variable with `once()` (flushed by Octane after each request) or a property on a `scoped()` service:

```php
return once(fn () => $this->load());
```',
    'Global variables ({count})' => 'Global variables ({count})',
    'In {source}, code uses `global` or writes to `$GLOBALS`.' => 'In {source}, code uses `global` or writes to `$GLOBALS`.',
    'Globals survive between requests in an Octane worker, so values set for one visitor are seen by the next.' => 'Globals survive between requests in an Octane worker, so values set for one visitor are seen by the next.',
    'Pass the value explicitly, or keep it on a `scoped()` service.' => 'Pass the value explicitly, or keep it on a `scoped()` service.',
    'Superglobals used instead of the request ({count})' => 'Superglobals used instead of the request ({count})',
    'In {source}, code reads or writes `$_GET`, `$_POST`, `$_SERVER`, `$_COOKIE`, `$_FILES`, `$_REQUEST` or `$_SESSION`.' => 'In {source}, code reads or writes `$_GET`, `$_POST`, `$_SERVER`, `$_COOKIE`, `$_FILES`, `$_REQUEST` or `$_SESSION`.',
    'Octane builds a Laravel request for each visit, but superglobals aren’t refreshed on every server: on Swoole and RoadRunner they hold whatever the worker started with. Code that reads them sees stale or empty values.' => 'Octane builds a Laravel request for each visit, but superglobals aren’t refreshed on every server: on Swoole and RoadRunner they hold whatever the worker started with. Code that reads them sees stale or empty values.',
    'Read from the request object:

```php
$page = request()->query(\'page\');
$ip = request()->ip();
$header = request()->header(\'X-Thing\');
```' => 'Read from the request object:

```php
$page = request()->query(\'page\');
$ip = request()->ip();
$header = request()->header(\'X-Thing\');
```',
    'Headers, cookies or sessions set with PHP’s own functions ({count})' => 'Headers, cookies or sessions set with PHP’s own functions ({count})',
    'In {source}, code calls `header()`, `setcookie()`, `http_response_code()` or `session_*()`.' => 'In {source}, code calls `header()`, `setcookie()`, `http_response_code()` or `session_*()`.',
    'Under Octane these functions don’t reach the response on Swoole or RoadRunner, and PHP’s native session isn’t Laravel’s. Headers and cookies go missing, or stick to the wrong response.' => 'Under Octane these functions don’t reach the response on Swoole or RoadRunner, and PHP’s native session isn’t Laravel’s. Headers and cookies go missing, or stick to the wrong response.',
    'Set them on the Laravel response, and use Laravel’s session:

```php
return response($html)
    ->header(\'X-Frame-Options\', \'DENY\')
    ->cookie(\'seen\', \'1\', 60);

session()->put(\'step\', 2);
```' => 'Set them on the Laravel response, and use Laravel’s session:

```php
return response($html)
    ->header(\'X-Frame-Options\', \'DENY\')
    ->cookie(\'seen\', \'1\', 60);

session()->put(\'step\', 2);
```',
    '`exit` or `die` while handling a request ({count})' => '`exit` or `die` while handling a request ({count})',
    'In {source}, code calls `exit` or `die`.' => 'In {source}, code calls `exit` or `die`.',
    'Octane serves many requests from one process. Exiting ends the worker: the response may be lost, and the server has to boot a new worker.' => 'Octane serves many requests from one process. Exiting ends the worker: the response may be lost, and the server has to boot a new worker.',
    'Return a response, or throw one:

```php
abort(404);
// or
response()->json($data)->throwResponse();
```' => 'Return a response, or throw one:

```php
abort(404);
// or
response()->json($data)->throwResponse();
```',
    'Process-wide settings changed while handling requests ({count})' => 'Process-wide settings changed while handling requests ({count})',
    'In {source}, code changes PHP settings that apply to the whole process, such as the time zone, locale, `ini_set()`, error handlers or environment variables.' => 'In {source}, code changes PHP settings that apply to the whole process, such as the time zone, locale, `ini_set()`, error handlers or environment variables.',
    'Under Octane a process serves many requests, so a time zone or locale set for one visitor applies to the next, and handlers registered per request pile up.' => 'Under Octane a process serves many requests, so a time zone or locale set for one visitor applies to the next, and handlers registered per request pile up.',
    'Change these once at boot, or put them back when the request ends. For dates, pass a time zone instead of changing the default:

```php
now($site->timezone);
```' => 'Change these once at boot, or put them back when the request ends. For dates, pass a time zone instead of changing the default:

```php
now($site->timezone);
```',
    'Shutdown functions registered while handling requests ({count})' => 'Shutdown functions registered while handling requests ({count})',
    'In {source}, code calls `register_shutdown_function()`.' => 'In {source}, code calls `register_shutdown_function()`.',
    'Under Octane PHP only shuts down when the worker stops, so the function doesn’t run after the request, and one is added per request until then.' => 'Under Octane PHP only shuts down when the worker stops, so the function doesn’t run after the request, and one is added per request until then.',
    'Run end-of-request work with Laravel’s `terminating()` callback, or `defer()`:

```php
defer(fn () => $this->flush());
```' => 'Run end-of-request work with Laravel’s `terminating()` callback, or `defer()`:

```php
defer(fn () => $this->flush());
```',
    'Event listeners registered outside boot code ({count})' => 'Event listeners registered outside boot code ({count})',
    'In {source}, `Event::listen()` or `Event::subscribe()` is called outside a `boot()` or `register()` method.' => 'In {source}, `Event::listen()` or `Event::subscribe()` is called outside a `boot()` or `register()` method.',
    'Listeners live on the application, which an Octane worker keeps. One added while serving a request is still there for every later request, and another is added each time: duplicate work and growing memory.' => 'Listeners live on the application, which an Octane worker keeps. One added while serving a request is still there for every later request, and another is added each time: duplicate work and growing memory.',
    'Register listeners once, in your plugin’s `boot()` or a service provider. If a listener is only wanted for one request, remove it when the request ends.' => 'Register listeners once, in your plugin’s `boot()` or a service provider. If a listener is only wanted for one request, remove it when the request ends.',
    'Constants defined while handling requests ({count})' => 'Constants defined while handling requests ({count})',
    'In {source}, `define()` is called inside a function without a `defined()` check.' => 'In {source}, `define()` is called inside a function without a `defined()` check.',
    'Constants can’t be redefined. Under Octane the second request to reach this code triggers a warning, and the value from the first request wins.' => 'Constants can’t be redefined. Under Octane the second request to reach this code triggers a warning, and the value from the first request wins.',
    'Guard it, or use a class constant or config value:

```php
if (! defined(\'MY_CONSTANT\')) {
    define(\'MY_CONSTANT\', \'value\');
}
```' => 'Guard it, or use a class constant or config value:

```php
if (! defined(\'MY_CONSTANT\')) {
    define(\'MY_CONSTANT\', \'value\');
}
```',
    'Some files couldn’t be read as PHP ({count})' => 'Some files couldn’t be read as PHP ({count})',
    'In {source}, these files have syntax Dipstick couldn’t parse, so they weren’t checked.' => 'In {source}, these files have syntax Dipstick couldn’t parse, so they weren’t checked.',
    'Nothing is known about these files. They may be fine.' => 'Nothing is known about these files. They may be fine.',
    'Check that the files are valid PHP 8 code. Blade files are checked only inside `@php` blocks and `{{ }}` echoes.' => 'Check that the files are valid PHP 8 code. Blade files are checked only inside `@php` blocks and `{{ }}` echoes.',
    'No leak patterns in {source}' => 'No leak patterns in {source}',
    'Dipstick found none of the patterns it looks for in {source}.' => 'Dipstick found none of the patterns it looks for in {source}.',
    '{target} keeps an earlier request' => '{target} keeps an earlier request',
    'After a simulated request on {site}, {target} still held a request object that isn’t the current one.' => 'After a simulated request on {site}, {target} still held a request object that isn’t the current one.',
    'Under Octane this service hands one visitor’s request — URL, query, headers, cookies — to the next. Anything built from it (redirects, permission checks, cache keys) uses the wrong visitor’s data.' => 'Under Octane this service hands one visitor’s request — URL, query, headers, cookies — to the next. Anything built from it (redirects, permission checks, cache keys) uses the wrong visitor’s data.',
    'Don’t store the request on a long-lived service. Inject it into the method that needs it, call `request()` at the point of use, or bind the service with `scoped()`:

```php
$this->app->scoped(MyService::class);
```' => 'Don’t store the request on a long-lived service. Inject it into the method that needs it, call `request()` at the point of use, or bind the service with `scoped()`:

```php
$this->app->scoped(MyService::class);
```',
    '{target} keeps the user from an earlier request' => '{target} keeps the user from an earlier request',
    'On {site}, {target} picked up the signed-in user while serving one request and still held that user while serving a request for someone else.' => 'On {site}, {target} picked up the signed-in user while serving one request and still held that user while serving a request for someone else.',
    'Under Octane the next visitor is treated as the previous one: they can see that person’s data, or act with their permissions.' => 'Under Octane the next visitor is treated as the previous one: they can see that person’s data, or act with their permissions.',
    'Look the user up when you need it rather than keeping it:

```php
$user = currentUserElement();
```

or bind the service with `scoped()` so it’s rebuilt for each request.' => 'Look the user up when you need it rather than keeping it:

```php
$user = currentUserElement();
```

or bind the service with `scoped()` so it’s rebuilt for each request.',
    '{target} keeps the site from an earlier request' => '{target} keeps the site from an earlier request',
    'On {site}, {target} picked up the current site during one request and still held it while serving a request for another site.' => 'On {site}, {target} picked up the current site during one request and still held it while serving a request for another site.',
    'Under Octane, visitors to one site get content, URLs, language or settings from another. On a multi-site install this is the most common Octane bug.' => 'Under Octane, visitors to one site get content, URLs, language or settings from another. On a multi-site install this is the most common Octane bug.',
    'Ask for the current site at the point of use:

```php
$site = Sites::getCurrentSite();
```

If you cache per site, key the cache by site ID instead of keeping one site.' => 'Ask for the current site at the point of use:

```php
$site = Sites::getCurrentSite();
```

If you cache per site, key the cache by site ID instead of keeping one site.',
    '{target} grows with every request' => '{target} grows with every request',
    'Across the simulated requests, the static array {target} got bigger every time.' => 'Across the simulated requests, the static array {target} got bigger every time.',
    'Under Octane the array is never emptied, so the worker’s memory grows until it’s recycled or killed, and stale entries are served.' => 'Under Octane the array is never emptied, so the worker’s memory grows until it’s recycled or killed, and stale entries are served.',
    'Cap or reset the array per request, or move it to a `scoped()` service or `once()`:

```php
Event::listen(\\Laravel\\Octane\\Events\\RequestReceived::class, fn () => Registry::$items = []);
```' => 'Cap or reset the array per request, or move it to a `scoped()` service or `once()`:

```php
Event::listen(\\Laravel\\Octane\\Events\\RequestReceived::class, fn () => Registry::$items = []);
```',
    'Memory grows with every request on {site}' => 'Memory grows with every request on {site}',
    'Across the simulated requests on {site}, memory in use rose each time (about {perRequest} per request).' => 'Across the simulated requests on {site}, memory in use rose each time (about {perRequest} per request).',
    'Under Octane a worker that keeps growing is eventually killed or recycled, and requests slow down as it grows.' => 'Under Octane a worker that keeps growing is eventually killed or recycled, and requests slow down as it grows.',
    'Look for static arrays and singletons that collect data per request (see the other findings). Octane’s `--max-requests` option recycles workers as a safety net, not a fix:

```bash
php artisan octane:start --max-requests=500
```' => 'Look for static arrays and singletons that collect data per request (see the other findings). Octane’s `--max-requests` option recycles workers as a safety net, not a fix:

```bash
php artisan octane:start --max-requests=500
```',
    'The runtime probe couldn’t run' => 'The runtime probe couldn’t run',
    'Only the static scan ran. {reason}' => 'Only the static scan ran. {reason}',
    'Leaks that only show up at runtime weren’t looked for.' => 'Leaks that only show up at runtime weren’t looked for.',
    'Run `php artisan craft:dipstick:probe` on the server to see the error. If PHP isn’t on the path for the queue worker, set `DIPSTICK_PHP_BINARY` to the PHP CLI binary.' => 'Run `php artisan craft:dipstick:probe` on the server to see the error. If PHP isn’t on the path for the queue worker, set `DIPSTICK_PHP_BINARY` to the PHP CLI binary.',
    '{target} changes from request to request' => '{target} changes from request to request',
    'The static property {target} had a different value after each simulated request, so state from one request is still there when the next starts.' => 'The static property {target} had a different value after each simulated request, so state from one request is still there when the next starts.',
    'It may be a harmless cache, or it may carry one visitor’s data into the next request. Check what it holds.' => 'It may be a harmless cache, or it may carry one visitor’s data into the next request. Check what it holds.',
    'If it holds anything about the request, user or site, reset it per request or move it to a `scoped()` service.' => 'If it holds anything about the request, user or site, reset it per request or move it to a `scoped()` service.',
    'A page on {site} failed during the probe' => 'A page on {site} failed during the probe',
    'Rendering the home page of {site} in the sandbox threw {exception}.' => 'Rendering the home page of {site} in the sandbox threw {exception}.',
    'The probe still checked services afterwards, but code that runs only while rendering may not have been reached.' => 'The probe still checked services afterwards, but code that runs only while rendering may not have been reached.',
    'Open the page in a browser to see whether it fails there too. Turn off “Render pages in the probe” if the home page can’t render without real data.' => 'Open the page in a browser to see whether it fails there too. Turn off “Render pages in the probe” if the home page can’t render without real data.',
    'Some services couldn’t be built for the probe ({count})' => 'Some services couldn’t be built for the probe ({count})',
    'In {source}, these container bindings couldn’t be constructed outside a real request, so the probe couldn’t check them.' => 'In {source}, these container bindings couldn’t be constructed outside a real request, so the probe couldn’t check them.',
    'They may need something only a real request provides. They’re still covered by the static scan.' => 'They may need something only a real request provides. They’re still covered by the static scan.',
    'Usually nothing to do. If one of them should be built at boot, check its constructor.' => 'Usually nothing to do. If one of them should be built at boot, check its constructor.',
    'No state carried between requests on {site}' => 'No state carried between requests on {site}',
    'Across {requests} simulated requests on {site}, with different users, no service or static property kept the request, user or site from an earlier request.' => 'Across {requests} simulated requests on {site}, with different users, no service or static property kept the request, user or site from an earlier request.',
    'Scan the app’s code' => 'Scan the app’s code',
    '`app/`, `modules/` and `routes/`.' => '`app/`, `modules/` and `routes/`.',
    'Scan installed plugins' => 'Scan installed plugins',
    'Each installed plugin’s own code (its Composer package), not its dependencies.' => 'Each installed plugin’s own code (its Composer package), not its dependencies.',
    'Scan templates' => 'Scan templates',
    'Blade templates are checked; Twig templates can’t keep state between requests, so they’re only counted.' => 'Blade templates are checked; Twig templates can’t keep state between requests, so they’re only counted.',
    'Leave out' => 'Leave out',
    'Paths to skip, one per line, as the report shows them. `*` matches anything.' => 'Paths to skip, one per line, as the report shows them. `*` matches anything.',
    'Ignore' => 'Ignore',
    'Fingerprints of findings (or single rows of evidence) you’ve checked and accept, one per line. A `#` starts a comment. You can also put `@dipstick-ignore` in a comment on, or just above, a line of code.' => 'Fingerprints of findings (or single rows of evidence) you’ve checked and accept, one per line. A `#` starts a comment. You can also put `@dipstick-ignore` in a comment on, or just above, a line of code.',
    'Run the runtime probe' => 'Run the runtime probe',
    'After the static scan, simulate several requests per site in one process, as an Octane worker would, and look for state carried between them. Runs in its own short-lived process with the database rolled back and mail, queues and HTTP faked.' => 'After the static scan, simulate several requests per site in one process, as an Octane worker would, and look for state carried between them. Runs in its own short-lived process with the database rolled back and mail, queues and HTTP faked.',
    'Render pages in the probe' => 'Render pages in the probe',
    'Render each site’s home page through the full request stack. Catches more, but runs your templates and plugins’ request code. Turn off if the home page writes files or calls services outside Laravel’s HTTP client.' => 'Render each site’s home page through the full request stack. Catches more, but runs your templates and plugins’ request code. Turn off if the home page writes files or calls services outside Laravel’s HTTP client.',
    'Requests per site' => 'Requests per site',
    'How many simulated requests each site gets, rotating between an admin, a guest and another user.' => 'How many simulated requests each site gets, rotating between an admin, a guest and another user.',
    'Audits to keep' => 'Audits to keep',
    'Show in the sidebar' => 'Show in the sidebar',
    'Only admins see it.' => 'Only admins see it.',
    'Sidebar label' => 'Sidebar label',
    'Sidebar icon' => 'Sidebar icon',
    'Leave blank for Dipstick’s own icon.' => 'Leave blank for Dipstick’s own icon.',
    'Details are in the log.' => 'Details are in the log.',
    'The audit failed. Details are in the log.' => 'The audit failed. Details are in the log.',
    'Critical' => 'Critical',
    'Warning' => 'Warning',
    'Notice' => 'Notice',
    'Pass' => 'Pass',
    'Auditing for Octane' => 'Auditing for Octane',
    'View the Octane readiness summary' => 'View the Octane readiness summary',
    'Adds the `dipstickReadiness` query: the latest audit’s score, grade and counts. No files, classes or findings.' => 'Adds the `dipstickReadiness` query: the latest audit’s score, grade and counts. No files, classes or findings.',
    'An audit is already queued or running.' => 'An audit is already queued or running.',
    'Audit queued. This page shows it when it’s done.' => 'Audit queued. This page shows it when it’s done.',
    'Dipstick' => 'Dipstick',
    'No critical or warning findings.' => 'No critical or warning findings.',
    'Fix the critical and warning findings before running on Octane.' => 'Fix the critical and warning findings before running on Octane.',
    '{critical} critical · {warning} warning · {notice} notice · {pass} passed' => '{critical} critical · {warning} warning · {notice} notice · {pass} passed',
    'static scan + runtime probe' => 'static scan + runtime probe',
    'static scan only' => 'static scan only',
    '{php} PHP files, {blade} Blade and {twig} Twig templates' => '{php} PHP files, {blade} Blade and {twig} Twig templates',
    '{requests} simulated requests across {sites} sites' => '{requests} simulated requests across {sites} sites',
    'Octane isn’t installed; the probe simulated its workers.' => 'Octane isn’t installed; the probe simulated its workers.',
    'The audit failed.' => 'The audit failed.',
    'An audit is queued or running. Reload this page in a moment.' => 'An audit is queued or running. Reload this page in a moment.',
    'No audits yet. Run one to see how ready this site is for Octane.' => 'No audits yet. Run one to see how ready this site is for Octane.',
    'Run an audit' => 'Run an audit',
    'Dipstick reads code without running it, then simulates requests in a separate, sandboxed process. It finds the common ways state leaks between requests; it can’t prove there are none. Craft 6 is in beta: test on staging before running Octane in production.' => 'Dipstick reads code without running it, then simulates requests in a separate, sandboxed process. It finds the common ways state leaks between requests; it can’t prove there are none. Craft 6 is in beta: test on staging before running Octane in production.',
    'Why it matters' => 'Why it matters',
    'How to fix it' => 'How to fix it',
    'Fingerprint (add it to Ignore to accept this line)' => 'Fingerprint (add it to Ignore to accept this line)',
    'And {n} more.' => 'And {n} more.',
    'Fingerprint' => 'Fingerprint',
    'Octane docs' => 'Octane docs',
    'Recent audits' => 'Recent audits',
    'Control panel' => 'Control panel',
    'Command line' => 'Command line',
];
