# WPForge — PROJECT STATUS (Phase 1 Audit)

**Branch:** `arena/87eda57c-wpforge` · **Audited commit:** `3345b479` (base of branch, = current `main` HEAD)
**Date:** 2026-11-16 · **Phase scope:** documentation-only audit — **no source code was changed.**

## Evidence conventions

| Tag | Meaning |
|-----|---------|
| **[E]** | Executed locally in this audit sandbox; command result observed directly. |
| **[C]** | Reported by the GitHub Actions API (job conclusion) for the exact audited commit `3345b479`. Log *bodies* were unretrievable (storage EOF), so per-test detail is not claimed. |
| **[S]** | Statically verified against source (script/grep with file:line evidence). |
| **[D]** | Document claim only — not verified (typically requires a live WordPress). |
| **[X]** | Claim **contradicted** by evidence. |

Statuses: ✅ verified-good · 🟡 works with gaps · ❌ broken or contradicted · ⬜ unverified / not runnable here.

---

## 1. Status Matrix

### 1.1 Component overview

| # | Component | Maturity verdict | Top blocking issues |
|---|-----------|------------------|---------------------|
| 1 | **WordPress plugin** (`wordpress/wpforge/`, v1.0.0, 79 PHP files **[S]**) | 🟡 **Feature-complete alpha with material doc-vs-code security gaps** — auth core solid; three advertised safety flags and both enforcement middlewares are dead code | Kill switch + `allow_plugin_installation`/`allow_theme_activation`/`allow_destructive_operations` unread **[S]**; rate limiting & audit logging advertised but not wired **[S]**; Connect-page assets missing **[S]** |
| 2 | **MCP server** (`mcp/`, TypeScript, 37 tools) | 🟡 **Compiles clean locally; red in CI; unpublished; zero tests** | CI root cause: committed `node_modules` `.bin/*` lack exec bit → `tsc: Permission denied` **[E]** **[C]**; `@wpforge/mcp-server` not on npm (404) **[E]**; `wp_flush_cache` targets nonexistent endpoint **[S]** |
| 3 | **React dashboard** (`UIUX/`, React 19 + Vite) | 🟢 **Builds clean, zero prod vulnerabilities** (but not shipped working inside the plugin) | 0 tests **[S]**; bundled `dist/` not copied to `assets/assets/`, and AdminUI references a stale JS hash → embedded Connect page loads no JS/CSS **[S]** |
| 4 | **Integration (plugin ⇄ MCP ⇄ dashboard)** | 🔴 **Two of three integration paths broken as shipped; none e2e-verified** | AdminUI→React page: missing assets **[S]**; AdminUI→`npx -y @wpforge/mcp-server`: package 404 **[E]**; MCP→API: `wp_flush_cache` 404 path **[S]**; dashboard→API: build-verified only, no live WP **[D]** |

### 1.2 WordPress plugin — detail

| Area | Status | Evidence |
|------|--------|----------|
| Route registration: **65 calls / 49 paths / 16 route files** | ✅ | Script over `routes/*.php` **[E]**; README's "65 … across 49 paths" ✓, README's "15 route files" **[X]** (16) |
| `permission_callback` present on all 65 registrations | ✅ | Python scan: 0 missing **[E]**; security suite asserts 401/403 behaviour **[C]** |
| Response envelope matches API_REFERENCE (`success/request_id/data`, `pagination`, `error{code,message}`) | ✅ | `Response.php` vs `docs/API_REFERENCE.md` **[S]** |
| Bearer + Application-Password auth via `rest_authentication_errors` | ✅ | `Core/Plugin.php:84–136` **[S]**; endpoint-auth CI suite green **[C]** |
| Token storage hashed (`wp_check_password`), revocable, per-user | ✅ | `Auth/TokenManager.php` + unit tests (5 methods) **[S]** **[C]** |
| Filesystem path containment (`realpath` + deny-list) | ✅ | `Filesystem/SecurityGuard.php`; 14 unit path cases + 16×2 security cases **[S]** **[C]** |
| DB queries read-only by default, `wpdb->prepare` parameters | ✅ | `Database/Inspector.php`, `routes/database.php` **[S]** |
| No `exec`/`shell_exec`/`system`/`eval`/`assert` anywhere in plugin | ✅ | grep across `wordpress/` **[E]** |
| Outbound HTTP limited to loopback REST probe + `download_url()` media sideload | ✅ | only `AdminUI.php` (`wp_remote_get(rest_url(…))`) and `routes/media.php` **[S]** |
| `developer_mode` double-gate on **filesystem writes** and **DB writes** | ✅ | `routes/filesystem.php:48`, `routes/database.php:58` **[S]** |
| **Same double-gate on plugin install/activation, theme activation, deletes** | ❌ | `allowPluginInstallation()`/`allowThemeActivation()`/`allowDestructiveOperations()` have **zero callers**; activate routes gate only on `activate_plugins`/`switch_themes`; `/files/delete` only `edit_files` **[S]** → README:13–16 claim **[X]** |
| **Master kill switch `enabled`** | ❌ | key never read by any live code path (only dead `Logging\Manager`) **[S]** → CONFIGURATION.md "Master kill switch" **[X]** |
| **Rate limiting (100 req/60 s)** | ❌ | `Middleware/RateLimit` never instantiated; no `addMiddleware`/`new Router` anywhere **[S]** → README:137, SECURITY.md:96 **[X]** |
| **Audit logging of mutations + file rotation** | ❌ | only Logger call is `plugin_init` (`Core/Plugin.php:50`); middlewares unwired; `Logging\Filter`/`Writer`/`Rotator` dead → README:138, SECURITY.md:105–107 **[X]** |
| `cors_origins` / `allowed_ips` / `blocked_ips` enforcement | ❌ | zero references outside `Config.php` **[S]** (documented in CONFIGURATION.md) **[X]** |
| `backup_retention_days` / `max_backup_size_mb` enforcement | ❌ | zero references outside `Config.php`; `Backup\CleanupManager` dead **[S]** → README:26 "backups with retention" **[X]** |
| Admin **Settings page** for the 20+ config keys | ❌ | AdminUI registers only *Dashboard* + *Connect*; **no code writes `wpforge_config`** (only `uninstall.php` deletes it) **[S]** → CONFIGURATION.md:36 "WPForge → Settings" **[X]**; config only settable programmatically |
| Connect-to-AI React page inside wp-admin | ❌ | enqueues `assets/assets/index-CMK_vOms.css` + `index-CnqWIx8j.js`; `assets/` contains only `css/` + `js/` **[S]**; fresh UIUX build emits matching CSS hash but JS `index-Cez4Fus8.js` **[E]** → page ships without JS/CSS |
| API_REFERENCE coverage vs code | 🟡 | doc covers 47/49 paths; missing `GET /` (manifest) and `GET /elementor/templates/{id}`; 5 paths document fewer methods than registered (PATCH duplicates PUT; undocumented `DELETE /pages/{id}`, `DELETE /users/{id}`, `GET /taxonomies/…/terms/{id}`) **[E]** |
| Per-object authorization depth | 🟡 | `PostManager::getPost` (no `read_post`), Elementor PUT (route-level `edit_pages` only, no `edit_post($id)`), `GET /users/{id}` (no per-object check) **[S]** — role-level caps otherwise consistent (media/users/taxonomies/menus/site verified) |
| Dead classes / unused wiring | 🟡 | ~20 unreferenced classes incl. `Router`, `Controller`, all 4 middlewares, `QueryBuilder`, `ReadOnlyExecutor`, `WriteExecutor`, `CapabilityChecker`, `Auth\ApplicationPassword`, filesystem `Reader/Writer/Scanner`, `BackupManager`, `ContentParser`, `CacheManager`, `RestoreManager`, `CleanupManager`, `Logging\Filter`, `InputFilter`, `NonceManager`, `Sanitizer`, `schemas/*.json` **[S]**; `use …Router` unused in `routes/system.php` **[S]** |
| `uninstall.php` cleanup (2 tables, 4 options, 2 dirs) | ✅ | `uninstall.php` **[S]**; live uninstall not executed **[D]** |
| CI pipeline (this exact tree) | 🟡 | Run `38201073602` (2026-10-12, head `3345b479`): Secret scan, PHPUnit ×2 (WP 6.7 / PHP 8.1+8.3), syntax 8.1–8.4, PHPCS, Plugin validation, Dashboard build all **success**; **MCP server build: failure** → run conclusion `failure` **[C]**. Last fully green run: `bc59d692`, 2026-09-28 **[C]** |

### 1.3 MCP server — detail

| Area | Status | Evidence |
|------|--------|----------|
| TypeScript compiles | ✅ | `node node_modules/typescript/bin/tsc -p tsconfig.json` → exit 0 (emit **and** `--noEmit`) **[E]** |
| MCP spec conformance (2025-06-18 tools spec) | ✅ | declares `capabilities:{tools:{}}`; implements `tools/list` + `tools/call`; per-tool JSON-Schema `inputSchema`; errors returned as `isError:true`; logs to stderr only **[S]** |
| Tool inventory | 🟡 | **37** tools in `src/tools/index.ts` **[E]**; `mcp/README.md` documents **32** (missing `wp_get_diagnostics`, `wp_get_capabilities`, `wp_get_media`, `wp_delete_backup`, `wp_get_user`) **[E]** |
| `wp_flush_cache` → `POST /cache/flush` | ❌ | no `/cache/flush` route exists; no cache section in API_REFERENCE; `CacheManager` dead **[S]** |
| CI build job | ❌ | every run since 2026-09-28 fails only "MCP server build" **[C]**; root cause reproduced locally: `sh: 1: tsc: Permission denied` because committed `mcp/node_modules/.bin/*` are mode 644 and `npm install` does not restore `+x` **[E]** |
| Repo hygiene | ❌ | `.gitignore` contains literally `(empty)` (7 bytes) **[E]**; **10,346 tracked files / 121 MB** under `mcp/node_modules` incl. an 11.6 MB Windows `esbuild.exe` **[E]**; CHANGELOG:60–61 "expanded `.gitignore`…" **[X]** |
| Tests | ❌ | jest configured, `test: jest` script exists, **0 test files** **[E]**; CI never runs MCP tests **[S]** |
| npm audit (prod deps) | 🟡 | `npm audit --omit=dev`: **8** vulnerabilities (5 moderate, 3 high — incl. `zod` GHSA-mrhx-c7xc-m3w5); total 28 **[E]** |
| npm publication | ❌ | `npm view @wpforge/mcp-server` → **E404** **[E]** → AdminUI's `npx -y @wpforge/mcp-server` snippet cannot work **[S]** |
| Credential transport | 🟡 | password accepted via CLI arg (visible in process list) or env **[S]** — env path is fine for local stdio servers |

### 1.4 React dashboard — detail

| Area | Status | Evidence |
|------|--------|----------|
| Install / typecheck / lint / build | ✅ | `npm ci`, `npx tsc --noEmit`, `npx eslint .`, `npx vite build` all **exit 0** **[E]** (CI "Dashboard (UIUX) build" also success **[C]**) |
| Build output | ✅ | `dist/index-CMK_vOms.css` 17.20 kB, `dist/index-Cez4Fus8.js` 389.40 kB **[E]** |
| npm audit | ✅ prod / 🟡 dev | `--omit=dev`: **0** vulns; total 5 (dev tooling) **[E]** |
| Tests | ❌ | no test script, **0 test files** **[E]**; CI has no dashboard test job **[S]** |
| Demo-vs-real data | ✅ | simulated activity stream only while `live && !connected` (`App.tsx:62–64`); real API data when connected **[S]** — demo does not substitute for connected functionality |
| Shipped inside plugin | ❌ | `dist/` is not copied into `wordpress/wpforge/assets/assets/`; no copy step in `tools/build/package.php` **[S]** |

### 1.5 Cross-component integration

| Path | Status | Evidence |
|------|--------|----------|
| wp-admin *Connect* page → React bundle | ❌ | missing files + stale JS hash (§1.2) **[S]** **[E]** |
| wp-admin *Connect* page → `npx @wpforge/mcp-server` | ❌ | npm 404 **[E]** (local-path instructions in `mcp/README.md` do work after a build **[D]**) |
| MCP server → REST API | 🟡 | axios Basic-auth client, 30 s timeout, config stripped from errors **[S]**; `tsc` green **[E]**; one broken tool path; no e2e run **[D]** |
| Dashboard → REST API (cross-origin, Basic auth) | ⬜ | plausible (relies on WP core REST CORS); **no live WordPress available in Phase 1** **[D]** |
| Token minting UI → `/tokens` API | ⬜ | AdminUI handlers exist with nonces **[S]**; click-path untested live **[D]** |

---

## 2. Claim verification ledger (docs vs code)

Claims from README / API_REFERENCE / CHANGELOG / docs that this audit checked:

| # | Claim (source) | Verdict | Evidence |
|---|----------------|---------|----------|
| 1 | "65 route registrations across 49 paths" (README:83) | ✅ true | script count 65/49 **[E]** |
| 2 | "15 route files" (README:118) | ❌ false — 16 | `ls routes/` **[E]** |
| 3 | "78 PHP files" (README:116) | ❌ false — 79 | `find wordpress -name '*.php'` **[E]** |
| 4 | "Everything that can change the site is off by default … plugin installation and theme activation each require `developer_mode` plus their own flag" (README:13–16) | ❌ false for plugin/theme/delete paths | zero callers of the three flags **[S]** |
| 5 | "Rate limiting (100 requests / 60s by default)" (README:137, SECURITY.md:96) | ❌ not enforced | middleware never instantiated **[S]** |
| 6 | "Audit logging with credential redaction" + "Mutating requests are written to `{prefix}wpforge_logs` and to rotating files" (README:138, SECURITY.md:105–107) | ❌ misleading | only `plugin_init` ever logged; file Writer/Rotator dead; redaction helper exists but has nothing to redact **[S]** |
| 7 | "`enabled` = Master kill switch" (CONFIGURATION.md:30) | ❌ not implemented | key unread **[S]** |
| 8 | "From the admin screen: WPForge → Settings" (CONFIGURATION.md:36) | ❌ no such page | only Dashboard + Connect menus **[S]** |
| 9 | "Database backups with retention" (README:26) | ❌ retention not enforced | `backup_retention_days`/`max_backup_size_mb` unread; CleanupManager dead **[S]** |
| 10 | CHANGELOG: "expanded `.gitignore` to cover cookie jars, `.env`, keys…" (CHANGELOG:60–61) | ❌ | `.gitignore` = `(empty)` **[E]** |
| 11 | API_REFERENCE endpoint list | 🟡 47/49 paths, 5 method-set mismatches | diff script **[E]** |
| 12 | Response envelope format (API_REFERENCE:5–26) | ✅ | `Response.php` **[S]** |
| 13 | FINAL-AUDIT "CI all jobs green" + "unit 5/5, security 68/68" | 🟡 stale | CI was green on 2026-09-28; every later run red (MCP job) **[C]**; "unit 5/5" predates `SecurityGuardTest` (added 4.5 h after FINAL-AUDIT was committed — proven by commit timestamps) → current static counts: unit **25**, security **68** **[E]** **[S]** |
| 14 | "MCP server (TypeScript)" in layout; tool counts | 🟡 | 37 actual vs 32 documented **[E]** |
| 15 | `.freebuff/` cookie incident purged; "credentials should be rotated" (CHANGELOG:54–58, FINAL-AUDIT:42) | 🟡 remediation claimed; rotation unverifiable from repo | docs + current-tree gitleaks CI job green **[C]**; live rotation = out of band **[D]** |
| 16 | readme.txt "Tested up to: 6.7" | ⚠️ stale | current WP stable is **7.1.4** (2026-10-28) **[E: web]**; CI also tests only WP 6.7 **[C]** |
| 17 | Plugin header `Requires WP: 6.0` | ⚠️ non-standard key | WP parses `Requires at least` from the main file since 5.8 **[E: web]**; readme.txt has the correct key |
| 18 | No `languages/*.pot` despite `Domain Path: /languages` | ⚠️ missing | directory absent **[E]** |

---

## 3. Test & CI snapshot (details in `TEST_REPORT.md`)

- **Local executions (this phase):** UIUX `npm ci`/`tsc`/`eslint`/`vite build` ✅; MCP `tsc` (emit + noEmit) ✅; `npm audit` both packages; route/permission/dead-code/grep scans ✅.
- **CI on the audited tree (`3345b479`):** 10/11 jobs success incl. both PHPUnit jobs and PHPCS; **MCP build failure** ⇒ run conclusion `failure` **[C]** (log bodies unavailable).
- **Not executable here:** PHPUnit/PHPCS/gitleaks locally (no PHP/composer/MySQL in sandbox — install attempts failed), live install/activate/uninstall, official Plugin Check, MCP e2e, browser QA. These remain ⬜/**[D]** — never claimed as passed.

## 4. Repository facts

- Public repo `avangardistic/WPForge`, created 2026-09-04; 43 PRs total, **6 open** (all dependabot, all CI-red), 0 open issues **[E]**.
- Dependabot + 4 automated update workflows running; `@modelcontextprotocol/sdk` at `^1.0.0` (installed 1.32.x).
- Release artifact path exists (`tools/build/package.php`, 86 files, export-ignores `mcp/`, `tests/`, `docs/`) but was not built (no PHP) ⬜.

## 5. Overall recommendation

**Not production-ready as advertised; solid base to fix.** The auth/permission core and path/SQL guards are genuinely good and CI-proven on this tree. However, the three "disabled-by-default" flags, the kill switch, rate limiting, and audit logging — all prominent README/SECURITY claims — are **not enforced in code**, and two shipped integration paths (Connect page assets, published MCP package) are broken. See `ROADMAP.md` for the prioritized plan and `PHASE1_REPORT.md` for the Persian executive report. **Phase 2 must not start until this report is approved.**
