---
name: wp-module-mcp
title: Changelog
description: Notable version-to-version changes for wp-module-mcp.
updated: 2026-03-26
---

# Changelog

Notable changes are listed here; older history may be on **GitHub Releases**.

## [Unreleased]

- **RestApiUtils:** per-request caching for routes/namespaces; fixed `find_route_by_resource` normalization (removes all named captures, collapses slashes); `build_item_route()` returns `null` for invalid IDs; `substitute_route_params()` URL-encodes non-numeric values; `log_registration_schema_fallback()` for registration-time schema discovery.
- **Error responses:** route-not-found errors now return HTTP 404 with `errorCode: blu_rest_route_unavailable` via `blu_standardize_route_unavailable_for_resource()`.
- **Delete semantics:** `blu/delete-post` and `blu/delete-page` default `force=true` (permanent delete), matching `blu/delete-media`. Pass `force=false` to move to trash.
- **Users:** `blu/delete-user` requires `reassign` per REST schema (breaking change). Restore default `context=edit` on read/update calls so email and other edit-context fields are returned (overridable via `context` input).
- **Prompts:** `product_id` on category suggestions enforces `minimum: 1` with runtime validation when provided.
- **Media:** `blu/search-media` retained as a deprecated alias of `blu/list-media`; both share the same handler.
- **WooAnalytics:** new `WooAnalytics` class for `wc-analytics` stats reports with execute-time route resolution; legacy `wc/v3` totals moved off `WooOrders`.
- **WooProducts:** `blu/wc-reports-reviews-totals` moved from `WooOrders` (product reviews report).
- **Tests:** added `RestApiUtilsWPUnitTest` and `MediaAbilitiesWPUnitTest` (search-media alias, WooAnalytics smoke).
- Documentation: added **AGENTS.md**, **CLAUDE.md** (symlink), and **docs/** per Newfold module documentation standards (replaces superseded `add/docs` branch work).

---

When tagging, add a section such as `## [x.y.z] - YYYY-MM-DD` and summarize API, dependency, or tool changes.
