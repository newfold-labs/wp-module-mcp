# WordPress MCP

A Composer package that exposes WordPress functionality through the Model Context Protocol (MCP), enabling AI assistants to interact with your WordPress site.

## Overview

This plugin registers a comprehensive set of WordPress abilities as MCP tools, allowing remote AI assistants to:

- Manage posts, pages, and custom post types
- Handle media uploads and management
- Create and manage users
- Configure site settings
- Work with WooCommerce products and orders (when WooCommerce is active)
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

Once connected, the following categories of tools will be available to your MCP client:

### Content Management
- **Posts**: Create, read, update, delete, and search blog posts
- **Pages**: Manage WordPress pages
- **Custom Post Types**: Work with any registered custom post type
- **Media**: Upload, retrieve, update, and delete media files

### Site Management
- **Users**: Create, update, delete, and search WordPress users
- **Settings**: Read and update WordPress site settings
- **Site Info**: Get detailed information about the WordPress installation

### WooCommerce (if active)
- **Products**: Manage WooCommerce products, categories, tags, and brands
- **Orders**: View WooCommerce orders and reports

### Advanced
- **REST API CRUD**: Execute generic WordPress REST API operations for extended functionality

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
