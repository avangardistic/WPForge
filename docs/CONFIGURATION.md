# Configuration

Defaults live in `wordpress/wpforge/config/defaults.php`. The `wpforge_config`
option is merged over them, so anything you do not set keeps the default.

Every privileged flag is false by default **and** requires `developer_mode` to be
true. Turning on `allow_filesystem_writes` alone does nothing.

## Keys

| Key | Default | Effect |
|-----|---------|--------|
| `enabled` | `true` | Master kill switch for the API |
| `developer_mode` | `false` | Required for every `allow_*` flag below |
| `filesystem_root` | `''` (→ `ABSPATH`) | Sandbox root for all file operations |
| `allow_filesystem_writes` | `false` | Enables `POST /files/write` |
| `allow_database_writes` | `false` | Permits non-`SELECT` statements |
| `allow_plugin_installation` | `false` | Enables plugin install/activate |
| `allow_theme_activation` | `false` | Enables `POST /themes/activate` |
| `allow_destructive_operations` | `false` | Deletes and other irreversible actions |
| `audit_logging` | `true` | Write mutations to the log table and files |
| `rate_limit_enabled` | `true` | Enable the rate-limit middleware |
| `rate_limit_requests` | `100` | Requests allowed per window |
| `rate_limit_window` | `60` | Window length in seconds |
| `backup_retention_days` | `7` | Age at which cleanup prunes backups |
| `max_backup_size_mb` | `100` | Refuse to create a backup larger than this |
| `security.cors_origins` | `[]` | Allowed CORS origins; empty means none |
| `security.allowed_ips` | `[]` | If non-empty, only these IPs may call the API |
| `security.blocked_ips` | `[]` | Always refused |
| `security.public_status_enabled` | `false` | Allow unauthenticated `GET /status` |
| `security.public_health_enabled` | `false` | Allow unauthenticated `GET /health` |
| `security.redact_db_credentials` | `true` | Strip `DB_NAME` from `GET /database/status` |

## Setting values

From the admin screen: **WPForge → Settings**.

Programmatically, in a mu-plugin or `functions.php`:

```php
$config = new WPForge\Core\Config();
$config->setMultiple([
    'developer_mode'          => true,
    'allow_filesystem_writes' => true,
]);
```

Or with WP-CLI, since it is one option:

```bash
wp option patch update wpforge_config developer_mode --format=json <<< 'true'
```

## Recommended production profile

Leave everything at its default and grant only what the integration needs. A
read-only agent — inspection, diagnostics, content reads — needs **no** flags
changed at all.

For a staging site where an agent edits templates:

```php
[
    'developer_mode'          => true,
    'allow_filesystem_writes' => true,
    'filesystem_root'         => WP_CONTENT_DIR . '/themes/your-child-theme',
]
```

Narrowing `filesystem_root` to the directory that actually needs writing is the
single most effective restriction available — see [Filesystem](FILESYSTEM.md).
