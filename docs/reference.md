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

## Ability category

| Slug | Label |
|------|--------|
| `blu-mcp` | Bluehost MCP |

Abilities registered in this category are exposed as MCP tools when the server is created.

## JWT validation (McpValidation)

Public key URLs (class constants):

| Constant | URL |
|----------|-----|
| Production | `https://cdn.hiive.space/jwt-public-key.pem` |
| Staging (qa audience) | `https://cdn.hiive.space/jwt-public-key-staging.pem` |

## Global helpers (`includes/functions.php`)

Notable **`blu_*`** functions: `blu_register_ability`, `blu_get_abilities`, `blu_get_abilities_by_category`, `blu_get_abilities_by_namespace`, `blu_prepare_ability_response`, `blu_standardize_rest_response`, `blu_get_status_type`.
