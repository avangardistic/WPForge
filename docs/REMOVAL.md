# Removal

## Uninstall

Deactivating leaves data in place. **Deleting** the plugin runs `uninstall.php`,
which removes everything WPForge created:

| Removed | |
|---------|--|
| `{prefix}wpforge_tokens` | table dropped |
| `{prefix}wpforge_logs` | table dropped |
| `wpforge_version`, `wpforge_enabled`, `wpforge_config`, `wpforge_developer_mode` | options deleted |
| `wp-content/wpforge-logs/` | directory removed recursively |
| `wp-content/wpforge-backups/` | directory removed recursively |

**Your backups are deleted with it.** Copy anything you want to keep out of
`wp-content/wpforge-backups/` before deleting the plugin.

Via wp-admin: **Plugins → Deactivate → Delete**.

With WP-CLI:

```bash
# Save backups first
cp -r wp-content/wpforge-backups ~/wpforge-backups-keep

wp plugin deactivate wpforge
wp plugin delete wpforge          # runs uninstall.php
```

## Turn it off without uninstalling

To stop the API responding while keeping configuration and logs:

```php
$config = new WPForge\Core\Config();
$config->set('enabled', false);
```

Or deactivate the plugin. Both leave the tables and directories intact.

## Revoke credentials

Uninstalling drops WPForge's own token table, but **Application Passwords are
WordPress core** and are not touched. Revoke them separately at
**Users → Profile → Application Passwords**.

Do this before removing the plugin if the site was reachable from anywhere you
no longer trust.

## Manual cleanup

If the plugin directory was deleted from disk without WordPress running
`uninstall.php`, clean up by hand:

```sql
DROP TABLE IF EXISTS wp_wpforge_tokens;
DROP TABLE IF EXISTS wp_wpforge_logs;
DELETE FROM wp_options
 WHERE option_name IN ('wpforge_version','wpforge_enabled','wpforge_config','wpforge_developer_mode');
```

```bash
rm -rf wp-content/wpforge-logs wp-content/wpforge-backups
```

Substitute your real table prefix for `wp_`.
