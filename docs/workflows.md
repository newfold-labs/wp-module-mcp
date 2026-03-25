---
name: wp-module-mcp
title: GitHub workflows
description: CI, translations, Playwright, release prep, and Satis.
updated: 2026-03-26
---

# GitHub workflows

Files live under **`.github/workflows/`**.

| Workflow file | Purpose |
|---------------|---------|
| **lint.yml** | PHPCS on push/PR when PHP files change |
| **codecoverage-main.yml** | Reusable **codecoverage** (PHP 7.4–8.4, minimum coverage) |
| **brand-plugin-test-playwright.yml** | Runs **module-plugin-test-playwright** with **wp-plugin-bluehost** + this repo’s branch |
| **newfold-prep-release.yml** | **workflow_dispatch** patch/minor/major → reusable **module prep release** (bumps **`package.json`**; see [release.md](release.md)) |
| **auto-translate.yml** | **reusable-translations** with **`text_domain: wp-module-mcp`** |
| **satis-webhook.yml** | On **release created**, dispatches to **newfold-labs/satis** to refresh Composer packages |

## Secrets

- **Satis / webhook:** `WEBHOOK_TOKEN` (for Satis dispatch)
- **Translations:** `TRANSLATOR_API_KEY` (for auto-translate)
