#!/bin/bash

# Run message threading migration on Render
# This adds parent_message_id and thread_id columns to the messages table

echo "Running message threading migration on Render..."

# Get the PHP command output from the migration script
RESULT=$(curl -s "https://collagendirect.health/admin/add-message-threading-columns.php")

echo "$RESULT"

if echo "$RESULT" | grep -q "Migration completed successfully"; then
    echo ""
    echo "✓ Migration successful!"
    exit 0
else
    echo ""
    echo "✗ Migration may have failed. Check the output above."
    exit 1
fi
