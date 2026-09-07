# Filesystem Access

Four endpoints, all sandboxed to a configured root and all subject to the same
path validator.

| Endpoint | Method | Requires |
|----------|--------|----------|
| `/files/list`   | GET    | authenticated user |
| `/files/read`   | GET    | authenticated user |
| `/files/write`  | POST   | `edit_files` + `developer_mode` + `allow_filesystem_writes` |
| `/files/delete` | DELETE | `edit_files` + `allow_destructive_operations` |

Paths are always **relative to the configured root** (`filesystem_root`, default
`ABSPATH`). Absolute paths and anything that escapes the root are refused.

## List a directory

```bash
curl -u "$USER:$APP_PASS" \
  "https://example.com/wp-json/wpforge/v1/files/list?path=wp-content/themes"
```

```json
{ "success": true, "data": { "path": "wp-content/themes", "files": [ … ], "count": 4 } }
```

## Read a file

```bash
curl -u "$USER:$APP_PASS" \
  "https://example.com/wp-json/wpforge/v1/files/read?path=wp-content/themes/twentytwentyfour/style.css"
```

## Write a file

```bash
curl -X POST -u "$USER:$APP_PASS" \
  -H "Content-Type: application/json" \
  -d '{"path":"wp-content/themes/child/custom.css","content":".btn{color:red}"}' \
  "https://example.com/wp-json/wpforge/v1/files/write"
```

### Dry run

Append `?dry_run=true` and the endpoint reports what it *would* write, without
touching disk:

```json
{ "success": true, "data": { "dry_run": true, "path": "…", "size": 15, "message": "Dry run — no file was written" } }
```

Use it whenever an agent drives this endpoint unattended: it turns a destructive
call into an assertion you can check first.

## Delete a file

```bash
curl -X DELETE -u "$USER:$APP_PASS" \
  "https://example.com/wp-json/wpforge/v1/files/delete?path=wp-content/uploads/tmp.txt"
```

## Path validation

`Security\PathValidator` normalises the path, resolves it with `realpath()`, and
rejects the request unless the result is inside the root. Independently of the
root, these are always refused:

```
../   ..\           traversal in any form
/etc/  /proc/  /sys/  /dev/
*/wp-config.php
*/.htaccess
```

Symlinks are resolved before the containment check, so a symlink pointing outside
the root does not grant access.

## Errors

| Code | Status | Meaning |
|------|--------|---------|
| `MISSING_PATH` | 400 | `path` parameter absent |
| `LIST_FAILED` / `READ_FAILED` | 400 | Path invalid, outside root, or unreadable |
| `FORBIDDEN` | 403 | Caller lacks `edit_files` |
| `WRITES_DISABLED` | 403 | `allow_filesystem_writes` (or `developer_mode`) is off |
| `WRITE_FAILED` / `DELETE_FAILED` | 500 | Permission or I/O error on disk |

## Practical guidance

- Narrow `filesystem_root` to the smallest directory that needs access — a child
  theme, not `ABSPATH`.
- Define `DISALLOW_FILE_EDIT` on production. It removes `edit_files` from every
  role, which closes write and delete regardless of the WPForge flags.
- `max_file_size` caps a single read; very large files should be fetched over
  SFTP instead.
