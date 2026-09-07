# Architecture

## Layout

```
WPForge/
├── wordpress/wpforge/      WordPress plugin (78 PHP files)
│   ├── wpforge.php         Bootstrap: constants, autoloader, activation hooks
│   ├── uninstall.php       Full data removal on delete
│   ├── config/defaults.php Default configuration array
│   ├── routes/             15 files, 65 register_rest_route() calls
│   ├── schemas/            JSON Schema for request/response shapes
│   └── src/                PSR-4 under the WPForge\ namespace
├── mcp/                    MCP server (TypeScript) wrapping the REST API
├── UIUX/                   React + Vite dashboard that talks to the API
├── tests/                  PHPUnit: unit, integration, security suites
├── examples/               cURL, JavaScript, and Python clients
└── tools/                  build/package.php, validate/validate-plugin.php
```

## Request lifecycle

```
HTTP request
  └─ WordPress REST dispatcher
       └─ permission_callback            is_user_logged_in / capability check
            └─ Auth\Authenticator        Application Password (Basic) → Bearer token
                 └─ Middleware\RateLimit  100 requests / 60s per identity (default)
                      └─ route closure
                           └─ src/ service class (PostManager, Inspector, Manager…)
                                └─ API\Response::success|paginated|error
                                     └─ Middleware\Logging   audit row + file log
```

`Core\RequestID` mints one id per request; it is echoed in every response body and in
the log line, so a client-side failure can be traced to a server-side log entry.

## Namespaces

| Namespace | Responsibility |
|-----------|----------------|
| `Core`      | `Plugin` bootstrap, `Container`, `Config`, `RequestID` |
| `API`       | `Router`, `Response`, `Controller`, and the four middlewares |
| `Auth`      | `Authenticator`, `TokenManager`, `ApplicationPassword`, `CapabilityChecker` |
| `Security`  | `PathValidator`, `Sanitizer`, `Validator`, `InputFilter`, `NonceManager` |
| `WordPress` | Thin managers over core APIs: posts, media, users, menus, taxonomies, themes, plugins, cache |
| `Elementor` | `Adapter`, `DocumentManager`, `TemplateManager`, `ContentParser`, `Validator`, `BackupManager` |
| `Filesystem`| `Manager` facade over `Reader`, `Writer`, `Scanner`, `SecurityGuard` |
| `Database`  | `Inspector`, `QueryBuilder`, `ReadOnlyExecutor`, `WriteExecutor` |
| `Backup`    | `Manager` over `DatabaseBackup`, `FilesystemBackup`, `RestoreManager`, `CleanupManager` |
| `Diagnostics` | `SystemChecker`, `PermissionsChecker`, `ComponentDetector`, `HealthReporter` |
| `Logging`   | `Manager`, `Writer`, `Rotator`, `Filter` (redaction) |
| `Admin`     | `AdminUI` — settings screen and token management |

## Autoloading

`wpforge.php` registers an `spl_autoload_register` callback mapping `WPForge\` to
`src/`. The plugin ships with **no Composer runtime dependencies**, so the `vendor/`
directory is not required on the server — `composer.json` exists for development
tooling (PHPUnit, PHPCS) and to declare PSR-4 for local work.

## Response envelope

Every endpoint returns the same shape, built by `API\Response`:

```json
{ "success": true,  "request_id": "…", "data": { … } }
{ "success": true,  "request_id": "…", "data": [ … ], "pagination": { "page": 1, "per_page": 50, "total": 120, "total_pages": 3 } }
{ "success": false, "request_id": "…", "error": { "code": "READ_FAILED", "message": "…" } }
```

Machine-readable schemas live in `wordpress/wpforge/schemas/`.

## Configuration precedence

`config/defaults.php` is the base; the `wpforge_config` option is merged over it.
`Core\Config` exposes typed accessors (`allowFilesystemWrites()`,
`isPublicStatusEnabled()`, …) rather than raw array access, so a missing key falls
back to the safe default. See [Configuration](CONFIGURATION.md).

## Extending

Add a new endpoint group by dropping a file into `routes/` and a service class into
`src/`. Route files are plain PHP executed in the plugin's `rest_api_init` context
with `$ns = WPFORGE_NAMESPACE` already in scope. Keep the permission callback and
the capability check separate: the callback gates *authentication*, the in-callback
`current_user_can()` gates *authorization*.
