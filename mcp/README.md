# WPForge MCP Server

Model Context Protocol server for WPForge — enables AI agents to control WordPress.

## Setup

```bash
cd mcp/
npm install
npm run build
```

## Usage

```bash
# With environment variables
export WPFORGE_BASE_URL=https://your-site.com
export WPFORGE_USERNAME=admin
export WPFORGE_PASSWORD=your-app-password
npm start

# With command line arguments
npm start -- --base-url=https://your-site.com --username=admin --password=your-app-password
```

## Available Tools

| Tool | Description |
|------|-------------|
| `wp_site_inspect` | Get comprehensive site info |
| `wp_site_health` | Get site health status |
| `wp_get_environment` | Get PHP/server/database info |
| `wp_list_posts` | List posts with filtering |
| `wp_get_post` | Get a specific post |
| `wp_create_post` | Create a new post |
| `wp_update_post` | Update an existing post |
| `wp_delete_post` | Delete a post |
| `wp_list_pages` | List pages |
| `wp_get_page` | Get a specific page |
| `wp_create_page` | Create a new page |
| `wp_list_media` | List media attachments |
| `wp_elementor_status` | Get Elementor capabilities |
| `wp_elementor_list_documents` | List Elementor documents |
| `wp_elementor_get_document` | Get a specific document |
| `wp_elementor_update_document` | Update a document |
| `wp_elementor_list_templates` | List Elementor templates |
| `wp_list_files` | List files in a directory |
| `wp_read_file` | Read a file |
| `wp_write_file` | Write a file |
| `wp_delete_file` | Delete a file |
| `wp_database_status` | Get database status |
| `wp_database_tables` | List database tables |
| `wp_database_query` | Execute a SELECT query |
| `wp_create_backup` | Create a backup |
| `wp_list_backups` | List backups |
| `wp_flush_cache` | Flush all caches |
| `wp_get_logs` | Get audit logs |
| `wp_list_users` | List users |
| `wp_list_themes` | List themes |
| `wp_list_plugins` | List plugins |
| `wp_list_menus` | List menus |

## Configuration for Claude Desktop

Add to `claude_desktop_config.json`:

```json
{
    "mcpServers": {
        "wpforge": {
            "command": "node",
            "args": ["/path/to/wpforge/mcp/dist/index.js"],
            "env": {
                "WPFORGE_BASE_URL": "https://your-site.com",
                "WPFORGE_USERNAME": "admin",
                "WPFORGE_PASSWORD": "your-app-password"
            }
        }
    }
}
```
