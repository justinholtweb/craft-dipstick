# Dipstick

Check the engine before you run Craft on Octane. Dipstick finds state that leaks between requests in your
app, your installed plugins and your Blade templates: static caches, singletons that keep the request, user
or site, globals, superglobals, growing memory. It reads the code without running it, then simulates a
worker serving several requests per site in a sandboxed process.

> [!WARNING]
> **Craft 6 only, and in beta.** Dipstick is a native **Craft CMS 6** plugin. Craft 6 is still in beta, and so
> is Dipstick (`6.0.0-beta.1`). Try it on development and staging sites first and **use caution on
> production**: keep backups, and expect changes between betas. It does not run on Craft 5.

Docs: [justinholt.com/plugins/craft-dipstick](https://justinholt.com/plugins/craft-dipstick/docs/). See [PLAN.md](PLAN.md).

## Requirements

- Craft CMS 6.0.0-beta.1 or later (native Craft 6 plugin, no Yii 2 adapter)
- PHP 8.5
- `nikic/php-parser` 5 (installed with it)
- Laravel Octane is **not** required. Dipstick tells you whether your code is ready for it.

## What it looks for

**Static scan** (every PHP file in `app/`, `modules/`, `routes/`, each installed plugin's package, and Blade
templates; Twig templates are counted, since compiled Twig can't keep state between requests):

| Severity | Finding |
|---|---|
| Critical | A static property set from the request, user or site |
| Critical | A singleton (bound with `singleton()` or `#[Singleton]`) that takes the request, user or site in its constructor |
| Critical | A controller that does (Laravel keeps controller instances on the route) |
| Warning | Static arrays that grow, static properties written outside boot code, `static $var`, globals |
| Warning | `$_GET`/`$_POST`/`$_SERVER`… instead of the request; `header()`/`setcookie()`/`session_*()`; `exit`/`die` |
| Warning | Process-wide settings changed per request (time zone, locale, `ini_set`, error handlers); shutdown functions |
| Notice | Event listeners registered outside `boot()`; `define()` without a guard; other classes keeping request state |

**Runtime probe** (one process, several simulated requests per site, rotating an admin, a guest and another
user, the way an Octane worker serves them): any service in the container, or static property, still holding
an earlier request, an earlier visitor or an earlier site; static arrays that grow every request; memory that
keeps rising. It runs in a child process with the database rolled back and mail, notifications, queues and
Laravel's HTTP client faked, so nothing it does is kept or sent.

Every finding comes with why it matters under Octane and how to fix it.

## Use

- **Control panel:** Dipstick in the sidebar (admins only) → **Run an audit**. Audits run on the queue.
- **Command line / CI:**

```bash
php artisan craft:dipstick:scan                    # exit 1 on any critical finding
php artisan craft:dipstick:scan --fail-on=warning  # or notice, or none
php artisan craft:dipstick:scan --json --no-probe  # static scan only, machine-readable
php artisan craft:dipstick:probe                   # the runtime probe on its own
```

Accept a finding by adding its fingerprint to Settings → Dipstick → Ignore, or with a `@dipstick-ignore`
comment on (or just above) the line.

## Security

- Admins only: the report names files and classes in your project. No permission opens it to anyone else.
- Paths are shown as `app/…` or `vendor/package/…`, never absolute.
- The scan never loads or runs the code it reads. The probe runs in its own short-lived process, never in a web
  or queue worker. Rendering pages in the probe runs your templates; switch it off if a home page writes files
  or calls services outside Laravel's HTTP client.
- GraphQL (`dipstickReadiness`) and the template API (`dipstick.readiness()`, admins only) give a summary only:
  score, grade and counts. No files, classes or findings.

## Development

```bash
composer install
composer test        # Pest
composer phpstan     # Larastan level 6
composer check-cs    # Pint
tests/Harness/check.sh   # live checks against ~/Sites/plugin-testing-craft6
```
