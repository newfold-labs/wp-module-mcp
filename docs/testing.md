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

Example test classes: **`ModuleLoadingWPUnitTest`**, **`McpServerWPUnitTest`**, **`McpValidationWPUnitTest`**, **`FunctionsWPUnitTest`**. Expand coverage when adding abilities or auth changes.

## End-to-end (Playwright)

This repo does **not** define Playwright specs under `tests/playwright/`. **Build and Test Module Updates in Brand Plugins (Playwright tests)** runs the **Bluehost** plugin’s Playwright suite against a branch of this module (see [workflows.md](workflows.md)). Helpers and fixtures live primarily in the **brand plugin**.

## CI

| Workflow | Role |
|----------|------|
| **Codecoverage-Main** | Reusable codecoverage across PHP 7.4–8.4; minimum coverage threshold |
| **Lint** | PHPCS on PHP file changes |
| **brand-plugin-test-playwright** | Bluehost + module branch E2E |

## Pull request expectations

- **Lint** and **codecoverage** should pass for PHP changes.
- Add or update **WPUnit** tests when behavior changes.
- Playwright failures may surface from the **Bluehost** workflow; coordinate fixes in the plugin or module as needed.
