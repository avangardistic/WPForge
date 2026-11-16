# WPForge — SECURITY AUDIT (Phase 1)

**Audited commit:** `3345b479` · **Date:** 2026-11-16 · **Method:** static source analysis + executed tooling (no live WordPress; no source changes)
**Evidence tags:** [E] executed locally · [C] GitHub Actions API conclusion · [S] static verification · [D] document claim only · [X] claim contradicted

Severity follows the mission priorities: **P0** auth bypass → **P1** functional/authz → **P2** MCP reliability → **P3** dashboard → **P4** performance/tests → **P5** docs/release.

---

## A. Executive summary

No **P0** (unauthenticated bypass) was found: all 65 route registrations carry a `permission_callback` **[E]**, anonymous access yields 401 and authenticated-but-unauthorized yields 403 (CI security suite, 68 cases, green on this tree **[C]**), Bearer tokens are stored hashed, and no `exec`/`eval`-class primitives exist in the plugin **[E]**.

The material problems are **P1**: several security controls that README/SECURITY.md advertise as on-by-default are **dead code** (never instantiated or never read), and three "mutating capabilities are off by default" flags have **zero callers**, contradicting both the README and the mission's fail-closed rule. Two shipped integration paths are broken; CI has been red for ~7 weeks on one job with a packaging root cause.

---

## B. Findings

### P1 — Advertised controls not enforced in code

#### SEC-01 · Rate limiting is never active ❌
- **Claim:** "Rate limiting (100 requests / 60s by default)" (README:137); SECURITY.md:96–100 documents `API\Middleware\RateLimit`.
- **Reality:** `API\Middleware\RateLimit` (and the whole middleware chain — `Auth`, `Logging`, `ErrorHandler`, `API\Router`) is **never instantiated**; no `addMiddleware`/`new Router` anywhere outside the class files themselves **[S]**. `RateLimitTest` unit-tests the class in isolation (happy path only) — it proves nothing about wiring **[S]**.
- **Impact:** An authenticated agent loop (the exact scenario SECURITY.md cites) can hammer any endpoint unthrottled; write endpoints are exposed to burst abuse within a valid session.
- **Latent bug (fix while wiring):** `RateLimit::getClientIp()` trusts `X-Forwarded-For` first → spoofable bucket key if deployed behind a misconfigured proxy **[S]**.
- **Fix (Phase 2):** wire middleware into `rest_api_init` dispatch (or per-route), add a failing-by-default wiring test, do **not** trust forwarded headers unless `REMOTE_ADDR` is a trusted proxy.

#### SEC-02 · Audit logging does not log mutations ❌
- **Claim:** "Audit logging with credential redaction" (README:138); "Mutating requests are written to `{prefix}wpforge_logs` and to rotating files in `wp-content/wpforge-logs/`. `Logging\Filter` redacts…" (SECURITY.md:105–110).
- **Reality:** the only `Logger` call in live code is `logSuccess('plugin_init', …)` at `Core/Plugin.php:50` **[S]**. The `Logging` middleware that would log requests is never instantiated; `Logging\Filter`, `Writer`, `Rotator`, `Manager` have **zero references** **[S]**. The log directory is created on activation but nothing writes rotating files to it **[S]**. The redaction *capability* inside `Logger::sanitizeMetadata()` (password/secret/token/api_key/authorization/cookie/credential/private_key) exists and is correct — it just never fires on request data **[S]**.
- **Impact:** no forensic trail of who changed what; docs promise an audit trail that does not exist.
- **Fix:** call `Logger` from a wired request listener (or in each mutating route) with method/path/user_id/ip/redacted-body; wire `Logging\Filter` or delete the claim.

#### SEC-03 · Three "disabled by default" capability flags are dead ❌ (fail-closed violation)
- **Claim:** "Everything that can change the site is off by default. Filesystem writes, database writes, plugin installation and theme activation each require `developer_mode` plus their own flag" (README:13–16); CONFIGURATION.md documents `allow_plugin_installation`, `allow_theme_activation`, `allow_destructive_operations`.
- **Reality:**
  - `allowFilesystemWrites()` → called once (`routes/filesystem.php:48`) ✅ — but `/files/delete` checks **only** `edit_files` (no destructive flag) **[S]**.
  - `allowDatabaseWrites()` → called once (`routes/database.php:58`) ✅.
  - `allowPluginInstallation()` / `allowThemeActivation()` / `allowDestructiveOperations()` → **zero callers** **[S]**. `routes/plugins.php` gates solely on `activate_plugins` (3 routes); `routes/themes.php` solely on `switch_themes` (3 routes) **[S]**.
- **Impact:** plugin activation and theme switching are possible whenever the WP capability allows, with no `developer_mode` second factor — contradicting the project's own fail-closed rule and the mission constraint "all mutating capabilities stay disabled by default". (Roles holding `activate_plugins`/`switch_themes` are normally administrators, so exploitability by low-privilege users is low — but the advertised control is absent, and `/files/delete` misses the advertised destructive flag even for admins.)
- **Fix:** add `$config->allowPluginInstallation()` / `allowThemeActivation()` / `allowDestructiveOperations()` checks to the six routes + `/files/delete`, each with a `*_DISABLED` 403 envelope, plus tests asserting 403 while flags are default-false.

#### SEC-04 · Master kill switch `enabled` never read ❌
- **Claim:** "`enabled` — Master kill switch for the API" (CONFIGURATION.md:30, `wpforge_config`).
- **Reality:** key read only by dead `Logging\Manager` **[S]**; no route/bootstrap consults it. `wpforge_config` is never written by any code path (only `uninstall.php` deletes it) **[S]** — all configuration is programmatic-only.
- **Impact:** an operator cannot emergency-disable the API via config as documented.
- **Fix:** enforce in `registerRestRoutes`/auth filter (`enabled=false` → `rest_forbidden` for every WPForge route), plus an admin toggle + tests.

#### SEC-05 · IP allowlist / blocklist / CORS config are no-ops ❌
- **Reality:** `security.allowed_ips`, `security.blocked_ips`, `security.cors_origins` referenced nowhere outside `Config.php` **[S]**.
- **Impact:** operators following CONFIGURATION.md believe network-level restrictions exist; they do not. (The WPForge dashboard is cross-origin by design; no plugin-level CORS handling exists — behavior depends entirely on WP core.)
- **Fix (choose one, document the other):** implement enforcement in the auth filter (deny by IP before route dispatch) **or** remove the keys from docs. Prefer implementing: cheap and fail-closed.

#### SEC-06 · Backup retention / size caps not enforced ❌
- **Reality:** `backup_retention_days`, `max_backup_size_mb` unread; `Backup\CleanupManager` dead; README:26 "backups with retention" **[X]** **[S]**.
- **Impact:** unbounded backup accumulation (disk exhaustion = availability risk), unbounded single-backup size.

### P1 — Authorization depth

#### SEC-07 · Missing per-object capability checks (route-level only) 🟡
- `GET /posts/{id}` → `PostManager::getPost()` performs no `current_user_can('read_post', $id)`; any user with `edit_posts` (Authors/Contributors) can read **any** post/page by ID including others' drafts/private posts **[S]**. WP core REST enforces per-object read caps; this API does not.
- `PUT/PATCH /elementor/documents/{id}` → route + handler check `edit_pages` only, no `current_user_can('edit_post', $id)` **[S]** — an editor can modify any user's document.
- `GET /users/{id}` → no per-object check (route-level `list_users` only) **[S]**.
- **Fix:** add object-level `read_post`/`edit_post`/`edit_user` checks in handlers; add tests with two authors.

### P2 — MCP server / packaging

#### SEC-08 · CI red for ~7 weeks; committed `node_modules` with broken exec bits ❌
- **Root cause [E]:** `.gitignore` contains the literal string `(empty)` → nothing ignored → **10,346 tracked files / 121 MB** under `mcp/node_modules` (incl. 11.6 MB Windows `esbuild.exe`); tracked `.bin/*` shims are mode **644**, so CI's `npm run build` dies with `sh: 1: tsc: Permission denied`; `npm install` does not restore `+x` **[E]**. TypeScript source itself compiles clean (`node node_modules/typescript/bin/tsc` exit 0, both emit and `--noEmit`) **[E]**.
- **Effect [C]:** every CI run since 2026-09-28 (last green `bc59d692`) fails solely this job — HEAD run `38201073602` (10 jobs ✅ / 1 ❌) and all 6 open dependabot PRs are red.
- **Supply-chain angle:** committed third-party binaries are frozen, unaudited by Dependabot's runtime path, and inflate clone size 10×; npm audit still reports **8 prod vulnerabilities (3 high)** incl. `zod` GHSA-mrhx-c7xc-m3w5 **[E]**.
- **Fix:** delete `mcp/node_modules` from git, write a real `.gitignore` (and root patterns: `node_modules/`, `dist/`, `.env`, cookie jars — as CHANGELOG already claims), switch CI to `npm ci`, resolve prod audit findings, unblock dependabot.

#### SEC-09 · Unpublished npm package advertised in wp-admin ❌
- `npm view @wpforge/mcp-server` → **E404** **[E]**; AdminUI "Connect to AI" page emits `npx -y @wpforge/mcp-server` (AdminUI.php:337–372) **[S]** → copy-paste onboarding fails for every user.
- **Fix:** publish scoped package (with provenance) **or** change snippet to the documented local-path run; requires maintainer approval (publishing gate).

#### SEC-10 · Broken MCP tool `wp_flush_cache` 🟡
- Calls `POST /cache/flush` — no such route (65 registrations contain no cache path) **[E]**; `CacheManager` class dead **[S]**. An agent "flushing cache" gets a 404 envelope; tool should be removed or the route implemented (design decision needed: WP object cache flush needs `wp_cache_flush()` — safe but must be capability-gated `manage_options` + flag).

#### SEC-11 · MCP credential handling 🟡
- Password via `--password=` CLI argument is visible in process listings; env var or config file (0600) preferred **[S]**. Positive: 30 s timeout, axios config stripped from error messages, `isError:true` envelope, stderr-only logging **[S]**.

### P3 — Dashboard / admin UI

#### SEC-12 · Admin-side app-password handling 🟡
- `AdminUI` "Test connection" probe stores the submitted application password in a **transient for up to 300 s** (one-time display flow) **[S]** — password persists in `wp_options` in plaintext during that window. Mitigations: admin-only screens, nonces present, transient expires. **Fix:** store only a boolean probe result + masked prefix, never the secret.

#### SEC-13 · Connect page ships broken (integrity/UX, not exploitable) ❌
- Enqueued `assets/assets/index-CMK_vOms.css` / `index-CnqWIx8j.js` do not exist in the plugin tree; fresh dashboard build produces CSS with the *same* hash but JS `index-Cez4Fus8.js` **[E]** → wp-admin Connect page loads 404s **[S]**. Also means versioned asset hashes drift on every UIUX build → build pipeline must copy `UIUX/dist/*` → `wordpress/wpforge/assets/assets/` and rewrite references (or use a stable filename).

### P4 — Tests / performance

#### SEC-14 · Test blind spots 🟡
- MCP: jest configured, **0 test files** **[E]**; UIUX: **0 tests, no test script** **[E]**; `tests/integration/` empty **[S]**; `RateLimitTest` covers only the un-wired middleware (false confidence). CI has no MCP/dashboard test jobs **[S]**.
- Rate-limit/perf: no test can catch SEC-01 because nothing asserts wiring — add "middleware registered" tests.

#### SEC-15 · Per-request DB write (`last_used_at`) 🟡
- `TokenManager::validateToken()` updates `last_used_at` on **every** authenticated request **[S]** — write amplification under agent loops (pairs badly with SEC-01). Fix: throttle update (e.g., once/minute per token) — Phase 2 P4.

### P5 — Docs / compliance inputs

- SECURITY.md hardening checklist references config keys that do not exist (`filesystem.allow_writes`, `allow_deletes`, `database.allow_writes`, `read_only`) — actual keys are `allow_filesystem_writes`, `allow_database_writes` **[S]**.
- SECURITY.md rate-limit keys (`rate_limits.max_requests`) ≠ CONFIGURATION.md keys (`rate_limit_requests`) ≠ any code **[S]** (neither is read anyway — SEC-01).
- README `15 route files` → actually 16; `78 PHP files` → 79 **[E]** (accuracy only).
- Historical incident (CHANGELOG:54–58, FINAL-AUDIT:42): committed `.freebuff/` contained **live `wordpress_logged_in_*` session cookies + wp-admin dumps of a production site**; history force-pushed and gitleaks CI added (Secret scan job green on this tree **[C]**). **Credential rotation for the affected site cannot be verified from the repository — treat as an open action for the maintainer.** [D]

---

## C. Verified-good controls (do not regress)

| Control | Evidence |
|---------|----------|
| 65/65 routes have `permission_callback`; anonymous→401, wrong-role→403 asserted across 11 protected endpoints ×2 | script **[E]** + CI security suite **[C]** |
| Bearer auth only via `rest_authentication_errors`; token hashed with `wp_check_password`; revocation list; `wpforge_tokens` schema has no plaintext column | `Authenticator`, `TokenManager`, activation `dbDelta` **[S]** |
| Application Passwords used, never the account password (docs + probe design) | docs/INSTALLATION, AdminUI **[S]** |
| Filesystem containment: `realpath`-based + deny list (wp-config, .env, .htaccess, traversal, null-byte, UNC, drive-letter) | `SecurityGuard` + 14 unit cases **[S]** **[C]** |
| SQL: SELECT-only by default (`Inspector` throws on non-SELECT), `wpdb->prepare` parameters, writes double-gated by `developer_mode && allow_database_writes` | `Database/Inspector.php`, `routes/database.php:58` **[S]** |
| No `exec`/`shell_exec`/`system`/`passthru`/`eval`/`assert`/`preg_replace /e` in plugin tree (only native ZipArchive backup path) | grep **[E]** |
| Outbound HTTP only: loopback REST probe (`rest_url`) + WP `download_url()` for media sideload (core private-IP blocking applies) | grep **[S]** |
| Input handling on mutating content routes: `wp_kses_post` for content, forced `post_author`, slug sanitization, taxonomy whitelist; DB identifiers regex-validated | `PostManager`, route files **[S]** |
| `Logger::sanitizeMetadata` redacts 10 credential-shaped keys (when logging is actually invoked) | `Logger.php` **[S]** |
| `/diagnostics/quick` (unauthenticated when enabled) returns booleans only — no versions/paths | `Diagnostics.php` **[S]** |
| `/status` + `/health` default closed; require `security.public_status_enabled` / `public_health_enabled` | `routes/system.php:30,124` **[S]** (CHANGELOG:50–56 documents prior fix of string-callback bug — regression covered by `EndpointAuthorizationTest`) **[C]** |
| Admin screens + `admin_post` handlers nonce-guarded; menus admin-only (`manage_options`) | `AdminUI.php` **[S]** |
| `uninstall.php`: drops exactly 2 tables + 4 options + 2 directories; no destructive wildcard | `uninstall.php` **[S]** |
| GPL-2.0-or-later consistent across LICENSE, composer, both package.json, headers | **[E]** |
| Secret scan (gitleaks) green on audited tree; no cookies/secrets found in current tree | CI **[C]** + grep **[E]** |

---

## D. Residual risks (accepted / deferred — record in DECISIONS.md)

1. **No live-runtime verification** of any PHP path in Phase 1 (sandbox has no PHP/MySQL) — CI job conclusions are the substitute; log bodies unretrievable.
2. `download_url()` media sideload relies on WP core SSRF protections (known-caveat: DNS-rebinding edge cases) — acceptable, but consider an explicit host allowlist later.
3. Cross-origin dashboard auth depends on WP core CORS behavior — unverified until live test.
4. Dependabot PRs blocked until SEC-08 fixed — dependency updates (incl. `zod` high) are queuing.
5. `.freebuff` incident rotation status unknown (out of band).

## E. Priority order for Phase 2 (from this audit)

`SEC-08 (P2 CI/hygiene, unblocks everything)` → `SEC-01/02/03/04/05 (P1 fail-closed wiring)` → `SEC-07 (P1 authz depth)` → `SEC-09/10 (P2 MCP)` → `SEC-12/13 (P3)` → `SEC-14/15 (P4)` → `P5 doc/key drift` — full plan in `ROADMAP.md`.
