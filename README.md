# BLU MCP

The Bluehost MCP module exposes tools via the **Bluehost MCP Server** at `/wp-json/blu/mcp`. These are WordPress abilities exposed as MCP tools for AI assistants and MCP clients.

**Developer documentation:** see **[docs/index.md](docs/index.md)** (table of contents) and **[AGENTS.md](AGENTS.md)** for agents and repo orientation.

**MCP tool naming:** Abilities registered as `blu/<something>` are exposed as MCP tools named `blu-<something>` (slash replaced with hyphen). For example, ability `blu/posts-search` becomes MCP tool `blu-posts-search`. This hyphen form is what appears in `tools/list` and what the gateway returns.

---

## Gateway mode (default)

The server exposes **3 gateway tools** instead of ~83 individual tools. This reduces token usage by ~96% — the LLM discovers and calls abilities on demand rather than receiving all tool schemas upfront.

### Gateway tools (exposed via `tools/list`)

The server registers exactly 3 gateway tools. The default names are shown below, but **MCP clients must not hardcode these names**. Instead, call `tools/list` and identify the 3 gateway roles by their input schema shape:

| Role | Default name | How to identify (from `tools/list` inputSchema) |
|------|-------------|------------------------------------------------|
| **List** | `blu-list-abilities` | Has optional `search` and `name_prefix` properties (both string), no `ability_name` |
| **Schema** | `blu-get-ability-schema` | Requires `ability_name` (string), no `parameters` property |
| **Call** | `blu-call-ability` | Requires `ability_name` (string) and has optional `parameters` (object) |

### Session setup (one-time)

Before calling any tools, establish an MCP session:

1. Send `initialize` → server returns `Mcp-Session-Id` header
2. Send `notifications/initialized` with that session ID
3. Use the same `Mcp-Session-Id` in all subsequent requests until it expires (24h inactivity timeout)

You do **not** need to re-initialize for each tool call — reuse the session ID.

### Usage flow

Once a session is established, call `tools/list` to get the 3 gateway tools and identify them by schema shape. Then interact in 3 steps:

**1. Discover** — call the **List** tool to see what's available:
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "blu-list-abilities",
    "arguments": {}
  }
}
```
Response:
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "content": [
      {
        "type": "text",
        "text": "{\"statusCode\":200,\"status\":\"success\",\"message\":[...]}"
      }
    ],
    "structuredContent": {
      "statusCode": 200,
      "status": "success",
      "message": [
        {
          "name": "blu-posts-search",
          "label": "Search Posts",
          "description": "Search and filter WordPress posts with pagination",
          "annotations": { "readonly": true }
        }
      ]
    }
  }
}
```
The ability list is in `result.structuredContent.message` (parsed) or `result.content[0].text` (JSON string). Each entry includes `name` (hyphen-form, use this with `blu-get-ability-schema` and `blu-call-ability`), `label`, `description`, and `annotations`.

**2. Inspect** — call the **Schema** tool to learn what parameters an ability accepts:
```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/call",
  "params": {
    "name": "blu-get-ability-schema",
    "arguments": { "ability_name": "blu-posts-search" }
  }
}
```
Response `result.structuredContent.message` contains:
```json
{
  "name": "blu-posts-search",
  "label": "Search Posts",
  "description": "Search and filter WordPress posts with pagination",
  "input_schema": {
    "type": "object",
    "properties": {
      "search": { "type": "string", "description": "Search term" },
      "per_page": { "type": "integer", "description": "Posts per page" }
    }
  },
  "annotations": { "readonly": true }
}
```

**3. Execute** — call the **Call** tool with the ability name and parameters:
```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "blu-call-ability",
    "arguments": {
      "ability_name": "blu-posts-search",
      "parameters": { "search": "hello", "per_page": 5 }
    }
  }
}
```
Response `result.structuredContent` contains the ability's result (format varies by ability).

> **Important:** Never call ability names directly as MCP tool names (e.g. `"name": "blu-posts-search"` at the `tools/call` level). Abilities are only accessible through the **Call** gateway tool. The only valid MCP tool names are the 3 gateway tools returned by `tools/list`.

### Filtering the list

Both gateway list tools (`blu-list-abilities`) and the REST catalog tool (`blu-list-api-functions`) accept optional filters. All filters are AND-composed; omit them to return the full catalog.

`blu-list-abilities`:

| Filter | Type | Behavior |
|--------|------|----------|
| `search` | string | Case-insensitive substring match across each ability's `name` (hyphen form), `label`, and `description`. |
| `name_prefix` | string | Prefix match on the MCP tool name (hyphen form). Two WooCommerce surfaces are exposed: `"blu-wc-"` for Bluehost's WooCommerce wrappers and `"woocommerce-"` for WooCommerce-native abilities. Slash form is normalized to hyphen form (e.g. `"blu/wc"` ≡ `"blu-wc"`). |

```json
// Bluehost's WooCommerce wrappers under "blu-wc-products"
{
  "method": "tools/call",
  "params": {
    "name": "blu-list-abilities",
    "arguments": { "name_prefix": "blu-wc-products", "search": "category" }
  }
}

// WooCommerce-native abilities under "woocommerce-products"
{
  "method": "tools/call",
  "params": {
    "name": "blu-list-abilities",
    "arguments": { "name_prefix": "woocommerce-products" }
  }
}
```

`blu-list-api-functions`:

| Filter | Type | Behavior |
|--------|------|----------|
| `namespace` | string | Exact match on the REST namespace as WordPress registered it. Multi-segment (`"wp/v2"`, `"wc/v3"`, `"wc-admin/marketing"`) and single-segment (`"wc-analytics"`) namespaces are both supported. Leading and trailing slashes are tolerated. |
| `methods` | array of `"GET" \| "POST" \| "PATCH" \| "DELETE"` | Restrict to listed HTTP methods (uppercase, validated by the schema enum). Omit or pass an empty array to allow all methods. |
| `search` | string | Case-insensitive substring match on the route string. |

```json
{
  "method": "tools/call",
  "params": {
    "name": "blu-list-api-functions",
    "arguments": { "namespace": "wp/v2", "methods": ["GET"] }
  }
}
```

Each item in the response is one `(route, method)` pair plus the derived `namespace` (e.g. `"wp/v2"` or `"wc-analytics"`), so clients can group or filter further without parsing route strings. Endpoints registered with combined methods (e.g. `WP_REST_Server::EDITABLE = "POST, PUT, PATCH"`) emit one row per method.

The MCP transport route `/blu/mcp` is excluded from the catalog so the LLM can't discover-and-invoke its way back into the transport. The same route is also rejected by `blu-run-api-function` if passed directly.

### Whitelist

The gateway only exposes abilities matching allowed namespaces or categories:

- **Namespaces:** `blu/`, `woocommerce/` (configurable via `blu_mcp_allowed_namespaces` filter)
- **Categories:** `blu-mcp`, `woocommerce-rest` (configurable via `blu_mcp_allowed_categories` filter)

`blu/` and `blu-mcp` cover Bluehost's own abilities (including the `blu/wc-*` WooCommerce wrappers). `woocommerce/` and `woocommerce-rest` cover the abilities WooCommerce registers natively (since WC 10.3 ships its own Abilities API integration — products, orders, etc. under `woocommerce/<resource>-<op>`).

To add another namespace:

```php
add_filter( 'blu_mcp_allowed_namespaces', function ( $namespaces ) {
    $namespaces[] = 'myplugin/';
    return $namespaces;
} );
```

### Legacy mode

To bypass the gateway and expose all individual tools directly (previous behavior):

```php
add_filter( 'blu_mcp_use_gateway', '__return_false' );
```

---

## Available abilities

All abilities below are accessible through the gateway. The **Ability name** column shows the internal registration name. The **MCP tool name** column shows the hyphen-form name to use with `blu-call-ability` and `blu-get-ability-schema`.

### Content management

#### Posts

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/posts-search` | `blu-posts-search` | Search and filter WordPress posts with pagination |
| `blu/get-post` | `blu-get-post` | Get a WordPress post by ID |
| `blu/add-post` | `blu-add-post` | Add a new WordPress post |
| `blu/update-post` | `blu-update-post` | Update a WordPress post by ID |
| `blu/delete-post` | `blu-delete-post` | Delete a WordPress post by ID |

#### Post categories

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/list-categories` | `blu-list-categories` | List all WordPress post categories |
| `blu/add-category` | `blu-add-category` | Add a new WordPress post category |
| `blu/update-category` | `blu-update-category` | Update a WordPress post category |
| `blu/delete-category` | `blu-delete-category` | Delete a WordPress post category |

#### Post tags

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/list-tags` | `blu-list-tags` | List all WordPress post tags |
| `blu/add-tag` | `blu-add-tag` | Add a new WordPress post tag |
| `blu/update-tag` | `blu-update-tag` | Update a WordPress post tag |
| `blu/delete-tag` | `blu-delete-tag` | Delete a WordPress post tag |

#### Pages

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/pages-search` | `blu-pages-search` | Search and filter WordPress pages with pagination |
| `blu/get-page` | `blu-get-page` | Get a WordPress page by ID |
| `blu/add-page` | `blu-add-page` | Add a new WordPress page |
| `blu/update-page` | `blu-update-page` | Update a WordPress page by ID |
| `blu/delete-page` | `blu-delete-page` | Delete a WordPress page by ID |

#### Media

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/list-media` | `blu-list-media` | List WordPress media items with pagination and filtering |
| `blu/get-media` | `blu-get-media` | Get a WordPress media item by ID |
| `blu/get-media-file` | `blu-get-media-file` | Get the actual file content (blob) of a WordPress media item |
| `blu/upload-media` | `blu-upload-media` | Upload a new media file to WordPress |
| `blu/update-media` | `blu-update-media` | Update a WordPress media item |
| `blu/delete-media` | `blu-delete-media` | Delete a WordPress media item permanently |
| `blu/search-media` | `blu-search-media` | Search WordPress media by title, caption, or description |

#### Custom post types

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/list-post-types` | `blu-list-post-types` | List all registered WordPress post types (built-in and custom) |
| `blu/cpt-search` | `blu-cpt-search` | Search and filter content items within a custom post type with pagination |
| `blu/get-cpt` | `blu-get-cpt` | Get a single content item from a custom post type by ID |
| `blu/add-cpt` | `blu-add-cpt` | Create a new content item within an existing custom post type |
| `blu/update-cpt` | `blu-update-cpt` | Update an existing content item in a custom post type by ID |
| `blu/delete-cpt` | `blu-delete-cpt` | Permanently delete a content item from a custom post type by ID |

---

### Site management

#### Users

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/users-search` | `blu-users-search` | Search and filter WordPress users with pagination |
| `blu/get-user` | `blu-get-user` | Get a WordPress user by ID |
| `blu/add-user` | `blu-add-user` | Add a new WordPress user |
| `blu/update-user` | `blu-update-user` | Update a WordPress user by ID |
| `blu/delete-user` | `blu-delete-user` | Delete a WordPress user by ID |

#### Settings

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/get-general-settings` | `blu-get-general-settings` | Get WordPress general site settings |
| `blu/update-general-settings` | `blu-update-general-settings` | Update WordPress general site settings |

#### Site info

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/get-site-info` | `blu-get-site-info` | Get detailed site information (name, URL, description, admin email, plugins, themes, users, etc.) |

---

### Global styles

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/get-global-styles` | `blu-get-global-styles` | Get a global styles configuration by ID |
| `blu/update-global-styles` | `blu-update-global-styles` | Update a global styles configuration (colors, typography, spacing, etc.) |
| `blu/get-active-global-styles` | `blu-get-active-global-styles` | Get the currently active global styles for the current theme |
| `blu/get-active-global-styles-id` | `blu-get-active-global-styles-id` | Get the active global styles ID (for get/update) |

---

### Themes

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/get-active-theme` | `blu-get-active-theme` | Get the active theme information |

---

### WooCommerce (when WooCommerce is active)

Two surfaces are exposed:

- **Bluehost WooCommerce tools** (`blu/wc-*`, MCP form `blu-wc-*`): wrappers under the `blu/` namespace listed below. Use `name_prefix: "blu-wc-"` on `blu-list-abilities` to isolate.
- **WooCommerce-native abilities** (`woocommerce/<resource>-<op>`, MCP form `woocommerce-<resource>-<op>`): registered by WooCommerce 10.3+ in `woocommerce/src/Internal/Abilities/AbilitiesRestBridge.php`. Covers products, orders, and other WC resources with list/get/create/update/delete operations. Use `name_prefix: "woocommerce-"` to isolate. Both `woocommerce/` namespace and `woocommerce-rest` category are whitelisted by default.

#### Products

| Ability name | MCP tool name                        | Description |
|-------------|--------------------------------------|-------------|
| `blu/wc-products-search` | `blu-wc-products-search`             | Search WooCommerce products |
| `blu/wc-get-product` | `blu-wc-get-product`                 | Get a WooCommerce product by ID |
| `blu/wc-add-product` | `blu-wc-add-product`                 | Add a WooCommerce product |
| `blu/wc-update-product` | `blu-wc-update-product`              | Update a WooCommerce product |
| `blu/wc-delete-product` | `blu-wc-delete-product`              | Delete a WooCommerce product |
| `blu/wc-list-product-variations` | `blu-wc-list-product-variations`     | List all variations for a WooCommerce variable product |
| `blu/wc-add-product-variation` | `blu-wc-add-product-variation`       | Create a variation for a WooCommerce variable product |
| `blu/wc-generate-product-variations` | `blu-wc-generate-product-variations` | Automatically generate all attribute combinations as variations for a WooCommerce variable product |

#### Product categories

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/wc-list-product-categories` | `blu-wc-list-product-categories` | List WooCommerce product categories |
| `blu/wc-add-product-category` | `blu-wc-add-product-category` | Add a WooCommerce product category |
| `blu/wc-update-product-category` | `blu-wc-update-product-category` | Update a WooCommerce product category |
| `blu/wc-delete-product-category` | `blu-wc-delete-product-category` | Delete a WooCommerce product category |

#### Product tags

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/wc-list-product-tags` | `blu-wc-list-product-tags` | List WooCommerce product tags |
| `blu/wc-add-product-tag` | `blu-wc-add-product-tag` | Add a WooCommerce product tag |
| `blu/wc-update-product-tag` | `blu-wc-update-product-tag` | Update a WooCommerce product tag |
| `blu/wc-delete-product-tag` | `blu-wc-delete-product-tag` | Delete a WooCommerce product tag |

#### Product brands

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/wc-list-product-brands` | `blu-wc-list-product-brands` | List WooCommerce product brands |
| `blu/wc-add-product-brand` | `blu-wc-add-product-brand` | Add a WooCommerce product brand |
| `blu/wc-update-product-brand` | `blu-wc-update-product-brand` | Update a WooCommerce product brand |
| `blu/wc-delete-product-brand` | `blu-wc-delete-product-brand` | Delete a WooCommerce product brand |

#### Product attributes

| Ability name | MCP tool name                     | Description                                                           |
|-------------|-----------------------------------|-----------------------------------------------------------------------|
| `blu/wc-list-product-attributes` | `blu-wc-list-product-attributes`  | List all WooCommerce product attributes                               |
| `blu/wc-add-product-attribute` | `blu-wc-add-product-attribute`    | Create a WooCommerce product attribute and <br/> optionally its terms |
| `blu/wc-delete-product-attribute` | `blu-wc-delete-product-attribute` | Delete a WooCommerce product attribute and all its terms |
| `blu/wc-list-attribute-terms` | `blu-wc-list-attribute-terms`     | List all terms for a WooCommerce product attribute |
| `blu/wc-add-attribute-term` | `blu-wc-add-attribute-term`       | Add a term to a WooCommerce product attribute |

#### Orders and reports

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/wc-orders-search` | `blu-wc-orders-search` | Get a list of WooCommerce orders |
| `blu/wc-reports-coupons-totals` | `blu-wc-reports-coupons-totals` | Get WooCommerce coupons totals report |
| `blu/wc-reports-customers-totals` | `blu-wc-reports-customers-totals` | Get WooCommerce customers totals report |
| `blu/wc-reports-orders-totals` | `blu-wc-reports-orders-totals` | Get WooCommerce orders totals report |
| `blu/wc-reports-products-totals` | `blu-wc-reports-products-totals` | Get WooCommerce products totals report |
| `blu/wc-reports-reviews-totals` | `blu-wc-reports-reviews-totals` | Get WooCommerce reviews totals report |
| `blu/wc-reports-sales` | `blu-wc-reports-sales` | Get WooCommerce sales report |

---

### Advanced: REST API CRUD

| Ability name | MCP tool name | Description |
|-------------|---------------|-------------|
| `blu/list-api-functions` | `blu-list-api-functions` | List all available WordPress REST API endpoints that support CRUD |
| `blu/get-function-details` | `blu-get-function-details` | Get detailed metadata for a specific REST API route and HTTP method |
| `blu/run-api-function` | `blu-run-api-function` | Execute a REST API request by route, method, and parameters |

---

## Integration requirements

Any MCP client connecting to this server must handle the following. This applies regardless of the LLM being used.

### Endpoint

- **URL:** `https://YOUR-SITE.com/wp-json/blu/mcp`
- **Methods:** POST (messages), GET (SSE, currently 405), DELETE (session termination)
- **Authentication:** Required (e.g. WordPress Application Password or JWT via Hiive)

### Session lifecycle

1. POST `initialize` request → server returns `Mcp-Session-Id` response header
2. POST `notifications/initialized` notification (no `id` field, no response expected) with that session header
3. Include `Mcp-Session-Id` header on **every** subsequent request
4. Sessions expire after 24 hours of inactivity (max 32 per user)
5. On "Invalid or expired session" error, re-run steps 1–2

```
POST /wp-json/blu/mcp
Authorization: Bearer <token>
Content-Type: application/json

{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"my-client","version":"1.0"}}}
```
Response includes `Mcp-Session-Id: <id>` header.

```
POST /wp-json/blu/mcp
Authorization: Bearer <token>
Mcp-Session-Id: <id>
Content-Type: application/json

{"jsonrpc":"2.0","method":"notifications/initialized"}
```

### JSON-RPC 2.0 envelope

Every request and response uses the JSON-RPC 2.0 format:

```
Request:  { "jsonrpc": "2.0", "id": <int|string>, "method": "<method>", "params": {...} }
Response: { "jsonrpc": "2.0", "id": <int|string>, "result": {...} }
Error:    { "jsonrpc": "2.0", "id": <int|string>, "error": { "code": <int>, "message": "<string>" } }
```

Batch requests (array of messages) are supported per the JSON-RPC 2.0 spec.

### Response format for `tools/call`

Successful tool calls return a nested response that clients must unwrap:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "content": [
      { "type": "text", "text": "<JSON string of the result>" }
    ],
    "structuredContent": {...}
  }
}
```

- **`result.structuredContent`** — the parsed result object (preferred)
- **`result.content[0].text`** — the same result as a JSON string (fallback)
- Image results use `content[0].type: "image"` with base64 `data` and `mimeType`

### Error shapes

Errors come in two forms that clients must distinguish:

**Protocol errors** (tool not found, invalid request) — JSON-RPC error format:
```json
{ "jsonrpc": "2.0", "id": 1, "error": { "code": -32602, "message": "Tool not found: foo" } }
```

**Tool execution errors** (permission denied, ability failure) — MCP `isError` format:
```json
{
  "jsonrpc": "2.0", "id": 1,
  "result": {
    "content": [{ "type": "text", "text": "Access denied for tool: blu-call-ability" }],
    "isError": true
  }
}
```

### Ability response wrapper

Inside `structuredContent`, gateway abilities return a consistent wrapper:

```json
{
  "statusCode": 200,
  "status": "success",
  "message": [ ... ]
}
```

- `statusCode` — HTTP-style status (200, 400, 404, 500)
- `status` — `"success"` or `"error"`
- `message` — the actual payload (array for lists, object for single items, string for errors)

### SSE

The MCP 2025-06-18 spec defines GET for SSE streaming. This server currently returns HTTP 405 for GET requests (not implemented), but spec-compliant clients should be prepared to handle SSE in future versions.
