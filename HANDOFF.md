# WPForge — Comprehensive Handoff Report (Post-PR #49)

**Generated:** 2026-11-17  
**Repository:** `avangardistic/WPForge`  
**`main` HEAD:** `ce778d2a9551efc650531db96163f09ec0d0e73f` (Merge PR #49)  
**Handoff Branch:** `arena/0b43bb68-wpforge` (branched from `ce778d2a9551efc650531db96163f09ec0d0e73f`)  
**Evidence tags:** `[E]` executed locally · `[C]` GitHub Actions API job conclusion · `[S]` static verification (`file:line`) · `[D]` doc/history claim

---

## 1. CRITICAL ALERT — Current `main` State

**`main` (`ce778d2a9551efc650531db96163f09ec0d0e73f`) is currently RED in CI (`44143063364`) `[C]`.**

- PR #49 (`Phase 2B S3` red-phase test contract) was explicitly merged by maintainer instruction after a trap-state warning so the S3 contract is on `main`.
- CI run `44143063364` on `main` `[C]`:
  - **Passed (`success`):** `PHPCS` (`134250779905`), `Plugin validation` (`134250779698`), `PHP 8.1 syntax` (`134250779712`), `PHP 8.2 syntax` (`134250779746`), `PHP 8.3 syntax` (`134250779775`), `PHP 8.4 syntax` (`134250779540`), `MCP server build` (`134250779496`), `Dashboard (UIUX) build` (`134250779101`), `Secret scan` (`134250779702`).
  - **Failed (`failure`):**
    - `PHPUnit (WP 6.7, PHP 8.1)` (`134250779391`) — `Run unit tests` ✓, **`Run security tests` ✗**
    - `PHPUnit (WP 6.7, PHP 8.3)` (`134250779616`) — `Run unit tests` ✓, **`Run security tests` ✗**
- **Cause `[S]`:** `tests/security/FeatureFlagsTest.php` contains the 26-case S3 contract, and none of the 6 route callbacks yet check `allowPluginInstallation()`, `allowThemeActivation()`, or `allowDestructiveOperations()`. Twelve assertions (6 default-off + 6 `developer_mode=false`) currently fail because the endpoints return `200` instead of `403 *_DISABLED`.
- **Rule:** Do **not** hotfix `main` directly, and do **not** weaken, skip, or delete any assertion in `tests/security/FeatureFlagsTest.php`. Fix forward via a Draft PR on an active working branch.

---

## 2. Work Completed So Far (Phase 1 → Phase 2B S3 Red)

| Phase / PR | Merge Commit | Outcome | Key Changes & Notes |
|---|---|---|---|
| **Phase 1** (PR #44) | `9818f845` | `MERGED` | Docs-only full audit: `PROJECT_STATUS.md`, `SECURITY_AUDIT.md`, `TEST_REPORT.md`, `WORDPRESS_COMPLIANCE.md`, `ROADMAP.md`, `DECISIONS.md`, `PHASE1_REPORT.md`. |
| **Phase 2A** (PR #45) | `4681a730` | `MERGED` | Untracked `mcp/node_modules` (10,346 files / 121 MB), wrote real `.gitignore`, switched CI to `npm ci`, added `$GITHUB_STEP_SUMMARY`, cleared all prod npm vulns in `mcp/` (`0` prod vulns; 20 dev-only jest/babel vulns documented in `DECISIONS.md`). |
| **Phase 2B S1 Red** (PR #46) | `1028aadc` | `MERGED` (revealed bug) | Committed 3 failing S1 tests (`bad6972`). Revealed CI was masking PHPUnit failures because `.github/workflows/ci.yml` piped PHPUnit to `tee` without `set -o pipefail`. |
| **Phase 2A-fix-1 + 2B S1 Green** (PR #47) | `2de8e3a0` | `MERGED`, `main` 11/11 green (`44022318928`) `[C]` | Added `set -o pipefail;` and `if: always()` `actions/upload-artifact@v7` log uploads in `.github/workflows/ci.yml`. Wired `API\Middleware\RateLimit` on `rest_pre_dispatch` (`Core\Plugin::enforceRateLimit`), added `Config::getTrustedProxies()`, implemented right-to-left `X-Forwarded-For` parsing in `RateLimit::resolveClientIp()` (untrusted by default, ignores `HTTP_CLIENT_IP`). |
| **Phase 2B S2** (PR #48) | `0f805077` | `MERGED`, `main` 11/11 green (`44060187216`) `[C]` | Fixed `Logger::LOG_TABLE_SUFFIX` (`'_wpforge_logs'` → `'wpforge_logs'`), added recursive `Logging\Filter::redact()` wired into `Logger::sanitizeMetadata()`, aligned `Logger::getClientIp()` with `RateLimit::resolveClientIp()`, hooked `Core\Plugin::auditLogMutation` on `rest_request_after_callbacks` for `/wpforge/v1` `POST`/`PUT`/`PATCH`/`DELETE` with 64KB body truncation (`[TRUNCATED] <N> bytes`) before redaction. Deferred file logging (`Logging\Manager`/`Writer`/`Rotator`) to `2E`. |
| **Phase 2B S3 Red** (PR #49) | `ce778d2a` | `MERGED` (`main` red as expected) | Added `tests/security/FeatureFlagsTest.php` (26 cases) and recorded S3 `DECISIONS.md` amendments. |

### Important Recorded Decisions & Exceptions (`DECISIONS.md`)
- **Locked decisions `O-1` through `O-9`** (2026-11-16) are non-negotiable (see `DECISIONS.md` §5):
  - `O-1`: Wire all dead controls (no doc-only shortcuts).
  - `O-2`: Implement `POST /cache/flush` gated `manage_options` + destructive flag (in `2C`).
  - `O-3`: Local-path MCP snippet only; no `npm publish` without separate release gate.
  - `O-4`: Delete dead classes in `2E` with list recorded in `DECISIONS.md`.
  - `O-5`: Build Admin Settings page (`WPForge → Settings`).
  - `O-6`: Keep both dashboards; fix asset pipeline.
  - `O-7`: Version bump only at release.
  - `O-8`: Closed — cookie auth behavior verified static-clean; do not mention credential rotation until release gate.
  - `O-9`: Add WP 7.1 to CI matrix before any `Tested up to: 7.1` claim.
- **S2 Test Exception & Audit Limitation (`DECISIONS.md`):**
  - `tests/security/AuditLoggingTest.php` hook assertion was changed from `rest_post_dispatch` to `rest_request_after_callbacks` because `rest_do_request()` calls `WP_REST_Server::dispatch()`, bypassing `serve_request()` (`rest_post_dispatch` never fires under `rest_do_request()`). All other assertions stayed untouched.
  - **Known S2 limitation assigned to S5:** Requests short-circuited in `rest_pre_dispatch` (`429`) or `permission_callback` (`401`/`403`) do not reach `rest_request_after_callbacks` and are not logged yet. S5 (IP allow/block) must add a parallel audit hook for rejected requests (including rejected `permission_callback` attempts on `/logs/clear` etc.).
- **S3 Route Inventory Amendment (`DECISIONS.md`):**
  - `SEC-03` in `SECURITY_AUDIT.md` miscounted routes: `/plugins/install` and `/themes/deactivate` **do not exist** in `wordpress/wpforge/routes/`.
  - `allow_plugin_installation` gates `POST /plugins/activate` and `POST /plugins/deactivate` (do **not** rename the flag — preserve backward compatibility and update `docs/CONFIGURATION.md` + `README.md` in `2F`).
  - `README.md:13` overclaims *"Everything that can change the site is off by default"* (content/media/user/Elementor/backup creation routes do not require `developer_mode`); fix wording in `2F`, do not gate `/posts`.

---

## 3. Immediate Next Task — Complete Phase 2B S3 (Green Phase)

Do **S3 implementation only** (after confirming/getting `"go"` from the maintainer if starting in a fresh session). Keep `tests/security/FeatureFlagsTest.php` untouched.

### 3.1 Exact 6 Gated Routes + 2 Open Read Routes (`[S]`)

**Why flag checks live in `callback` rather than `permission_callback` `[S]`:**
- WordPress `WP_REST_Server` converts a `WP_Error` returned from `permission_callback` into bare WP error JSON (`{"code": ..., "message": ..., "data": {"status": 403}}`), NOT WPForge's `Response::error()` envelope (`{"success": false, "request_id": "...", "error": {"code": "...", "message": "..."}}`).
- Returning a `WP_REST_Response` from `permission_callback` is treated by WordPress as truthy (bypass!).
- Therefore, keep `Permissions::can(...)` in `permission_callback` (handles `401` anonymous and `403 wpforge_forbidden` missing WP capabilities), keep any extra per-route `current_user_can(...)` checks, and add the `$config->allow*()` check in the route `callback` returning `Response::error(...)` — matching existing `routes/filesystem.php:48-49` and `routes/database.php:58-59`.

| # | Route | File & Lines | Capability Gate | Config Check (`developer_mode && flag`) | 403 Envelope Code |
|---|---|---|---|---|---|
| 1 | `POST /wpforge/v1/plugins/activate` | `wordpress/wpforge/routes/plugins.php:17-35` | `activate_plugins` | `$config->allowPluginInstallation()` | `PLUGIN_INSTALLATION_DISABLED` |
| 2 | `POST /wpforge/v1/plugins/deactivate` | `wordpress/wpforge/routes/plugins.php:37-51` | `activate_plugins` | `$config->allowPluginInstallation()` | `PLUGIN_INSTALLATION_DISABLED` |
| 3 | `POST /wpforge/v1/themes/activate` | `wordpress/wpforge/routes/themes.php:26-44` | `switch_themes` | `$config->allowThemeActivation()` | `THEME_ACTIVATION_DISABLED` |
| 4 | `DELETE /wpforge/v1/files/delete` | `wordpress/wpforge/routes/filesystem.php:72-90` | `manage_options` + `edit_files` | `$config->allowDestructiveOperations()` | `DESTRUCTIVE_OPERATIONS_DISABLED` |
| 5 | `DELETE /wpforge/v1/logs/clear` | `wordpress/wpforge/routes/logs.php:31-40` | `manage_options` | `$config->allowDestructiveOperations()` | `DESTRUCTIVE_OPERATIONS_DISABLED` |
| 6 | `DELETE /wpforge/v1/backup/(?P<id>[a-zA-Z0-9_]+)` | `wordpress/wpforge/routes/backup.php:45-58` | `manage_options` | `$config->allowDestructiveOperations()` | `DESTRUCTIVE_OPERATIONS_DISABLED` |

Read-only routes that **must stay 200** for authorized users even when `developer_mode = false`:
- `GET /wpforge/v1/plugins` (`wordpress/wpforge/routes/plugins.php:9-15`)
- `GET /wpforge/v1/themes` (`wordpress/wpforge/routes/themes.php:9-15`) and `GET /wpforge/v1/themes/(?P<stylesheet>...)` (`routes/themes.php:17-24`)

Standard content/CRUD deletes (`DELETE /posts/{id}`, `DELETE /media/{id}`, `DELETE /taxonomies/.../terms/{id}`, `DELETE /users/{id}`, `DELETE /tokens/{id}`) remain gated by WordPress capabilities only.

### 3.2 Latent Production Bug Discovered During S3 INSPECT (`[S]`)

- `wordpress/wpforge/src/WordPress/PluginManager.php:74-95`:
  - `activatePlugin()` and `deactivatePlugin()` call `is_plugin_active()`, `activate_plugin()`, and `deactivate_plugins()` **without** requiring `ABSPATH . 'wp-admin/includes/plugin.php'` (unlike `getPlugins()` at line 16 and `getPlugin()` at line 49).
  - In a pure REST request where `GET /plugins` was not called first, `is_plugin_active()` throws an undefined function fatal error.
  - **Fix during S3:** Add `if (!function_exists('is_plugin_active')) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }` to both `activatePlugin()` and `deactivatePlugin()`.

### 3.3 Note on `Config` Caching (`[S]`)
- `Config::all()` (`wordpress/wpforge/src/Core/Config.php:88-107`) calls `get_option('wpforge_config', [])` fresh on every call, so reusing `$config = new Config();` inside route closures dynamically sees `update_option('wpforge_config', ...)` changes. (However, `routes/filesystem.php:8-9` captures `$fsManager = new Manager($config->getFilesystemRoot())` once at `rest_api_init` time — keep that in mind for `filesystem_root` tests.)

### 3.4 S3 Exit Checklist
1. Implement flag gates in the 5 route files (`plugins.php`, `themes.php`, `filesystem.php`, `logs.php`, `backup.php`) + `PluginManager.php` include fix.
2. Update `DECISIONS.md` (`S3 planned/inspected` → `S3 wired`).
3. Ensure PSR-12 / `phpcs.xml` compliance (`composer lint` runs in CI; note PHPCS 3.7 rejects multiline arrays directly inside `foreach ([...] as $x)`).
4. Push to active branch, open Draft PR to `main`, verify **11/11 CI jobs `success`** (including both PHPUnit 8.1 and 8.3 jobs under `set -o pipefail`).
5. Report green in Persian and wait for explicit maintainer approval before merging to `main` and starting S4 INSPECT.

---

## 4. Remaining Phase 2 Roadmap (Strict Sequential Order)

Do not skip ahead, reorder, or bundle sub-phases. Each sub-phase requires: **INSPECT (report findings explicitly even if empty) → RESEARCH → PLAN (wait for approval) → RED TEST (push, watch red, report, wait for `"go"`) → GREEN FIX (push, watch green, report, wait for approval) → MERGE & VERIFY `main`**.

### Phase 2B (remaining)
- **S4 — Kill switch + Admin Settings page (`SEC-04`, `O-5`):**
  - Enforce `enabled = false` globally on all WPForge REST routes (`403`, e.g. `API_DISABLED`). Check whether `GET /diagnostics/quick` or all `/wpforge/v1/*` routes should be blocked (`DECISIONS.md` / `ROADMAP.md` says every WPForge route → 403).
  - Build `WPForge → Settings` admin screen (`AdminUI`) writing `wpforge_config` with nonce/capability checks (`manage_options`), escaping via `AdminUI` helpers (`phpcs.xml`), and clean round-trip of documented keys.
- **S5 — IP allowlist/blocklist + CORS + S2/S3 audit short-circuit follow-up (`SEC-05`):**
  - Enforce `security.blocked_ips` and `security.allowed_ips` (using `RateLimit::resolveClientIp()` CIDR/IP matching, honoring `trusted_proxies`).
  - Decide and record `security.cors_origins` handling in `DECISIONS.md` (implement or explicitly document WP core behavior).
  - **S2/S3 follow-up:** Add parallel audit logging for requests short-circuited before `rest_request_after_callbacks` (`429` in `rest_pre_dispatch`, `401`/`403` in auth/`permission_callback`, including rejected `/logs/clear` attempts).
- **S6 — Backup retention & size caps (`SEC-06`):**
  - Wire `Backup\CleanupManager` with `backup_retention_days` and enforce `max_backup_size_mb` during backup creation.
- **S7 — Per-object authorization depth (`SEC-07`):**
  - Add object-level checks (`read_post` in `PostManager::getPost()`, `edit_post` on Elementor `PUT /elementor/documents/{id}`, `edit_user` on `GET /users/{id}` — check all handlers for missing per-object caps).
  - Add two-author security tests (Author B cannot read Author A's draft/private post or update Author A's Elementor doc).
- **S8 — Integration suite & regression sweep:**
  - Populate `tests/integration/` (currently empty).

### Phase 2C — MCP Reliability (`M1`–`M5`)
- Implement `POST /wpforge/v1/cache/flush` gated `manage_options` + `allowDestructiveOperations()` (`O-2`).
- Document all 37 MCP tools in `mcp/README.md` (`M2`).
- Add Jest unit/contract tests (`npm test`) and wire an MCP test job into CI (`M3`).
- Rewrite `AdminUI` onboarding snippet to local-path run (`O-3`, do **not** `npm publish`).
- Replace `--password=` CLI default with env/config-file input (`SEC-11`, `M5`).

### Phase 2D — Dashboard (`O-6`, `D1`–`D4`)
- Keep both dashboards (`O-6`); fix asset pipeline so `UIUX/dist` copies to `wordpress/wpforge/assets/assets/` with stable filenames referenced by `AdminUI.php` (`SEC-13`).
- Stop persisting plain-text Application Password in a transient (`SEC-12` — store boolean + masked prefix only).
- Add minimal UIUX tests + CI job (`D4`).

### Phase 2E — Performance & Dead-Code Quarantine (`O-4`, `T1`–`T4`)
- Throttle `TokenManager::validateToken()` `last_used` DB writes (`SEC-15`, ≤ 1 write/token/minute).
- Audit, wire, or delete ~20 dead PHP classes (including deciding on `Logging\Manager`/`Writer`/`Rotator` and `schemas/*.json`); record the deletion/exclusion list in `DECISIONS.md` (`O-4`).
- Publish PHPUnit coverage artifact (`T3`) and add edge tests (`T4`).

### Phase 2F — Docs Truth-Up (`C1`–`C5`)
- Update `README.md` (file counts: 16 route files, 79 PHP files; tool count 37; accurate line 13 wording).
- Fix key drift in `docs/SECURITY.md` and update `docs/CONFIGURATION.md` (`allow_plugin_installation` gates activate/deactivate, Settings UI).
- Reconcile `docs/API_REFERENCE.md`, `CHANGELOG.md`, `readme.txt`, and `.pot` i18n generation.

### Phase 3 — Release Gate
- Add WordPress `7.1` to `.github/workflows/ci.yml` matrix before claiming `Tested up to: 7.1` (`O-9`).
- Bump version only at release (`O-7`).
- Remind maintainer to rotate leaked `.freebuff` production cookies only at the release gate (`O-8`).
- Never publish to npm, WordPress.org, or SVN without explicit release approval.

---

## 5. Sandbox & CI Operational Notes (Save Yourself Time)

1. **No local PHP runtime `[E]`:** The Arena Debian 12 sandbox does **not** have `php`, `phpunit`, `composer`, `mysql`, or `wp`. All PHPCS, syntax, plugin validation, and PHPUnit checks run via GitHub Actions CI (`.github/workflows/ci.yml`).
2. **Azure Blob Storage is unreachable from the sandbox (`[E]`):** `gh run download`, `gh run view --log`, and `gh api .../actions/jobs/<id>/logs` fail with `EOF` on `*.blob.core.windows.net`.
   - Do **not** waste turns trying to download CI log artifacts.
   - Report CI results at job-conclusion granularity (`gh api /repos/avangardistic/WPForge/actions/runs/<id>/jobs`) and state `"not readable from this sandbox"` for exact `Tests:/Assertions:` counts.
   - If `PHPCS` fails, you *can* inspect `gh api /repos/avangardistic/WPForge/check-runs/<job_id>/annotations`, though PHPCS details are usually just exit code 2 — check `phpcs.xml` (`PSR12`, line length `160`, `WordPress.Security.ValidatedSanitizedInput`, `WordPress.WP.Deprecated*`, `WordPress.DB.PreparedSQLPlaceholders`, `PHPCompatibilityWP >= 8.1`).
3. **Maintainer reporting format:**
   - Every sub-phase report to the maintainer must be in **Persian**, include evidence tags (`[E]`, `[C]`, `[S]`, `[D]`, `[X]`), explicitly report INSPECT findings even when empty, and stop for explicit approval before moving on.
4. **Git / PR rules:**
   - Always work on the active session branch assigned by Arena (`git branch` / system message). Never create/switch branches without explicit approval, never force-push or rewrite history, never merge PRs without explicit approval every time, and always verify `main` CI after any merge.
