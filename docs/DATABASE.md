# Database Access

Read-only by default. Four endpoints, all requiring the `manage_options`
capability.

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/database/status` | GET | Connection state, prefix, server version, table count |
| `/database/tables` | GET | Every table with row count and size |
| `/database/tables/{name}` | GET | `DESCRIBE` output for one table |
| `/database/query` | POST | Execute a query |

## Status

```bash
curl -u "$USER:$APP_PASS" https://example.com/wp-json/wpforge/v1/database/status
```

`DB_NAME` is stripped from the response while `security.redact_db_credentials` is
true (the default).

## Queries

```bash
curl -X POST -u "$USER:$APP_PASS" \
  -H "Content-Type: application/json" \
  -d '{
        "sql": "SELECT ID, post_title FROM wp_posts WHERE post_type = :type AND post_status = :status",
        "params": { "type": "page", "status": "publish" }
      }' \
  https://example.com/wp-json/wpforge/v1/database/query
```

```json
{ "success": true, "data": { "success": true, "rows": 12, "data": [ … ], "query": "SELECT …" } }
```

### Parameter binding

Use `:name` placeholders and pass values in `params`. Named placeholders are
rewritten to positional `?` in order of appearance and bound through
`$wpdb->prepare()` — values are never interpolated into the SQL string, so a
value containing quotes or SQL keywords is inert.

Placeholders bind **values only**. A table or column name cannot be parameterised;
build those from an allowlist in your own code, never from user input.

### Write queries

Anything whose first token is not `SELECT` is refused unless both
`developer_mode` and `allow_database_writes` are on:

```json
{ "success": false, "error": { "code": "WRITES_DISABLED", "message": "Write queries are disabled" } }
```

Enable writes only on a site you can restore, and take a backup first:

```bash
curl -X POST -u "$USER:$APP_PASS" \
  "https://example.com/wp-json/wpforge/v1/backup?type=database"
```

## Limits

| Setting | Default | Effect |
|---------|---------|--------|
| `database.max_rows` | 1000 | Result rows returned per query |
| `database.query_timeout` | 30 | Seconds before the query is abandoned |

## Errors

| Code | Status | Meaning |
|------|--------|---------|
| `MISSING_QUERY` | 400 | `sql` absent from the body |
| `DESCRIBE_FAILED` | 400 | Unknown table |
| `WRITES_DISABLED` | 403 | Non-SELECT while writes are off |
| `QUERY_FAILED` | 500 | MySQL rejected the statement |
