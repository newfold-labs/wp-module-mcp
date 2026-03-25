---
name: wp-module-mcp
title: Overview
description: What the MCP module does, who maintains it, and main capabilities.
updated: 2026-03-26
---

# Overview

**wp-module-mcp** connects WordPress **Abilities API** tooling to the **Model Context Protocol (MCP)** so AI assistants and remote clients can perform site operations through a standardized HTTP surface (`wordpress/mcp-adapter`).

## What it provides

- Registration of a BLU MCP **server** (`blu-mcp` route namespace, HTTP transport) with tools derived from abilities in the **`blu-mcp`** category.
- A broad set of **abilities** (posts, pages, media, users, settings, themes, global styles, REST CRUD, WooCommerce when active, prompts, resources, etc.) implemented under `includes/Abilities/`.
- **Transport permission** callback via **`McpValidation`**: admin session or JWT bearer validation (Hiive public keys).
- Thin **`blu_*` helpers** in `includes/functions.php` around the Abilities API (`wp_register_ability`, categories, filtered lists, standardized REST responses).

## Who maintains it

Released as **newfold-labs/wp-module-mcp** (Composer / Satis). It is bundled in brand plugins such as **Bluehost**; server wiring uses **Bluehost**-namespaced MCP adapter classes from the host plugin.

## Related reading

- [architecture.md](architecture.md) – lifecycle and hooks
- [README.md](../README.md) – tool catalog and remote client JSON example
