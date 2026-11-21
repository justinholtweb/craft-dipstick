# Release Notes for Dipstick

## 6.0.0-beta.2 - 2026-11-21

### Changed
- The docs FAQ now compares Dipstick with Microscope and Heat.

## 6.0.0-beta.1 - 2026-11-19

> Dipstick is for Craft 6, which is in beta. Use caution on production.

### Added
- Static scan (`nikic/php-parser`) of the app, installed plugins' packages and Blade templates for state that leaks between requests under Octane: static properties set from the request, user or site; singletons and controllers capturing them in constructors (judged by how the class is bound); growing static arrays; `static` variables; globals; superglobals; native headers, cookies and sessions; `exit`; process-wide settings; shutdown functions; listeners registered outside boot; unguarded `define()`.
- Runtime probe in a sandboxed child process: several simulated requests per site, rotating visitors, optionally rendering each home page; finds services and statics keeping an earlier request, user or site, static growth and memory growth.
- Per-site probing on multi-site installs.
- Stored audits with a score, grade and remediation-first findings (why it matters, how to fix it, Octane docs), translated when shown (English and French).
- Admin-only control-panel report with a queued **Run an audit** button and audit history.
- `craft:dipstick:scan` (CI exit codes, `--json`, `--no-probe`, `--fail-on`) and `craft:dipstick:probe`.
- Ignore findings by fingerprint or `@dipstick-ignore`; exclude paths.
- GraphQL `dipstickReadiness` (summary only, explicit schema permission) and `dipstick.readiness()` for Twig and Blade (admins only).
- `AuditCompleted` event for other tools (Microscope).
