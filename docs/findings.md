---
title: Findings
slug: findings
order: 30
summary: Every rule Dipstick reports, its severity, and the usual fix.
---

> Dipstick is for **Craft 6**, which is **in beta**. Use caution on production.

The score starts at 100: each critical finding costs 25, each warning 10, each notice 3. **Ready** means no
critical or warning findings. Findings are grouped per rule per source (the app, a plugin package, the
templates), with every matching line as evidence.

| Rule | Severity | Usual fix |
|---|---|---|
| `static-request-state` | Critical | Keep per-request values on a `scoped()` service, or reset the static on `RequestReceived` |
| `singleton-captures-request` | Critical | Bind with `scoped()` / `#[Scoped]`, or read the user/site when needed |
| `controller-captures-request` | Critical | Take the request in the action, not the constructor |
| `static-array-growth` | Warning | `once()`, a `scoped()` service, or cap and reset the array |
| `static-property-write`, `static-variable`, `global-state` | Warning | Move the value onto a `scoped()` service |
| `superglobal-access` | Warning | `request()->query()`, `->ip()`, `->header()` |
| `native-response` | Warning | Set headers and cookies on the Laravel response; use `session()` |
| `worker-exit` | Warning | `abort()` or `->throwResponse()` |
| `process-state`, `shutdown-function` | Warning | Do it once at boot, or `defer()` |
| `listener-in-request`, `runtime-define`, `constructor-captures-request` | Notice | Register once in `boot()`; guard `define()` |
| `probe-request-leak`, `probe-user-leak`, `probe-site-leak` | Critical | Don't keep the request, user or site on a long-lived service |
| `probe-static-growth`, `probe-memory-growth` | Warning | Find what collects per request; cap or reset it |
| `probe-static-mutation`, `probe-render-error`, `probe-unresolvable` | Notice | Check what the static holds; check the page renders |

## Accepting a finding

Each finding and each row of evidence has a 12-character fingerprint that doesn't change between audits
(it has no line numbers). Put it in **Settings → Dipstick → Ignore**, one per line (`#` starts a comment), or add
a `@dipstick-ignore` comment on, or just above, the line of code. **Leave out** takes paths as the report shows
them, with `*` wildcards (`app/Legacy/*`).
