# WPForge — Final Audit

**Scope:** production-hardening and polish pass over the existing WPForge product.
No new MCP tools or REST capabilities were added; the existing feature surface was
audited, fixed, hardened, and polished.

**Baseline reviewed at:** commit `07415dc` (branch `main`).
**CI:** all jobs green — see the Actions tab for `.github/workflows/ci.yml`.

---

## 1. Executive summary

### Before
The repository was functional but not production-ready. It carried committed live
session cookies and client site dumps; the plugin could not load on its declared
minimum PHP version; `composer lint`, `composer test`, `composer validate` and the
build/packaging script had never run successfully; `wp-config.php` was readable
through the API by any logged-in user; every read endpoint authorised on login
alone; and the readme claimed a WordPress version that does not exist.

### Work performed
Security fixes (credential purge, a path-traversal disclosure, an authentication
bypass, capability-based authorization on every route, deeper filesystem
hardening, removal of process execution), a repaired and expanded test and CI
pipeline, a UI/UX polish pass on the dashboard, and WordPress.org readiness.

### After
No process-execution calls, no known credential exposure, capability-gated
endpoints with regression tests, a green CI matrix across PHP 8.1–8.4 plus a real
WordPress test environment, a clean release archive, and documentation that
matches the code.

---

## 2. Security

### Findings and fixes (verified)

| # | Finding | Severity | Status |
|---|---------|----------|--------|
| 1 | `.freebuff/` held live `wordpress_logged_in_*` session cookies and authenticated wp-admin dumps of a production site | Critical | Purged from all history; history rewritten and force-pushed |
| 2 | `wp-config.php` (DB credentials, auth salts) readable via `GET /files/read` by any authenticated user — the anchored deny patterns never fired after normalisation, and `SecurityGuard` lacked the rule entirely | Critical | Fixed; regression tests added |
| 3 | `/status` and `/health` reachable unauthenticated (permission_callback returned a truthy string instead of calling it) | High | Fixed; honours config flags |
| 4 | Every read endpoint authorised on `is_user_logged_in` alone — a Subscriber could read files, run SELECTs, list users | High | Capability gating on every route via `WPForge\API\Permissions`; regression tests |
| 5 | Path traversal was silently clamped rather than refused | Medium | `normalizePath()` now throws; test-covered |
| 6 | Filesystem guards did not reject null/control bytes, drive letters, ADS, or UNC prefixes | Medium | Both guards hardened; test-covered |
| 7 | `FilesystemBackup` shelled out via `exec()`/`which` | Low (not injectable) / compliance | Replaced with native `ZipArchive` |
| 8 | `GET /` discovery endpoint was public | Low | Now requires authentication |

### Authorization model (after)

Enforced at the permission layer by `WPForge\API\Permissions::can(...)`, returning
`401` when unauthenticated and `403` when under-privileged, before any handler runs.
Handlers keep their per-object `current_user_can()` checks as defence in depth.

| Endpoint group | Capability |
|----------------|-----------|
| `/files/*`, `/database/*`, `/site/*`, `/environment`, `/diagnostics`, `/logs/*`, `/backup/*` | `manage_options` |
| `/plugins/*` | `activate_plugins` |
| `/themes/*` | `switch_themes` |
| `/menus/*` | `edit_theme_options` |
| `/media/*` | `upload_files` |
| `/users/*` | `list_users` (writes add `edit_users`/`create_users`) |
| `/taxonomies/*` | `manage_categories` |
| `/elementor/*` | `edit_pages` |
| `/posts/*`, `/pages/*` | `edit_posts` / `edit_pages` (+ per-object write caps) |
| `/tokens/*`, `/capabilities`, `/` | authenticated |
| `/diagnostics/quick` | public (five booleans, no version/path disclosure) |

### Filesystem (verified against both guards)

Refused: `wp-config.php`, `wp-config-sample.php`, `.htaccess`, `.env`, `/etc`,
`/proc`, `/sys`, `/dev`, `..` traversal (throws, not clamped), absolute paths,
Windows drive letters, alternate-data-stream names, null/control bytes, and
UNC / authority prefixes (`\\server\share`, `//host`). Symlink escapes are caught
by `realpath()` containment. Allowed: legitimate relative paths within the root,
including interior `..` that stays inside it.

### Database

Read-only by default; non-`SELECT` requires `developer_mode` + `allow_database_writes`.
Values bind through `$wpdb->prepare()`; only table identifiers are interpolated.
`DB_NAME` is redacted from `/database/status` by default.

### Error handling (no secret/trace leakage)

- REST handlers return structured `{code, message}`; the global `ErrorHandler`
  returns a generic `"An internal error occurred."` with no trace to the client.
  Stack traces are written to the server log only, guarded by `WP_DEBUG`.
- The MCP client interceptor throws a plain `Error(message)`, stripping the axios
  config that holds credentials; the tool handler returns `{error, message}` with
  no stack trace.
- `Logging\Filter` redacts credential-shaped values before persistence.

### Remaining risk (accepted / documented)

- These endpoints trust the WordPress capability model; a compromised
  administrator account is out of scope, as for any admin-level plugin.
- `Filesystem\SecurityGuard` and `Security\PathValidator` are near-duplicate
  implementations kept in sync by hand and by shared tests; consolidating them is
  a future cleanup, not a defect.

---

## 3. Existing functionality (preserved)

No MCP tools, REST routes, or WordPress integrations were removed. The MCP server,
the REST API surface, Elementor/filesystem/database/backup subsystems, and the
dashboard's live-data flow are intact. Changes were the smallest ones that fixed a
defect or closed a risk, and each mutating endpoint retains its behaviour.

---

## 4. UI/UX (dashboard)

- **Design system:** added semantic tokens (`bad`/`warn`/`info`); removed ~25 raw
  `red-*` utilities in favour of the `bad` token.
- **Accessibility:** the connection dialog traps focus, focuses the first field on
  open, restores focus to the trigger on close, and is labelled by its heading.
- **States:** human-readable error messages (network/401/403/404/429/5xx),
  reduced-motion-aware loading skeletons, and existing empty/disconnected states.
- **Responsive:** a scrollable capability navigator below `lg` makes every area
  reachable on a phone (previously only the audit log was).
- **Head:** corrected `<title>`, replaced a missing (404) favicon with an inline
  branded icon, added description/theme-color/color-scheme meta.
- **Dependencies:** removed the unused `@emotion/react`.

Verified visually at desktop (1280px) and mobile (375px) — connection modal, error
state, and the mobile navigator. Tablet and a full keyboard-only walkthrough were
not screenshot-verified in this pass (see Not verified).

---

## 5. Testing — exact commands and results

Local (portable PHP 8.1–8.4, PHPUnit 9.6, PHPCS 3.13 + WPCS 3.4 + PHPCompatibility):

| Check | Command | Result |
|-------|---------|--------|
| PHP lint 8.1 | `php -l` over 89 files | PASS |
| PHP lint 8.2 | `php -l` over 89 files | PASS |
| PHP lint 8.3 | `php -l` over 89 files | PASS |
| PHP lint 8.4 | `php -l` over 89 files | PASS |
| PHPCS | `composer lint` | PASS (0 errors) |
| PHPCompatibility | `phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.1-` | PASS |
| Plugin validation | `php tools/validate/validate-plugin.php` | PASS |
| Build ZIP | `php tools/build/package.php` | PASS (86 files, no dev artifacts) |
| UIUX typecheck | `npx tsc --noEmit` | PASS |
| UIUX lint | `npx eslint . --ext .ts,.tsx` | PASS |
| UIUX build | `npx vite build` | PASS |

CI (`.github/workflows/ci.yml`, run on `main`, all green):

| Job | Result |
|-----|--------|
| PHPCS | PASS |
| PHP 8.1 / 8.2 / 8.3 / 8.4 syntax | PASS |
| Plugin validation | PASS |
| MCP server build | PASS |
| Dashboard (UIUX) build | PASS |
| Secret scan (gitleaks) | PASS |
| PHPUnit (WP 6.7, PHP 8.1) | PASS — unit 5/5, security 68/68 |
| PHPUnit (WP 6.7, PHP 8.3) | PASS |

The PHPUnit jobs install a real WordPress 6.7 and its test library, activate the
plugin under `WP_UnitTestCase`, and dispatch real REST requests — so a subscriber
receiving `403` and an anonymous request receiving `401` on protected endpoints,
and the filesystem guard refusing every traversal vector, are verified against
live WordPress, not mocks.

---

## 6. WordPress.org readiness checklist

| Item | State |
|------|-------|
| Plugin header (Name, URI, Version, Author, License, Requires PHP/WP, Text Domain) | Present |
| `readme.txt` (lowercase, valid header, sections) | Present |
| `Tested up to` a real WordPress version | Fixed (6.7) |
| Licence GPL-compatible and consistent (GPL-2.0-or-later across LICENSE, composer, npm, headers) | Consistent |
| No process-execution functions (`exec`/`shell_exec`/`system`/…) | None |
| No secrets, cookies, or debug dumps in the tree | None |
| No third-party external services (only self-requests to the site's own REST API) | Confirmed |
| `uninstall.php` removes tables, options, and directories | Present |
| Capability checks on privileged operations | Enforced |
| No obfuscated code | None |

Recommended before an actual wordpress.org submission (maintainer decisions, not
done here): bump the version from `1.0.0` (these are post-1.0.0 fixes), run the
official Plugin Check plugin in a live install, and add a `languages/*.pot`.

---

## 7. Release

- **Version:** 1.0.0 (unchanged — a bump to 1.0.1 is recommended before release).
- **Artifact:** `build/wpforge-1.0.0.zip`, produced by `composer build` /
  `php tools/build/package.php`. 86 files, single top-level `wpforge/` directory,
  no `.git`, `node_modules`, tests, logs, `.env`, or scratch files.
- The archive is reproducible from a clean checkout; it is not committed.

---

## 8. Not verified in this pass

- A full end-to-end install/activate/deactivate/uninstall through the wp-admin UI
  on a live server (CI exercises the plugin under the WordPress test harness, which
  covers loading and REST dispatch but not the admin-screen click-path).
- Browser QA of the dashboard at tablet width, and a complete keyboard-only /
  screen-reader walkthrough (desktop and mobile were visually verified).
- The official wordpress.org Plugin Check tool.
- MCP tools exercised end-to-end against a live site (the server builds and its
  error paths were reviewed; no behavioural change was made to the tools).

---

## 9. Verdict

Production-ready for the audited scope, pending the maintainer's release-time steps
in §6 (version bump, Plugin Check, live install test). No known critical or high
vulnerabilities remain; the full CI matrix is green.
