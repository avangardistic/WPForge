# WPForge

**AI-Powered WordPress Remote Control & Development Bridge**

WPForge is a production-quality WordPress plugin that exposes a structured, secure HTTP API allowing AI coding agents (such as Claude Code, Claude Desktop, Qwen, or other MCP-compatible agents) to remotely inspect, develop, modify, configure, and maintain a WordPress website.

## Overview

WPForge provides developer-level control over a WordPress installation through a well-documented REST API namespace: `/wp-json/wpforge/v1/`

### Key Features

- **Site Inspection**: Comprehensive discovery of WordPress environment, plugins, themes, Elementor status
- **Content Management**: Full CRUD operations for posts, pages, custom post types
- **Media Handling**: Upload, manage, and organize media assets
- **Elementor Integration**: Deep inspection and modification of Elementor documents and templates
- **Filesystem Access**: Controlled read/write access to WordPress files with path traversal protection
- **Database Inspection**: Read-only SQL queries and table discovery
- **Plugin/Theme Management**: List, activate, deactivate, install plugins and themes
- **Backup System**: Create and manage database backups before destructive operations
- **Audit Logging**: Complete audit trail of all mutating operations
- **MCP Compatible**: Designed for integration with Model Context Protocol servers

## Security Model

WPForge implements a controlled "developer mode" with explicit operation handlers:

- ✅ Authentication required for ALL mutating operations
- ✅ WordPress Application Passwords support
- ✅ Capability-based authorization
- ✅ Path traversal protection
- ✅ No arbitrary shell execution
- ✅ No unrestricted SQL writes by default
- ✅ Audit logging for all mutations
- ✅ Structured error responses (no stack traces)

## Installation

1. Download the WPForge plugin
2. Upload to `wp-content/plugins/wpforge/`
3. Activate the plugin in WordPress Admin
4. Configure authentication (Application Passwords recommended)
5. Test connectivity: `GET /wp-json/wpforge/v1/status`

## Quick Start

```bash
# Check API status
curl -u "username:application_password" \
  https://example.com/wp-json/wpforge/v1/status

# Get site diagnostics
curl -u "username:application_password" \
  https://example.com/wp-json/wpforge/v1/diagnostics

# List pages
curl -u "username:application_password" \
  https://example.com/wp-json/wpforge/v1/pages

# Inspect Elementor status
curl -u "username:application_password" \
  https://example.com/wp-json/wpforge/v1/elementor/status
```

## Documentation

- [Architecture](docs/architecture.md)
- [Installation Guide](docs/installation.md)
- [Authentication](docs/authentication.md)
- [API Reference](docs/api-reference.md)
- [Security](docs/security.md)
- [Elementor Integration](docs/elementor.md)
- [Filesystem Access](docs/filesystem.md)
- [Database Access](docs/database.md)
- [Troubleshooting](docs/troubleshooting.md)

## MCP Integration

WPForge includes an MCP server adapter for seamless integration with AI agents:

```bash
cd mcp/
# See mcp/README.md for setup instructions
```

## Removal

To completely remove WPForge:

1. Deactivate the plugin in WordPress Admin
2. Delete the plugin
3. Remove `wp-content/plugins/wpforge/` directory
4. Optionally clean up logs and backups via the API before removal

See [docs/removal.md](docs/removal.md) for detailed instructions.

## Requirements

- WordPress 6.x+
- PHP 8.1+
- MySQL/MariaDB
- HTTPS recommended
- WordPress REST API enabled

## License

MIT License - see LICENSE file for details.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines.

---

**Note**: WPForge provides powerful access to your WordPress installation. Use only in trusted environments and remove when no longer needed.