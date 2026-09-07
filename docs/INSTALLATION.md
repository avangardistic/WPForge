# Installation

## Requirements

| Component | Minimum | Notes |
|-----------|---------|-------|
| WordPress | 6.0     | Tested up to 7.1 |
| PHP       | 8.1     | Typed properties and `mixed` are used throughout |
| Database  | MySQL 5.7 / MariaDB 10.3 | Two custom tables are created |
| Transport | HTTPS   | Required in practice — Application Passwords travel in the `Authorization` header |

## Install from a release archive

1. Download `wpforge-<version>.zip` from the [releases page](https://github.com/avangardistic/WPForge/releases).
2. WordPress Admin → **Plugins → Add New → Upload Plugin** → choose the zip → **Install Now**.
3. Click **Activate**.

## Install from source

```bash
git clone https://github.com/avangardistic/WPForge.git
cd WPForge
cp -r wordpress/wpforge /path/to/wordpress/wp-content/plugins/
```

Then activate via **Plugins** in wp-admin, or with WP-CLI:

```bash
wp plugin activate wpforge
```

## Build a distributable zip

```bash
composer install
composer build      # writes build/wpforge-<version>.zip
composer validate   # sanity-checks the plugin layout and headers
```

## What activation creates

| Object | Purpose |
|--------|---------|
| `{prefix}wpforge_tokens` | Hashed API tokens (`wp_hash_password`), one row per token |
| `{prefix}wpforge_logs`   | Audit log of mutating requests |
| `wp-content/wpforge-logs/`    | Rotating file logs |
| `wp-content/wpforge-backups/` | Backup archives |
| `wpforge_config` option  | Merged configuration (see [Configuration](CONFIGURATION.md)) |

All of it is removed on plugin deletion — see [Removal](REMOVAL.md).

## Verify the install

Create an Application Password (**Users → Profile → Application Passwords**), then:

```bash
curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  https://example.com/wp-json/wpforge/v1/status
```

A healthy response looks like:

```json
{
  "success": true,
  "request_id": "…",
  "data": { "status": "ok", "version": "1.0.0", "wordpress_version": "6.7", "php_version": "8.3.0" }
}
```

If you get a 404, permalinks may be off — the REST API needs pretty permalinks or the
`?rest_route=/wpforge/v1/status` form. See [Troubleshooting](TROUBLESHOOTING.md).

## Next steps

- [Authentication](AUTHENTICATION.md) — Application Passwords vs. API tokens
- [Configuration](CONFIGURATION.md) — what is off by default and how to turn it on
- [Security](SECURITY.md) — read this before enabling filesystem or database writes
