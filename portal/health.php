<?php
// /public/portal/health.php
declare(strict_types=1);

// Setup mode: /portal/health.php?setup=temp-setup-token-2024
if (isset($_GET['setup']) && $_GET['setup'] === 'temp-setup-token-2024') {
    // Send headers FIRST before any output
    header('Content-Type: text/plain; charset=utf-8');

    echo "=== CollagenDirect Database Setup ===\n\n";

    // Database connection (no db.php to avoid header conflicts)
    $DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
    $DB_NAME = getenv('DB_NAME') ?: 'collagen_db';
    $DB_USER = getenv('DB_USER') ?: 'postgres';
    $DB_PASS = getenv('DB_PASS') ?: '';
    $DB_PORT = getenv('DB_PORT') ?: '5432';

    try {
        $pdo = new PDO(
            "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}",
            $DB_USER,
            $DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage() . "\n");
    }

    echo "✓ Connected to database\n";
    echo "  Host: $DB_HOST\n";
    echo "  Database: $DB_NAME\n\n";

    // Check if tables exist
    echo "Checking schema...\n";
    $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables) || !in_array('users', $tables)) {
        echo "Applying schema...\n";
        $schema = file_get_contents(__DIR__ . '/../schema-postgresql.sql');
        $pdo->exec($schema);
        echo "✓ Schema applied\n\n";
    } else {
        echo "✓ Schema exists\n\n";
    }

    // Create user
    $email = 'sparkingmatt@gmail.com';
    $password = 'TempPassword123!';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    echo "Creating user: $email\n";

    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->fetch()) {
        $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?")
            ->execute([$hash, $email]);
        echo "✓ Password updated\n";
    } else {
        $userId = rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=');
        $pdo->prepare("INSERT INTO users (id, email, password_hash, first_name, last_name, verified, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())")
            ->execute([$userId, $email, $hash, 'Matt', 'Sparkman', true]);
        echo "✓ User created\n";
    }

    echo "\n=== Setup Complete ===\n";
    echo "URL: https://collagendirect.onrender.com/login\n";
    echo "Email: $email\n";
    echo "Password: $password\n";

    exit;
}

// Regular health check
echo "OK";
