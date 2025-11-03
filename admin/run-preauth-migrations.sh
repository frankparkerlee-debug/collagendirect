#!/bin/bash

# PreAuth Agent Database Migrations Runner
# This script runs all preauth-related database migrations

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "========================================"
echo "PreAuth Agent Database Migrations"
echo "========================================"
echo ""

# Get database credentials
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-collagen_db}"
DB_USER="${DB_USER:-postgres}"

echo "Database Configuration:"
echo "  Host: $DB_HOST"
echo "  Port: $DB_PORT"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo ""

# Find psql
PSQL=$(which psql 2>/dev/null || find /usr -name psql 2>/dev/null | head -1 || find /Applications -name psql 2>/dev/null | head -1 || echo "")

if [ -z "$PSQL" ]; then
    echo -e "${RED}ERROR: psql command not found${NC}"
    echo "Please install PostgreSQL client tools"
    exit 1
fi

echo "Using psql: $PSQL"
echo ""

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MIGRATIONS_DIR="$SCRIPT_DIR/migrations"

if [ ! -d "$MIGRATIONS_DIR" ]; then
    echo -e "${RED}ERROR: Migrations directory not found: $MIGRATIONS_DIR${NC}"
    exit 1
fi

# Function to run a migration
run_migration() {
    local migration_file=$1
    local migration_name=$(basename "$migration_file")

    echo -e "${YELLOW}Running migration: $migration_name${NC}"

    if PGPASSWORD="${DB_PASS:-}" "$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f "$migration_file"; then
        echo -e "${GREEN}✓ $migration_name completed successfully${NC}"
        echo ""
        return 0
    else
        echo -e "${RED}✗ $migration_name failed${NC}"
        echo ""
        return 1
    fi
}

# Run migrations in order
echo "Starting migrations..."
echo ""

FAILED=0

# Migration 1: preauth_requests table
if [ -f "$MIGRATIONS_DIR/001_create_preauth_requests_table.sql" ]; then
    run_migration "$MIGRATIONS_DIR/001_create_preauth_requests_table.sql" || FAILED=1
else
    echo -e "${RED}ERROR: Migration file not found: 001_create_preauth_requests_table.sql${NC}"
    FAILED=1
fi

# Migration 2: preauth_rules table
if [ -f "$MIGRATIONS_DIR/002_create_preauth_rules_table.sql" ]; then
    run_migration "$MIGRATIONS_DIR/002_create_preauth_rules_table.sql" || FAILED=1
else
    echo -e "${RED}ERROR: Migration file not found: 002_create_preauth_rules_table.sql${NC}"
    FAILED=1
fi

# Migration 3: preauth_audit_log table
if [ -f "$MIGRATIONS_DIR/003_create_preauth_audit_log_table.sql" ]; then
    run_migration "$MIGRATIONS_DIR/003_create_preauth_audit_log_table.sql" || FAILED=1
else
    echo -e "${RED}ERROR: Migration file not found: 003_create_preauth_audit_log_table.sql${NC}"
    FAILED=1
fi

# Create optional eligibility_cache table
echo -e "${YELLOW}Creating optional eligibility_cache table...${NC}"
ELIGIBILITY_SQL="$MIGRATIONS_DIR/004_create_eligibility_cache_table.sql"

if [ ! -f "$ELIGIBILITY_SQL" ]; then
    # Create the SQL on the fly if it doesn't exist
    cat > "$ELIGIBILITY_SQL" << 'EOF'
-- Optional eligibility cache table
CREATE TABLE IF NOT EXISTS eligibility_cache (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_id VARCHAR(100) NOT NULL,
    carrier_name VARCHAR(255) NOT NULL,
    eligibility_data JSONB NOT NULL,
    verified_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(member_id, carrier_name)
);

CREATE INDEX idx_eligibility_member ON eligibility_cache(member_id);
CREATE INDEX idx_eligibility_carrier ON eligibility_cache(carrier_name);
CREATE INDEX idx_eligibility_verified ON eligibility_cache(verified_at);

COMMENT ON TABLE eligibility_cache IS 'Caches insurance eligibility verification results to reduce API calls';
EOF
fi

run_migration "$ELIGIBILITY_SQL" || echo -e "${YELLOW}Note: Eligibility cache table creation failed (may already exist)${NC}"

echo ""
echo "========================================"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All migrations completed successfully!${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Review the preauth_rules table and customize carrier rules as needed"
    echo "2. Set up cron jobs (see PREAUTH_AGENT_README.md)"
    echo "3. Configure carrier API credentials in environment variables"
    echo "4. Test the PreAuth Agent with a sample order"
    echo ""
    echo "Admin Dashboard: /admin/preauth-dashboard.php"
    echo "API Endpoint: /api/preauth.php"
    exit 0
else
    echo -e "${RED}✗ Some migrations failed${NC}"
    echo "Please review the errors above and fix any issues"
    exit 1
fi
