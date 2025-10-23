<?php
// Web-accessible setup script for production
// Access via: https://collagendirect.onrender.com/setup.php
// DELETE THIS FILE AFTER RUNNING!

// Security: Only allow from localhost or require a token
$token = $_GET['token'] ?? '';
$expectedToken = getenv('SETUP_TOKEN') ?: 'temp-setup-token-2024';

if ($token !== $expectedToken) {
    http_response_code(403);
    die('Access denied. Use: /setup.php?token=' . htmlspecialchars($expectedToken));
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== CollagenDirect Database Setup ===\n\n";

// Database connection
require __DIR__ . '/api/db.php';

echo "✓ Connected to database\n";
echo "  Host: " . getenv('DB_HOST') . "\n";
echo "  Database: " . getenv('DB_NAME') . "\n\n";

// Check tables
echo "Checking database schema...\n";
try {
    $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n\n";

    if (empty($tables)) {
        echo "✗ No tables found! Need to apply schema.\n";
        echo "\nApplying schema...\n";

        // Read and execute schema file
        $schema = file_get_contents(__DIR__ . '/schema-postgresql.sql');
        if ($schema === false) {
            die("✗ Could not read schema file\n");
        }

        $pdo->exec($schema);
        echo "✓ Schema applied successfully\n\n";
    } else {
        echo "✓ Schema already exists\n\n";
    }
} catch (Exception $e) {
    die("✗ Error: " . $e->getMessage() . "\n");
}

// Create test user
$email = 'sparkingmatt@gmail.com';
$password = 'TempPassword123!';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Creating/updating user: $email\n";

try {
    $check = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $check->execute([$email]);
    $existing = $check->fetch();

    if ($existing) {
        echo "User exists. Updating password...\n";
        $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?")
            ->execute([$hash, $email]);
    } else {
        echo "Creating new user...\n";
        $userId = rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=');
        $pdo->prepare("INSERT INTO users (id, email, password_hash, first_name, last_name, verified, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())")
            ->execute([$userId, $email, $hash, 'Matt', 'Sparkman', true]);
    }

    // Verify
    $user = $pdo->query("SELECT * FROM users WHERE email = '$email'")->fetch();

    echo "\n=== Setup Complete! ===\n\n";
    echo "Login Details:\n";
    echo "  URL: https://collagendirect.onrender.com/login\n";
    echo "  Email: $email\n";
    echo "  Password: $password\n";
    echo "  User ID: {$user['id']}\n";
    echo "  Verified: " . ($user['verified'] ? 'Yes' : 'No') . "\n";
    echo "\n✓ All done!\n";
    echo "\n⚠️  IMPORTANT: Delete this file (setup.php) after use!\n";

} catch (Exception $e) {
    die("✗ Error: " . $e->getMessage() . "\n");
}
