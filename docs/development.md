---
name: wp-module-mcp
title: Development
description: Lint, tests, and day-to-day workflow.
updated: 2026-03-26
---

# Development

## PHP coding standards

```bash
composer run lint    # PHPCS against phpcs.xml (Newfold + project paths)
composer run fix     # PHPCBF
```

Scanned paths: **`bootstrap.php`**, **`includes/`** (see `phpcs.xml`). Minimum WordPress version for PHPCS: **6.0**.

## Tests

```bash
composer run test
composer run test-coverage
```

See [testing.md](testing.md).

## Internationalization

If string translations are added, the repo uses **`text_domain: wp-module-mcp`** in the **Check for Updates to Translations** workflow (see [workflows.md](workflows.md)).

## Versioning

Release process: [release.md](release.md).
