---
title: FAQ
slug: faq
order: 70
summary: Octane not installed, multi-site, false positives, and production use.
---

> Dipstick is for **Craft 6**, which is **in beta**. Use caution on production.

**Do I need Octane installed?** No. The probe simulates a worker in a plain PHP process.

**Does a clean report mean I'm safe?** It means none of the common patterns were found. It can't prove there
are none: code that only runs on some pages, in jobs or after a post isn't reached by the probe, and the static
scan can't follow every path. Test on staging under Octane before production.

**Why multi-site?** A service that keeps the current site serves one site's content, URLs or language to
another under Octane. The probe sends every site requests in turn, non-primary sites first, so it can tell a
service that captured the site from one that just holds the primary site.

**What about Craft itself?** Dipstick audits your app and plugins, not Craft's core.

**A finding is wrong.** Accept it with its fingerprint (Settings → Ignore) or `@dipstick-ignore`, and tell us.

**Can I run it on production?** The static scan only reads files. The probe rolls back the database and fakes
mail, queues and HTTP, but rendering pages runs your templates; turn page rendering off, or run audits on
staging. Craft 6 is in beta: use caution.
