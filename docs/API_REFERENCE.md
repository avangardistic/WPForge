# API Reference

Base URL: `https://your-site.com/wp-json/wpforge/v1/`

## Response Format

### Success
```json
{
    "success": true,
    "request_id": "wf_abc123",
    "data": { ... }
}
```

### Error
```json
{
    "success": false,
    "request_id": "wf_abc123",
    "error": {
        "code": "ERROR_CODE",
        "message": "Human readable message"
    }
}
```

## Endpoints

### System
- `GET /status` — API status
- `GET /capabilities` — User capabilities
- `GET /environment` — Server environment
- `GET /health` — Health check
- `GET /` — API manifest

### Site
- `GET /site` — Full site inspection
- `GET /site/structure` — Content counts
- `GET /site/routes` — All registered routes

### Content
- `GET /posts` — List posts
- `GET /posts/{id}` — Get post
- `POST /posts` — Create post
- `PUT /posts/{id}` — Update post
- `DELETE /posts/{id}` — Delete post
- `GET /pages` — List pages
- `GET /pages/{id}` — Get page
- `POST /pages` — Create page

### Media
- `GET /media` — List media
- `GET /media/{id}` — Get media item
- `POST /media/upload` — Upload from URL
- `DELETE /media/{id}` — Delete media

### Taxonomies
- `GET /taxonomies/{taxonomy}/terms` — List terms
- `POST /taxonomies/{taxonomy}/terms` — Create term
- `PUT /taxonomies/{taxonomy}/terms/{id}` — Update term
- `DELETE /taxonomies/{taxonomy}/terms/{id}` — Delete term

### Users
- `GET /users` — List users
- `GET /users/{id}` — Get user
- `POST /users` — Create user
- `PUT /users/{id}` — Update user

### Menus
- `GET /menus` — List navigation menus
- `GET /menus/{id}` — Get one menu with its items
- `GET /menus/locations` — Registered theme menu locations

### Themes
- `GET /themes` — List installed themes
- `GET /themes/{stylesheet}` — Get one theme
- `POST /themes/activate` — Activate a theme (`switch_themes`)

### Plugins
- `GET /plugins` — List installed plugins
- `POST /plugins/activate` — Activate a plugin (`activate_plugins`)
- `POST /plugins/deactivate` — Deactivate a plugin (`activate_plugins`)

### Elementor
- `GET /elementor/status` — Elementor capabilities
- `GET /elementor/documents` — List documents
- `GET /elementor/documents/{id}` — Get document
- `PUT /elementor/documents/{id}` — Update document
- `GET /elementor/templates` — List templates

### Filesystem
- `GET /files/list` — List directory
- `GET /files/read` — Read file
- `POST /files/write` — Write file
- `DELETE /files/delete` — Delete file

### Database
- `GET /database/status` — DB status
- `GET /database/tables` — List tables
- `GET /database/tables/{name}` — Describe table
- `POST /database/query` — Run query

### Backup
- `POST /backup` — Create backup
- `GET /backup` — List backups
- `GET /backup/{id}` — Get backup info
- `DELETE /backup/{id}` — Delete backup

### Tokens
- `GET /tokens` — List the current user's API tokens
- `POST /tokens` — Create a token (returns the plaintext once)
- `DELETE /tokens/{id}` — Revoke a token

### Diagnostics
- `GET /diagnostics` — Full diagnostic report
- `GET /diagnostics/quick` — Quick health check

### Logs
- `GET /logs` — List audit logs
- `DELETE /logs/clear` — Clear logs
