<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Findings;

use function CraftCms\Cms\t;

/**
 * Every rule Dipstick reports, with the words for it: a title, what was seen,
 * why it matters under Octane, and the fix (Markdown). Looked up when a report
 * is shown, through Craft's translator (category `dipstick`).
 *
 * Titles and summaries take `{count}`, `{source}`, `{site}` and `{target}`.
 * Fix recipes take no parameters, so their code samples can use braces freely.
 */
final class Rules
{
    public const string DOCS_DI = 'https://laravel.com/docs/octane#dependency-injection-and-octane';

    public const string DOCS_MEMORY = 'https://laravel.com/docs/octane#managing-memory-leaks';

    /** Static-scan rules and their default severity. */
    public const array STATIC = [
        'static-request-state' => Severity::Critical,
        'singleton-captures-request' => Severity::Critical,
        'controller-captures-request' => Severity::Critical,
        'constructor-captures-request' => Severity::Notice,
        'static-array-growth' => Severity::Warning,
        'static-property-write' => Severity::Warning,
        'static-variable' => Severity::Warning,
        'global-state' => Severity::Warning,
        'superglobal-access' => Severity::Warning,
        'native-response' => Severity::Warning,
        'worker-exit' => Severity::Warning,
        'process-state' => Severity::Warning,
        'shutdown-function' => Severity::Warning,
        'listener-in-request' => Severity::Notice,
        'runtime-define' => Severity::Notice,
        'parse-error' => Severity::Notice,
        'static-clean' => Severity::Pass,
    ];

    /** Runtime-probe rules and their default severity. */
    public const array RUNTIME = [
        'probe-request-leak' => Severity::Critical,
        'probe-user-leak' => Severity::Critical,
        'probe-site-leak' => Severity::Critical,
        'probe-static-growth' => Severity::Warning,
        'probe-memory-growth' => Severity::Warning,
        'probe-failed' => Severity::Warning,
        'probe-static-mutation' => Severity::Notice,
        'probe-render-error' => Severity::Notice,
        'probe-unresolvable' => Severity::Notice,
        'probe-clean' => Severity::Pass,
    ];

    public static function exists(string $rule): bool
    {
        return isset(self::STATIC[$rule]) || isset(self::RUNTIME[$rule]);
    }

    public static function severity(string $rule): Severity
    {
        return self::STATIC[$rule] ?? self::RUNTIME[$rule] ?? Severity::Notice;
    }

    /**
     * The words for a finding, translated.
     *
     * @return array{title: string, summary: string, impact: string, remediation: string, docsUrl: string|null}
     */
    public static function describe(Finding $finding): array
    {
        $params = [
            'count' => $finding->occurrences,
            'source' => $finding->source,
            'site' => (string) $finding->site,
            'target' => (string) $finding->target,
            ...$finding->params,
        ];

        $text = self::text($finding->rule);

        return [
            'title' => t($text[0], $params, category: 'dipstick'),
            'summary' => t($text[1], $params, category: 'dipstick'),
            'impact' => t($text[2], category: 'dipstick'),
            'remediation' => t($text[3], category: 'dipstick'),
            'docsUrl' => $text[4],
        ];
    }

    /**
     * Source strings (English) for a rule: title, summary, impact, fix, docs URL.
     *
     * @return array{string, string, string, string, string|null}
     */
    public static function text(string $rule): array
    {
        return match ($rule) {
            'static-request-state' => [
                'A static property is set from the request, user or site ({count})',
                'In {source}, a value taken from the current request, signed-in user or current site is written to a static property.',
                'Under Octane one PHP process serves many requests. A static property keeps its value between them, so the next visitor gets the previous visitor’s request, user or site: someone else’s account details, the wrong language, the wrong site’s content.',
                "Don’t keep per-request values in static properties. Read them where you need them, or keep them on a service bound with `scoped()`, which Octane throws away after every request:\n\n```php\n\$this->app->scoped(CurrentShopper::class);\n```\n\nIf it has to be static, reset it at the start of every request:\n\n```php\nEvent::listen(\\Laravel\\Octane\\Events\\RequestReceived::class, fn () => Cart::\$current = null);\n```",
                self::DOCS_DI,
            ],
            'singleton-captures-request' => [
                'Singletons capture the request, user or site when they’re built ({count})',
                'In {source}, a class bound as a singleton takes the request, the signed-in user or the current site in its constructor and keeps it.',
                'A singleton is built once per Octane worker and then shared by every request that worker serves. Whatever it captured while serving the first request stays with it: later visitors see the first visitor’s user, site or request data.',
                "Bind the class with `scoped()` instead of `singleton()` (or swap `#[Singleton]` for `#[Scoped]`), so it’s rebuilt for each request:\n\n```php\n\$this->app->scoped(Basket::class);\n```\n\nOr keep it a singleton and ask for the current value when you need it, instead of in the constructor:\n\n```php\npublic function owner(): ?User\n{\n    return currentUserElement();\n}\n```",
                self::DOCS_DI,
            ],
            'controller-captures-request' => [
                'Controllers capture the request, user or site in their constructor ({count})',
                'In {source}, a controller takes the request, the signed-in user or the current site in its constructor.',
                'Laravel keeps the controller instance on its route. Under Octane routes live for the life of the worker, so the constructor runs once and every later request reuses what the first one captured.',
                "Take the request in the action instead of the constructor; Laravel injects it per call:\n\n```php\npublic function show(Request \$request, string \$slug)\n{\n    \$user = \$request->user();\n}\n```",
                self::DOCS_DI,
            ],
            'constructor-captures-request' => [
                'Classes keep the request, user or site from their constructor ({count})',
                'In {source}, classes store the request, signed-in user or current site when they’re built. That’s fine while they’re built per request; it leaks if they’re ever bound as a singleton.',
                'Harmless as long as each request builds its own instance. If one of these is later bound as a singleton, or cached on a route, it starts handing one visitor’s data to the next.',
                'Prefer passing the request, user or site to the method that needs it. If the class is resolved from the container, bind it with `scoped()` so Octane rebuilds it per request.',
                self::DOCS_DI,
            ],
            'static-array-growth' => [
                'Static arrays that grow with every request ({count})',
                'In {source}, values are appended to, or added by key to, a static array.',
                'Under Octane a static array is never emptied between requests. A cache keyed by URL, user or element grows until the worker runs out of memory or is recycled, and entries from one request are served to the next.',
                "Keep the cache on a `scoped()` service, or use Laravel’s `once()`, which Octane flushes after each request:\n\n```php\nreturn once(fn () => \$this->expensiveLookup(\$id));\n```\n\nIf a static cache is intended, cap its size, and reset it in a `RequestReceived` listener.",
                self::DOCS_MEMORY,
            ],
            'static-property-write' => [
                'Static properties written while handling requests ({count})',
                'In {source}, static properties are written outside of boot code.',
                'A static property outlives the request under Octane. Flags, counters and memoised values set while serving one request are still set for the next.',
                "Move the value onto a `scoped()` service, or reset it at the start of every request:\n\n```php\nEvent::listen(\\Laravel\\Octane\\Events\\RequestReceived::class, fn () => Thing::\$value = null);\n```\n\nWrites in `boot()` / `register()` are skipped: they run once per worker, which is what they mean.",
                self::DOCS_DI,
            ],
            'static-variable' => [
                'Function-level `static` variables ({count})',
                'In {source}, functions or methods declare `static $var`, which keeps its value across calls.',
                'A `static` variable lives as long as the PHP process. Under Octane that’s every request a worker serves, so the first request’s value is reused by all later ones.',
                "Replace the `static` variable with `once()` (flushed by Octane after each request) or a property on a `scoped()` service:\n\n```php\nreturn once(fn () => \$this->load());\n```",
                self::DOCS_DI,
            ],
            'global-state' => [
                'Global variables ({count})',
                'In {source}, code uses `global` or writes to `$GLOBALS`.',
                'Globals survive between requests in an Octane worker, so values set for one visitor are seen by the next.',
                'Pass the value explicitly, or keep it on a `scoped()` service.',
                self::DOCS_DI,
            ],
            'superglobal-access' => [
                'Superglobals used instead of the request ({count})',
                'In {source}, code reads or writes `$_GET`, `$_POST`, `$_SERVER`, `$_COOKIE`, `$_FILES`, `$_REQUEST` or `$_SESSION`.',
                'Octane builds a Laravel request for each visit, but superglobals aren’t refreshed on every server: on Swoole and RoadRunner they hold whatever the worker started with. Code that reads them sees stale or empty values.',
                "Read from the request object:\n\n```php\n\$page = request()->query('page');\n\$ip = request()->ip();\n\$header = request()->header('X-Thing');\n```",
                self::DOCS_DI,
            ],
            'native-response' => [
                'Headers, cookies or sessions set with PHP’s own functions ({count})',
                'In {source}, code calls `header()`, `setcookie()`, `http_response_code()` or `session_*()`.',
                'Under Octane these functions don’t reach the response on Swoole or RoadRunner, and PHP’s native session isn’t Laravel’s. Headers and cookies go missing, or stick to the wrong response.',
                "Set them on the Laravel response, and use Laravel’s session:\n\n```php\nreturn response(\$html)\n    ->header('X-Frame-Options', 'DENY')\n    ->cookie('seen', '1', 60);\n\nsession()->put('step', 2);\n```",
                self::DOCS_DI,
            ],
            'worker-exit' => [
                '`exit` or `die` while handling a request ({count})',
                'In {source}, code calls `exit` or `die`.',
                'Octane serves many requests from one process. Exiting ends the worker: the response may be lost, and the server has to boot a new worker.',
                "Return a response, or throw one:\n\n```php\nabort(404);\n// or\nresponse()->json(\$data)->throwResponse();\n```",
                null,
            ],
            'process-state' => [
                'Process-wide settings changed while handling requests ({count})',
                'In {source}, code changes PHP settings that apply to the whole process, such as the time zone, locale, `ini_set()`, error handlers or environment variables.',
                'Under Octane a process serves many requests, so a time zone or locale set for one visitor applies to the next, and handlers registered per request pile up.',
                "Change these once at boot, or put them back when the request ends. For dates, pass a time zone instead of changing the default:\n\n```php\nnow(\$site->timezone);\n```",
                null,
            ],
            'shutdown-function' => [
                'Shutdown functions registered while handling requests ({count})',
                'In {source}, code calls `register_shutdown_function()`.',
                'Under Octane PHP only shuts down when the worker stops, so the function doesn’t run after the request, and one is added per request until then.',
                "Run end-of-request work with Laravel’s `terminating()` callback, or `defer()`:\n\n```php\ndefer(fn () => \$this->flush());\n```",
                null,
            ],
            'listener-in-request' => [
                'Event listeners registered outside boot code ({count})',
                'In {source}, `Event::listen()` or `Event::subscribe()` is called outside a `boot()` or `register()` method.',
                'Listeners live on the application, which an Octane worker keeps. One added while serving a request is still there for every later request, and another is added each time: duplicate work and growing memory.',
                'Register listeners once, in your plugin’s `boot()` or a service provider. If a listener is only wanted for one request, remove it when the request ends.',
                self::DOCS_MEMORY,
            ],
            'runtime-define' => [
                'Constants defined while handling requests ({count})',
                'In {source}, `define()` is called inside a function without a `defined()` check.',
                'Constants can’t be redefined. Under Octane the second request to reach this code triggers a warning, and the value from the first request wins.',
                "Guard it, or use a class constant or config value:\n\n```php\nif (! defined('MY_CONSTANT')) {\n    define('MY_CONSTANT', 'value');\n}\n```",
                null,
            ],
            'parse-error' => [
                'Some files couldn’t be read as PHP ({count})',
                'In {source}, these files have syntax Dipstick couldn’t parse, so they weren’t checked.',
                'Nothing is known about these files. They may be fine.',
                'Check that the files are valid PHP 8 code. Blade files are checked only inside `@php` blocks and `{{ }}` echoes.',
                null,
            ],
            'static-clean' => [
                'No leak patterns in {source}',
                'Dipstick found none of the patterns it looks for in {source}.',
                '',
                '',
                null,
            ],
            'probe-request-leak' => [
                '{target} keeps an earlier request',
                'After a simulated request on {site}, {target} still held a request object that isn’t the current one.',
                'Under Octane this service hands one visitor’s request — URL, query, headers, cookies — to the next. Anything built from it (redirects, permission checks, cache keys) uses the wrong visitor’s data.',
                "Don’t store the request on a long-lived service. Inject it into the method that needs it, call `request()` at the point of use, or bind the service with `scoped()`:\n\n```php\n\$this->app->scoped(MyService::class);\n```",
                self::DOCS_DI,
            ],
            'probe-user-leak' => [
                '{target} keeps the user from an earlier request',
                'On {site}, {target} picked up the signed-in user while serving one request and still held that user while serving a request for someone else.',
                'Under Octane the next visitor is treated as the previous one: they can see that person’s data, or act with their permissions.',
                "Look the user up when you need it rather than keeping it:\n\n```php\n\$user = currentUserElement();\n```\n\nor bind the service with `scoped()` so it’s rebuilt for each request.",
                self::DOCS_DI,
            ],
            'probe-site-leak' => [
                '{target} keeps the site from an earlier request',
                'On {site}, {target} picked up the current site during one request and still held it while serving a request for another site.',
                'Under Octane, visitors to one site get content, URLs, language or settings from another. On a multi-site install this is the most common Octane bug.',
                "Ask for the current site at the point of use:\n\n```php\n\$site = Sites::getCurrentSite();\n```\n\nIf you cache per site, key the cache by site ID instead of keeping one site.",
                self::DOCS_DI,
            ],
            'probe-static-growth' => [
                '{target} grows with every request',
                'Across the simulated requests, the static array {target} got bigger every time.',
                'Under Octane the array is never emptied, so the worker’s memory grows until it’s recycled or killed, and stale entries are served.',
                "Cap or reset the array per request, or move it to a `scoped()` service or `once()`:\n\n```php\nEvent::listen(\\Laravel\\Octane\\Events\\RequestReceived::class, fn () => Registry::\$items = []);\n```",
                self::DOCS_MEMORY,
            ],
            'probe-static-mutation' => [
                '{target} changes from request to request',
                'The static property {target} had a different value after each simulated request, so state from one request is still there when the next starts.',
                'It may be a harmless cache, or it may carry one visitor’s data into the next request. Check what it holds.',
                'If it holds anything about the request, user or site, reset it per request or move it to a `scoped()` service.',
                self::DOCS_DI,
            ],
            'probe-memory-growth' => [
                'Memory grows with every request on {site}',
                'Across the simulated requests on {site}, memory in use rose each time (about {perRequest} per request).',
                'Under Octane a worker that keeps growing is eventually killed or recycled, and requests slow down as it grows.',
                "Look for static arrays and singletons that collect data per request (see the other findings). Octane’s `--max-requests` option recycles workers as a safety net, not a fix:\n\n```bash\nphp artisan octane:start --max-requests=500\n```",
                self::DOCS_MEMORY,
            ],
            'probe-failed' => [
                'The runtime probe couldn’t run',
                'Only the static scan ran. {reason}',
                'Leaks that only show up at runtime weren’t looked for.',
                'Run `php artisan craft:dipstick:probe` on the server to see the error. If PHP isn’t on the path for the queue worker, set `DIPSTICK_PHP_BINARY` to the PHP CLI binary.',
                null,
            ],
            'probe-render-error' => [
                'A page on {site} failed during the probe',
                'Rendering the home page of {site} in the sandbox threw {exception}.',
                'The probe still checked services afterwards, but code that runs only while rendering may not have been reached.',
                'Open the page in a browser to see whether it fails there too. Turn off “Render pages in the probe” if the home page can’t render without real data.',
                null,
            ],
            'probe-unresolvable' => [
                'Some services couldn’t be built for the probe ({count})',
                'In {source}, these container bindings couldn’t be constructed outside a real request, so the probe couldn’t check them.',
                'They may need something only a real request provides. They’re still covered by the static scan.',
                'Usually nothing to do. If one of them should be built at boot, check its constructor.',
                null,
            ],
            'probe-clean' => [
                'No state carried between requests on {site}',
                'Across {requests} simulated requests on {site}, with different users, no service or static property kept the request, user or site from an earlier request.',
                '',
                '',
                null,
            ],
            default => [$rule, '', '', '', null],
        };
    }
}
