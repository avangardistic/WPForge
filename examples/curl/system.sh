#!/bin/bash
# WPForge System API Examples
# Set these before running:
SITE_URL="https://your-site.com"
AUTH="username:application_password"

echo "=== Status ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/status" | jq .

echo "=== Health ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/health" | jq .

echo "=== Environment ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/environment" | jq .

echo "=== Capabilities ==="
curl -u "$AUTH" "$SITE_URL/wp-json/wpforge/v1/capabilities" | jq .
