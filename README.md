# WordPress MCP

A Composer package that exposes WordPress functionality through the Model Context Protocol (MCP), enabling AI assistants to interact with your WordPress site.

## Overview

This plugin registers a comprehensive set of WordPress abilities as MCP tools, allowing remote AI assistants to:

- Manage posts, pages, and custom post types (including categories and tags)
- Handle media uploads, retrieval, and management
- Create and manage users
- Configure site settings and retrieve site info
- Manage themes and block editor global styles
- Work with WooCommerce products, orders, and reports (when WooCommerce is active)
- Use AI-assisted content prompts (product descriptions, categories, tags, brands, variation attributes)
- Access resources such as the Google Product Taxonomy
- Execute generic WordPress REST API operations

Tools located here were extracted from the [WordPress MCP](https://github.com/Automattic/wordpress-mcp) plugin.

## Dependencies
- WordPress Abilities API plugin (https://github.com/WordPress/abilities-api)
- WordPress MCP Adapter plugin (https://github.com/WordPress/mcp-adapter)

## Installation

1. Download or clone this plugin to your WordPress plugins directory
2. Ensure the WordPress MCP plugin (abilities API) is installed and activated
3. Activate the "Blu MCP" plugin from the WordPress admin panel

## Remote Connection Setup

To connect to your WordPress site remotely using MCP, you'll use the [@automattic/mcp-wordpress-remote](https://github.com/Automattic/mcp-wordpress-remote) package.

### Configuration

Add the following configuration to your MCP client settings (e.g., Claude Desktop's `claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote"],
      "env": {
        "WP_API_URL": "https://wp.lndo.site/wp-json/blu/mcp",
        "WP_API_USERNAME": "admin",
        "WP_API_PASSWORD": "password",
        "OAUTH_ENABLED": "false",
        "NODE_TLS_REJECT_UNAUTHORIZED": "0"
      }
    }
  }
}
```

### Configuration Parameters

- **WP_API_URL**: Your WordPress site's MCP endpoint URL. Replace with your site's URL, keeping the `/wp-json/blu/mcp` path.
- **WP_API_USERNAME**: Your WordPress admin username
- **WP_API_PASSWORD**: Your WordPress user's application password (recommended) or account password
- **OAUTH_ENABLED**: Set to `"false"` to use basic authentication
- **NODE_TLS_REJECT_UNAUTHORIZED**: Set to `"0"` for local development environments with self-signed certificates. Remove or set to `"1"` for production.

## Available Tools

Once connected, the following tools will be available to your MCP client, organized by category.

### Content Management — Posts

| Tool | Description |
|------|-------------|
| `blu/posts-search` | Search and filter WordPress posts with pagination |
| `blu/get-post` | Get a WordPress post by ID |
| `blu/add-post` | Add a new WordPress post |
| `blu/update-post` | Update a WordPress post by ID |
| `blu/delete-post` | Delete a WordPress post by ID |
| `blu/list-categories` | List all WordPress post categories |
| `blu/add-category` | Add a new WordPress post category |
| `blu/update-category` | Update a WordPress post category |
| `blu/delete-category` | Delete a WordPress post category |
| `blu/list-tags` | List all WordPress post tags |
| `blu/add-tag` | Add a new WordPress post tag |
| `blu/update-tag` | Update a WordPress post tag |
| `blu/delete-tag` | Delete a WordPress post tag |

### Content Management — Pages

| Tool | Description |
|------|-------------|
| `blu/pages-search` | Search and filter WordPress pages with pagination |
| `blu/get-page` | Get a WordPress page by ID |
| `blu/add-page` | Add a new WordPress page |
| `blu/update-page` | Update a WordPress page by ID |
| `blu/delete-page` | Delete a WordPress page by ID |

### Content Management — Custom Post Types

| Tool | Description |
|------|-------------|
| `blu/list-post-types` | List all available WordPress custom post types |
| `blu/cpt-search` | Search and filter WordPress custom post types with pagination |
| `blu/get-cpt` | Get a WordPress custom post type by ID |
| `blu/add-cpt` | Add a new WordPress custom post type |
| `blu/update-cpt` | Update a WordPress custom post type by ID |
| `blu/delete-cpt` | Delete a WordPress custom post type by ID |

### Media

| Tool | Description |
|------|-------------|
| `blu/list-media` | List WordPress media items with pagination and filtering |
| `blu/get-media` | Get a WordPress media item details by ID |
| `blu/get-media-file` | Get the actual file content (blob) of a WordPress media item |
| `blu/upload-media` | Upload a new media file to WordPress |
| `blu/update-media` | Update a WordPress media item |
| `blu/delete-media` | Delete a WordPress media item permanently |
| `blu/search-media` | Search WordPress media items by title, caption, or description |

### Users

| Tool | Description |
|------|-------------|
| `blu/users-search` | Search and filter WordPress users with pagination |
| `blu/get-user` | Get a WordPress user by ID |
| `blu/add-user` | Add a new WordPress user |
| `blu/update-user` | Update a WordPress user by ID |
| `blu/delete-user` | Delete a WordPress user by ID |
| `blu/get-current-user` | Get the current logged-in user |
| `blu/update-current-user` | Update the current logged-in user |

### Site Management

| Tool | Description |
|------|-------------|
| `blu/get-site-info` | Get detailed information about the WordPress site (name, URL, description, admin email, plugins, themes, users, etc.) |
| `blu/get-general-settings` | Get WordPress general site settings |
| `blu/update-general-settings` | Update WordPress general site settings |

### Themes

| Tool | Description |
|------|-------------|
| `blu/get-active-theme` | Get the active theme information |

### Global Styles (Block Editor)

| Tool | Description |
|------|-------------|
| `blu/get-global-styles` | Get a specific global styles configuration by ID (theme.json settings and user customizations) |
| `blu/update-global-styles` | Update global styles (colors, typography, spacing, etc.) |
| `blu/get-active-global-styles` | Get the currently active global styles configuration for the current theme |
| `blu/get-active-global-styles-id` | Get the active global styles ID (used for get/update operations) |

### REST API

| Tool | Description |
|------|-------------|
| `blu/list-api-functions` | List all available WordPress REST API endpoints that support CRUD operations |
| `blu/get-function-details` | Get detailed metadata for a specific REST API endpoint and HTTP method |
| `blu/run-api-function` | Execute a REST API function by providing the endpoint route, HTTP method, and parameters |

### Resources

| Tool | Description |
|------|-------------|
| `blu/google-product-taxonomy` | The official Google Product Taxonomy resource |

### AI-Assisted Content (Prompts)

| Tool | Description |
|------|-------------|
| `blu/suggest-product-description` | Generate a description and short description from product details |
| `blu/improve-product-description` | Improve existing product description and short description |
| `blu/suggest-product-categories` | Generate product category suggestions from product details |
| `blu/suggest-product-tag` | Generate product tag suggestions from product details |
| `blu/suggest-product-brand` | Generate product brand suggestions from product details |
| `blu/smart-product-details` | Merchant Content Intelligence Generator — generates required materials, size charts, care instructions, warranty info, ingredient lists from product ID and details |
| `blu/suggest-product-variation-attributes` | Generate product terms and attributes for variations from product details |

### WooCommerce — Products (when WooCommerce is active)

| Tool | Description |
|------|-------------|
| `blu/wc-products-search` | Search and filter WooCommerce products with pagination |
| `blu/wc-get-product` | Get a WooCommerce product by ID |
| `blu/wc-add-product` | Add a new WooCommerce product |
| `blu/wc-update-product` | Update a WooCommerce product by ID |
| `blu/wc-delete-product` | Delete a WooCommerce product by ID |
| `blu/wc-list-product-categories` | List all WooCommerce product categories |
| `blu/wc-add-product-category` | Add one or more WooCommerce product categories |
| `blu/wc-update-product-category` | Update a WooCommerce product category |
| `blu/wc-delete-product-category` | Delete a WooCommerce product category |
| `blu/wc-list-product-tags` | List all WooCommerce product tags |
| `blu/wc-add-product-tag` | Add one or more WooCommerce product tags |
| `blu/wc-update-product-tag` | Update a WooCommerce product tag |
| `blu/wc-delete-product-tag` | Delete a WooCommerce product tag |
| `blu/wc-list-product-brands` | List all WooCommerce product brands |
| `blu/wc-add-product-brand` | Add one or more WooCommerce product brands |
| `blu/wc-update-product-brand` | Update a WooCommerce product brand |
| `blu/wc-delete-product-brand` | Delete a WooCommerce product brand |

### WooCommerce — Orders & Reports (when WooCommerce is active)

| Tool | Description |
|------|-------------|
| `blu/wc-orders-search` | Get a list of WooCommerce orders |
| `blu/wc-reports-coupons-totals` | Get WooCommerce coupons totals report |
| `blu/wc-reports-customers-totals` | Get WooCommerce customers totals report |
| `blu/wc-reports-orders-totals` | Get WooCommerce orders totals report |
| `blu/wc-reports-products-totals` | Get WooCommerce products totals report |
| `blu/wc-reports-reviews-totals` | Get WooCommerce reviews totals report |
| `blu/wc-reports-sales` | Get WooCommerce sales report |

## Usage

After configuring your MCP client, restart it to establish the connection. The tools will appear in your AI assistant's available tools, organized by the category "Bluehost MCP".

You can then ask your AI assistant to perform WordPress tasks, such as:
- "Create a new blog post about..."
- "Upload this image to the media library"
- "Show me the latest orders"
- "Update the site tagline to..."

## Testing
- Use the [MCP Inspector](https://modelcontextprotocol.io/docs/tools/inspector) to test specific calls

## Support

For issues or questions, please contact the plugin author or refer to the WordPress MCP documentation.

## License

GPL V2 or later
