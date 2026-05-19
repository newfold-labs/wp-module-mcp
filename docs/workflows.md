---
name: wp-module-mcp
title: GitHub workflows
description: CI, translations, Playwright, release prep, and Satis.
updated: 2026-05-18
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
| **ai-evals.yml** | PR / manual **MCP AI evals** (`evals/` TypeScript + **@modelcontextprotocol/sdk**): loads tools from a **live MCP server** (gateway `list-abilities` + `get-ability-schema`), runs tool-selection via **Cloudflare AI Gateway**, reports coverage vs **`evals/test-cases.json`** (non-blocking — does not fail the job; only eval pass/fail is enforced). |

## Secrets

- **Satis / webhook:** `WEBHOOK_TOKEN` (for Satis dispatch)
- **Translations:** `TRANSLATOR_API_KEY` (for auto-translate)
- **AI evals (`ai-evals.yml`):** `MCP_EVAL_AUTH_TOKEN` (Hiive JWT for staging/CI) **or** `MCP_EVAL_AUTH_BASIC` (WordPress `user:application-password` for local — use one); `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_AI_GATEWAY_TOKEN` (org secrets).

## Variables (repository / org)

- **AI evals:** `MCP_EVAL_SERVER_URL` — full MCP URL (e.g. `https://your-site.com/wp-json/blu/mcp` or `http://bluehost-local.local/wp-json/blu/mcp` when the runner can reach it); optional `MCP_EVAL_NAMESPACE` (default `blu`); `CLOUDFLARE_AI_GATEWAY_ID`.
