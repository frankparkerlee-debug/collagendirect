<?php
/**
 * Add NPI Field to Users Table
 *
 * Adds National Provider Identifier field for billing export
 * Run via: https://collagendirect.health/admin/add-npi-to-users.php
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Adding NPI Field to Users Table ===\n\n";

try {
    // Check if column already exists
    echo "Step 1: Checking existing columns...\n";
    $checkStmt = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'users'
        AND column_name = 'npi'
    ");
    $exists = $checkStmt->fetch();

    if ($exists) {
        echo "  - Column 'npi' already exists\n\n";
        echo "✓ No changes needed\n";
        exit(0);
    }

    // Add NPI column
    echo "\nStep 2: Adding NPI column...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN npi VARCHAR(10)");
    echo "  ✓ Added column: npi\n";

    // Add index for faster lookups
    echo "\nStep 3: Adding index...\n";
    $pdo->exec("CREATE INDEX idx_users_npi ON users(npi)");
    echo "  ✓ Index created\n";

    // Add column comment
    echo "\nStep 4: Adding column comment...\n";
    $pdo->exec("COMMENT ON COLUMN users.npi IS 'National Provider Identifier (10-digit number)'");
    echo "  ✓ Comment added\n";

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✓ SUCCESS! NPI field added to users table\n\n";

    echo "New Field:\n";
    echo "  - npi (VARCHAR 10): National Provider Identifier\n\n";

    echo "Next Steps:\n";
    echo "1. Update physician user records with their NPI numbers\n";
    echo "2. NPI will be included in billing export CSV\n";
    echo "3. Required for claim submission to insurance companies\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
