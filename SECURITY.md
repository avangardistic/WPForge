# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability, please report it responsibly:

1. **Do NOT** open a public GitHub issue for security vulnerabilities
2. Report via GitHub's private vulnerability reporting: https://github.com/avangardistic/WPForge/security/advisories
3. Include: description, steps to reproduce, potential impact

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.0.x   | ✅ Yes     |

## Security Measures

- Application Password authentication (recommended)
- Bearer token authentication with hashed storage
- WordPress capability checks on every mutating endpoint
- Path traversal protection on filesystem operations
- SQL injection prevention via prepared statements
- Rate limiting on all endpoints
- Audit logging of all mutations
- Sensitive data redaction in logs
- Read-only database mode by default
