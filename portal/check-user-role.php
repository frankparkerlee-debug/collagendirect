<?php
// Temporary script to check and fix user role
declare(strict_types=1);

// Security key
$SECRET_KEY = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $SECRET_KEY) {
    http_response_code(403);
    die('Access denied. Provide ?key=SECRET in URL');
}

header('Content-Type: text/plain; charset=utf-8');

// Database connection
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

    echo "=== Checking sparkingmatt@gmail.com role ===\n\n";

    // Check current role
    $stmt = $pdo->prepare("SELECT id, email, role, first_name, last_name FROM users WHERE email = ?");
    $stmt->execute(['sparkingmatt@gmail.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "Current user data:\n";
        echo "  ID: {$user['id']}\n";
        echo "  Email: {$user['email']}\n";
        echo "  Name: {$user['first_name']} {$user['last_name']}\n";
        echo "  Role: {$user['role']}\n\n";

        if ($user['role'] !== 'superadmin') {
            echo "⚠ Role is NOT superadmin. Fixing...\n";
            $pdo->prepare("UPDATE users SET role = 'superadmin' WHERE email = ?")
                ->execute(['sparkingmatt@gmail.com']);
            echo "✓ Updated role to 'superadmin'\n\n";

            // Verify
            $stmt->execute(['sparkingmatt@gmail.com']);
            $updated = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Verified role: {$updated['role']}\n";
        } else {
            echo "✓ Role is already 'superadmin'\n";
        }
    } else {
        echo "❌ User not found!\n";
    }

    echo "\n=== All superadmin users ===\n";
    $stmt = $pdo->query("SELECT email, role, first_name, last_name FROM users WHERE role = 'superadmin'");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        echo "  - {$u['email']} ({$u['first_name']} {$u['last_name']})\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
