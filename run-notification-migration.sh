#!/bin/bash
# Run notification tables migration on Render
# This creates tables for delivery confirmations and status tracking

echo "Running notification tables migration on Render..."
curl -s "https://collagendirect.onrender.com/migrations/run-notification-migration.php"
echo ""
echo "Migration complete!"
