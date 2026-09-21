---
name: wp-module-mcp
title: Changelog
description: Notable version-to-version changes for wp-module-mcp.
updated: 2026-03-26
---

# Changelog

Notable changes are listed here; older history may be on **GitHub Releases**.

## [Unreleased]

- Changed: post, page, custom post type, and WooCommerce product searches default to `status=publish`; tool descriptions explain explicit `status=any` and `status=trash` lookups, and each result reports its actual status. Post and page searches return compact results with a plain title and request only the REST fields they need. Custom post type results are filtered for read permission before pagination, so totals count accessible items.
- Fixed: custom post type abilities now check the requested type's capabilities and the target item's permissions before direct reads, writes, or deletes. (PRESS0-5133)

- Documentation: added **AGENTS.md**, **CLAUDE.md** (symlink), and **docs/** per Newfold module documentation standards (replaces superseded `add/docs` branch work).

---

When tagging, add a section such as `## [x.y.z] - YYYY-MM-DD` and summarize API, dependency, or tool changes.
