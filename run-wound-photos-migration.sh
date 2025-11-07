#!/bin/bash
set -e

echo "🔧 Running wound_photos schema fix migration..."

if [ -z "$DATABASE_URL" ]; then
  echo "❌ DATABASE_URL not set"
  echo "Please set DATABASE_URL environment variable"
  exit 1
fi

psql "$DATABASE_URL" < migrations/fix-wound-photos-schema.sql

echo "✅ Migration complete!"
echo ""
echo "Schema updates:"
echo "  - Added order_id column to wound_photos"
echo "  - Added updated_at column to wound_photos"
echo "  - Added index on order_id"
echo "  - Fixed photo_path values for web access"
