#!/bin/bash
# Script to migrate all data from old Render database to new Render database
# This uses pg_dump and pg_restore for a complete, safe migration

set -e

echo "=== Render Database Migration Script ==="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check for required tools
if ! command -v pg_dump &> /dev/null; then
    echo -e "${RED}Error: pg_dump is not installed${NC}"
    echo "Install PostgreSQL client tools:"
    echo "  macOS: brew install postgresql"
    exit 1
fi

echo -e "${GREEN}✓ PostgreSQL client tools found${NC}"
echo ""

echo "You'll need database credentials from both Render instances."
echo "Get these from: https://dashboard.render.com → Your Database → Info"
echo ""

# Old database credentials
echo -e "${BLUE}=== OLD DATABASE (source) ===${NC}"
read -p "Old DB Host: " OLD_HOST
read -p "Old DB Port [5432]: " OLD_PORT
OLD_PORT=${OLD_PORT:-5432}
read -p "Old DB Name: " OLD_DB
read -p "Old DB User: " OLD_USER
read -sp "Old DB Password: " OLD_PASS
echo ""

# New database credentials
echo ""
echo -e "${BLUE}=== NEW DATABASE (destination) ===${NC}"
read -p "New DB Host: " NEW_HOST
read -p "New DB Port [5432]: " NEW_PORT
NEW_PORT=${NEW_PORT:-5432}
read -p "New DB Name: " NEW_DB
read -p "New DB User: " NEW_USER
read -sp "New DB Password: " NEW_PASS
echo ""
echo ""

# Confirm
echo -e "${YELLOW}WARNING: This will:${NC}"
echo "  1. Export ALL data from the old database"
echo "  2. Import ALL data into the new database"
echo "  3. OVERWRITE any existing data in the new database"
echo ""
echo "Source: ${OLD_USER}@${OLD_HOST}:${OLD_PORT}/${OLD_DB}"
echo "Destination: ${NEW_USER}@${NEW_HOST}:${NEW_PORT}/${NEW_DB}"
echo ""
read -p "Continue? (type 'yes' to proceed): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Aborted."
    exit 0
fi

# Create dump file
DUMP_FILE="/tmp/collagendirect_migration_$(date +%Y%m%d_%H%M%S).sql"

echo ""
echo -e "${BLUE}Step 1: Exporting old database...${NC}"
export PGPASSWORD="$OLD_PASS"
pg_dump -h "$OLD_HOST" -p "$OLD_PORT" -U "$OLD_USER" -d "$OLD_DB" \
  --no-owner --no-acl --clean --if-exists \
  -f "$DUMP_FILE"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database exported successfully${NC}"
    echo "  Dump file: $DUMP_FILE"
    echo "  Size: $(du -h "$DUMP_FILE" | cut -f1)"
else
    echo -e "${RED}✗ Export failed${NC}"
    exit 1
fi

# Import to new database
echo ""
echo -e "${BLUE}Step 2: Importing to new database...${NC}"
export PGPASSWORD="$NEW_PASS"
psql -h "$NEW_HOST" -p "$NEW_PORT" -U "$NEW_USER" -d "$NEW_DB" \
  -f "$DUMP_FILE"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database imported successfully${NC}"
else
    echo -e "${RED}✗ Import failed${NC}"
    echo "The dump file is preserved at: $DUMP_FILE"
    exit 1
fi

# Run additional migrations that were added after
echo ""
echo -e "${BLUE}Step 3: Running new migrations...${NC}"
echo "Waiting for deployment to pick up the migration file..."
sleep 30

# Run the schema fix migration
echo "Running schema fix migration..."
RESPONSE=$(curl -s "https://collagendirect-qorw.onrender.com/admin/run-migration-fix-missing-schema.php")
if echo "$RESPONSE" | grep -q "Migration completed"; then
    echo -e "${GREEN}✓ Schema fix migration completed${NC}"
else
    echo -e "${YELLOW}⚠ Schema fix migration may have issues:${NC}"
    echo "$RESPONSE" | head -20
fi

# Cleanup
echo ""
echo -e "${BLUE}Cleaning up...${NC}"
rm "$DUMP_FILE"
echo -e "${GREEN}✓ Removed temporary dump file${NC}"

echo ""
echo -e "${GREEN}=== Migration Complete! ===${NC}"
echo ""
echo "Next steps:"
echo "  1. Test login at: https://collagendirect-qorw.onrender.com"
echo "  2. Verify all data is present"
echo "  3. Update your domain DNS to point to the new instance (when ready)"
echo ""
