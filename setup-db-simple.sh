#!/bin/bash
# Simple script to set up the database - you'll need to provide the connection string

echo "🚀 CollagenDirect Database Setup"
echo "================================"
echo ""
echo "Please go to: https://dashboard.render.com/d/dpg-d3t3i83e5dus73flkang-a"
echo "And copy the 'Internal Database URL'"
echo ""
echo "It should look like: postgresql://user:password@host:5432/collagen_db"
echo ""
read -p "Paste the connection string here: " DB_URL

if [ -z "$DB_URL" ]; then
    echo "❌ No connection string provided"
    exit 1
fi

echo ""
echo "📥 Importing schema..."
psql "$DB_URL" < schema-postgresql.sql

if [ $? -ne 0 ]; then
    echo "❌ Schema import failed"
    exit 1
fi

echo "✅ Schema imported successfully!"
echo ""
echo "👤 Creating admin user..."

# Generate password hash
PASSWORD_HASH='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'  # TempPassword123!

psql "$DB_URL" << EOFSQL
INSERT INTO users (
    id, email, password_hash, first_name, last_name,
    practice_name, npi, status, account_type,
    agree_msa, agree_baa, created_at, updated_at
) VALUES (
    encode(gen_random_bytes(16), 'hex'),
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

if [ $? -ne 0 ]; then
    echo "❌ User creation failed"
    exit 1
fi

echo "✅ Admin user created!"
echo ""
echo "🎉 Setup complete!"
echo ""
echo "Login Credentials:"
echo "=================="
echo "Email:    sparkingmatt@gmail.com"
echo "Password: TempPassword123!"
echo ""
echo "Portal:   https://collagendirect-1.onrender.com/portal"
echo ""

