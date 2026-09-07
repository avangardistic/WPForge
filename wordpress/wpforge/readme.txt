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

== Changelog ==

= 1.0.0 =
* Initial release
