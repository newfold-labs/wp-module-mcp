---
name: wp-module-mcp
title: Backend (PHP)
description: Main classes, abilities layout, validation, and helpers.
updated: 2026-03-26
---

# Backend (PHP)

## Core

| Piece | Role |
|-------|------|
| `includes/McpServer.php` | Wires `mcp_adapter_init` / Abilities API hooks; creates MCP server via adapter; registers category `blu-mcp`. |
| `includes/functions.php` | Global **`blu_*`** helpers: register/unregister/get abilities and categories; filter by category/namespace; **`blu_prepare_ability_response`** / **`blu_standardize_rest_response`**; status mapping. |
| `includes/Validation/McpValidation.php` | Transport permission: logged-in admin **or** Bearer JWT verified against Hiive public keys (staging vs prod URLs). |

## Abilities

Implementations live under **`includes/Abilities/`** (e.g. `Posts`, `Pages`, `Media`, `Users`, `SiteInfo`, `Settings`, `CustomPostTypes`, `RestApiCrud`, `GlobalStyles`, `Themes`, `WooProducts`, `WooOrders`, `Prompts`, `Resources`). Each class registers its abilities on construction.

## Discovery tools

`AbilityGateway` registers three meta-tools (`blu-list-abilities`, `blu-get-ability-schema`, `blu-call-ability`). `RestApiCrud` registers `blu-list-api-functions`, `blu-get-function-details`, and `blu-run-api-function` for raw REST access.

The two list tools accept optional filter arguments documented in **[api.md](api.md)** and **[reference.md](reference.md)**. `blu-list-abilities` filters by `search` and `name_prefix`; `blu-list-api-functions` filters by `namespace`, `methods`, and `search`. Both set `additionalProperties: false` on input schemas to reject unknown fields. Output schemas are omitted, matching the module's existing convention; response shapes are documented in `api.md`.

## Assets

**`includes/instructions/`** holds prompt/instruction content used by prompt-related abilities where applicable.

## Autoload

Composer **PSR-4** maps namespace **`BLU\\`** → **`includes/`**.
