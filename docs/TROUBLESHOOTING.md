# Troubleshooting

Every response carries a `request_id`. Quote it when reporting a problem — it ties
the response you saw to a line in `wp-content/wpforge-logs/`.

## 404 on every endpoint

```json
{ "code": "rest_no_route", "message": "No route was found matching the URL and request method" }
```

- **Plugin inactive.** `wp plugin list | grep wpforge`.
- **Permalinks.** The REST API needs pretty permalinks, or the fallback form:
  `https://example.com/?rest_route=/wpforge/v1/status`. Re-save
  **Settings → Permalinks** to flush the rules.
- **REST API disabled.** Some security plugins switch it off wholesale. Confirm
  `https://example.com/wp-json/` responds first.
- **Namespace missing.** If `/wp-json/` lists no `wpforge/v1`, the plugin loaded
  but `rest_api_init` never ran its route files — check the PHP error log for a
  fatal during bootstrap.

## 401 Unauthorized

- The `Authorization` header is being stripped. Apache with CGI/FastCGI often
  drops it; add to `.htaccess`:

  ```apache
  RewriteEngine On
  RewriteCond %{HTTP:Authorization} ^(.*)
  RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]
  ```

  On Nginx + php-fpm, make sure `fastcgi_pass_request_headers` is not disabled.
- **Real password used instead of an Application Password.** Application Passwords
  are the 24-character spaced strings from **Users → Profile**.
- **Not HTTPS.** WordPress refuses Application Passwords over plain HTTP unless
  the site is `localhost`.
- **Revoked token.** `GET /tokens` lists what is still valid.

## 403 Forbidden

The user authenticated but lacks either the capability or the config flag:

| Error code | Fix |
|------------|-----|
| `FORBIDDEN` | The user's role lacks the capability (`edit_files`, `manage_options`, …) |
| `WRITES_DISABLED` | Turn on `developer_mode` **and** the specific `allow_*` flag |

Remember every `allow_*` flag is inert while `developer_mode` is false — see
[Configuration](CONFIGURATION.md).

`edit_files` is removed from every role when `DISALLOW_FILE_EDIT` is defined in
`wp-config.php`. That is usually correct on production; do filesystem work on
staging instead.

## 429 Too Many Requests

The rate limiter allows 100 requests per 60 seconds per identity by default. An
agent looping over posts will hit this. Either batch the work, use the paginated
endpoints with a larger `per_page`, or raise `rate_limit_requests`.

## Filesystem errors

| Symptom | Cause |
|---------|-------|
| `READ_FAILED` on a path you can see | Path is outside `filesystem_root`, or matches a forbidden pattern (`wp-config.php`, `.htaccess`) |
| `WRITE_FAILED` with writes enabled | OS-level permissions — the PHP user cannot write there |
| Traversal rejected unexpectedly | Paths are relative to the root; drop any leading `/` |

## Database errors

- `WRITES_DISABLED` on a `SELECT`: check for a leading comment or whitespace —
  the guard requires `SELECT` to be the first token.
- `DESCRIBE_FAILED`: the table name must match `[a-zA-Z0-9_]+` and exist; include
  the real prefix (`wp_posts`, not `posts`).
- Empty results where rows exist: `database.max_rows` (default 1000) caps output.

## Elementor 503

`ELEMENTOR_NOT_AVAILABLE` means the plugin is missing or inactive. Confirm with
`GET /elementor/status`, which reports the detected version.

## Logs

```bash
tail -f wp-content/wpforge-logs/wpforge-$(date +%Y-%m-%d).log
```

Or over the API: `GET /logs`. Raise detail with `logging.level` set to `debug`.

If nothing is being written, check that `wp-content/` is writable by the PHP user
and that `audit_logging` is true.

## Fatal error / white screen after activating

Get the real message rather than guessing:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Then read `wp-content/debug.log`. The usual cause is a PHP version below 8.1 —
WPForge uses typed properties and `mixed`, which are parse errors on older
runtimes. Deactivate by renaming the folder:

```bash
mv wp-content/plugins/wpforge wp-content/plugins/wpforge.disabled
```

## Still stuck

Open an issue with the `request_id`, the endpoint, the response body, and your
WordPress and PHP versions:
<https://github.com/avangardistic/WPForge/issues>

Never paste an Application Password, a token, or cookies into an issue.
