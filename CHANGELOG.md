# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security
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
- `phpunit.xml` used PHPUnit 9 attributes (`convertDeprecationsToExceptions`,
  `convertWarningsToExceptions`, `convertErrorsToExceptions`, `verbose`) that
  PHPUnit 10 removed, so `composer test` failed to start against the required
  `phpunit/phpunit ^10`. Migrated to the PHPUnit 10 schema.
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
