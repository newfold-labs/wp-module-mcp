---
name: wp-module-mcp
title: Architecture
description: Bootstrap sequence, WordPress hooks, and integration with the MCP adapter.
updated: 2026-03-26
---

# Architecture

## Bootstrap (`bootstrap.php`)

On **`plugins_loaded`**:

1. **`McpAdapter::instance()`** (from the host plugin’s `Bluehost\Plugin\WP\MCP\Core\McpAdapter`) – ensures the MCP adapter initializes and can hook **`rest_api_init`** and related MCP plumbing.
2. **`new BLU\McpServer()`** – registers WordPress actions that defer work to MCP and Abilities API hooks.

## Hooks

| Hook | Handler |
|------|---------|
| `mcp_adapter_init` | `McpServer::register_server` – builds tool list from abilities in category `blu-mcp`, calls `$adapter->create_server(...)` with server id `blu-mcp`, route namespace `blu`, route segment `mcp`, HTTP transport, error/observability handlers, and a **permission callback** that delegates to **`McpValidation::is_authenticated()`**. |
| `wp_abilities_api_init` | `McpServer::register_abilities` – instantiates ability classes under `includes/Abilities/` (Prompts, Resources, Posts, Pages, …). |
| `wp_abilities_api_categories_init` | `McpServer::register_ability_categories` – registers category **`blu-mcp`** (“Bluehost MCP”). |

## Ability model

- Abilities register through **`blu_register_ability`** (wrapping **`wp_register_ability`**) and assign the **`blu-mcp`** category so the MCP server can collect their names for the tool list.
- The **mcp-adapter** exposes those tools over the REST MCP endpoint; exact REST routes are registered by the adapter package, not duplicated in this module.

## Host plugin coupling

**`McpServer`** imports **Bluehost** classes: `McpAdapter`, `HttpTransport`, `ErrorLogMcpErrorHandler`, `NullMcpObservabilityHandler`. The module is not fully generic without a compatible adapter implementation on the classpath.
