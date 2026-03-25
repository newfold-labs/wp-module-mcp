---
name: wp-module-mcp
title: Dependencies
description: Composer packages and host-plugin integration for MCP.
updated: 2026-03-26
---

# Dependencies

## Runtime (`require`)

| Package | Role |
|---------|------|
| **wordpress/mcp-adapter** (^0.4.1) | MCP server/transport integration with WordPress REST. |
| **wordpress/abilities-api** (^0.4.0) | Abilities registration API (`wp_register_ability`, categories, etc.). |
| **firebase/php-jwt** (^6.10) | JWT parsing/verification in **`McpValidation`**. |

## Host plugin (not Composer)

**`McpServer`** depends on classes under **`Bluehost\Plugin\WP\MCP\...`** (adapter, HTTP transport, error handler, observability). These ship with the **Bluehost WordPress plugin** (or an equivalent fork). Loading this module outside that environment requires those classes to be present or the architecture to be refactored.

## Development (`require-dev`)

| Package | Role |
|---------|------|
| **johnpbloch/wordpress** | WordPress core for tests |
| **lucatume/wp-browser** | Codeception WordPress integration |
| **phpunit/phpcov** | Coverage merge for `composer run test-coverage` |
| **newfold-labs/wp-php-standards** | PHPCS **Newfold** ruleset |
