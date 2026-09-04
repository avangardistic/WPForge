# Authentication

## Application Passwords (Recommended)

```bash
# Create via wp-admin: Users → Profile → Application Passwords
curl -u "username:application_password" \
     https://example.com/wp-json/wpforge/v1/status
```

## API Tokens

```bash
# Generate token (requires auth)
curl -X POST -u "user:pass" \
     -H "Content-Type: application/json" \
     https://example.com/wp-json/wpforge/v1/tokens

# Use token
curl -H "Authorization: Bearer wf_xxx.yyy" \
     https://example.com/wp-json/wpforge/v1/status
```

## Permissions

All mutating operations require authentication. Read-only endpoints (status, health) are public.
