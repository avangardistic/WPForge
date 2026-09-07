# Backup Management

WPForge can snapshot the database (and, with `FilesystemBackup`, plugin/theme
directories) before an agent makes a change. Archives land in
`wp-content/wpforge-backups/`.

> Backups contain a full SQL dump. Deny web access to `wp-content/wpforge-backups/`
> at the server level — it is not protected by WordPress.

| Endpoint | Method | Requires |
|----------|--------|----------|
| `/backup` | POST | `manage_options` |
| `/backup` | GET | authenticated user |
| `/backup/{id}` | GET | authenticated user |
| `/backup/{id}` | DELETE | `manage_options` |

## Create

```bash
curl -X POST -u "$USER:$APP_PASS" \
  "https://example.com/wp-json/wpforge/v1/backup?type=database"
```

`type` defaults to `database`. Response is `201`:

```json
{
  "success": true,
  "data": {
    "success": true,
    "backup": { "id": "backup_20260907_143022", "type": "database", "size": 4718592, "created": "2026-09-07 14:30:22" },
    "message": "Backup created successfully"
  }
}
```

## List and inspect

```bash
curl -u "$USER:$APP_PASS" https://example.com/wp-json/wpforge/v1/backup
curl -u "$USER:$APP_PASS" https://example.com/wp-json/wpforge/v1/backup/backup_20260907_143022
```

## Delete

```bash
curl -X DELETE -u "$USER:$APP_PASS" \
  https://example.com/wp-json/wpforge/v1/backup/backup_20260907_143022
```

## Retention

`CleanupManager` prunes on a schedule using two settings:

| Setting | Default | Effect |
|---------|---------|--------|
| `backup_retention_days` | 7 | Archives older than this are removed |
| `max_backup_size_mb` | 100 | A backup exceeding this is not created |

## Restoring

`Backup\RestoreManager` exists in the codebase but is **not exposed over HTTP** —
restoring is deliberately a manual, on-server operation:

```bash
gunzip < wp-content/wpforge-backups/backup_20260907_143022.sql.gz | wp db import -
```

Verify on a staging copy before restoring production.

## Scope and limits

This is a convenience snapshot for "undo the thing the agent just did", not a
disaster-recovery system. It does not capture the uploads directory or anything
outside WordPress. Keep a real off-site backup regime alongside it.
