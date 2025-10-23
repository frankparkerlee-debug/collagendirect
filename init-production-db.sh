#!/bin/bash
# Initialize production database on Render

echo "=== Initializing CollagenDirect Production Database ==="
echo ""

# Get database credentials from Render
echo "Getting database connection info..."
DB_URL=$(render services env get srv-d3sn5lm3jp1c739tjoj0 2>&1 | grep "DATABASE_URL" | cut -d'"' -f4)

if [ -z "$DB_URL" ]; then
    echo "Error: Could not get database URL from Render"
    echo "Please set DATABASE_URL manually or check Render dashboard"
    exit 1
fi

echo "Database URL found!"
echo ""

# Apply schema
echo "Applying database schema..."
psql "$DB_URL" < schema-postgresql.sql

if [ $? -eq 0 ]; then
    echo "✓ Schema applied successfully"
else
    echo "✗ Schema application failed"
    exit 1
fi

echo ""

# Create test user
echo "Creating test user..."
psql "$DB_URL" << 'EOF'
INSERT INTO users (id, email, password_hash, first_name, last_name, verified, created_at, updated_at)
VALUES (
    encode(gen_random_bytes(16), 'base64'),
    'sparkingmatt@gmail.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- TempPassword123!
    'Matt',
    'Sparkman',
    true,
    NOW(),
    NOW()
)
ON CONFLICT (email) DO UPDATE SET
    password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    updated_at = NOW();
EOF

if [ $? -eq 0 ]; then
    echo "✓ Test user created/updated"
else
    echo "✗ User creation failed"
    exit 1
fi

echo ""
echo "=== Database Initialization Complete! ==="
echo ""
echo "Login credentials:"
echo "  URL: https://collagendirect.onrender.com/login"
echo "  Email: sparkingmatt@gmail.com"
echo "  Password: TempPassword123!"
echo ""
