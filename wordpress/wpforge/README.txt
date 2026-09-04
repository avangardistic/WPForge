=== WPForge ===
Contributors: avangardistic
Tags: api, rest-api, ai, remote-control, elementor
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

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
3. Test: GET `/wp-json/wpforge/v1/status`

== Changelog ==

= 1.0.0 =
* Initial release
