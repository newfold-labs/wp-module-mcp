---
name: wp-module-mcp
title: Changelog
description: Notable version-to-version changes for wp-module-mcp.
updated: 2026-03-26
---

# Changelog

Notable changes are listed here; older history may be on **GitHub Releases**.

## [Unreleased]

- **RestApiUtils:** centralized route helpers (`build_item_route`, `resolve_item_route`, `resolve_param_route`, `eager_load_rest_routes`); controller schemas now set `additionalProperties: true` for native REST pass-through params.
- **Users:** restore default `context=edit` on read/update calls so email and other edit-context fields are returned (overridable via `context` input).
- **Users:** `blu/delete-user` now requires `reassign` in the input schema (matching the native `wp/v2/users` DELETE endpoint, which rejects requests missing it). Previously this module silently defaulted `reassign` to `false` when omitted; callers must now explicitly pass a user ID or `false`. `force` still defaults to `true` since users cannot be trashed.
- **Media:** `blu/search-media` retained as a deprecated alias of `blu/list-media`; both share the same handler.
- **WooAnalytics:** new `WooAnalytics` class for `wc-analytics` stats reports with execute-time route resolution; legacy `wc/v3` totals moved off `WooOrders`.
- **WooProducts:** `blu/wc-reports-reviews-totals` moved from `WooOrders` (product reviews report).
- Documentation: added **AGENTS.md**, **CLAUDE.md** (symlink), and **docs/** per Newfold module documentation standards (replaces superseded `add/docs` branch work).

---

When tagging, add a section such as `## [x.y.z] - YYYY-MM-DD` and summarize API, dependency, or tool changes.
