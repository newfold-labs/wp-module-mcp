# Agent guidance – wp-module-mcp

This file gives AI agents a quick orientation to the repo. For full detail, see the **docs/** directory.

## What this project is

- **wp-module-mcp** – WordPress MCP module for Newfold Labs BLU. Registers with the Newfold Module Loader; depends on wordpress/mcp-adapter, wordpress/abilities-api, firebase/php-jwt. Maintained by Newfold Labs.

- **Stack:** PHP 8.1+. See docs/dependencies.md.

- **Architecture:** Registers with the loader; provides MCP (Model Context Protocol) integration for BLU. See docs/integration.md.

## Key paths

| Purpose | Location |
|---------|----------|
| Bootstrap | `bootstrap.php` |
| Includes | `includes/` (BLU namespace) |
| Tests | `tests/` |

## Essential commands

```bash
composer install
composer run test
composer run test:coverage
```

## Documentation

- **Full documentation** is in **docs/**. Start with **docs/index.md**.
- **CLAUDE.md** is a symlink to this file (AGENTS.md).

---

## Keeping documentation current

When you change code, features, or workflows, update the docs. Keep **docs/index.md** current: when you add, remove, or rename doc files, update the table of contents (and quick links if present). When adding or changing dependencies, update **docs/dependencies.md**. When cutting a release, update **docs/changelog.md**.
