---
title: Configuration
slug: configuration
order: 50
summary: Settings, config/craft/dipstick.php, and the sidebar link.
---

> Dipstick is for **Craft 6**, which is **in beta**. Use caution on production.

Settings → Plugins → Dipstick, or `config/craft/dipstick.php` (which wins):

| Setting | Default | |
|---|---|---|
| `scanApp` | `true` | `app/`, `modules/`, `routes/` |
| `scanVendor` | `true` | Installed plugins' own packages |
| `scanTemplates` | `true` | Blade checked, Twig counted |
| `excludePaths` | – | Globs, one per line, as the report shows paths |
| `ignore` | – | Fingerprints, one per line |
| `probeEnabled` | `true` | Run the runtime probe in audits |
| `probeRender` | `true` | Render each site's home page in the sandbox |
| `probeRequests` | `3` | Simulated requests per site (2–10) |
| `probeSkip` | `[]` | Config file only: classes the probe must never build |
| `keepScans` | `10` | Audits kept |
| `showInCpNav`, `cpNavLabel`, `cpNavIcon` | on, –, – | The sidebar link (admins only) |

```php
<?php

return [
    'probeRequests' => 4,
    'excludePaths' => "app/Legacy/*",
    'probeSkip' => [App\Services\ConnectsOnConstruct::class],
];
```

The PHP binary for the probe isn't a setting: set `DIPSTICK_PHP_BINARY` in the environment if needed.
