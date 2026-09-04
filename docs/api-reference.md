# WPForge API Reference

## Base URL

```
https://example.com/wp-json/wpforge/v1/
```

## Authentication

```bash
curl -u "username:application_password" \
  https://example.com/wp-json/wpforge/v1/status
```

## System Endpoints

### GET /status
API status information.

### GET /health
Health check endpoint.

### GET /capabilities
Current user's capabilities.

### GET /environment
Environment information.

### GET /
API manifest with available routes.

## Site Inspection

### GET /site
Complete site overview.

### GET /site/structure
Post types, taxonomies, menus, widgets.

### GET /site/routes
Available REST routes.

## Content Management

### GET /posts
List posts.

### GET /posts/{id}
Get single post.

### POST /posts
Create post.

### PUT/PATCH /posts/{id}
Update post.

### DELETE /posts/{id}
Delete post.

### GET /pages
List pages.

### POST /pages
Create page.

### PUT/PATCH /pages/{id}
Update page.

### DELETE /pages/{id}
Delete page.

## Media

### GET /media
List media items.

### POST /media
Upload media (multipart/form-data).

### DELETE /media/{id}
Delete media.

## Elementor

### GET /elementor/status
Elementor installation status.

### GET /elementor/documents
List Elementor documents.

### GET /elementor/document/{id}
Get specific document.

### PUT/PATCH /elementor/document/{id}
Update document content.

### POST /elementor/page
Create Elementor page.

### GET /elementor/templates
List library templates.

## Filesystem

### GET /filesystem/read?path=...
Read file content.

### POST /filesystem/write
Write file content.

### DELETE /filesystem/delete
Delete file.

### GET /filesystem/list?path=...
List directory.

### POST /filesystem/create-dir
Create directory.

## Database

### GET /database/status
Database connection status.

### GET /database/tables
List tables.

### GET /database/table/{name}
Describe table.

### POST /database/query
Run SQL query (read-only by default).

## Plugins

### GET /plugins
List all plugins.

### GET /plugins/{slug}
Get plugin details.

### POST /plugins/{slug}/activate
Activate plugin.

### POST /plugins/{slug}/deactivate
Deactivate plugin.

### POST /plugins/install
Install new plugin.

## Themes

### GET /themes
List installed themes.

### GET /themes/active
Get active theme.

### POST /themes/{stylesheet}/activate
Activate theme.

## Backup

### POST /backup
Create backup.

### GET /backups
List backups.

### GET /backups/{id}
Get backup details.

### DELETE /backups/{id}
Delete backup.

## Diagnostics

### GET /diagnostics
Comprehensive site diagnostics.

## Logs

### GET /logs
View audit logs.

### GET /logs/{id}
Get specific log entry.

### DELETE /logs/clear
Clear all logs (requires confirmation).

## Response Format

### Success
```json
{
  "success": true,
  "request_id": "20240101120000-abc123",
  "data": {...}
}
```

### Error
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable message",
    "request_id": "20240101120000-abc123"
  }
}
```

## Query Parameters

| Parameter | Description |
|-----------|-------------|
| `per_page` | Items per page (max 100) |
| `page` | Page number |
| `dry_run` | Preview without executing |
| `confirm` | Confirm destructive action |
