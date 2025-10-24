<?php
// portal/set-superadmin-roles.php
// One-time script to set superadmin roles
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

$SECRET_KEY = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $SECRET_KEY) {
    http_response_code(403);
    die('Access denied. Provide ?key=SECRET in URL');
}

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

    echo "Setting superadmin roles...\n\n";

    // Update both users to superadmin
    $stmt = $pdo->prepare("
        UPDATE users
        SET role = 'superadmin'
        WHERE email IN ('sparkingmatt@gmail.com', 'parker@senecawest.com')
    ");
    $stmt->execute();

    echo "✓ Updated " . $stmt->rowCount() . " users to superadmin\n\n";

    // Verify
    $stmt = $pdo->query("
        SELECT id, email, first_name, last_name, role
        FROM users
        WHERE email IN ('sparkingmatt@gmail.com', 'parker@senecawest.com')
    ");

    echo "Current superadmin users:\n";
    echo "========================\n\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Email: {$row['email']}\n";
        echo "Name: {$row['first_name']} {$row['last_name']}\n";
        echo "Role: {$row['role']}\n";
        echo "\n";
    }

    echo "✓ Done!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
