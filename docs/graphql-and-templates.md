---
title: GraphQL and templates
slug: graphql-and-templates
order: 60
summary: A read-only readiness summary for a status page or deploy check.
---

> Dipstick is for **Craft 6**, which is **in beta**. Use caution on production.

Both give the latest audit's **summary only** — score, grade, whether it's ready, and counts. Never files,
classes, findings or paths.

## GraphQL

Tick **Dipstick → View the Octane readiness summary** on a schema (for user-authenticated tokens it's admins only):

```graphql
{
  dipstickReadiness { score grade ready critical warning notice scannedAt }
}
```

`null` before the first audit. A schema without the permission doesn't have the query.

## Twig and Blade

For signed-in admins only; everyone else gets `null`.

```twig
{% set r = dipstick.readiness() %}
{% if r %}Octane: {{ r.score }}/100 ({{ r.grade }}){% endif %}
```

```blade
@php($r = $dipstick->readiness())
@if ($r) Octane: {{ $r['score'] }}/100 ({{ $r['grade'] }}) @endif
```
