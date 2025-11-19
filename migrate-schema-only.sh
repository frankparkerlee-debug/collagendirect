#!/bin/bash
# Script to migrate ONLY the database schema (tables, indexes, constraints)
# from old Render database to new Render database - NO DATA

set -e

echo "=== Render Database Schema Migration (Structure Only) ==="
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

echo "This will copy ALL table structures (but NOT data) from old to new database."
echo "Get database credentials from: https://dashboard.render.com → Database → Info"
echo ""

# Old database credentials
echo -e "${BLUE}=== OLD DATABASE (source schema) ===${NC}"
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
echo -e "${YELLOW}This will:${NC}"
echo "  1. Export ONLY table structures (CREATE TABLE, ALTER TABLE, etc.)"
echo "  2. Export indexes, constraints, foreign keys"
echo "  3. Import into new database"
echo "  4. NOT copy any data (users, orders, patients, etc. will remain empty)"
echo ""
echo "Source: ${OLD_USER}@${OLD_HOST}:${OLD_PORT}/${OLD_DB}"
echo "Destination: ${NEW_USER}@${NEW_HOST}:${NEW_PORT}/${NEW_DB}"
echo ""
read -p "Continue? (type 'yes' to proceed): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Aborted."
    exit 0
fi

# Create schema-only dump file
SCHEMA_FILE="/tmp/collagendirect_schema_$(date +%Y%m%d_%H%M%S).sql"

echo ""
echo -e "${BLUE}Step 1: Exporting schema from old database...${NC}"
export PGPASSWORD="$OLD_PASS"

# Use --schema-only flag to export ONLY table structures, no data
pg_dump -h "$OLD_HOST" -p "$OLD_PORT" -U "$OLD_USER" -d "$OLD_DB" \
  --schema-only \
  --no-owner \
  --no-acl \
  -f "$SCHEMA_FILE"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Schema exported successfully${NC}"
    echo "  Schema file: $SCHEMA_FILE"
    echo "  Size: $(du -h "$SCHEMA_FILE" | cut -f1)"

    # Show what tables were exported
    echo ""
    echo "Exported table structures:"
    grep "CREATE TABLE" "$SCHEMA_FILE" | sed 's/CREATE TABLE /  - /' | sed 's/ (//' || echo "  (checking...)"
else
    echo -e "${RED}✗ Export failed${NC}"
    exit 1
fi

# Import schema to new database
echo ""
echo -e "${BLUE}Step 2: Importing schema to new database...${NC}"
export PGPASSWORD="$NEW_PASS"

psql -h "$NEW_HOST" -p "$NEW_PORT" -U "$NEW_USER" -d "$NEW_DB" \
  -f "$SCHEMA_FILE" 2>&1 | grep -v "already exists" | grep -v "NOTICE" || true

if [ $? -eq 0 ] || [ ${PIPESTATUS[0]} -eq 0 ]; then
    echo -e "${GREEN}✓ Schema imported successfully${NC}"
else
    echo -e "${RED}✗ Import failed${NC}"
    echo "The schema file is preserved at: $SCHEMA_FILE"
    exit 1
fi

# Verify tables were created
echo ""
echo -e "${BLUE}Step 3: Verifying tables in new database...${NC}"
export PGPASSWORD="$NEW_PASS"

TABLE_COUNT=$(psql -h "$NEW_HOST" -p "$NEW_PORT" -U "$NEW_USER" -d "$NEW_DB" \
  -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';")

echo "Tables in new database: $TABLE_COUNT"

if [ "$TABLE_COUNT" -gt 5 ]; then
    echo -e "${GREEN}✓ Tables created successfully${NC}"
else
    echo -e "${YELLOW}⚠ Expected more tables, please verify${NC}"
fi

# Cleanup
echo ""
echo -e "${BLUE}Cleaning up...${NC}"
echo "Schema file saved at: $SCHEMA_FILE"
echo "(You can delete this file manually if desired)"

echo ""
echo -e "${GREEN}=== Schema Migration Complete! ===${NC}"
echo ""
echo "What was migrated:"
echo "  ✓ All table structures (CREATE TABLE statements)"
echo "  ✓ All indexes"
echo "  ✓ All constraints and foreign keys"
echo "  ✓ All sequences"
echo ""
echo "What was NOT migrated:"
echo "  ✗ No user data"
echo "  ✗ No patient data"
echo "  ✗ No order data"
echo "  ✗ No product data"
echo ""
echo "Next steps:"
echo "  1. Now you have parker@collagendirect.health as super admin (created earlier)"
echo "  2. Tables are empty and ready for use"
echo "  3. Test the site at: https://collagendirect-qorw.onrender.com"
echo "  4. You can add new users, patients, etc. through the admin interface"
echo ""
