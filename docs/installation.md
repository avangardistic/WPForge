# WPForge Installation Guide

## Requirements

- WordPress 6.0 or higher
- PHP 8.1 or higher
- MySQL 5.6+ or MariaDB 10.1+
- HTTPS recommended

## Installation Steps

### 1. Download the Plugin

Clone or download the WPForge repository:

```bash
git clone https://github.com/wpforge/wpforge.git
```

### 2. Upload to WordPress

Copy the plugin to your WordPress installation:

```bash
cp -r wpforge/wordpress/wpforge /path/to/wordpress/wp-content/plugins/
```

Or upload via FTP/SFTP to:
```
wp-content/plugins/wpforge/
```

### 3. Activate the Plugin

1. Log in to WordPress Admin
2. Navigate to **Plugins** → **Installed Plugins**
3. Find **WPForge** and click **Activate**

### 4. Configure Authentication

#### Using Application Passwords (Recommended)

1. Go to **Users** → **Profile**
2. Scroll to **Application Passwords** section
3. Enter a name (e.g., "WPForge AI Agent")
4. Click **Add New Application Password**
5. Copy the generated password (shown only once!)

#### Using the API

```bash
# Generate application password via API
curl -X POST \
  -u "admin:your_password" \
  -H "Content-Type: application/json" \
  https://example.com/wp-json/wpforge/v1/application-passwords \
  -d '{"name": "WPForge AI"}'
```

### 5. Verify Installation

Test the API connection:

```bash
curl -u "username:application_password" \
  https://example.com/wp-json/wpforge/v1/status
```

Expected response:
```json
{
  "success": true,
  "request_id": "...",
  "data": {
    "name": "WPForge",
    "version": "0.1.0",
    ...
  }
}
```

### 6. Enable Developer Mode (Optional)

For advanced operations like filesystem writes:

1. Go to **Settings** → **WPForge** (if admin UI is implemented)
2. Or update via database:

```sql
UPDATE wp_options 
SET option_value = '{"enabled":true,"developer_mode":true,"allow_filesystem_writes":true}' 
WHERE option_name = 'wpforge_config';
```

## Post-Installation Checklist

- [ ] `/wp-json/wpforge/v1/status` returns success
- [ ] `/wp-json/wpforge/v1/health` shows healthy
- [ ] Authentication works with application password
- [ ] Unauthorized requests are rejected
- [ ] Audit logs are being created

## Troubleshooting

### Plugin won't activate

Check PHP version:
```bash
php -v
```

Check WordPress version in `wp-includes/version.php`.

### REST API returns 404

1. Go to **Settings** → **Permalinks**
2. Click **Save Changes** to flush rewrite rules

### Authentication fails

1. Verify application password was copied correctly
2. Ensure user has appropriate capabilities
3. Check for security plugins blocking REST API

### Logs table not created

The logs table is created on first log entry. Trigger by making an authenticated request.
