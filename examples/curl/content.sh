#!/bin/bash
SITE_URL="https://your-site.com"
AUTH="username:application_password"

echo "=== List Posts ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/posts?per_page=5" | jq .

echo "=== Get Post ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/posts/1" | jq .

echo "=== Create Post ==="
curl -X POST -u "$AUTH" \
     -H "Content-Type: application/json" \
     -d '{"post_title":"API Test","post_content":"<p>Hello from WPForge!</p>","post_status":"draft"}' \
     "$SITE_URL/wp-json/wpforge/v1/posts" | jq .

echo "=== List Pages ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/pages" | jq .
