---
name: wp-module-mcp
title: Changelog
description: Notable version-to-version changes for wp-module-mcp.
updated: 2026-09-08
---

# Changelog

Notable changes are listed here; older history may be on **GitHub Releases**.

## [Unreleased]

- **RestApiUtils:** centralized route helpers (`build_item_route`, `resolve_item_route`, `resolve_param_route`, `eager_load_rest_routes`); controller schemas now set `additionalProperties: true` for native REST pass-through params.
- **RestApiUtils:** `extract_input_schema()` now marks named route captures (e.g. `id`, `product_id`, `attribute_id`) as `required` on the generated ability schema. Native REST controllers generally don't mark these `required` in their own `args` — the URL regex already guarantees them — but that guarantee doesn't carry over once the value becomes a plain ability input property. This closes a gap where, for example, `blu/wc-update-product` could be called without `id` and would fail deep inside the callback instead of at schema validation.
- **RestApiUtils:** `args_to_input_schema()` now also carries through `pattern`, `minItems`, and `maxItems` from the native REST arg definition (previously only `type`, `enum`, `minimum`/`maximum`, `format`, `items`, and `default` were preserved).
- **RestApiUtils:** `get_latest_available_rest_route()` and `resolve_param_route()` are now resource-aware: instead of picking the highest-versioned namespace and then looking for the resource (which returned `null` if a newer namespace existed but didn't expose that particular resource), they search matching namespaces in descending version order and return the route from the first one that actually exposes it.
- **WooProducts:** restored `minimum: 1` validation on `product_id` and other numeric ID inputs (product, category, tag, brand, attribute, variation IDs) across `WooProducts`/`WooOrders` manually-declared schemas.
- **Users:** restore default `context=edit` on read/update calls so email and other edit-context fields are returned (overridable via `context` input).
- **Users:** `blu/delete-user` now requires `reassign` in the input schema (matching the native `wp/v2/users` DELETE endpoint, which rejects requests missing it). Previously this module silently defaulted `reassign` to `false` when omitted; callers must now explicitly pass a `reassign` value. **Correction:** `reassign` must be an **integer** — pass `0` to delete the user's content instead of reassigning it. The generated schema's `type` is `integer`, so the literal boolean `false` (which native WordPress core also accepts) is rejected by ability-schema validation before the request ever reaches WordPress. `force` still defaults to `true` since users cannot be trashed.
- **Users:** restored the current-user deletion guard — `blu/delete-user` refuses to delete the account it is currently authenticated as, regardless of the `id` passed, returning a 400 instead of silently deleting the acting user.
- **WooProducts:** `blu/wc-add-product` no longer accepts a `variation_attributes` parameter to auto-create variations inline. To build a variable product: create/resolve attributes and terms first (`blu/wc-add-product-attribute`, `blu/wc-add-attribute-term`), pass the resolved `attributes` array to `blu/wc-add-product`, then create variations with `blu/wc-add-product-variation` or `blu/wc-generate-product-variations`.
- **WooProducts:** added `blu/wc-delete-product-variation` (was implemented but missing from the README).
- **WooOrders:** added `blu/wc-update-order` (was implemented but missing from the README).
- **Prompts:** documented in the README that `blu/guided-product-creation-prompt` may create taxonomy entities (categories, tags, attributes, terms) during its enrichment steps — before the merchant confirms and before the product itself is created. Only the product (and its variations) waits for explicit confirmation; taxonomy entities created along the way are not rolled back if the merchant backs out.
- **Media:** `blu/search-media` retained as a deprecated alias of `blu/list-media`; both share the same handler.
- **WooAnalytics:** new `WooAnalytics` class for `wc-analytics` stats reports with execute-time route resolution; legacy `wc/v3` totals moved off `WooOrders`.
- **WooProducts:** `blu/wc-reports-reviews-totals` moved from `WooOrders` (product reviews report).
- Documentation: added **AGENTS.md**, **CLAUDE.md** (symlink), and **docs/** per Newfold module documentation standards (replaces superseded `add/docs` branch work).

---

When tagging, add a section such as `## [x.y.z] - YYYY-MM-DD` and summarize API, dependency, or tool changes.
