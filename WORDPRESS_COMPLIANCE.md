# WPForge — WORDPRESS COMPLIANCE (Phase 1)

**Audited commit:** `3345b479` · **Date:** 2026-11-16
**External baseline used:** WordPress.org *Detailed Plugin Guidelines* (fetched 2026-11-16, page last updated 2026-03-11), *Plugin Readmes* handbook, *Security* (Common APIs Handbook, updated 2026-07-23). Current stable WordPress: **7.1.4** (2026-10-28; WP 7.2 Beta → scheduled 2026-12-08) [E: web search].

**No approval claim:** nothing here states or implies wordpress.org acceptance; submission requires maintainer action + human review (approval gate).

## 1. Detailed Plugin Guidelines — rule-by-rule

| # | Rule | Status | Evidence / gap |
|---|------|--------|----------------|
| 1 | GPLv2-compatible licensing for all code/data/images | ✅ | `LICENSE` = GPL-2.0-or-later; `composer.json`, `mcp/package.json`, `UIUX/package.json`, plugin header, readme.txt all `GPL-2.0-or-later` [E]; bundled deps are npm/Composer packages with compatible/compatible-with-notices licenses (JS deps shipped only in dev tree, not in plugin zip — export-ignored) |
| 2 | Developer responsible for contents; verify 3rd-party licensing | 🟡 | Static-only audit; `mcp/node_modules` committed to **git** (121 MB, incl. prebuilt binaries) is a licensing/attack-surface liability even though `package.php` excludes it from the zip [E] |
| 3 | Stable version available on directory page | ⬜ | Not submitted; tag/`Stable tag: 1.0.0` consistent internally |
| 4 | Mostly human-readable code; source + build tools public | ✅ | Plain PHP/TS/TSX, no obfuscation/mangling; sources and build scripts (`tools/`, `UIUX/`, `mcp/`) public in repo; README documents layout |
| 5 | No trialware / feature-locking | ✅ | No paywalled/crippled functionality; all features present (flags are fail-closed config, not licensing) |
| 6 | SaaS permitted with clear documentation | ✅ n/a | No external service dependency; plugin is self-contained (only self-HTTP requests) |
| 7 | No tracking without consent; no external calls to unrelated servers | ✅ | Grep: no telemetry; outbound HTTP = `wp_remote_get(rest_url(...))` loopback probe + `download_url()` for user-requested media import only [S]; no offloaded assets (CSS/JS local) |
| 8 | No executable code via third-party systems; local JS/CSS (non-font) | ✅ | No CDN loads; no update channels outside wordpress.org (plugin has no updater); no iframed admin pages |
| 9 | Nothing illegal/dishonest/abusive | ✅ (with caveat) | Honest functionality; **but** README/SECURITY over-claims (rate limiting, audit logging, kill switch — see `PROJECT_STATUS.md` §2) must be corrected before submission to avoid “misleading” review outcomes |
| 10 | No external links/credits on the **public site** without permission | ✅ | Plugin adds nothing to the front end (admin-only menus; no widgets/shortcodes/blocks) |
| 11 | Don't hijack the admin dashboard | 🟡 | Two submenu pages only (`Dashboard`, `Connect to AI`); no site-wide nags, no dashboard widgets [S]. Residual: React *Connect* page currently 404s its assets (broken, not nagging) — fix before submission |
| 12 | No readme spam; ≤5 tags; no competitor tags | ✅ | readme.txt has 5 tags: `rest-api, api, remote-control, ai, elementor` — exactly ≤5, no competitors; no affiliate links; no keyword stuffing [E] |
| 13 | Use WordPress default libraries | ✅ | Uses WP core APIs (`wpdb`, `wp_remote_*`, `download_url`, `wp_kses_post`, `wp_check_password`, `register_rest_route`); no bundled copies of jQuery/PHPass/etc. [S]; PHP-side uses PHPass via WP core functions |
| 14 | Avoid frequent SVN commits | ⬜ n/a | Not submitted; process note for release (squash, meaningful messages) |
| 15 | Increment version per release | ⚠️ pending | Still `1.0.0` while tree contains post-1.0.0 fixes (FINAL-AUDIT recommends 1.0.1) [S] — bump required at release |
| 16 | Complete plugin at submission | ⚠️ | Zip build path exists (`tools/build/package.php` → 86 files, no node_modules/tests/.git) but **not executed in Phase 1** (no PHP); completeness of a built artifact unverified ⬜ |
| 17 | Respect trademarks / slug policy | ✅ | Slug `wpforge` contains no third-party term; Plugin URI/Author URI point to own GitHub |
| 18 | Directory-maintenance right (informational) | — | — |

Additional standing requirement (guidelines preamble): *“all code must be as secure as possible”* (security team enforcement) → Phase 1 security findings in `SECURITY_AUDIT.md` are submission blockers in spirit: SEC-01…SEC-05 (advertised controls that don't exist) would surface in review.

## 2. readme.txt + headers (Plugin Readmes handbook)

| Field | Value found | Requirement | Verdict |
|-------|-------------|-------------|---------|
| File name | `wordpress/wpforge/readme.txt` | lowercase `readme.txt` | ✅ |
| Contributors | `avangardistic` | valid .org usernames | ⬜ must be a real .org account |
| Tags | 5 | 1–5, no competitors | ✅ |
| Requires at least | 6.0 | numbers only | ✅ (readme) — but see header below |
| Tested up to | **6.7** | current major (7.1) | ❌ **stale by two majors** — update to 7.1 after live testing; CI only tests WP 6.7 [C] |
| Requires PHP | 8.1 | numbers only | ✅ |
| Stable tag | 1.0.0 | matches plugin version | ✅ internally; bump with release (rule 15) |
| License / URI | GPLv2 or later + gnu.org | required | ✅ |
| Short description | present, <150 chars | no markup | ✅ |
| Sections | Description/Installation/FAQ/Changelog present | freeform OK | ✅ (changelog diverges from `CHANGELOG.md` root file — keep in sync at release) |
| Parse note | Since WP 5.8 requirements parse from the **main plugin file** | see next row | — |

**Plugin main-file headers** (`wpforge.php`):

| Header | Found | Standard | Verdict |
|--------|-------|----------|---------|
| Plugin Name / URI / Description / Version / Author / Author URI | present | required | ✅ |
| License / License URI | GPLv2 or later | recommended | ✅ |
| Text Domain `wpforge` / Domain Path `/languages` | present | must match file names | 🟡 **`languages/` directory does not exist** — no `.pot`; strings are wrapped (`__()`/`esc_html__()` with `wpforge`) but no catalog ships [E] |
| `Requires PHP: 8.1` | present | parsed since 5.8 | ✅ |
| **`Requires WP: 6.0`** | found | key must be **`Requires at least`** | ❌ **non-standard key, ignored by WP** — change to `Requires at least: 6.0` (readme.txt already has the right field) [E: handbook] |
| `@package WPForge` / `@license` docblocks | present | good practice | ✅ |

## 3. Security-practice alignment (developer.wordpress.org/apis/security)

| Guiding principle | Conformance |
|-------------------|-------------|
| Never trust user input; validate/sanitize; escape late | 🟡 strong on paths/SQL/ksearch (`SecurityGuard`, `wpdb->prepare`, `wp_kses_post`, regex identifiers, `sanitize_key`) [S]; gaps: per-object caps (SEC-07), schema files unused (request validation is hand-rolled per route), some doc-only config keys |
| Rely on WP APIs | ✅ (`register_rest_route`, `rest_authentication_errors`, Application Passwords, `download_url`, `wp_remote_get`, `wpdb`) |
| Keep code up to date | 🟡 dependencies: MCP prod audit 8 vulns / 3 high [E]; dependabot PRs blocked by red CI (SEC-08) |

## 4. Submission-gate checklist (for Phase 3 — requires approval)

1. Fix Phase 2 P1/P2 findings (SECURITY_AUDIT §E), especially fail-closed flags, rate limit, audit logging, kill switch — rule 9/“as secure as possible”.
2. Correct README/SECURITY/CONFIGURATION over-claims and key names (no misleading statements).
3. `Requires at least:` header fix; `Tested up to: 7.1` **after** testing on WP 7.1 (CI matrix must move beyond 6.7).
4. Version bump 1.0.0 → 1.0.1 (rule 15) + changelog sync.
5. Generate `languages/wpforge.pot` (Domain Path currently dangles).
6. Run official **Plugin Check** on a live install + full install/activate/uninstall walkthrough — **not yet done**.
7. Build `wpforge-1.0.1.zip` via `tools/build/package.php`, inspect contents (86-file scope, no node_modules).
8. Choose SVN release process (rule 14: squash, meaningful messages) and confirm `.org` account for Contributors.
9. Publishing/submission only with explicit maintainer approval — **no submission has been made or is being claimed**.

## 5. Compliance summary

- **Solid:** licensing, no-tracking/no-CDN, no front-end footprint, readme tag hygiene, WP-API usage, human-readable code, trademark-safe slug.
- **Must-fix before any submission:** stale `Tested up to`, wrong `Requires WP` header key, missing `.pot`, version bump, broken Connect assets, unpublished npx package advertised in wp-admin, and — most importantly — the security-control over-claims from `SECURITY_AUDIT.md`.
- **Not claimed:** official Plugin Check results, live testing on WP 7.1, or wordpress.org approval — all pending Phase 3 with approval.
