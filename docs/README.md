# WPForge Documentation

WPForge is an AI-powered WordPress remote control and development bridge: a secure,
structured HTTP API that lets an agent inspect, develop, modify and maintain a
WordPress site.

## Start here

| | |
|---|---|
| [Installation](INSTALLATION.md) | Requirements, install, verify, build a zip |
| [Authentication](AUTHENTICATION.md) | Application Passwords and API tokens |
| [Configuration](CONFIGURATION.md) | Every setting, its default, and what it unlocks |
| [Security](SECURITY.md) | The threat model — read before enabling writes |

## Reference

| | |
|---|---|
| [API Reference](API_REFERENCE.md) | All endpoints, parameters and responses |
| [Architecture](ARCHITECTURE.md) | Layout, request lifecycle, namespaces |
| [Filesystem](FILESYSTEM.md) | Sandboxed file read/write/delete |
| [Database](DATABASE.md) | Inspection and parameterised queries |
| [Backup](BACKUP.md) | Snapshots, retention, restoring |
| [Elementor](ELEMENTOR.md) | Documents and templates |

## Operations

| | |
|---|---|
| [Troubleshooting](TROUBLESHOOTING.md) | 404s, 401s, 403s, rate limits, fatals |
| [Removal](REMOVAL.md) | Uninstall and what it deletes |

## Quick start

```bash
cp -r wordpress/wpforge/ /path/to/wordpress/wp-content/plugins/
# Activate in wp-admin, create an Application Password, then:
curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  https://example.com/wp-json/wpforge/v1/status
```

## Requirements

- WordPress 6.0+ (tested to 6.7)
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- HTTPS

## Other components

- `mcp/` — MCP server exposing the API as tools for AI agents ([README](../mcp/README.md))
- `UIUX/` — React dashboard for the API ([README](../UIUX/README.md))
- `examples/` — cURL, JavaScript and Python clients
