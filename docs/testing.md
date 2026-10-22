---
name: wp-module-mcp
title: Testing
description: WPUnit via Codeception, coverage, CI, Playwright via brand plugin.
updated: 2026-03-26
---

# Testing

## Unit / integration tests (WPUnit)

| Item | Location / notes |
|------|------------------|
| Suite | `tests/wpunit/` |
| Suite config | `tests/wpunit.suite.yml` |
| Global config | `codeception.dist.yml` (params from `.env.testing`) |
| Support | `tests/_support/` |

### Commands

```bash
composer run test
composer run test-coverage   # coverage + phpcov HTML under tests/_output/
```

### What exists today

Example test classes: **`ModuleLoadingWPUnitTest`**, **`McpServerWPUnitTest`**, **`McpValidationWPUnitTest`**, **`FunctionsWPUnitTest`**, **`CustomPostTypesWPUnitTest`**. Expand coverage when adding abilities or auth changes.

Ability tests register their fixtures (post types, REST routes) in `set_up` and unregister them in `tear_down` so state does not leak between tests. `CustomPostTypesWPUnitTest` is the reference for testing an ability end to end: register the `blu-mcp` category, fire `wp_abilities_api_init`, then call `blu_get_ability( 'blu/...' )->execute( $input )` and assert on the `statusCode` / `status` / `message` response shape.

## End-to-end (Playwright)

This repo does **not** define Playwright specs under `tests/playwright/`. **Build and Test Module Updates in Brand Plugins (Playwright tests)** runs the **Bluehost** plugin’s Playwright suite against a branch of this module (see [workflows.md](workflows.md)). Helpers and fixtures live primarily in the **brand plugin**.

## MCP gateway smoke test

`evals/smoke.mjs` connects to a running Bluehost WordPress instance with `WP_EVAL_USER`, `WP_APP_PASSWORD`, and `MCP_EVAL_SERVER_URL`. Run `node evals/smoke.mjs` after installing dependencies in `evals/`. It checks the three top-level MCP tools, lists WooCommerce MCP prompts and retrieves a guided prompt, then uses the gateway to discover REST abilities, inspect the posts endpoint, and dispatch a read request. The `ai-evals.yml` workflow runs this deterministic test as a merge gate. MCP prompts are tested through the prompt interface rather than tool-selection evals. Model tool-selection evals still run and report their results, but their variable scores are advisory. The menu cases create a page and navigation menu, then verify the saved menu item points to that page and is assigned to that menu. Each run saves a limited, argument-free AI trace as a workflow artifact; the PR table stays compact.

## CI

| Workflow | Role |
|----------|------|
| **Codecoverage-Main** | Reusable codecoverage across PHP 7.4–8.4; minimum coverage threshold |
| **Lint** | PHPCS on PHP file changes |
| **brand-plugin-test-playwright** | Bluehost + module branch E2E |
| **MCP AI Evals** | Required live MCP gateway smoke test; advisory model-selection report |

The AI selection report distinguishes exact ability selections (pass), successful REST dispatches explicitly listed as equivalent for that test (warning), other selections (fail), and eval errors. The expected-tool pass rate includes warnings in its denominator. Tool coverage compares `tools/list` with tool abilities; MCP prompts are outside that count. See [the 2026-10-21 recent-run analysis](../evals/reports/2026-10-21-recent-runs.md) for per-case historical rates and the remaining fixture-dependent coverage gaps.

## Pull request expectations

- **Lint** and **codecoverage** should pass for PHP changes.
- Add or update **WPUnit** tests when behavior changes.
- Playwright failures may surface from the **Bluehost** workflow; coordinate fixes in the plugin or module as needed.
