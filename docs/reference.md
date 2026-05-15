---
name: wp-module-mcp
title: Reference
description: Ability category, server ids, and authentication endpoints.
updated: 2026-03-26
---

# Reference

## MCP server registration

| Setting | Value |
|---------|--------|
| Server ID | `blu-mcp` |
| REST route namespace | `blu` |
| Route segment | `mcp` |
| Server name / description | Set in `McpServer::register_server()` (Bluehost MCP Server) |

## Ability categories

The gateway whitelists abilities matching either an allowed namespace or an allowed category. Defaults:

| Namespace | Category | Source |
|-----------|----------|--------|
| `blu/` | `blu-mcp` | Bluehost (this module, registered in `McpServer::register_ability_categories`) |
| `woocommerce/` | `woocommerce-rest` | WooCommerce 10.3+ native abilities (registered by `woocommerce/src/Internal/Abilities/AbilitiesCategories.php`) |

Both lists are filterable via `blu_mcp_allowed_namespaces` and `blu_mcp_allowed_categories`.

## JWT validation (McpValidation)

Public key URLs (class constants):

| Constant | URL |
|----------|-----|
| Production | `https://cdn.hiive.space/jwt-public-key.pem` |
| Staging (qa audience) | `https://cdn.hiive.space/jwt-public-key-staging.pem` |

## Global helpers (`includes/functions.php`)

Notable **`blu_*`** functions: `blu_register_ability`, `blu_get_abilities`, `blu_get_abilities_by_category`, `blu_get_abilities_by_namespace`, `blu_prepare_ability_response`, `blu_standardize_rest_response`, `blu_get_status_type`.

## Discovery tool inputs

`blu-list-abilities` input properties (all optional, AND-composed):

| Property | Type | Constraints | Description |
|----------|------|-------------|-------------|
| `search` | string | 1–100 chars | Case-insensitive substring on `name`/`label`/`description`. |
| `name_prefix` | string | 1–100 chars, `^[A-Za-z0-9/_-]+$` | Prefix on MCP tool name (hyphen form); slash form normalized. |

Schema sets `additionalProperties: false` so unknown fields are rejected by `mcp-adapter`.

`blu-list-api-functions` input properties (all optional, AND-composed):

| Property | Type | Constraints | Description |
|----------|------|-------------|-------------|
| `namespace` | string | 1–100 chars, `^/?[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*/?$` | REST namespace as registered with WordPress, e.g. `wp/v2`, `wc/v3`, `wc-analytics`, `wc-admin/marketing`. Both single-segment (unversioned) and multi-segment namespaces are supported. |
| `methods` | array of string | items `enum: ["GET","POST","PATCH","DELETE"]`, `uniqueItems`, `maxItems: 4` | HTTP methods (must be uppercase to satisfy the enum). |
| `search` | string | 1–200 chars | Case-insensitive substring on the route. |

Schema sets `additionalProperties: false`.
