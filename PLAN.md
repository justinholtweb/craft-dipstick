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

## Status (2026-11-18, overnight)
- Phase 1 static scan: **done** (AST, 17 rules, constructor captures joined with bindings, Blade, ignore/exclude).
- Phase 2 runtime probe: **done**, sandboxed child process, per site, rotating visitors, optional page render;
  request/user/site leaks, static growth/churn, memory growth. Octane itself is simulated, not installed.
- Phase 3 report: **done** (admin-only CP report + history, queued audits, `craft:dipstick:scan` with CI exit
  codes and JSON, `craft:dipstick:probe`), remediation-first findings translated at display (en + fr).
- Phase 4 Microscope: **deferred** — Microscope has no Craft 6 port yet. Dipstick fires `AuditCompleted` with
  its findings for it to consume.
- GraphQL `dipstickReadiness` (summary only, explicit schema component); Twig/Blade `dipstick.readiness()` (admins only).
- Checks: Pest 20, harness `tests/Harness/check.sh` 64/0 (multi-site with its own `dipstickfr` site).

## Definition of done (every phase)
`bin/port-check craft-dipstick` green · Pest suite green · Pint + Larastan level 6 clean · installs, uninstalls and
reinstalls cleanly in the harness · CP pages smoke-tested with `bin/cp-session`.

## Questions for Justin
1. ~~Icon~~ — resolved 2026-11-19: traced from Justin's `src/icon.jpg` (white dipstick on amber #D9953C);
   promos and the marketing page rebranded to match.
2. GraphQL: Dipstick is an admin tool, so `dipstickReadiness` is deliberately thin (score, grade, counts, date;
   explicit schema permission; admins only for user tokens). Keep it, or drop GraphQL entirely?
3. Templates: `dipstick.readiness()` / `$dipstick->readiness()` returns the summary to signed-in admins only
   (for a badge). Keep, or remove the front-end surface entirely?
4. The probe renders each site's home page by default (sandboxed: DB rolled back, mail/queue/HTTP faked). It
   can't stop file writes or non-Laravel network calls. Default on or off?
5. Scope: Dipstick audits the app + installed Craft plugins, not other Composer packages or Craft core.
   (Core itself keeps per-request state in singletons, e.g. `Sites::$currentSite` — worth reporting upstream
   before Craft supports Octane.) Want a "scan other packages" option?
6. Free + Craft License confirmed?
7. Microscope integration waits for a Craft 6 Microscope. OK to leave as the `AuditCompleted` event for now?
