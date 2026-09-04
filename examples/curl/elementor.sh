#!/bin/bash
SITE_URL="https://your-site.com"
AUTH="username:application_password"

echo "=== Elementor Status ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/elementor/status" | jq .

echo "=== List Documents ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/elementor/documents" | jq .

echo "=== Get Document ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/elementor/documents/123" | jq .

echo "=== List Templates ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/elementor/templates" | jq .
