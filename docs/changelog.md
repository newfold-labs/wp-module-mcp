---
name: wp-module-mcp
title: Changelog
description: Notable version-to-version changes for wp-module-mcp.
updated: 2026-03-26
---

# Changelog

Notable changes are listed here; older history may be on **GitHub Releases**.

## [Unreleased]

- Fixed: a trashed post, page, product or CPT item is findable again by title. `blu-posts-search`, `blu-pages-search`, `blu-wc-products-search` and `blu-cpt-search` retry once against `status=trash` when a search term returns nothing and the caller named no status, so a just-deleted item comes back carrying `status: "trash"` instead of looking like it never existed. Unfiltered listings still exclude trash. The `status` descriptions now name `trash` as an accepted value and describe the retry; the posts and pages descriptions previously claimed omitting `status` searched "all statuses", which was never true of the trash. (PRESS0-4902)
- Documentation: added **AGENTS.md**, **CLAUDE.md** (symlink), and **docs/** per Newfold module documentation standards (replaces superseded `add/docs` branch work).

---

When tagging, add a section such as `## [x.y.z] - YYYY-MM-DD` and summarize API, dependency, or tool changes.
