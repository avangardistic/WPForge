# WPForge — AI-Powered WordPress Remote Control & Development Bridge

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)]()
[![PHP](https://img.shields.io/badge/PHP-8.1+-777bb4.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-0073aa.svg)](https://wordpress.org)

## Overview

WPForge is a production-grade WordPress plugin that provides a secure, structured HTTP API for AI agents to remotely inspect, develop, modify, and maintain WordPress websites.

**Key Features:**
- 🔐 Secure authentication via Application Passwords and API Tokens
- 📊 Complete site inspection and diagnostics
- 📝 Full CRUD operations for posts, pages, and custom post types
- 🎨 Elementor integration with document and template management
- 📁 Controlled filesystem access with path traversal protection
- 🗄️ Database inspection with read/write control
- 💾 Backup creation and management
- 📋 Comprehensive audit logging
- 🤖 MCP (Model Context Protocol) compatible

## Installation

1. Upload the `wpforge` folder to `/wp-content/plugins/`
2. Activate via WordPress Admin → Plugins
3. Test: `GET /wp-json/wpforge/v1/status`

## Quick Start

```bash
# System status
curl -u "user:password" https://example.com/wp-json/wpforge/v1/status

# Site inspection
curl -u "user:password" https://example.com/wp-json/wpforge/v1/site

# List posts
curl -u "user:password" https://example.com/wp-json/wpforge/v1/posts

# Create post
curl -X POST -u "user:password" \
     -H "Content-Type: application/json" \
     -d '{"post_title":"Hello","post_content":"<p>World</p>","post_status":"publish"}' \
     https://example.com/wp-json/wpforge/v1/posts

# Elementor documents
curl -u "user:password" https://example.com/wp-json/wpforge/v1/elementor/documents

# Read file
curl -u "user:password" "https://example.com/wp-json/wpforge/v1/files/read?path=wp-content/themes/twentytwentyfour/style.css"

# Database query
curl -X POST -u "user:password" \
     -H "Content-Type: application/json" \
     -d '{"sql":"SELECT COUNT(*) as count FROM wp_posts WHERE post_type = :type","params":{"type":"post"}}' \
     https://example.com/wp-json/wpforge/v1/database/query

# Create backup
curl -X POST -u "user:password" https://example.com/wp-json/wpforge/v1/backup

# Full diagnostics
curl -u "user:password" https://example.com/wp-json/wpforge/v1/diagnostics
```

## API Endpoints (40+)

| Category | Endpoints |
|----------|-----------|
| **System** | `/status`, `/capabilities`, `/environment`, `/health`, `/` |
| **Site** | `/site`, `/site/structure`, `/site/routes` |
| **Content** | `/posts`, `/pages` (full CRUD) |
| **Media** | `/media`, `/media/upload` |
| **Taxonomies** | `/taxonomies/{type}/terms` (CRUD) |
| **Users** | `/users` (CRUD) |
| **Menus** | `/menus`, `/menus/locations` |
| **Themes** | `/themes`, `/themes/activate` |
| **Plugins** | `/plugins`, `/plugins/activate`, `/plugins/deactivate` |
| **Elementor** | `/elementor/status`, `/elementor/documents`, `/elementor/templates` |
| **Filesystem** | `/files/list`, `/files/read`, `/files/write`, `/files/delete` |
| **Database** | `/database/status`, `/database/tables`, `/database/query` |
| **Backup** | `/backup` (create, list, get, delete) |
| **Cache** | `/cache/flush`, `/cache/status` |
| **Diagnostics** | `/diagnostics`, `/diagnostics/quick` |
| **Logs** | `/logs`, `/logs/clear` |

## Architecture

```
wpforge/
├── wordpress/wpforge/         # WordPress plugin (58 PHP files)
│   ├── src/Core/              # Plugin bootstrap, Container, Config
│   ├── src/API/               # Router, Response, Controller, Middleware
│   ├── src/Auth/              # Authenticator, TokenManager, Capabilities
│   ├── src/Security/          # Validator, Sanitizer, PathValidator
│   ├── src/WordPress/         # SiteInspector, PostManager, + 7 more
│   ├── src/Elementor/         # Adapter, DocumentManager, + 4 more
│   ├── src/Filesystem/        # Manager, SecurityGuard, Reader, Writer
│   ├── src/Database/          # Inspector, QueryBuilder, Executors
│   ├── src/Backup/            # Manager, DB/File Backup, Restore, Cleanup
│   ├── src/Diagnostics/       # SystemCheck, Permissions, Components, Health
│   ├── src/Logging/           # Manager, Writer, Rotator, Filter
│   └── routes/                # 15 route files
├── mcp/                       # MCP server (TypeScript)
├── tests/                     # PHPUnit tests
├── docs/                      # 12 documentation files
├── examples/                  # cURL, JS, Python clients
└── tools/                     # Build, validate, docs generators
```

## Security

- ✅ Application Passwords (recommended) + Bearer tokens
- ✅ WordPress capability checks on every mutation
- ✅ Path traversal protection on filesystem operations
- ✅ SQL injection prevention via prepared statements
- ✅ Read-only database mode by default
- ✅ Configurable write/delete permissions
- ✅ Rate limiting (100 req/60s default)
- ✅ Audit logging for all mutations
- ✅ Sensitive data redaction in logs

## MCP Integration

```bash
cd mcp/ && npm install && npm run build
export WPFORGE_BASE_URL=https://your-site.com
export WPFORGE_USERNAME=admin
export WPFORGE_PASSWORD=your-app-password
npm start
```

35 MCP tools available for AI agents (see `mcp/README.md`).

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Architecture](docs/ARCHITECTURE.md)
- [API Reference](docs/API_REFERENCE.md)
- [Authentication](docs/AUTHENTICATION.md)
- [Security](docs/SECURITY.md)
- [Elementor](docs/ELEMENTOR.md)
- [Filesystem](docs/FILESYSTEM.md)
- [Database](docs/DATABASE.md)
- [Backup](docs/BACKUP.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [Removal](docs/REMOVAL.md)

## Requirements

- WordPress 6.0+
- PHP 8.1+
- MySQL/MariaDB
- HTTPS recommended

## License

MIT License — see [LICENSE](LICENSE) file.
