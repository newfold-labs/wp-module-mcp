---
name: wp-module-mcp
title: Release process
description: Version bump locations and Newfold prep-release workflow.
updated: 2026-03-26
---

# Release process

## Preferred: Newfold Prepare Release

Use **Actions → Newfold Prepare Release → Run workflow** (`.github/workflows/newfold-prep-release.yml`). It calls **`reusable-module-prep-release.yml`** with:

- **`json-file`:** `package.json`
- **`php-file`:** *(empty)* — no PHP file is passed for version bump in this workflow configuration

So the **single source of truth** for the automated bump is **`package.json` → `version`**.

## Hardcoded version locations

| Location | Notes |
|----------|--------|
| **`package.json`** | **`version`** — updated by prep-release workflow |

There is **no** `NFD_*_VERSION` constant in `bootstrap.php` on current `main`. If you add one later, document it here and add **`php-file`** to the prep-release workflow inputs.

## Build step

There is **no** `npm run build` or similar required for releasing this PHP package. **`package.json`** includes **`@wordpress/env`** as a devDependency for local environments only; it is not part of a release artifact build in this repo.

After publishing a GitHub **release**, **Trigger Satis Build** (`satis-webhook.yml`) notifies Satis (see [workflows.md](workflows.md)).

## Changelog

Record notable changes in **docs/changelog.md** when you cut releases.
