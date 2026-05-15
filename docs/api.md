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

## Discovery tool filters

The two discovery tools accept optional, AND-composed filter arguments. All filters are optional; passing an empty `arguments` object returns the full catalog.

`blu-list-abilities`:

| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Case-insensitive substring match across each ability's `name`, `label`, and `description`. |
| `name_prefix` | string | Prefix on the MCP tool name (hyphen form). Slash form is normalized to hyphen form (`"blu/wc"` ≡ `"blu-wc"`). |

Response items: `{ name, label, description, annotations }`. The previously-returned `namespace` field is no longer included — it always equaled `"blu"` and existed only for the now-removed `namespace` filter.

`blu-list-api-functions`:

| Parameter | Type | Description |
|-----------|------|-------------|
| `namespace` | string | Exact match on the REST namespace (first two path segments, e.g. `"wp/v2"`). Leading/trailing slashes tolerated. |
| `methods` | array of `"GET" \| "POST" \| "PATCH" \| "DELETE"` | HTTP methods to include (uppercase, validated by the schema enum). Empty/omitted means all methods. |
| `search` | string | Case-insensitive substring match on the route string. |

Response items: `{ route, method, namespace }`. `namespace` is derived from the route's first two path segments (empty string for malformed routes).

Both tools set `additionalProperties: false` on their input schemas so unknown fields are rejected at validation time. Output schemas are intentionally omitted to keep `tools/list` payload small — response shapes are documented above and stable.

## Related code

- `includes/McpServer.php` – `create_server(...)` arguments
- `includes/Validation/McpValidation.php` – auth implementation
- `includes/Abilities/AbilityGateway.php` – `blu-list-abilities` registration and filter logic
- `includes/Abilities/RestApiCrud.php` – `blu-list-api-functions` registration and filter logic
