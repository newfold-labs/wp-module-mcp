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

### Post-type permissions

The custom post type abilities query posts directly. Their permission callbacks check the requested type's capabilities; `get-cpt` checks `read_post`, while updates and deletes check `edit_post` and `delete_post`. Creating requires the type's `create_posts` capability; publishing requires `publish_posts`. These checks honor WordPress denials even for administrators. Search filters inaccessible posts before pagination; totals describe accessible results.

### Searching by status

Post, page, custom post type, and WooCommerce product searches default to `status=publish`. Pass `status=any` to include other non-trash statuses, or `status=trash` to locate a deleted item. Each result includes its actual `status`; a normal search never retries in the trash. Post and page searches return compact `id`, plain `title`, `status`, and `slug` entries; their get abilities provide full details. Custom post type searches check visibility before pagination and return counts of accessible items because they query posts directly rather than through a REST controller.

WordPress uses `future` for scheduled content. Its current, visible status slugs, including eligible custom statuses, are available from `GET /wp/v2/statuses` through the REST gateway. The search abilities accept status strings rather than a fixed enum so plugin-registered statuses are not frozen into the tool schema. WooCommerce validates product status against its own REST endpoint's accepted values, which may differ from the WordPress status catalog.

### Direct media access

`blu/get-media-file` reads attachment bytes directly, so it verifies the target is an attachment and checks `read_post` for that ID before opening the file. `blu/upload-media` uses `wp_check_filetype_and_ext` after writing upload bits; it deletes files whose contents do not match an allowed extension before registering the attachment.

## Discovery tools

`AbilityGateway` registers three meta-tools (`blu-list-abilities`, `blu-get-ability-schema`, `blu-call-ability`). `RestApiCrud` registers `blu-list-api-functions`, `blu-get-function-details`, and `blu-run-api-function` for raw REST access.

The gateway includes Yoast SEO abilities when Yoast registers them. Its whitelist requires both the `yoast-seo/` namespace and `yoast-seo` category; the abilities remain behind the three gateway tools and retain Yoast's own permission checks. Yoast may withhold its abilities when its indexables are disabled, including on local environments.

In gateway mode, only the three meta-tools appear in MCP `tools/list`. Clients discover and execute the REST abilities through those tools; the REST catalog reflects routes registered by active plugins.

For abilities with lengthy guidance, `blu-list-abilities` returns the optional `meta.mcp.summary` as the discovery description. `blu-get-ability-schema` still returns the complete ability description and input schema. Search matches the complete description so shorter catalog entries do not hide an ability from discovery.

The two list tools accept optional filter arguments documented in **[api.md](api.md)** and **[reference.md](reference.md)**. `blu-list-abilities` filters by `search` and `name_prefix`; `blu-list-api-functions` filters by `namespace`, `methods`, and `search`. Both set `additionalProperties: false` on input schemas to reject unknown fields. Output schemas are omitted, matching the module's existing convention; response shapes are documented in `api.md`.

## Assets

**`includes/instructions/`** holds prompt/instruction content used by prompt-related abilities where applicable.

## Autoload

Composer **PSR-4** maps namespace **`BLU\\`** → **`includes/`**.
