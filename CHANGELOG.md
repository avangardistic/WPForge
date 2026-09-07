# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security
- **Read endpoints no longer authorise on login alone.** Every REST route
  registered a `permission_callback` of `is_user_logged_in`, so any
  authenticated user of any role — a Subscriber — could read files under the
  sandbox root, run SELECT queries, list users, and read site internals; only
  mutating handlers checked a capability. A new `WPForge\API\Permissions`
  factory now gates each route at the permission layer with an appropriate
  capability (`manage_options` for files/database/site/logs/backups,
  `activate_plugins`, `switch_themes`, `list_users`, `upload_files`,
  `edit_posts`/`edit_pages`, etc.), returning a real 401 (unauthenticated) or
  403 (under-privileged) before the handler runs. Per-object handler checks are
  retained as defence in depth. The `/` discovery endpoint, previously public,
  now requires authentication; `/diagnostics/quick` remains the only
  intentionally public route.
- **Filesystem guards hardened further.** Both `Filesystem\SecurityGuard` (used
  by the routes) and `Security\PathValidator` now reject null bytes and other
  control characters, Windows drive letters and alternate-data-stream names
  (any `:`), and UNC / authority prefixes (`\server\share`, `//host`), and
  the containment check is separator-aware so a sibling directory sharing the
  root's name prefix can no longer masquerade as being inside it. Symlink
  escapes were already caught by `realpath()` containment. Every vector is
  covered by `PathTraversalTest`.
- **`wp-config.php` was readable through `GET /files/read`.** The deny rules
  (`/\/wp-config\.php$/`, `/\/\.htaccess$/`, `/^\/etc\//` and the rest) are
  written to anchor on a separator, but `normalizePath()` strips the leading
  slash before they run, so a request for `wp-config.php` normalised to
  `wp-config.php` with no `/` in front and matched nothing — every one of those
  rules was dead. `Filesystem\SecurityGuard`, which is what the `/files/*`
  routes actually use, did not carry the `wp-config.php` rule at all. Since the
  sandbox root defaults to `ABSPATH` and the read endpoints gate only on
  `is_user_logged_in()`, any authenticated user of any role could read the
  database credentials and authentication salts. Patterns are now matched
  against a leading-slash form so they fire as written, `SecurityGuard` carries
  the same protected-file list as `PathValidator`, and both also refuse
  `wp-config-sample.php` and `.env`.
- Path traversal is now **refused rather than silently clamped**. A `..` that
  climbed above the root used to be absorbed, so `../../etc/passwd` resolved to
  `<root>/etc/passwd` and the caller was handed a different file with no error.
  Containment held, but nothing was reported. `normalizePath()` now throws.
  This is what the repository's own `PathTraversalTest` already asserted; those
  two tests had never been run.
- `GET /status` and `GET /health` are no longer reachable without authentication.
  Their `permission_callback` returned the *string* `'is_user_logged_in'` rather
  than calling it; WordPress treats a non-empty string as truthy, so both
  endpoints granted access to everyone regardless of the
  `public_status_enabled` / `public_health_enabled` settings, disclosing the
  WordPress version, PHP version and site URL. Both now call
  `is_user_logged_in()` and honour their config flag.
- Purged a committed scratch directory that contained live WordPress session
  cookies and captured wp-admin pages from a production site. History was
  rewritten; the affected site's credentials should be rotated.
- Added a gitleaks secret scan to CI, and expanded `.gitignore` to cover
  cookie jars, `.env` files, keys and agent scratch directories.

### Fixed
- `Backup\FilesystemBackup` no longer shells out to `tar` via `exec()`, nor
  probes for it with `which`. It archives with PHP's bundled `ZipArchive`
  instead, falling back to a JSON manifest when the zip extension is absent.
  The old path was guarded by `escapeshellarg()` and never received
  user-supplied paths, so it was not injectable, but `exec()`/`which` break on
  the many hosts that disable shell functions, fail on Windows, and are flagged
  by WordPress.org's plugin review. The plugin now contains no
  process-execution calls at all.
- `phpcs.xml` referenced a ruleset named `WordPress-Phpcs`, which does not
  exist, and the WordPress Coding Standards package was never a dependency, so
  `composer lint` failed with "Referenced sniff does not exist" and had never
  run. The ruleset also combined `WordPress` with `PSR12`, which contradict each
  other line for line — tabs against spaces, snake_case against camelCase. It is
  now PSR-12 (matching the code) plus the WordPress sniffs that catch real
  defects, with WPCS and PHPCompatibility added as dev dependencies. 233
  violations were auto-fixed and the rest resolved; the tree is clean.
- `Logging\Logger` and `WordPress\SiteInspector` passed `$_SERVER` values to
  `sanitize_text_field()` without `wp_unslash()`, leaving escaped slashes in the
  audit log and in API output. `Diagnostics\SystemChecker`, `routes/system.php`,
  `API\Middleware\RateLimit` and `Auth\Authenticator` read `$_SERVER` with no
  unslashing or sanitising at all. All are now unslashed and sanitised, except
  the two credential-carrying headers, which are annotated explaining why
  sanitising them would corrupt the value being verified.
- Seven source files failed to parse on PHP 8.1, the version the plugin declares
  as its minimum in five places. They used `true` as a standalone type in a union
  return type (`true|WP_Error`), which is a PHP **8.2** feature and a fatal parse
  error on 8.1 — the plugin could not load at all on a conforming host. Widened
  those return types to `bool|WP_Error`. All 86 PHP files now parse on 8.1
  through 8.4.
- `tools/validate/validate-plugin.php` was truncated mid-`foreach`, so
  `composer validate` died with a parse error and had never run. Completed it:
  it now checks for `TODO`/`FIXME` markers, warns on debug-output calls, verifies
  the version agrees across the plugin header, `WPFORGE_VERSION` and
  `README.txt`, and exits non-zero on failure.
- `tools/build/package.php` produced an unusable archive on Windows: it stripped
  the source prefix with a hard-coded `/`, so every zip entry was named
  `wpforge/<absolute path>` instead of `wpforge/<relative path>`, and WordPress
  could not install the result. Separators are now normalised, and the version
  is read from the plugin header rather than hard-coded, so the archive name can
  no longer drift from the plugin it contains.
- `config/defaults.php` was never loaded. `Core\Config` kept a second,
  divergent set of defaults inline, so the file that looked like the
  configuration reference described keys the plugin does not read.
  `Config` now loads `config/defaults.php`, with the inline array retained as a
  fallback for partial installs.
- `composer.json` required `phpunit/phpunit ^10` while `phpunit.xml` was written
  for PHPUnit 9, so `composer test` failed to start. Resolved in favour of
  **PHPUnit 9.6**, not 10: every suite extends `WP_UnitTestCase`, and the
  WordPress test library still targets PHPUnit 9 — on 10 it fatals on
  `PHPUnit\Util\Test::parseTestMethodAnnotations()` and on the
  `PHPUnit\Framework\Error\*` classes that release removed.
- The `integration` test suite pointed at a directory that did not exist.
- Licensing was contradictory: `LICENSE` and the Composer/npm manifests said MIT
  while the plugin headers and `README.txt` declared GPLv2-or-later. Unified on
  **GPL-2.0-or-later**, which is what WordPress plugin distribution requires.
- `.gitignore` was wrapped in Markdown code fences.

### Added
- `tools/ci/install-wp-tests.sh`, which provisions WordPress and its PHPUnit
  test library. Every suite extends `WP_UnitTestCase`, so the tests were
  unrunnable without one and CI had nothing to run them against.
- GitHub Actions CI: PHPCS, `php -l` across PHP 8.1-8.4, PHPUnit unit and
  security suites, plugin validation, MCP TypeScript build, and a secret scan.
- Issue and pull request templates, and a Dependabot configuration.
- `.editorconfig` and `.gitattributes` (the latter keeps release archives to the
  plugin itself via `export-ignore`).
- Eight documentation pages that the READMEs linked to but that did not exist:
  Installation, Architecture, Security, Configuration, Filesystem, Database,
  Backup, Troubleshooting and Removal. `docs/ELEMENTOR.md` had been truncated to
  its first six characters and is now written.
- Explicit `public_status_enabled` and `public_health_enabled` keys in the
  default configuration.

### Changed
- `README.md` corrected: it advertised `/cache/flush` and `/cache/status`
  endpoints that are not registered, claimed 58 PHP files (there are 78) and
  "40+" endpoints (there are 65 registrations across 49 paths), and linked to
  eight documentation pages that returned 404.
- Removed client-specific material that did not belong in a public product
  repository: a deployment report and a site redesign roadmap under `docs/`, and
  `skill.md`, which documented work on one particular site and exposed the
  author's local filesystem paths. The dashboard's connection dialog no longer
  pre-fills that site's hostname.
- Pinned `autoprefixer` and `postcss` in `UIUX/package.json`, which were floating
  on `latest`.

### WordPress.org readiness
- `README.txt` renamed to `readme.txt` (lowercase), which is the filename the
  plugin directory's readme parser expects.
- `Tested up to` corrected from `7.1` — not a released WordPress version, it was
  carried over from a test environment — to `6.7`, and the same claim fixed in
  the READMEs and docs.
- Trimmed the readme tags to the five most relevant.
- Added a dashboard (UIUX) build job to CI: `tsc --noEmit`, ESLint, and a
  production `vite build` now run on every push and pull request.

### UI/UX (dashboard)
- Added semantic colour tokens (`bad`, `warn`, `info`) to the Tailwind config and
  replaced ~25 raw `red-400`/`red-500` utilities with the `bad` token, so the
  dashboard no longer hard-codes arbitrary colours outside the design system.
- The connection dialog now traps focus, moves focus to the first field on open,
  restores focus to the trigger on close, and is labelled by its heading
  (`aria-labelledby`) — a keyboard and screen-reader user can no longer tab out
  of the open modal into the page behind it.
- Network and HTTP failures are translated into human-readable guidance
  ("Could not reach the site…", "Authentication failed…", "The WPForge API was
  not found…") instead of surfacing a raw `Failed to fetch` or bare status code.
- Panels show shimmer skeleton rows while data loads, in place of a bare
  "Loading…" line; the shimmer respects `prefers-reduced-motion`.
- Added a horizontally scrollable capability navigator for viewports below `lg`,
  where the sidebar is hidden — every area is now reachable on a phone, not just
  the audit log.
- Fixed the document `<title>` ("Repository Check Tool" → "WPForge Site Control"),
  replaced the missing `/vite.svg` favicon (a 404) with an inline branded icon,
  and added `description`, `theme-color` and `color-scheme` meta tags.
- Removed the unused `@emotion/react` dependency, and stopped pre-filling a
  client-specific username in the connection form.

## [1.0.0] - 2024-01-15

### Added
- Complete REST API with 40+ endpoints
- Site inspection and diagnostics
- Content management (posts, pages, custom post types)
- Media management (list, get, upload, delete)
- Taxonomy management (CRUD for terms)
- User management
- Navigation menu listing
- Theme management (list, get, activate)
- Plugin management (list, activate, deactivate)
- Elementor integration (documents, templates, content parsing)
- Filesystem access with path traversal protection
- Database inspection (tables, schema, read-only queries)
- Backup creation (database SQL export)
- Cache management (flush, status)
- Comprehensive audit logging
- Application Password authentication
- API Token authentication with create/revoke/list
- Capability-based authorization
- Rate limiting middleware
- MCP server adapter (TypeScript)
- PHPUnit tests (unit, security)
- Full documentation
- Build/packaging tools
- Validation tools
- Example clients (cURL, JavaScript, Python)

### Security
- Path traversal prevention on all filesystem operations
- SQL injection prevention via prepared statements
- Input sanitization on all endpoints
- Sensitive data redaction in logs
- Read-only database mode by default
- Configurable filesystem write/delete permissions

[Unreleased]: https://github.com/avangardistic/WPForge/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/avangardistic/WPForge/releases/tag/v1.0.0
