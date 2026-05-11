# cURL commands for BLU MCP. The gateway exposes only THREE MCP tools at
# tools/list (blu-list-abilities, blu-get-ability-schema, blu-call-ability).
#
# - Gateway tools (the 3 above) are invoked directly: `params.name` is the
#   gateway tool's own name. Section (0) below is an example.
# - Every other ability (including the three REST-API-function ones in
#   sections 1–3) is invoked through `blu-call-ability`: `params.name` is
#   `"blu-call-ability"` and the target ability name goes in
#   `arguments.ability_name` with its per-ability args in `arguments.parameters`.
#
# Postman import:
#   Postman -> Import -> Raw text -> paste a single curl block below -> Continue -> Import.
#   Repeat for each of the three blocks.
#
# Variables:
#   The {{...}} placeholders below are Postman variable references. Define these
#   in your Postman environment once and all three requests resolve them:
#     - SITE_URL          e.g. https://your-site.example.com
#     - JWT               Bearer token from the auth flow (see README "Authentication")
#     - MCP_SESSION_ID    From the `Mcp-Session-Id` response header of the initialize call
#
# Session handshake (run once, before the three requests below):
#   1. POST /wp-json/blu/mcp with `{"jsonrpc":"2.0","id":0,"method":"initialize",...}`
#      -- capture the `Mcp-Session-Id` response header into {{MCP_SESSION_ID}}.
#   2. POST /wp-json/blu/mcp with `{"jsonrpc":"2.0","method":"notifications/initialized","params":{}}`
#      using the session id.
#   See README.md "Session setup" for the full handshake JSON.

# ============================================================================
# 0) blu-list-abilities  (gateway tool — invoked directly)
#    Lists abilities available through the gateway. Filters are optional and
#    AND-composed:
#      - search       case-insensitive substring on name/label/description
#      - name_prefix  prefix on the MCP tool name (hyphen form); slash form
#                     (e.g. "blu/wc") is normalized to hyphen form
#    Each item: { name, label, description, annotations }.
#
#    Example below: WooCommerce-native abilities with "product" in their text.
#    Send `arguments: {}` for the full catalog.
# ============================================================================
curl -X POST "{{SITE_URL}}/wp-json/blu/mcp" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{JWT}}" \
  -H "MCP-Protocol-Version: 2025-06-18" \
  -H "Mcp-Session-Id: {{MCP_SESSION_ID}}" \
  -d '{
    "jsonrpc": "2.0",
    "id": 0,
    "method": "tools/call",
    "params": {
      "name": "blu-list-abilities",
      "arguments": {
        "name_prefix": "woocommerce-",
        "search": "product"
      }
    }
  }'

# ============================================================================
# 1) blu-list-api-functions  (via gateway: blu-call-ability)
#    Lists available WordPress REST endpoints. All filters are optional and
#    AND-composed. Each result item: { route, method, namespace }.
#
#    Example below: GET endpoints under the wp/v2 namespace whose route contains
#    "posts". Drop the `parameters` keys you don't need (or send `parameters: {}`).
# ============================================================================
curl -X POST "{{SITE_URL}}/wp-json/blu/mcp" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{JWT}}" \
  -H "MCP-Protocol-Version: 2025-06-18" \
  -H "Mcp-Session-Id: {{MCP_SESSION_ID}}" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/call",
    "params": {
      "name": "blu-call-ability",
      "arguments": {
        "ability_name": "blu-list-api-functions",
        "parameters": {
          "namespace": "wp/v2",
          "methods": ["GET"],
          "search": "posts"
        }
      }
    }
  }'

# ============================================================================
# 2) blu-get-function-details  (via gateway: blu-call-ability)
#    Returns the full WordPress REST endpoint metadata (args schema, callback,
#    permission_callback, etc.) for one route + method pair.
# ============================================================================
curl -X POST "{{SITE_URL}}/wp-json/blu/mcp" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{JWT}}" \
  -H "MCP-Protocol-Version: 2025-06-18" \
  -H "Mcp-Session-Id: {{MCP_SESSION_ID}}" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "tools/call",
    "params": {
      "name": "blu-call-ability",
      "arguments": {
        "ability_name": "blu-get-function-details",
        "parameters": {
          "route": "/wp/v2/posts",
          "method": "GET"
        }
      }
    }
  }'

# ============================================================================
# 3) blu-run-api-function  (via gateway: blu-call-ability)
#    Executes a WordPress REST endpoint with the given route, method, and data.
#    For GET/DELETE, `data` is sent as query parameters. For POST/PATCH, it is
#    sent as the request body.
# ============================================================================
curl -X POST "{{SITE_URL}}/wp-json/blu/mcp" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{JWT}}" \
  -H "MCP-Protocol-Version: 2025-06-18" \
  -H "Mcp-Session-Id: {{MCP_SESSION_ID}}" \
  -d '{
    "jsonrpc": "2.0",
    "id": 3,
    "method": "tools/call",
    "params": {
      "name": "blu-call-ability",
      "arguments": {
        "ability_name": "blu-run-api-function",
        "parameters": {
          "route": "/wp/v2/posts",
          "method": "GET",
          "data": { "per_page": 5 }
        }
      }
    }
  }'
