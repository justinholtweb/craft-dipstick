# Dipstick — plan

Check the engine before you run Craft on Octane — finds state that leaks between requests in plugins and modules.

- Package: `justinholtweb/craft-dipstick` · handle `dipstick` · namespace `JustinHoltWeb\Dipstick`
- Editions: single edition · pricing: Free (provisional)
- Laravel integration: laravel/octane — Audits for Octane; Octane itself is not required to run the static scan.
- Craft 6 only, native Laravel (no `craftcms/yii2-adapter`, ever). Follow
  `~/Sites/plugin-testing-craft6/porting/COOKBOOK.md` for Craft 6 idioms.

## Why it exists
Craft 6 on Laravel makes Octane (FrankenPHP/Swoole/RoadRunner) a real option, but plugins written for
request-per-process PHP leak state: static caches, singletons holding the request or user, memoised `once()`
values. Dipstick finds them before production does. Natural feed for Microscope and Heat.

## Phases
1. **Static scan.** AST scan (nikic/php-parser) of plugins/modules for: static properties written outside
   constructors, `#[Singleton]`/`singleton()` services storing request/user/site, `request()`/`Auth::user()`
   captured in constructors, `once()` over request state, global/`$_SERVER` writes.
2. **Runtime probe.** Replay N requests (different users/sites) in one process and diff container singletons
   and static state between requests.
3. **Report.** CP report + `artisan craft:dipstick:scan` (CI-friendly exit codes, JSON output), each finding
   with a fix recipe.
4. **Microscope integration.** Expose findings as Microscope checks.

## Definition of done (every phase)
`bin/port-check craft-dipstick` green · Pest suite green · Pint + Larastan level 6 clean · installs, uninstalls and
reinstalls cleanly in the harness · CP pages smoke-tested with `bin/cp-session`.
