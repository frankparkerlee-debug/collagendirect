#!/bin/bash
# Script to run AI approval feedback table migration on production
# This creates the patient_approval_scores table

echo "Running AI approval feedback migration on production..."

# Run the specific migration via web
curl -s https://collagendirect.health/admin/add-approval-feedback-table.php

echo ""
echo "Migration complete!"
echo ""
echo "Verify the table was created:"
echo "https://collagendirect.health/admin/debug-ai-approval.php"
