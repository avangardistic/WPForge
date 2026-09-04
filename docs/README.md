# WPForge Documentation

## Overview

WPForge is an AI-Powered WordPress Remote Control & Development Bridge. It provides a secure REST API for AI agents to remotely inspect, develop, modify, and maintain WordPress websites.

## Documentation

- [Installation Guide](INSTALLATION.md)
- [Architecture](ARCHITECTURE.md)
- [Security](SECURITY.md)
- [API Reference](API_REFERENCE.md)
- [Authentication](AUTHENTICATION.md)
- [Elementor Integration](ELEMENTOR.md)
- [Filesystem Access](FILESYSTEM.md)
- [Database Access](DATABASE.md)
- [Backup Management](BACKUP.md)
- [Troubleshooting](TROUBLESHOOTING.md)
- [Removal](REMOVAL.md)

## Quick Start

```bash
# Install the plugin
cp -r wordpress/wpforge/ /path/to/wordpress/wp-content/plugins/
# Activate via WordPress admin, then:
curl -u "user:password" https://yoursite.com/wp-json/wpforge/v1/status
```

## Requirements

- WordPress 6.0+
- PHP 8.1+
- MySQL/MariaDB
