# Security

WPForge deliberately exposes powerful capabilities — filesystem writes, SQL, plugin
activation. Everything dangerous is **off by default** and must be turned on
explicitly. This page describes the model and the threat surface you accept when you
open each door.

> To report a vulnerability, use the private advisory process in
> [SECURITY.md at the repository root](../SECURITY.md). Do not open a public issue.

## Authentication

Two mechanisms, both resolved by `Auth\Authenticator`:

1. **Application Passwords** (recommended) — WordPress core, revocable per
   integration, sent as HTTP Basic. Never use a user's real login password.
2. **API tokens** — issued by `POST /tokens`, stored only as a
   `wp_hash_password()` digest. The plaintext is shown once at creation and cannot
   be recovered. Sent as `Authorization: Bearer <token>`.

Both resolve to a real `WP_User`, so every capability check below is a normal
WordPress check against that user's role.

## Authorization

`permission_callback` gates authentication; a `current_user_can()` call inside the
handler gates the action:

| Action | Capability |
|--------|-----------|
| File write / delete | `edit_files` |
| Elementor document update | `edit_pages` |
| Backup create / delete | `manage_options` |
| Token creation | `edit_users` |
| Plugin / theme activation | `activate_plugins` / `switch_themes` |

`edit_files` is false whenever `DISALLOW_FILE_EDIT` is defined — which is a good
reason to define it on a production site and use a dedicated staging site for
filesystem work.

## Endpoints reachable without authentication

Only one, by default: `GET /diagnostics/quick`, which returns five booleans
(`wordpress`, `rest_api`, `database`, `filesystem`, `memory`) and no version,
path, or hostname. It exists so an uptime monitor can probe the site.

`GET /status` and `GET /health` disclose the WordPress version, PHP version and site
URL, so they require an authenticated user unless you opt in:

```php
// wpforge_config → security
'public_status_enabled' => true,   // default false
'public_health_enabled' => true,   // default false
```

Leave them false unless something outside WordPress genuinely needs them; a public
`/status` is a free version-fingerprint for anyone scanning for known CVEs.

## Filesystem

`Security\PathValidator` resolves every path with `realpath()` and rejects it unless
the result is inside the configured root. Independently of the root, these patterns
are always refused:

```
../  and ..\      /etc/  /proc/  /sys/  /dev/
*/wp-config.php   */.htaccess
```

Writes and deletes each require their own flag (`allow_writes`, `allow_deletes`),
both false by default, *and* the `edit_files` capability. `POST /files/write`
accepts `?dry_run=true`, which reports the byte count it would have written and
touches nothing — use it when an agent is driving the endpoint unattended.
Details in [Filesystem](FILESYSTEM.md).

## Database

Read-only by default. `Database\Inspector::query()` refuses anything whose first
token is not `SELECT`; write statements additionally require
`database.allow_writes`. Named parameters are converted to positional placeholders
and bound through `$wpdb->prepare()` — values are never interpolated into SQL.

```json
{ "sql": "SELECT ID, post_title FROM wp_posts WHERE post_type = :type", "params": { "type": "page" } }
```

`GET /database/status` can return `DB_NAME`; set `redact_db_credentials` to strip it.
Details in [Database](DATABASE.md).

## Rate limiting

`API\Middleware\RateLimit` allows 100 requests per 60-second window per identity by
default (`rate_limits.max_requests`, `rate_limits.window`). It is a guardrail against
a runaway agent loop, not a defence against a distributed attacker — put a real WAF
or reverse proxy in front of an internet-facing site.

## Logging and redaction

Mutating requests are written to `{prefix}wpforge_logs` and to rotating files in
`wp-content/wpforge-logs/`. `Logging\Filter` redacts credential-shaped values before
anything is persisted. The log directory sits under `wp-content/`; if your host
serves that directory directly, deny access to `wpforge-logs/` and
`wpforge-backups/` at the web-server level. Backups contain a full SQL dump —
treat the directory as sensitive.

## Hardening checklist

- [ ] HTTPS enforced; `FORCE_SSL_ADMIN` on
- [ ] Application Passwords, never the account password
- [ ] One integration = one credential, so it can be revoked alone
- [ ] `filesystem.allow_writes` / `allow_deletes` off unless actively needed
- [ ] `database.allow_writes` off; `read_only` on
- [ ] `public_status_enabled` / `public_health_enabled` off
- [ ] `security.allowed_ips` set if the caller has a stable address
- [ ] `wp-content/wpforge-logs/` and `wpforge-backups/` not web-readable
- [ ] Tokens listed and pruned periodically via `GET /tokens`

## Reporting

Private advisories: <https://github.com/avangardistic/WPForge/security/advisories>
