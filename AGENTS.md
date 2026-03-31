# Agent guidance – wp-module-mcp

Short orientation for AI agents and developers. Full detail lives in **docs/**; start with **docs/index.md**.

## What this project is

**wp-module-mcp** is a **Newfold Labs** Composer package that wires WordPress **Abilities API** abilities into the **MCP (Model Context Protocol)** stack used by BLU / brand plugins. On `plugins_loaded` it initializes the host **McpAdapter**, registers a **BLU `McpServer`** that exposes tools over HTTP (WordPress **mcp-adapter**), and registers ability categories and tool classes under the **`blu-mcp`** category. It is consumed by brand plugins (e.g. Bluehost) via Composer (Satis).

## Stack

- **PHP** – Composer `platform` declares **7.4**; PHPCS **Newfold** ruleset, minimum WordPress **6.0**. Some code uses PHP 8+ APIs (e.g. `str_starts_with`); run and release versions should follow the **host plugin** and CI matrix.
- **WordPress packages (Composer)** – `wordpress/mcp-adapter`, `wordpress/abilities-api`, `firebase/php-jwt`
- **Host plugin classes** – `McpServer` references **Bluehost** MCP adapter/transport/error-handler classes; the module expects those to exist when loaded inside the Bluehost plugin (or a compatible build).

## Key paths

| Purpose | Location |
|--------|----------|
| Loader entry, adapter + server bootstrap | `bootstrap.php` |
| Global helpers (`blu_*` ability wrappers, REST helpers) | `includes/functions.php` |
| MCP server registration, ability bootstrapping | `includes/McpServer.php` |
| Ability implementations (posts, media, Woo, etc.) | `includes/Abilities/` |
| MCP transport auth / JWT validation | `includes/Validation/McpValidation.php` |
| Prompt / instruction assets | `includes/instructions/` |
| PHP coding standard | `phpcs.xml` |
| WPUnit tests (Codeception) | `tests/wpunit/` |
| Version for releases (prep-release workflow) | `package.json` → `version` |
| CI workflows | `.github/workflows/` |

## Essential commands

```bash
composer install
composer run lint          # PHPCS
composer run fix           # PHPCBF
composer run test          # codecept run wpunit
composer run test-coverage # wpunit + phpcov HTML under tests/_output/
```

Codeception reads **`.env.testing`** (see `codeception.dist.yml`). Copy from `.env.testing.example` if present.

## Documentation

- **Full documentation:** **docs/** — table of contents in **docs/index.md**.
- **CLAUDE.md** should be a **symlink to AGENTS.md** (Cursor/Claude). Recreate the link if your clone does not preserve symlinks.

## Keeping documentation current

When you change code, features, or workflows, update the docs so they stay accurate.

- **Keep docs/index.md current** when you add, remove, or rename files under `docs/`.
- Prefer updating the right file in **docs/** over leaving stale text.
- Examples: new abilities or categories → **overview.md**, **backend.md**, **architecture.md**; transport or auth changes → **api.md**, **reference.md**; new Composer deps → **dependencies.md**; CI/release changes → **workflows.md**, **release.md**; test commands/layout → **testing.md**.
- For releases, update **docs/changelog.md** and align with GitHub releases when applicable.
