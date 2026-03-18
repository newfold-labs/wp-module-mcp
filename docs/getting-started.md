---
name: wp-module-mcp
title: Getting started
description: Prerequisites, install, and run.
updated: 2025-03-18
---

# Getting started

Prerequisites: PHP 8.1+, Composer. The module requires wordpress/mcp-adapter, wordpress/abilities-api, firebase/php-jwt.

```bash
composer install
composer run test
composer run test:coverage
```

See [integration.md](integration.md) for using in a host plugin.
