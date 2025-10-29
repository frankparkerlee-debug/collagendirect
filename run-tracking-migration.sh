#!/bin/bash
# Run the tracking columns migration on production database

set -e

echo "====================================="
echo "Running Tracking Columns Migration"
echo "====================================="
echo ""

# Check if DATABASE_URL is set
if [ -z "$DATABASE_URL" ]; then
  echo "❌ ERROR: DATABASE_URL environment variable not set"
  echo "   Please set it first, e.g.:"
  echo "   export DATABASE_URL='postgresql://user:pass@host/dbname'"
  exit 1
fi

echo "Database URL: ${DATABASE_URL%%@*}@..."  # Show username but hide password and host
echo ""
echo "This migration will:"
echo "  1. Add tracking_number and carrier columns to orders table"
echo "  2. Migrate data from rx_note_name/rx_note_mime to new columns"
echo "  3. Update the status change trigger"
echo ""
read -p "Continue? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  echo "Aborted."
  exit 0
fi

echo ""
echo "Running migration..."
echo ""

# Run the migration SQL
psql "$DATABASE_URL" < migrations/add-tracking-columns.sql

if [ $? -eq 0 ]; then
  echo ""
  echo "✅ Migration completed successfully!"
  echo ""
  echo "Verifying changes..."

  # Verify the columns were added
  psql "$DATABASE_URL" -c "\d orders" | grep -E "tracking_number|carrier"

  echo ""
  echo "Checking migrated data..."
  psql "$DATABASE_URL" -c "SELECT id, tracking_number, carrier, rx_note_name, rx_note_mime FROM orders WHERE tracking_number IS NOT NULL LIMIT 5;"

  echo ""
  echo "Done! You can now deploy the updated code."
else
  echo ""
  echo "❌ Migration failed!"
  echo "   Please check the error message above."
  exit 1
fi
