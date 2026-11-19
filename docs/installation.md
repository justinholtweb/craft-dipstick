---
title: Installation
slug: installation
order: 10
summary: Requirements, the Craft 6 beta caution, install, and your first audit.
---

> **Craft 6 only, and still in beta.** Dipstick is a native **Craft CMS 6** plugin. Craft 6 is still in beta,
> and so is Dipstick (`6.0.0-beta.1`). Try it on development and staging sites first, and **use caution before
> running it on a production site**. It does not run on Craft 5.

## Requirements

- Craft CMS 6.0.0-beta.1 or later, PHP 8.5
- A queue worker, if you run audits from the control panel
- Laravel Octane is not needed. Dipstick is what you run *before* you install it.

## Install

```bash
composer require justinholtweb/craft-dipstick
php artisan craft:plugin:install dipstick
```

## Your first audit

Open **Dipstick** in the sidebar (admins only) and click **Run an audit**, or from the command line:

```bash
php artisan craft:dipstick:scan
```

The report lists findings by severity. Critical ones leak a visitor's request, account or site to the next
visitor under Octane: fix those first.
