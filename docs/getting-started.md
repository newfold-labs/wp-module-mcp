---
name: wp-module-mcp
title: Getting started
description: Prerequisites, installing the module, and running tests.
updated: 2026-03-26
---

# Getting started

## Prerequisites

- **PHP** and **Composer**
- A **WordPress** install and test DB if you run **Codeception WPUnit** locally
- The module is designed to run **inside a brand plugin** that provides **McpAdapter** and related classes (see [dependencies.md](dependencies.md))

## Install as a dependency

```json
"require": {
  "newfold-labs/wp-module-mcp": "^1.0"
}
```

Composer autoloads `includes/functions.php` and `bootstrap.php`.

## Local clone

```bash
git clone <repository-url> wp-module-mcp
cd wp-module-mcp
composer install
```

## Tests

Configure **`.env.testing`** for paths and DB credentials (see `codeception.dist.yml`). Then:

```bash
composer run test
composer run test-coverage
```

See [testing.md](testing.md) for CI and coverage details.

## Remote MCP client

See the root **[README.md](../README.md)** for example `WP_API_URL` (`/wp-json/blu/mcp`) and environment variables for `@newfold/wp-mcp-connector`.
