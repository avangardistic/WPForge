# WPForge Security Model

## Overview

WPForge provides powerful access to WordPress installations. This document describes the security measures implemented to protect your site.

## Authentication

### Required for All Mutating Operations

- **Read operations**: May be public (status, health)
- **Write operations**: Always require authentication

### Supported Methods

1. **WordPress Application Passwords** (Recommended)
   - Use `Authorization: Basic base64(username:password)`
   - Generated per-user in WordPress admin
   - Can be revoked individually

2. **Cookie Authentication**
   - Standard WordPress logged-in cookie
   - Useful for same-origin requests

## Authorization

### Capability-Based Access Control

All operations check WordPress capabilities:

| Operation | Required Capability |
|-----------|---------------------|
| Read site info | `read` |
| Edit pages | `edit_pages` |
| Publish pages | `publish_pages` |
| Upload files | `upload_files` |
| Activate plugins | `activate_plugins` |
| Install plugins | `install_plugins` |
| Switch themes | `switch_themes` |
| Edit theme files | `edit_themes` |
| Manage options | `manage_options` |

### Developer Mode

High-risk operations require developer mode to be enabled:

- Filesystem writes
- Database writes
- Plugin installation
- Theme activation

Enable via configuration:
```php
update_option('wpforge_config', [
    'developer_mode' => true,
    'allow_filesystem_writes' => true,
]);
```

## Input Validation

### Path Traversal Prevention

All filesystem paths are validated:
- `../` sequences rejected
- Paths normalized and resolved
- Operations bounded to WordPress root

### SQL Injection Prevention

- Read-only queries validated
- Write queries blocked by default
- Prepared statements used internally

### File Upload Safety

- MIME type validation
- Extension checking
- WordPress media handlers used

## Audit Logging

Every mutating operation is logged:
- Timestamp
- User ID and username
- Operation type
- Target resource
- Success/failure
- Request ID for correlation

Logs accessible via `/wp-json/wpforge/v1/logs`

## Rate Limiting

Configurable rate limits protect against abuse:
- Default: 100 requests per minute
- Per-user or per-IP tracking
- Configurable via settings

## What WPForge Does NOT Do

- ❌ No arbitrary shell execution (`system()`, `exec()`)
- ❌ No unrestricted file access
- ❌ No exposed database credentials
- ❌ No stack traces in responses
- ❌ No logging of sensitive data

## Best Practices

1. **Use HTTPS** - Always enable SSL/TLS
2. **Limit user capabilities** - Create dedicated API users
3. **Enable audit logging** - Monitor all operations
4. **Remove when done** - This is a temporary bridge
5. **Review logs regularly** - Check for unusual activity
