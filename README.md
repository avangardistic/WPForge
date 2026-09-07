# WPForge — AI-Powered WordPress Remote Control & Development Bridge

[![CI](https://github.com/avangardistic/WPForge/actions/workflows/ci.yml/badge.svg)](https://github.com/avangardistic/WPForge/actions/workflows/ci.yml)
[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777bb4.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-0073aa.svg)](https://wordpress.org)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)

WPForge is a WordPress plugin that exposes a secure, structured HTTP API so AI
agents can inspect, develop, modify and maintain a WordPress site — without
screen-scraping wp-admin.

**Everything that can change the site is off by default.** Filesystem writes,
database writes, plugin installation and theme activation each require
`developer_mode` plus their own flag, plus the matching WordPress capability.
See [Configuration](docs/CONFIGURATION.md) and [Security](docs/SECURITY.md).

## Features

- 🔐 Application Passwords and hashed Bearer tokens
- 📊 Site inspection, diagnostics and health reporting
- 📝 CRUD for posts, pages and custom post types
- 🎨 Elementor document and template access
- 📁 Sandboxed filesystem access with traversal protection
- 🗄️ Database inspection with parameterised, read-only-by-default queries
- 💾 Database backups with retention
- 📋 Audit logging with credential redaction
- 🤖 MCP server for agent integration

## Installation

```bash
cp -r wordpress/wpforge/ /path/to/wordpress/wp-content/plugins/
```

Activate via **Plugins** in wp-admin, create an Application Password under
**Users → Profile**, then:

```bash
curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  https://example.com/wp-json/wpforge/v1/status
```

Full instructions: [docs/INSTALLATION.md](docs/INSTALLATION.md).

## Quick start

```bash
export WPF="https://example.com/wp-json/wpforge/v1"
export AUTH="admin:xxxx xxxx xxxx xxxx xxxx xxxx"

# Site inspection
curl -u "$AUTH" "$WPF/site"

# List posts
curl -u "$AUTH" "$WPF/posts"

# Create a post
curl -X POST -u "$AUTH" -H "Content-Type: application/json" \
     -d '{"post_title":"Hello","post_content":"<p>World</p>","post_status":"publish"}' \
     "$WPF/posts"

# Elementor documents
curl -u "$AUTH" "$WPF/elementor/documents"

# Read a file
curl -u "$AUTH" "$WPF/files/read?path=wp-content/themes/twentytwentyfour/style.css"

# Parameterised query
curl -X POST -u "$AUTH" -H "Content-Type: application/json" \
     -d '{"sql":"SELECT COUNT(*) AS n FROM wp_posts WHERE post_type = :type","params":{"type":"post"}}' \
     "$WPF/database/query"

# Create a backup
curl -X POST -u "$AUTH" "$WPF/backup"

# Full diagnostics
curl -u "$AUTH" "$WPF/diagnostics"
```

## API

65 route registrations across 49 paths. Full detail in
[docs/API_REFERENCE.md](docs/API_REFERENCE.md).

| Category | Paths |
|----------|-------|
| **System** | `/`, `/status`, `/capabilities`, `/environment`, `/health` |
| **Site** | `/site`, `/site/structure`, `/site/routes` |
| **Content** | `/posts`, `/posts/{id}`, `/pages`, `/pages/{id}` |
| **Media** | `/media`, `/media/{id}`, `/media/upload` |
| **Taxonomies** | `/taxonomies/{taxonomy}/terms`, `/taxonomies/{taxonomy}/terms/{id}` |
| **Users** | `/users`, `/users/{id}` |
| **Menus** | `/menus`, `/menus/{id}`, `/menus/locations` |
| **Themes** | `/themes`, `/themes/{stylesheet}`, `/themes/activate` |
| **Plugins** | `/plugins`, `/plugins/activate`, `/plugins/deactivate` |
| **Elementor** | `/elementor/status`, `/elementor/documents[/{id}]`, `/elementor/templates[/{id}]` |
| **Filesystem** | `/files/list`, `/files/read`, `/files/write`, `/files/delete` |
| **Database** | `/database/status`, `/database/tables[/{name}]`, `/database/query` |
| **Backup** | `/backup`, `/backup/{id}` |
| **Tokens** | `/tokens`, `/tokens/{id}` |
| **Diagnostics** | `/diagnostics`, `/diagnostics/quick` |
| **Logs** | `/logs`, `/logs/clear` |

Every response uses one envelope:

```json
{ "success": true, "request_id": "…", "data": { … } }
{ "success": false, "request_id": "…", "error": { "code": "…", "message": "…" } }
```

## Repository layout

```
WPForge/
├── wordpress/wpforge/    WordPress plugin (78 PHP files)
│   ├── src/              PSR-4 classes under WPForge\
│   ├── routes/           15 route files
│   ├── config/           Default configuration
│   └── schemas/          JSON Schema for requests and responses
├── mcp/                  MCP server (TypeScript)
├── UIUX/                 React + Vite dashboard
├── tests/                PHPUnit: unit, integration, security
├── docs/                 Documentation
├── examples/             cURL, JavaScript, Python clients
└── tools/                Build and validation scripts
```

Architecture notes: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Security

- Application Passwords and Bearer tokens, stored only as hashes
- WordPress capability check on every mutating endpoint
- `realpath()`-based containment plus a deny list on all filesystem operations
- Parameterised queries via `$wpdb->prepare()`; SELECT-only unless writes are enabled
- Rate limiting (100 requests / 60s by default)
- Audit logging with credential redaction
- No unauthenticated endpoint discloses versions or paths by default

Read [docs/SECURITY.md](docs/SECURITY.md) before enabling writes.
Report vulnerabilities privately — see [SECURITY.md](SECURITY.md).

## MCP integration

```bash
cd mcp/ && npm install && npm run build
export WPFORGE_BASE_URL=https://your-site.com
export WPFORGE_USERNAME=admin
export WPFORGE_PASSWORD=your-application-password
npm start
```

See [mcp/README.md](mcp/README.md).

## Dashboard

```bash
cd UIUX/ && npm install && npm run dev
```

See [UIUX/README.md](UIUX/README.md).

## Development

```bash
composer install
composer lint       # PHPCS
composer test       # PHPUnit — needs a WordPress test install
composer build      # build/wpforge-<version>.zip
composer validate   # check plugin layout and headers
```

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Requirements

- WordPress 6.0+ (tested to 7.1)
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- HTTPS

## Author

**Hossein Parasteh** — [github.com/avangardistic](https://github.com/avangardistic)

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
