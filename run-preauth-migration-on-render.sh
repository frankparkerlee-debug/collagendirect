#!/bin/bash
# Run PreAuth migrations on Render via HTTP

echo "🚀 Running PreAuth Agent Migrations on Render"
echo "=============================================="
echo ""

# Migration secret
MIGRATION_SECRET="${MIGRATION_SECRET:-preauth_migration_2024}"

# Render URL
RENDER_URL="https://collagendirect.health/admin/migrate-preauth.php?secret=${MIGRATION_SECRET}"

echo "Executing migrations via HTTP..."
echo "URL: https://collagendirect.health/admin/migrate-preauth.php"
echo ""

# Run migration
RESPONSE=$(curl -s -w "\n\nHTTP_CODE:%{http_code}" "$RENDER_URL")

# Extract HTTP code
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

# Show response
echo "$BODY"

echo ""
echo "=============================================="

# Check result
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Migrations completed successfully"
    echo ""
    echo "Next steps:"
    echo "1. Access admin dashboard: https://collagendirect.health/admin/preauth-dashboard.php"
    echo "2. Review carrier rules in database"
    echo "3. Set up cron jobs for automated preauth processing"
    exit 0
else
    echo "❌ Migration failed with HTTP code: $HTTP_CODE"
    exit 1
fi
