<?php
// Setup script to create test user in production database
// Run this via: render ssh srv-d3sn5lm3jp1c739tjoj0 'php /opt/render/project/src/setup-user.php'

echo "=== CollagenDirect User Setup ===\n\n";

// Database connection from environment
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'collagen_db';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '5432';

echo "Connecting to database...\n";
echo "Host: $DB_HOST\n";
echo "Database: $DB_NAME\n";
echo "User: $DB_USER\n\n";

try {
    $pdo = new PDO(
        "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "✓ Connected to database\n\n";
} catch (PDOException $e) {
    die("✗ Database connection failed: " . $e->getMessage() . "\n");
}

// Check if users table exists
echo "Checking database schema...\n";
try {
    $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . implode(', ', $tables) . "\n\n";

    if (!in_array('users', $tables)) {
        echo "✗ Users table does not exist!\n";
        echo "You need to apply the schema first.\n";
        echo "Run: cat schema-postgresql.sql | render psql dpg-d3t3i83e5dus73flkang-a\n";
        exit(1);
    }
    echo "✓ Users table exists\n\n";
} catch (PDOException $e) {
    die("✗ Error checking schema: " . $e->getMessage() . "\n");
}

// Create/update test user
$email = 'sparkingmatt@gmail.com';
$password = 'TempPassword123!';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Creating/updating user: $email\n";

try {
    // Check if user exists
    $check = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $check->execute([$email]);
    $existing = $check->fetch();

    if ($existing) {
        echo "User exists (ID: {$existing['id']})\n";
        echo "Updating password...\n";

        $update = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?");
        $update->execute([$hash, $email]);

        echo "✓ Password updated\n";
    } else {
        echo "Creating new user...\n";

        $userId = rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=');
        $insert = $pdo->prepare("
            INSERT INTO users (id, email, password_hash, first_name, last_name, verified, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $insert->execute([$userId, $email, $hash, 'Matt', 'Sparkman', true]);

        echo "✓ User created (ID: $userId)\n";
    }

    // Verify
    $verify = $pdo->prepare("SELECT id, email, first_name, last_name, verified FROM users WHERE email = ?");
    $verify->execute([$email]);
    $user = $verify->fetch();

    echo "\n=== Setup Complete ===\n";
    echo "User ID: {$user['id']}\n";
    echo "Email: {$user['email']}\n";
    echo "Name: {$user['first_name']} {$user['last_name']}\n";
    echo "Verified: " . ($user['verified'] ? 'Yes' : 'No') . "\n";
    echo "\n=== Login Details ===\n";
    echo "URL: https://collagendirect.onrender.com/login\n";
    echo "Email: $email\n";
    echo "Password: $password\n";

} catch (PDOException $e) {
    die("✗ Error creating/updating user: " . $e->getMessage() . "\n");
}
