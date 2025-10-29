#!/bin/bash
# Run the patient status and comments migration

set -e

echo "====================================="
echo "Running Patient Status Migration"
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
echo "  1. Add state column to patients table"
echo "  2. Add status_comment column for manufacturer feedback"
echo "  3. Add status_updated_at and status_updated_by columns"
echo "  4. Set existing patients to 'active' or 'pending' based on orders"
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
psql "$DATABASE_URL" < migrations/add-patient-status-and-comments.sql

if [ $? -eq 0 ]; then
  echo ""
  echo "✅ Migration completed successfully!"
  echo ""
  echo "Verifying changes..."

  # Verify the columns were added
  psql "$DATABASE_URL" -c "\d patients" | grep -E "state|status_comment|status_updated"

  echo ""
  echo "Checking patient states..."
  psql "$DATABASE_URL" -c "SELECT state, COUNT(*) FROM patients GROUP BY state;"

  echo ""
  echo "Done! You can now use the patient status workflow."
else
  echo ""
  echo "❌ Migration failed!"
  echo "   Please check the error message above."
  exit 1
fi
