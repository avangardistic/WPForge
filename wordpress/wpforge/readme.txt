=== WPForge - AI Remote Control Bridge ===
Contributors: avangardistic
Tags: rest-api, api, remote-control, ai, elementor
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-Powered WordPress Remote Control & Development Bridge.

== Description ==

WPForge provides a secure, structured HTTP API for AI agents to remotely inspect, develop, modify, and maintain WordPress websites.

Features:
* Site inspection and diagnostics
* Content management (posts, pages, custom post types)
* Elementor integration
* Filesystem access with path traversal protection
* Database inspection
* Backup management
* Audit logging
* MCP compatible

== Installation ==

1. Upload the `wpforge` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Access the UI: Go to **WPForge** in the left sidebar of wp-admin (gear icon)
   - **Dashboard**: View API status, plugin info, developer mode toggle
   - **Connect to AI**: Generate credentials, configure MCP clients, test connections
4. Test API: GET `/wp-json/wpforge/v1/status`

== Frequently Asked Questions ==

= Where do I find the WPForge UI? =

After activating the plugin, look for the **WPForge** menu item in your WordPress admin sidebar (under the gear icon). Only users with Administrator capabilities (`manage_options`) can access it.

The menu has two pages:
1. **Dashboard** - Shows plugin version, REST API status, developer mode status, and active credentials count
2. **Connect to AI** - Generate Application Passwords or API tokens, copy MCP client configurations (Claude Desktop, Cursor, CLI), and test your connection

= Is WPForge secure? =

Yes. WPForge uses WordPress Application Passwords for authentication, implements strict capability checks (requires `manage_options`), includes path traversal protection for filesystem operations, and maintains comprehensive audit logs of all API requests.

= What is Developer Mode? =

Developer Mode enables additional diagnostic endpoints and detailed error reporting useful during development. It can be toggled from the WPForge Dashboard in wp-admin. For production sites, it's recommended to keep Developer Mode disabled.

= Can I use WPForge with any AI agent? =

WPForge is designed to be MCP (Model Context Protocol) compatible, making it usable with AI agents that support MCP. You can generate credentials and copy pre-configured MCP client settings for Claude Desktop, Cursor, or CLI tools from the "Connect to AI" page.

= Does WPForge send data to external servers? =

No. WPForge operates entirely within your WordPress installation. It does not make outbound connections, send telemetry, or include any "powered by" links. All API communication happens between your AI agent and your WordPress site directly.

= How do I revoke access? =

You can delete API tokens or revoke Application Passwords from the "Connect to AI" page in the WPForge admin UI. This immediately invalidates the credentials and prevents further API access.

== Changelog ==

= 1.0.0 =
* Initial release
* Secure REST API for AI-driven WordPress management
* Dashboard with API status and developer mode toggle
* Credential generation for Application Passwords and API tokens
* MCP client configuration for Claude Desktop, Cursor, and CLI
* Site inspection and diagnostics endpoints
* Content management (posts, pages, custom post types, taxonomies)
* Elementor integration
* Filesystem access with security protections
* Database inspection capabilities
* Backup management
* Comprehensive audit logging
* Uninstall cleanup routine
