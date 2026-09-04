# Security Bug Report: WPForge Critical Vulnerabilities

## Summary
Multiple critical security vulnerabilities discovered in WPForge v1.0.0 that could allow unauthorized access to WordPress databases and filesystem.

## Vulnerabilities

### 1. SQL Injection in prepareQuery() - CRITICAL
**File:** `wordpress/wpforge/src/Database/Inspector.php` (lines 125-138)
**Severity:** Critical
**Issue:** The `prepareQuery()` method uses string replacement for parameter binding instead of proper prepared statements.

```php
private function prepareQuery(string $sql, array $params): string
{
    foreach ($params as $key => $value) {
        if (is_string($value)) {
            $value = $this->wpdb->prepare('%s', $value);  // Prepares but then...
        }
        // ...
        $sql = str_replace(':' . $key, $value, $sql);  // String replacement!
    }
    return $sql;
}
```

**Exploit:** An attacker can inject SQL through the params array since `str_replace` doesn't properly escape quotes in string values.

**Recommendation:** Use `$wpdb->prepare()` with the full query, not string replacement:
```php
$query = $wpdb->prepare($sql, ...array_values($params));
```

### 2. Missing WriteExecutor Import - HIGH
**File:** `wordpress/wpforge/routes/database.php`
**Severity:** High (Feature Not Working)
**Issue:** The route tries to use `$dbInspector->query()` but WriteExecutor.php exists but is never imported or used.

### 3. Information Disclosure via Public Endpoints - MEDIUM
**File:** `wordpress/wpforge/routes/system.php`
**Severity:** Medium
**Issue:** 
- `/status` endpoint returns full site URL, WordPress version, PHP version without authentication
- `/health` endpoint uses `__return_true` permission callback

These endpoints can be used for:
- Server fingerprinting
- WordPress version detection (to find known vulnerabilities)
- PHP version detection

### 4. Database Credentials Exposure - HIGH
**File:** `wordpress/wpforge/src/Database/Inspector.php` (lines 21-34)
**Severity:** High
**Issue:** The `getStatus()` method returns DB_NAME, DB_USER, DB_HOST in API response if authenticated user has access:
```php
'db_name'    => defined('DB_NAME') ? DB_NAME : '',
'db_user'    => defined('DB_USER') ? DB_USER : '',
'db_host'    => defined('DB_HOST') ? DB_HOST : '',
```

## OWASP Top 10 Classification
- A03:2021 – Injection (SQL Injection)
- A01:2021 – Broken Access Control (Public Endpoints)
- A05:2021 – Security Misconfiguration (Info Disclosure)

## Affected Versions
- All versions up to and including 1.0.0

## Steps to Reproduce

### SQL Injection
```bash
# Authenticate first
curl -u "user:app_password" \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT * FROM wp_users WHERE ID = :id","params":{"id":"1 OR 1=1"}}' \
  https://example.com/wp-json/wpforge/v1/database/query
```

## References
- CWE-89: SQL Injection
- CWE-200: Exposure of Sensitive Information
- WordPress VIP Coding Standards: $wpdb->prepare() usage