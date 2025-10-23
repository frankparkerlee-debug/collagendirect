<?php
// Web-accessible migration script for production database
// Access via: https://collagendirect.onrender.com/migrate.php?token=temp-setup-token-2024
// DELETE THIS FILE AFTER RUNNING!

$token = $_GET['token'] ?? '';
if ($token !== 'temp-setup-token-2024') {
    http_response_code(403);
    die('Access denied');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== CollagenDirect Database Migration ===\n\n";

require __DIR__ . '/api/db.php';

echo "✓ Connected to database\n";
echo "  Host: " . getenv('DB_HOST') . "\n";
echo "  Database: " . getenv('DB_NAME') . "\n\n";

try {
    echo "Adding missing columns to patients table...\n";

    // Add sex column
    $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS sex VARCHAR(10)");
    echo "  ✓ sex\n";

    // Add insurance fields
    $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS insurance_provider VARCHAR(255)");
    echo "  ✓ insurance_provider\n";

    $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS insurance_member_id VARCHAR(100)");
    echo "  ✓ insurance_member_id\n";

    $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS insurance_group_id VARCHAR(100)");
    echo "  ✓ insurance_group_id\n";

    $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS insurance_payer_phone VARCHAR(50)");
    echo "  ✓ insurance_payer_phone\n";

    // Add AOB IP tracking
    $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS aob_ip VARCHAR(100)");
    echo "  ✓ aob_ip\n";

    echo "\n=== Verifying columns ===\n";

    $result = $pdo->query("
        SELECT column_name, data_type, character_maximum_length
        FROM information_schema.columns
        WHERE table_name = 'patients'
          AND column_name IN ('sex', 'insurance_provider', 'insurance_member_id', 'insurance_group_id', 'insurance_payer_phone', 'aob_ip')
        ORDER BY column_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($result as $col) {
        echo "  ✓ {$col['column_name']} ({$col['data_type']}";
        if ($col['character_maximum_length']) {
            echo ", max length: {$col['character_maximum_length']}";
        }
        echo ")\n";
    }

    echo "\n=== Migration Complete! ===\n";
    echo "All missing columns have been added to the patients table.\n";
    echo "\n⚠️  DELETE this file (migrate.php) after use!\n";

} catch (Exception $e) {
    die("\n✗ Migration failed: " . $e->getMessage() . "\n");
}
