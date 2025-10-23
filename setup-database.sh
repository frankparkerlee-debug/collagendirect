#!/bin/bash
# Setup production database on Render

echo "=== CollagenDirect Database Setup ==="
echo ""
echo "This will:"
echo "  1. Apply the schema to your Render PostgreSQL database"
echo "  2. Create a test user (sparkingmatt@gmail.com / TempPassword123!)"
echo ""
read -p "Press Enter to continue..."

echo ""
echo "Applying schema..."
render psql dpg-d3t3i83e5dus73flkang-a < schema-postgresql.sql

echo ""
echo "Creating test user..."
render psql dpg-d3t3i83e5dus73flkang-a << 'EOF'
INSERT INTO users (id, email, password_hash, first_name, last_name, verified, created_at, updated_at)
VALUES (
    encode(gen_random_bytes(16), 'base64'),
    'sparkingmatt@gmail.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Matt',
    'Sparkman',
    true,
    NOW(),
    NOW()
)
ON CONFLICT (email) DO UPDATE SET
    password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    updated_at = NOW();

SELECT 'User created/updated: ' || email || ' (ID: ' || id || ')' FROM users WHERE email = 'sparkingmatt@gmail.com';
EOF

echo ""
echo "=== Setup Complete! ==="
echo ""
echo "You can now login at:"
echo "  https://collagendirect.onrender.com/login"
echo ""
echo "Credentials:"
echo "  Email: sparkingmatt@gmail.com"
echo "  Password: TempPassword123!"
echo ""
