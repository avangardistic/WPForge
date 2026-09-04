#!/bin/bash
SITE_URL="https://your-site.com"
AUTH="username:application_password"

echo "=== List Themes ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/files/list?path=wp-content/themes" | jq .

echo "=== Read File ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/files/read?path=wp-content/themes/twentytwentyfour/style.css" | jq .

echo "=== Write File (Dry Run) ==="
curl -X POST -u "$AUTH" \
     -H "Content-Type: application/json" \
     -d '{"path":"wp-content/uploads/test.txt","content":"Hello WPForge!"}' \
     "$SITE_URL/wp-json/wpforge/v1/files/write?dry_run=true" | jq .
