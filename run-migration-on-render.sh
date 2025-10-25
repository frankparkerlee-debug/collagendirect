#!/bin/bash
#
# Script to run the registration revamp migration on Render
#
# This script will execute the migration on the production database
# Run this AFTER the Render deployment is complete
#

echo "======================================"
echo "Registration Revamp Migration Runner"
echo "======================================"
echo ""

# Check if MIGRATION_SECRET is set
if [ -z "$MIGRATION_SECRET" ]; then
  echo "⚠️  MIGRATION_SECRET environment variable not set"
  echo "Using default secret (not recommended for production)"
  MIGRATION_SECRET="change-me-in-production"
fi

echo "Running migration on Render..."
echo ""

# Run the migration via HTTP request
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
  "https://collagendirect.onrender.com/migrate-registration-revamp.php?secret=${MIGRATION_SECRET}")

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

echo "HTTP Status: $HTTP_CODE"
echo ""

if [ "$HTTP_CODE" = "200" ]; then
  echo "✅ Migration completed successfully!"
  echo ""
  echo "Output:"
  echo "$BODY"
else
  echo "❌ Migration failed with HTTP $HTTP_CODE"
  echo ""
  echo "Response:"
  echo "$BODY"
  exit 1
fi

echo ""
echo "======================================"
echo "Next Steps:"
echo "1. Test registration at: https://collagendirect.onrender.com/register"
echo "2. Try all 4 user types:"
echo "   - Practice Manager / Admin"
echo "   - Physician"
echo "   - DME Hybrid Referrer"
echo "   - DME Wholesale Only"
echo "======================================"
