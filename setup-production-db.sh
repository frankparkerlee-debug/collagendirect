#!/bin/bash
# Setup script for CollagenDirect Production Database on Render

set -e  # Exit on error

echo "🚀 CollagenDirect Production Database Setup"
echo "==========================================="
echo ""

# Get database credentials from Render
echo "📊 Getting database connection details from Render..."
DB_INFO=$(render services list -o json | jq '.[] | select(.postgres.name == "collagendirect-db") | .postgres')

if [ "$DB_INFO" == "null" ] || [ -z "$DB_INFO" ]; then
    echo "❌ Error: collagendirect-db database not found!"
    echo "Please ensure the database is created in Render first."
    exit 1
fi

# Extract connection details
DB_HOST=$(echo $DB_INFO | jq -r '.hostname // empty')
DB_PORT=$(echo $DB_INFO | jq -r '.port // "5432"')
DB_NAME=$(echo $DB_INFO | jq -r '.databaseName')
DB_USER=$(echo $DB_INFO | jq -r '.databaseUser')

# Get internal connection string which includes password
INTERNAL_URL=$(echo $DB_INFO | jq -r '.connectionInfo.internalConnectionString // empty')

if [ -z "$INTERNAL_URL" ]; then
    echo "⚠️  Cannot extract password automatically."
    echo "Please enter your database password manually:"
    read -s DB_PASS
else
    # Extract password from connection string: postgresql://user:pass@host:port/db
    DB_PASS=$(echo $INTERNAL_URL | sed -n 's/.*:\/\/[^:]*:\([^@]*\)@.*/\1/p')
fi

echo "✅ Database connection details retrieved"
echo "   Host: $DB_HOST"
echo "   Port: $DB_PORT"
echo "   Database: $DB_NAME"
echo "   User: $DB_USER"
echo ""

# Check if psql is installed
if ! command -v psql &> /dev/null; then
    echo "❌ Error: psql (PostgreSQL client) is not installed"
    echo "Install with: brew install postgresql"
    exit 1
fi

# Import schema
echo "📥 Importing database schema..."
export PGPASSWORD="$DB_PASS"
psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f schema-postgresql.sql

if [ $? -eq 0 ]; then
    echo "✅ Schema imported successfully!"
else
    echo "❌ Error importing schema"
    exit 1
fi

# Create admin user
echo ""
echo "👤 Creating admin user..."
echo "   Email: sparkingmatt@gmail.com"
echo "   Password: TempPassword123!"

# Generate password hash using PHP
PASSWORD_HASH=$(php -r "echo password_hash('TempPassword123!', PASSWORD_BCRYPT);")
USER_ID=$(php -r "echo bin2hex(random_bytes(16));")

psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" << EOFSQL
INSERT INTO users (
    id, email, password_hash, first_name, last_name,
    practice_name, npi, status, account_type,
    agree_msa, agree_baa, created_at, updated_at
) VALUES (
    '$USER_ID',
    'sparkingmatt@gmail.com',
    '$PASSWORD_HASH',
    'Matthew',
    'Brown',
    'Medical Practice',
    '1234567890',
    'active',
    'referral',
    TRUE,
    TRUE,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
) ON CONFLICT (email) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    updated_at = CURRENT_TIMESTAMP;
EOFSQL

if [ $? -eq 0 ]; then
    echo "✅ Admin user created successfully!"
else
    echo "❌ Error creating admin user"
    exit 1
fi

unset PGPASSWORD

echo ""
echo "🎉 Database setup complete!"
echo ""
echo "Login Credentials:"
echo "=================="
echo "Email:    sparkingmatt@gmail.com"
echo "Password: TempPassword123!"
echo ""
echo "Portal URL: https://collagendirect-1.onrender.com/portal"
echo ""
echo "⚠️  IMPORTANT: Change your password after first login!"
echo ""

