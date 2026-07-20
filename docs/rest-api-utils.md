---
name: wp-module-mcp
title: RestApiUtils
description: Dynamic REST API discovery, schema extraction, and route building for BLU abilities.
updated: 2026-07-15
---

# RestApiUtils

`BLU\Abilities\RestApiUtils` is the shared utility for discovering WordPress REST routes, building JSON schemas, and constructing concrete item URLs. Ability classes should prefer these helpers over hardcoded `/wp/v2/...` paths or ad hoc `str_replace()` on route capture groups.

## Schema vs route timing

BLU uses a **hybrid pattern**:

| Concern | When | How |
|---------|------|-----|
| **Input schema** | Ability registration (`wp_abilities_api_init`) | `RestControllerSchemaBuilder` reads controller public methods, or `extract_input_schema()` when routes are already registered |
| **Route path** | Ability execution | `get_latest_available_rest_route()`, `resolve_item_route()`, or `resolve_param_route()` |

Core WP controllers expose args before `rest_api_init` priority 99, so schemas can be built early. WooCommerce and other plugins may register namespaces lazily; call `eager_load_rest_routes()` before discovery when routes are not visible yet.

## Core discovery methods

### `get_latest_namespace( string $base_namespace ): ?string`

Returns the highest registered version for a base namespace (`wc` → `wc/v3`, `wp` → `wp/v2`).

### `get_latest_available_rest_route( string $base_namespace, string $resource_path ): ?string`

Resolves the latest namespace, then finds the collection route for a resource (`products`, `posts`, `users`, etc.). Calls `eager_load_rest_routes()` automatically and retries with a fresh route snapshot if the first lookup fails (e.g. stale per-request cache from before `rest_api_init`).

### `find_route_by_resource( string $namespace, string $resource_path, bool $exact_match = true ): ?string`

Finds a registered route within a known namespace. Use parameterized `resource_path` values such as `orders/(?P<id>[\d]+)` when you need the item route pattern.

### `eager_load_rest_routes(): void`

Fires `rest_pre_dispatch` with a synthetic `GET /` request so lazy namespaces (WooCommerce 10.3+) register before route enumeration. Respects the `blu_mcp_list_api_eager_load` filter (default `true`).

## Schema helpers

### `extract_input_schema( string $route, string $method ): ?array`

Builds a JSON schema from a registered endpoint's `args`. Returns `additionalProperties: true` so callers can pass native REST parameters not listed explicitly.

### `args_to_input_schema( array $args, bool $skip_context = true ): array`

Converts REST arg definitions to JSON Schema. Used by `RestControllerSchemaBuilder`.

### `schema_from_controller_args( ... ): array`

Merges controller args with extra properties/required fields. Preferred path for WP core abilities.

### `passthrough_object_schema( string $description ): array`

Returns `{ type: object, description, additionalProperties: true }` for abilities that accept arbitrary native REST payloads when controller enumeration is not used.

## Route building helpers

Avoid duplicating ID substitution logic at call sites. Use these instead of manual `str_replace( '(?P<id>...)', ... )` or `$route . '/' . $id` when the collection route may include a trailing capture group.

### `build_item_route( string $route, $id ): ?string`

Strips a trailing `(?P<name>...)` segment (if present) and appends `/{$id}`. Returns `null` when the ID is missing or not a positive integer.

```php
$item = RestApiUtils::build_item_route( '/wp/v2/global-styles/(?P<id>[\d]+)', 42 );
// → /wp/v2/global-styles/42
```

### `substitute_route_params( string $route, array $params ): string`

Replaces named capture groups left-to-right. Numeric IDs are inserted as-is; other values are `rawurlencode()`'d for safe path segments:

```php
$route = RestApiUtils::substitute_route_params(
    '/wc/v3/products/(?P<product_id>[\d]+)/variations/(?P<id>[\d]+)',
    array( 'product_id' => 10, 'id' => 55 )
);
```

### `normalize_route_for_match( string $route ): string`

Strips all `(?P<name>...)` segments and collapses repeated slashes. Used internally by `find_route_by_resource()` to avoid false negatives on routes with mid-path captures.

### `log_registration_schema_fallback( string $ability_id, string $reason ): void`

Logs when registration-time `extract_input_schema()` falls back to a minimal object schema. Runtime REST validation still applies. Enable via the `blu_mcp_log_schema_fallback` filter (defaults to `WP_DEBUG`).

### `resolve_item_route( string $base_namespace, string $resource_path, $id ): ?string`

Combines `get_latest_available_rest_route()` + `build_item_route()`:

```php
$route = RestApiUtils::resolve_item_route( 'wp', 'users', $user_id );
```

### `resolve_param_route( string $base_namespace, string $resource_pattern_path, array $params ): ?string`

Discovers a parameterized route, then substitutes capture groups. Used for nested WooCommerce resources (variations, attribute terms, etc.):

```php
$route = RestApiUtils::resolve_param_route(
    'wc',
    'products/(?P<id>[\d]+)/variations',
    array( 'id' => $product_id )
);
```

## Recommended ability patterns

### WordPress core resource (posts, users, media)

```php
$schema = RestControllerSchemaBuilder::for_post_type( 'post' );

blu_register_ability( 'blu/posts-search', array(
    'input_schema'     => $schema->collection(),
    'execute_callback' => function ( $input = null ) {
        $root = RestApiUtils::get_latest_available_rest_route( 'wp', 'posts' );
        if ( ! $root ) {
            return blu_standardize_route_unavailable_for_resource( 'posts' );
        }
        $request = new \WP_REST_Request( 'GET', $root );
        // ...
    },
) );
```

### WooCommerce resource (deferred route resolution)

```php
'execute_callback' => function ( $input ) {
    RestApiUtils::eager_load_rest_routes();
    $route = RestApiUtils::resolve_item_route( 'wc', 'products', $input['id'] );
    if ( ! $route ) {
        return blu_standardize_route_unavailable_for_resource( 'products', 'wc' );
    }
    $request = new \WP_REST_Request( 'GET', $route );
    // ...
},
```

### WooCommerce Analytics (`wc-analytics`)

Lazy-loaded like `wc`; use `get_latest_available_rest_route( 'wc-analytics', 'reports/orders/stats' )` at execute time. See `WooAnalytics.php`.

## Timing caveats

1. **`wp_abilities_api_init` precedes full REST registration** for some plugins. Register abilities unconditionally; resolve routes at execute time.
2. **WooCommerce lazy namespaces** — without `eager_load_rest_routes()`, `find_route_by_resource()` may return `null` during registration even when the store is active.
3. **Schema at registration, route at execution** — do not capture route strings in `use ($route)` closures during construction unless you also retry discovery at execution.
4. **Optional endpoints** (e.g. `products/brands`) — register the ability and return a clear error at execute time if the route is unavailable.

## See also

- [backend.md](backend.md) — ability layout and validation
- [architecture.md](architecture.md) — hook order and bootstrap
- `includes/RestControllerSchema/RestControllerSchemaBuilder.php` — controller-driven schemas
