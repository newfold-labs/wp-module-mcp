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
| `includes/functions.php` | Global **`blu_*`** helpers: register/unregister/get abilities and categories; filter by category/namespace; **`blu_prepare_ability_response`** / **`blu_standardize_rest_response`**; status mapping; **`blu_should_retry_in_trash`** (see [Searching and the trash](#searching-and-the-trash)). |
| `includes/Validation/McpValidation.php` | Transport permission: logged-in admin **or** Bearer JWT verified against Hiive public keys (staging vs prod URLs). |

## Abilities

Implementations live under **`includes/Abilities/`** (e.g. `Posts`, `Pages`, `Media`, `Users`, `SiteInfo`, `Settings`, `CustomPostTypes`, `RestApiCrud`, `GlobalStyles`, `Themes`, `WooProducts`, `WooOrders`, `Prompts`, `Resources`). Each class registers its abilities on construction.

### Searching and the trash

`blu-posts-search`, `blu-pages-search`, `blu-wc-products-search` and `blu-cpt-search` do **not** include trashed items in an ordinary listing. A listing should show what the site has, not what was thrown away.

They do make a trashed item findable when someone looks for it by name. Deleting through MCP is a soft delete: WordPress keeps the title and moves the item to `trash`, which is outside every status these tools query by default (WP_Query's `any` excludes it too). A caller that resolves a delete target by title (which is how the BLU agent is instructed to work, rather than by raw ID) would search straight after a delete, get nothing, and conclude the item never existed.

So each of the four retries **once**, against `status=trash`, and only when all of these hold (`blu_should_retry_in_trash`):

- the caller passed a non-empty `search` term, and
- the caller did not name its own `status`, and
- the first query returned zero rows.

The retry returns the trashed rows as-is, so the item arrives carrying `status: "trash"` and the caller can offer to restore it or delete it permanently (`force=true`). A caller that asks for specific statuses gets exactly those, and a search that matches a live item never has trash mixed in.

Two guards keep the retry from making things worse:

- **A failed retry is discarded.** Only a `200` with at least one row replaces the original response. A REST error is still an array, so without this check an unrelated failure (a forbidden status, or `rest_post_invalid_page_number` when `page` runs past the end of the trashed set) would replace a perfectly good "nothing matched" with a 4xx the caller never caused.
- **`blu-cpt-search` scopes its retry with `perm => 'editable'`.** The three REST-backed searches inherit WordPress's own capability filtering; a bare `WP_Query` does not, so without this an author searching by title would be handed another user's trashed item. Note this scoping applies to the retry only. `blu-cpt-search` performs no capability filtering on the statuses a caller names explicitly, which predates this change and is tracked separately.

Regression coverage: `tests/wpunit/TrashSearchFallbackWPUnitTest.php`.

## Discovery tools

`AbilityGateway` registers three meta-tools (`blu-list-abilities`, `blu-get-ability-schema`, `blu-call-ability`). `RestApiCrud` registers `blu-list-api-functions`, `blu-get-function-details`, and `blu-run-api-function` for raw REST access.

The two list tools accept optional filter arguments documented in **[api.md](api.md)** and **[reference.md](reference.md)**. `blu-list-abilities` filters by `search` and `name_prefix`; `blu-list-api-functions` filters by `namespace`, `methods`, and `search`. Both set `additionalProperties: false` on input schemas to reject unknown fields. Output schemas are omitted, matching the module's existing convention; response shapes are documented in `api.md`.

## Assets

**`includes/instructions/`** holds prompt/instruction content used by prompt-related abilities where applicable.

## Autoload

Composer **PSR-4** maps namespace **`BLU\\`** → **`includes/`**.
