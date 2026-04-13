---
name: wp-module-mcp
title: API and MCP endpoint
description: HTTP MCP surface, authentication, and client configuration.
updated: 2026-03-26
---

# API and MCP endpoint

This module does **not** call `register_rest_route` directly for the MCP endpoint; routes are registered by **wordpress/mcp-adapter** when the server is created. Behavior is defined in **`McpServer::register_server()`** (server id **`blu-mcp`**, route namespace **`blu`**, route **`mcp`**).

## Effective REST base

Clients typically call:

```text
{origin}/wp-json/blu/mcp
```

(Example in root **[README.md](../README.md)**: `WP_API_URL` = `https://example.com/wp-json/blu/mcp`.)

## Authentication

The **transport permission callback** instantiates **`McpValidation`** with the current `WP_REST_Request`:

1. If the user is **logged in** and has **`manage_options`**, the request is allowed.
2. Otherwise a **Bearer** token is required in the `Authorization` header; JWT is verified using Firebase JWT and public keys from Hiive CDN (see [reference.md](reference.md)).

## Tools

Tool names correspond to registered **abilities** in category **`blu-mcp`**. See **[README.md](../README.md)** for a tables of tools (posts, pages, media, WooCommerce, etc.).

## Related code

- `includes/McpServer.php` – `create_server(...)` arguments
- `includes/Validation/McpValidation.php` – auth implementation
