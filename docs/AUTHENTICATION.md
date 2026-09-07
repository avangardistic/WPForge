# Authentication

Two mechanisms, both resolving to a real `WP_User` so every capability check is a
normal WordPress check. `Auth\Authenticator` tries them in order: Application
Password first, then Bearer token.

## Application Passwords (recommended)

Created in wp-admin under **Users → Profile → Application Passwords**. They are
WordPress core, revocable one at a time, and never expose the account password.

```bash
curl -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
     https://example.com/wp-json/wpforge/v1/status
```

Keep the spaces — WordPress accepts the password with or without them, but copying
it verbatim avoids transcription mistakes.

Application Passwords require HTTPS. WordPress refuses them over plain HTTP unless
the site is `localhost`.

## API tokens

Useful when you want a credential scoped to WPForge specifically, with its own
expiry and audit trail.

### Create

Requires `edit_users`. Creating a token for another user requires `edit_users` too.

```bash
curl -X POST -u "$USER:$APP_PASS" \
     -H "Content-Type: application/json" \
     -d '{"description":"CI deploy agent"}' \
     https://example.com/wp-json/wpforge/v1/tokens
```

```json
{
  "success": true,
  "data": {
    "token_id": "…",
    "token_secret": "…",
    "full_token": "<token_id>.<token_secret>",
    "created_at": "2026-09-07 14:30:22",
    "expires_at": "2027-09-07 14:30:22"
  }
}
```

Only the **hash** of the secret is stored (`wp_hash_password`). `full_token` is
shown once and cannot be recovered — save it at creation or issue a new one.
Tokens expire one year after creation.

### Use

```bash
curl -H "Authorization: Bearer <token_id>.<token_secret>" \
     https://example.com/wp-json/wpforge/v1/status
```

### List and revoke

```bash
curl -u "$USER:$APP_PASS" https://example.com/wp-json/wpforge/v1/tokens
curl -X DELETE -u "$USER:$APP_PASS" \
     https://example.com/wp-json/wpforge/v1/tokens/<token_id>
```

Listing returns `token_id`, `description`, `created_at`, `last_used`, `expires_at`
and `revoked` — never the secret. `last_used` is the quickest way to spot a
credential nothing is using any more.

## What each endpoint requires

Authentication is not the same as authorization. `permission_callback` establishes
*who you are*; a `current_user_can()` check inside the handler decides *what you
may do*:

Every endpoint is gated at the permission layer by a capability, not merely by
being logged in. An under-privileged user receives a `403` before the handler
runs; an anonymous request receives a `401`.

| Endpoint group | Capability |
|----------------|-----------|
| `/files/*`, `/database/*`, `/site/*`, `/environment`, `/diagnostics`, `/logs/*`, `/backup/*` | `manage_options` |
| `/plugins/*` | `activate_plugins` |
| `/themes/*` | `switch_themes` |
| `/menus/*` | `edit_theme_options` |
| `/media/*` | `upload_files` |
| `/users/*` (read) | `list_users` |
| `/users/*` (write) | `edit_users` / `create_users` |
| `/taxonomies/*` | `manage_categories` |
| `/elementor/*` | `edit_pages` |
| `/posts/*` | `edit_posts` (writes add `publish_posts` / per-object `edit_post`) |
| `/pages/*` | `edit_pages` (writes add `publish_pages` / per-object `edit_post`) |
| `/files/write`, `/files/delete` | `manage_options` **and** `edit_files` |
| `/tokens/*` | authenticated; creating a token needs `edit_users` |

`/capabilities` and `/` require only authentication (the former reports the
current user's own capabilities).

## Unauthenticated access

Only `GET /diagnostics/quick` responds without credentials. It returns five
booleans and discloses no version, path or hostname, so an uptime monitor can use
it safely. `GET /` (discovery) now requires authentication.

`GET /status` and `GET /health` **require authentication** unless you explicitly
opt in with `security.public_status_enabled` / `security.public_health_enabled`.
Both disclose the WordPress version, PHP version and site URL, so leaving them
closed is the right default. See [Configuration](CONFIGURATION.md).

## Header not arriving

If every request comes back `401`, the `Authorization` header is probably being
stripped before PHP sees it — common on Apache with CGI/FastCGI. See
[Troubleshooting](TROUBLESHOOTING.md#401-unauthorized).

## Handling credentials

- One integration, one credential, so it can be revoked without disturbing others.
- Keep them in environment variables or a secret manager — never in the repository.
- Rotate anything that has been pasted into a shared channel, an issue, or a log.
