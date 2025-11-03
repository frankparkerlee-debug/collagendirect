#!/bin/bash
# Manual PreAuth Agent Migration Script
# Run this when you have your database connection details

echo "🚀 PreAuth Agent Migration Deployment"
echo "======================================="
echo ""
echo "Please provide your database connection details:"
echo ""

# Prompt for database details
read -p "Database Host (e.g., dpg-xxx.oregon-postgres.render.com): " DB_HOST
read -p "Database Port [5432]: " DB_PORT
DB_PORT=${DB_PORT:-5432}
read -p "Database Name: " DB_NAME
read -p "Database User: " DB_USER
read -sp "Database Password: " DB_PASS
echo ""
echo ""

echo "✅ Connection details received"
echo "   Host: $DB_HOST"
echo "   Port: $DB_PORT"
echo "   Database: $DB_NAME"
echo "   User: $DB_USER"
echo ""

# Find psql
PSQL=$(which psql 2>/dev/null || find /usr/local -name psql 2>/dev/null | head -1 || echo "")

if [ -z "$PSQL" ]; then
    echo "❌ Error: psql not found"
    echo "Install with: brew install libpq"
    exit 1
fi

echo "Using psql: $PSQL"
echo ""

# Test connection
echo "🔌 Testing database connection..."
export PGPASSWORD="$DB_PASS"
if "$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT version();" > /dev/null 2>&1; then
    echo "✅ Connection successful"
    echo ""
else
    echo "❌ Connection failed"
    echo "Please check your credentials and try again"
    exit 1
fi

# Run migrations
echo "🔄 Running migrations..."
echo ""

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MIGRATIONS_DIR="$SCRIPT_DIR/migrations"

MIGRATIONS=(
    "001_create_preauth_requests_table.sql"
    "002_create_preauth_rules_table.sql"
    "003_create_preauth_audit_log_table.sql"
    "004_create_eligibility_cache_table.sql"
)

FAILED=0

for MIGRATION in "${MIGRATIONS[@]}"; do
    MIGRATION_FILE="$MIGRATIONS_DIR/$MIGRATION"

    if [ ! -f "$MIGRATION_FILE" ]; then
        echo "❌ Migration file not found: $MIGRATION"
        FAILED=1
        continue
    fi

    echo "📝 Running: $MIGRATION"

    if "$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f "$MIGRATION_FILE"; then
        echo "✅ $MIGRATION completed"
        echo ""
    else
        echo "❌ $MIGRATION failed"
        echo ""
        FAILED=1
        break
    fi
done

if [ $FAILED -eq 0 ]; then
    echo "======================================="
    echo "✅ All migrations completed successfully!"
    echo ""

    # Verify
    echo "🔍 Verifying tables..."
    TABLES=$("$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND (table_name LIKE 'preauth%' OR table_name = 'eligibility_cache')
        ORDER BY table_name;
    ")

    echo "Created tables:"
    echo "$TABLES" | sed 's/^/   ✓ /'
    echo ""

    # Count rules
    COUNT=$("$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM preauth_rules;")
    echo "✅ Carrier rules loaded: $COUNT"
    echo ""

    echo "Next steps:"
    echo "1. Review carrier rules: SELECT * FROM preauth_rules;"
    echo "2. Set up cron jobs"
    echo "3. Configure carrier API credentials"
    echo "4. Access admin dashboard: /admin/preauth-dashboard.php"
    exit 0
else
    echo "======================================="
    echo "❌ Migrations failed"
    exit 1
fi
