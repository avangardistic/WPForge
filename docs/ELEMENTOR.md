# Elementor Integration

WPForge reads and writes Elementor's page data directly, so an agent can inspect
and modify built pages without driving the editor UI.

Every endpoint returns `503 ELEMENTOR_NOT_AVAILABLE` when Elementor is not
installed or not active. Check first:

```bash
curl -u "$USER:$APP_PASS" https://example.com/wp-json/wpforge/v1/elementor/status
```

`Elementor\Adapter::getCapabilities()` reports the detected version and which
features are usable, so a client can degrade gracefully rather than guess.

## Endpoints

| Endpoint | Method | Requires |
|----------|--------|----------|
| `/elementor/status` | GET | authenticated user |
| `/elementor/documents` | GET | authenticated user |
| `/elementor/documents/{id}` | GET | authenticated user |
| `/elementor/documents/{id}` | PUT, PATCH | `edit_pages` |
| `/elementor/templates` | GET | authenticated user |
| `/elementor/templates/{id}` | GET | authenticated user |

## Listing documents

```bash
curl -u "$USER:$APP_PASS" \
  "https://example.com/wp-json/wpforge/v1/elementor/documents?per_page=20&page=1&post_type=page&search=contact"
```

Parameters: `per_page` (default 50), `page`, `post_type` (default `any`),
`search`. The response is the paginated envelope:

```json
{ "success": true, "data": [ … ], "pagination": { "page": 1, "per_page": 20, "total": 34, "total_pages": 2 } }
```

## Reading one document

```bash
curl -u "$USER:$APP_PASS" \
  https://example.com/wp-json/wpforge/v1/elementor/documents/142
```

Returns the parsed `_elementor_data` element tree — sections, columns, and widgets
with their settings — via `Elementor\ContentParser`, rather than the raw JSON blob
stored in postmeta.

## Updating a document

```bash
curl -X PUT -u "$USER:$APP_PASS" \
  -H "Content-Type: application/json" \
  -d '{"elementor_data": [ ... ]}' \
  https://example.com/wp-json/wpforge/v1/elementor/documents/142
```

Accepted keys, all optional:

| Key | Applied as |
|-----|-----------|
| `elementor_data` | `_elementor_data` postmeta - the element tree |
| `title` | `post_title`, through `sanitize_text_field()` |
| `status` | `post_status` |
| `content` | `post_content`, through `wp_kses_post()` |

What happens around the write:

1. `Elementor\Validator::validateDocumentId()` confirms the post exists and is an
   Elementor document. A failure returns `400` with the validator's error code.
2. The document is read before the change and returned in the response under
   `backup`, alongside a `changes` diff. This is **in the response body only** -
   nothing is persisted server-side, so keep the response if you may need to roll
   back.
3. When `elementor_data` is written, the post's compiled Elementor CSS file is
   deleted so the front end regenerates it on the next render. Without this, an
   API-driven edit leaves a stale `post-{id}.css` behind.

```json
{ "success": true, "data": { "success": true, "document": {}, "backup": {}, "changes": {} } }
```

Sending a malformed element tree is the main risk here - it can render a page
blank in the editor. Read the document first, modify the tree you got back, and
send the whole thing; do not hand-author `elementor_data` from scratch. Take a
[backup](BACKUP.md) before a bulk run.

## Templates

```bash
curl -u "$USER:$APP_PASS" \
  "https://example.com/wp-json/wpforge/v1/elementor/templates?type=section&per_page=20"
```

`type` filters by Elementor's library type (`section`, `page`, `header`, …).
Template updates are not exposed; create templates through Elementor itself.

## Errors

| Code | Status | Meaning |
|------|--------|---------|
| `ELEMENTOR_NOT_AVAILABLE` | 503 | Plugin missing or inactive |
| `NOT_FOUND` | 404 | No document or template with that id |
| `FORBIDDEN` | 403 | Caller lacks `edit_pages` |
| `UPDATE_FAILED` | 500 | Write rejected while saving |
