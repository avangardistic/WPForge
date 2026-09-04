# WPForge Deployment Report — neginhafari.ir

**Date:** September 4, 2026
**Status:** ✅ DEPLOYED AND VERIFIED — 32/32 API tests passing against the live site

---

## 1. What Was Done

### Discovery & Access
- Confirmed `neginhafari.ir` is a live WordPress 7.1 site (PHP 8.5.9, LiteSpeed, Elementor + Elementor Pro).
- WPForge was **not** previously installed (no `wpforge/v1` REST namespace).
- SSH (22/2222), FTP (21) are firewalled from this network; cPanel port 2083 is reachable but not needed.
- Received WP admin credentials from the user; wrote **all credentials to `.env`** (see §4).
- Logged into wp-admin programmatically (cookie + nonce auth) and uploaded the plugin through the standard WordPress plugin installer.

### Deployment Flow
1. Built `build/wpforge-1.0.0.zip` (81–82 files, correct `wpforge/` layout).
2. Uploaded via `update.php?action=upload-plugin`.
3. Since the plugin folder already existed on later updates, used WP's "Replace current with uploaded" overwrite flow (staged package + confirm).
4. Activated the plugin. Verified REST endpoint: `GET /wp-json/wpforge/v1/status` → HTTP 200.

### Live API Test Suite
Ran a 32-test suite against the live site covering:
system, site, content (posts/pages), media, taxonomies, users, menus, themes, plugins, Elementor, filesystem (read-only), database (status/tables/SELECT), diagnostics, logs, backup listing, auth rejection, and a create-then-delete write cycle.

**Result: 32/32 PASS.**

---

## 2. Faults Found & Fixed

| # | Fault | Location | Root Cause | Fix |
|---|-------|----------|------------|-----|
| 1 | **PHP fatal on activation** — "unexpected identifier `src`" | `wpforge.php` | Namespace prefix written as `'WPForge\';` — single backslash escaped the closing quote in the string literal, swallowing the rest of the line | Fixed to `'WPForge\\'` (properly escaped) in the local file and patched on the server via the plugin editor |
| 2 | **Parse error — unclosed `{`** | `routes/database.php` | File was truncated to 30 lines during initial heredoc-based writing | Rewrote the full file with `write_file` |
| 3 | **Parse error — unexpected string content** | `routes/themes.php` | Same heredoc truncation | Rewrote full file |
| 4 | **Parse error — unclosed `(`** | `routes/users.php` | Same heredoc truncation | Rewrote full file |
| 5 | **`Undefined property: WP_Post_Type::$supports`** (warning) | `src/WordPress/SiteInspector.php` | `$type->supports` is not a property in modern WP; `supports` is exposed via `get_all_post_type_supports()` | Replaced with `get_all_post_type_supports($name)` |
| 6 | **Fatal — `Call to undefined method WP_Theme::get_screenshot_url()`** | `src/WordPress/ThemeManager.php` (2 places) | Method doesn't exist on `WP_Theme` | Replaced with `$theme->get_screenshot()` |
| 7 | **Warning — undefined `$activePlugins`** | `src/Diagnostics/ComponentDetector.php` | Loop used `$activePlugins` but variable is `$active` | Changed loop to iterate `$active` |
| 8 | **Fatal — `Class "WPForge\Api\Response" not found`** | `routes/system.php`, `routes/database.php`, `routes/tokens.php` | `use WPForge\Api\...` (lowercase `Api`) vs actual directory `src/API/` — PHP class names are case-insensitive, but the autoloader maps them to file paths, which fails on Linux's case-sensitive filesystem | Changed to `WPForge\API\...` (uppercase) |
| 9 | **Fatal — undefined `$config` in closure** | `routes/database.php` `/database/status` | Closure captured only `$dbInspector`, not `$config` | Added `$config` to the `use (...)` list |

### Process faults on my side (honest accounting)
- **No local PHP binary** — the early "php -l passes" were false positives (php wasn't installed, every check silently returned success). Parse errors were only caught after deployment. Fixed by validating against the *live server* output instead.
- **Heredoc truncation** — several route files were written via bash heredocs that died mid-write, producing silently truncated files. Caught later via a structural-balance scanner; rewrote the three affected files.
- **Windows path mismatch** — Python (native Windows) and Git Bash resolve `/tmp/...` differently, causing cookie jar and script failures. Fixed by using workspace-relative paths (`.freebuff/tmp/...`).
- **Windows console encoding** — Python subprocess output decoding crashed on UTF-8 (cp1252 default). Fixed with `encoding='utf-8', errors='replace'`.
- **Test expectation error** — my "POST /posts should be blocked" test was wrong: the `wpforge` user is admin, so post creation legitimately succeeds. Corrected the test to assert successful create + cleanup, and deleted the two draft posts (IDs 285, 287) the bad test had created.

---

## 3. Current State on the Server

- **Plugin:** WPForge 1.0.0 — active
- **REST namespace:** `https://neginhafari.ir/wp-json/wpforge/v1/`
- **Public endpoints:** `/status`, `/health`
- **Authenticated endpoints:** all others — require WP admin session (cookie + nonce) or (per plugin docs) Application Password / API token
- **Verification:** `GET /wp-json/wpforge/v1/status` → `{"success":true,"version":"1.0.0","wordpress_version":"7.1","php_version":"8.5.9"}`

---

## 4. Credentials — `.env`

All credentials are stored in **`.env`** at the repository root (git-ignored, never committed):

| Variable | Value |
|----------|-------|
| `WPFORGE_SITE_URL` | `https://neginhafari.ir` |
| `WP_ADMIN_USER` | `wpforge` |
| `WP_ADMIN_PASSWORD` | (see `.env`) |
| `FTP_HOST` / `FTP_USER` / `FTP_PASSWORD` | cloudylink FTP details (see `.env`) |
| `WPFORGE_BASE_URL` / `WPFORGE_USERNAME` / `WPFORGE_PASSWORD` | MCP server credentials |
| `SERVER_IP` | `194.5.175.247` (reference) |

⚠️ **Security note:** `.env` contains live production credentials. It is covered by `.gitignore`, but you should still rotate these passwords after this deployment session and treat the file as sensitive.

---

## 5. What Remains (Optional Follow-ups)

- Create a WP **Application Password** for the `wpforge` user so Bearer/App-Password auth works for the MCP server (the MCP client currently needs cookie auth or a token).
- Enable developer mode (`wpforge_developer_mode`) to test write endpoints (filesystem writes, DB writes).
- Run the local PHPUnit suite (requires PHP locally — not available on this machine).