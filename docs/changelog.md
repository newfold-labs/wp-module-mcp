---
name: wp-module-mcp
title: Changelog
description: Notable version-to-version changes for wp-module-mcp.
updated: 2026-03-26
---

# Changelog

Notable changes are listed here; older history may be on **GitHub Releases**.

## [Unreleased]

- Changed: post, page, custom post type, and WooCommerce product searches default to `status=any`; tool descriptions explain explicit `status=trash` lookup and each result reports its actual status. Custom post type results are filtered for read permission so the broader default does not expose another user's unpublished items.

- Documentation: added **AGENTS.md**, **CLAUDE.md** (symlink), and **docs/** per Newfold module documentation standards (replaces superseded `add/docs` branch work).

---

When tagging, add a section such as `## [x.y.z] - YYYY-MM-DD` and summarize API, dependency, or tool changes.
