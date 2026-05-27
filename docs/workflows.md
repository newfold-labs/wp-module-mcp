---
name: wp-module-mcp
title: GitHub workflows
description: CI, translations, Playwright, release prep, and Satis.
updated: 2026-05-22
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
| **dependabot-auto-merge.yml** | On completion of `Lint`, `Codecoverage-Main`, or `Build and Test … (Playwright tests)`, calls reusable **dependabot-auto-merge** — verifies every check run on the head SHA is green, then approves and merges Dependabot PRs. Gates on its own check-run aggregation, so it works without branch-protection required status checks |
| **ai-evals.yml** | PR / manual **MCP AI evals**: spins up **wp-env** with **wp-plugin-bluehost** (PR branch mapped into vendor), installs/activates **WooCommerce**, logs WP users, resolves an admin eval username, logs auth context (`siteurl`, eval user login/ID), creates a WP **application password** via WP-CLI, runs multi-turn tool-selection evals via **Cloudflare AI Gateway** + **MCP SDK** (`tools/list` only; gateway meta-tools ignored for assertions). Cases with the same **`series_id`** run in order with **shared chat history** (so “update the post” still knows which post was created). Each step is still scored on its own **`expected_tool`**. Coverage vs **`evals/test-cases.json`** is non-blocking. |

## Secrets

- **Satis / webhook:** `WEBHOOK_TOKEN` (for Satis dispatch)
- **Translations:** `TRANSLATOR_API_KEY` (for auto-translate)
- **AI evals (`ai-evals.yml`):** `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_AI_GATEWAY_TOKEN` (org secrets). No MCP URL or auth secrets — CI uses local wp-env + application password.

## Variables (repository / org)

- **AI evals:** `CLOUDFLARE_AI_GATEWAY_ID`. Optional `MCP_EVAL_NAMESPACE` (default `blu`) if set on the job env.


```
