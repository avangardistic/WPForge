# Changelog

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
