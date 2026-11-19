---
title: Command line and CI
slug: command-line
order: 40
summary: craft:dipstick:scan exit codes and JSON, and running the probe on its own.
---

> Dipstick is for **Craft 6**, which is **in beta**. Use caution on production.

```bash
php artisan craft:dipstick:scan [--json] [--no-probe] [--fail-on=critical|warning|notice|none]
```

Exit codes: **0** nothing at or above `--fail-on` (default `critical`), **1** something is, **2** the audit
couldn't run. Every run is stored and shows up in the control panel. `--json` prints the score, grade,
counts and findings (rule, severity, source, site, evidence with project-relative paths, fingerprint, title).

```bash
php artisan craft:dipstick:probe [--requests=3] [--no-render] [--json]
```

Runs the runtime probe in the current process (it's sandboxed and nothing is kept). Audits run it as a child
process; use this to see probe errors. If the queue worker's PHP isn't on the path, set
`DIPSTICK_PHP_BINARY` to the PHP CLI binary.

A CI step:

```yaml
- run: php artisan craft:dipstick:scan --no-probe --fail-on=warning
```
