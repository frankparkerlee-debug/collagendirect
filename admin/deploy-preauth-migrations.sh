#!/bin/bash
# Deploy PreAuth Agent Migrations to Render Database

set -e  # Exit on error

echo "🚀 PreAuth Agent Migration Deployment"
echo "======================================="
echo ""

# Get database credentials from Render
echo "📊 Getting database connection details from Render..."
DB_INFO=$(render services list -o json 2>/dev/null | jq '.[] | select(.postgres.name == "collagendirect-db") | .postgres' 2>/dev/null)

if [ "$DB_INFO" == "null" ] || [ -z "$DB_INFO" ]; then
    echo "⚠️  Render CLI not available or database not found."
    echo "Please provide database connection details manually:"
    echo ""
    read -p "Database Host: " DB_HOST
    read -p "Database Port [5432]: " DB_PORT
    DB_PORT=${DB_PORT:-5432}
    read -p "Database Name: " DB_NAME
    read -p "Database User: " DB_USER
    read -sp "Database Password: " DB_PASS
    echo ""
else
    # Extract connection details from Render
    DB_HOST=$(echo $DB_INFO | jq -r '.hostname // empty')
    DB_PORT=$(echo $DB_INFO | jq -r '.port // "5432"')
    DB_NAME=$(echo $DB_INFO | jq -r '.databaseName')
    DB_USER=$(echo $DB_INFO | jq -r '.databaseUser')

    # Get internal connection string which includes password
    INTERNAL_URL=$(echo $DB_INFO | jq -r '.connectionInfo.internalConnectionString // empty')

    if [ -z "$INTERNAL_URL" ]; then
        echo "⚠️  Cannot extract password automatically."
        read -sp "Please enter database password: " DB_PASS
        echo ""
    else
        # Extract password from connection string
        DB_PASS=$(echo $INTERNAL_URL | sed -n 's/.*:\/\/[^:]*:\([^@]*\)@.*/\1/p')
    fi
fi

echo "✅ Database connection details retrieved"
echo "   Host: $DB_HOST"
echo "   Port: $DB_PORT"
echo "   Database: $DB_NAME"
echo "   User: $DB_USER"
echo ""

# Check if psql is installed
PSQL=$(which psql 2>/dev/null || find /usr/local -name psql 2>/dev/null | head -1 || echo "")

if [ -z "$PSQL" ]; then
    echo "❌ Error: psql (PostgreSQL client) is not installed"
    echo "Install with: brew install libpq"
    echo "Or: brew install postgresql"
    exit 1
fi

echo "Using psql: $PSQL"
echo ""

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MIGRATIONS_DIR="$SCRIPT_DIR/migrations"

# Function to run a migration
run_migration() {
    local migration_file=$1
    local migration_name=$(basename "$migration_file")

    echo "📝 Running migration: $migration_name"

    export PGPASSWORD="$DB_PASS"
    if "$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f "$migration_file" 2>&1; then
        echo "✅ $migration_name completed successfully"
        echo ""
        return 0
    else
        local exit_code=$?
        # Check if error is just "already exists" warning
        if [ $exit_code -eq 0 ]; then
            echo "✅ $migration_name completed (with warnings)"
            echo ""
            return 0
        else
            echo "❌ $migration_name failed"
            echo ""
            return 1
        fi
    fi
}

# Run migrations in order
echo "🔄 Starting migrations..."
echo ""

FAILED=0

# Migration 1
if [ -f "$MIGRATIONS_DIR/001_create_preauth_requests_table.sql" ]; then
    run_migration "$MIGRATIONS_DIR/001_create_preauth_requests_table.sql" || FAILED=1
else
    echo "❌ Migration file not found: 001_create_preauth_requests_table.sql"
    FAILED=1
fi

# Migration 2
if [ $FAILED -eq 0 ] && [ -f "$MIGRATIONS_DIR/002_create_preauth_rules_table.sql" ]; then
    run_migration "$MIGRATIONS_DIR/002_create_preauth_rules_table.sql" || FAILED=1
else
    [ -f "$MIGRATIONS_DIR/002_create_preauth_rules_table.sql" ] || { echo "❌ Migration file not found: 002_create_preauth_rules_table.sql"; FAILED=1; }
fi

# Migration 3
if [ $FAILED -eq 0 ] && [ -f "$MIGRATIONS_DIR/003_create_preauth_audit_log_table.sql" ]; then
    run_migration "$MIGRATIONS_DIR/003_create_preauth_audit_log_table.sql" || FAILED=1
else
    [ -f "$MIGRATIONS_DIR/003_create_preauth_audit_log_table.sql" ] || { echo "❌ Migration file not found: 003_create_preauth_audit_log_table.sql"; FAILED=1; }
fi

# Migration 4 (optional)
if [ $FAILED -eq 0 ] && [ -f "$MIGRATIONS_DIR/004_create_eligibility_cache_table.sql" ]; then
    echo "📝 Creating optional eligibility_cache table..."
    run_migration "$MIGRATIONS_DIR/004_create_eligibility_cache_table.sql" || echo "⚠️  Eligibility cache table creation failed (may already exist)"
fi

# Verify tables
if [ $FAILED -eq 0 ]; then
    echo "🔍 Verifying tables..."
    export PGPASSWORD="$DB_PASS"

    TABLES=$("$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND (table_name LIKE 'preauth%' OR table_name = 'eligibility_cache')
        ORDER BY table_name;
    " 2>&1)

    if [ $? -eq 0 ]; then
        echo "✅ Created tables:"
        echo "$TABLES" | sed 's/^/   ✓ /'
        echo ""

        # Check preauth_rules count
        COUNT=$("$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM preauth_rules;" 2>&1)
        if [ $? -eq 0 ]; then
            echo "✅ Carrier rules loaded: $COUNT"
        fi
    fi
fi

echo ""
echo "======================================="

if [ $FAILED -eq 0 ]; then
    echo "✅ All migrations completed successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Review carrier rules in preauth_rules table"
    echo "2. Set up cron jobs (see PREAUTH_AGENT_README.md)"
    echo "3. Configure carrier API credentials"
    echo "4. Test the agent with a sample order"
    echo ""
    echo "Admin Dashboard: https://collagendirect.health/admin/preauth-dashboard.php"
    echo "API Endpoint: https://collagendirect.health/api/preauth.php"
    exit 0
else
    echo "❌ Some migrations failed"
    echo "Please review the errors above"
    exit 1
fi
