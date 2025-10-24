<?php
// /portal/add-referral-only-flag.php
// Run once via web browser with secret key
declare(strict_types=1);

$SECRET_KEY = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $SECRET_KEY) {
    http_response_code(403);
    die('Access denied. Provide ?key=SECRET in URL');
}

header('Content-Type: text/plain; charset=utf-8');

echo "Adding is_referral_only flag to users table...\n\n";

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

    // Add column
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_referral_only BOOLEAN DEFAULT FALSE");
    echo "✓ Added is_referral_only column (defaults to FALSE)\n\n";

    // Verify
    $result = $pdo->query("
        SELECT column_name, data_type, column_default
        FROM information_schema.columns
        WHERE table_name = 'users' AND column_name = 'is_referral_only'
    ")->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo "Column details:\n";
        echo "  Name: {$result['column_name']}\n";
        echo "  Type: {$result['data_type']}\n";
        echo "  Default: {$result['column_default']}\n\n";

        // Show current users
        $users = $pdo->query("
            SELECT email, role, COALESCE(is_referral_only, FALSE) as is_referral_only
            FROM users
            ORDER BY email
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo "Current users:\n";
        foreach ($users as $u) {
            $refFlag = $u['is_referral_only'] ? 'YES' : 'NO';
            echo "  - {$u['email']} ({$u['role']}) - Referral Only: {$refFlag}\n";
        }

        echo "\n✓ Migration completed successfully!\n";
        echo "\nTo mark a user as referral-only, run:\n";
        echo "UPDATE users SET is_referral_only = TRUE WHERE email = 'user@example.com';\n";
    } else {
        echo "⚠ Column not found after creation. Please check manually.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
