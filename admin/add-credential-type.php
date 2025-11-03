<?php
/**
 * Migration: Add credential_type to users table
 *
 * Purpose: Track provider credentials (MD, DO, NP, PA, RN) for billing compliance
 *
 * Billing Rules:
 * - MD/DO: Can bill E/M codes at 100% rate
 * - NP: Can bill E/M codes at 85-100% rate (state dependent)
 * - PA: Can bill E/M codes at 85% rate (requires supervision)
 * - RN: Cannot bill E/M codes (can only bill 99211, nursing services)
 *
 * Run: https://collagendirect.health/admin/add-credential-type.php
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Add Credential Type Migration ===\n\n";

try {
    // Check if column exists
    $checkCol = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'users'
        AND column_name = 'credential_type'
    ");

    if ($checkCol->rowCount() > 0) {
        echo "✓ credential_type column already exists\n";
    } else {
        // Add credential_type column
        $pdo->exec("
            ALTER TABLE users
            ADD COLUMN credential_type VARCHAR(10) DEFAULT 'MD'
        ");
        echo "✓ Added credential_type column\n";

        // Add check constraint
        $pdo->exec("
            ALTER TABLE users
            ADD CONSTRAINT check_credential_type
            CHECK (credential_type IN ('MD', 'DO', 'NP', 'PA', 'RN', 'OTHER'))
        ");
        echo "✓ Added credential type constraint (MD, DO, NP, PA, RN, OTHER)\n";
    }

    // Add supervising_physician_id for PAs
    $checkSupervising = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'users'
        AND column_name = 'supervising_physician_id'
    ");

    if ($checkSupervising->rowCount() > 0) {
        echo "✓ supervising_physician_id column already exists\n";
    } else {
        $pdo->exec("
            ALTER TABLE users
            ADD COLUMN supervising_physician_id VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL
        ");
        echo "✓ Added supervising_physician_id column (for PA supervision tracking)\n";

        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_users_supervising_physician
            ON users(supervising_physician_id)
        ");
        echo "✓ Added index on supervising_physician_id\n";
    }

    // Add comments for documentation
    $pdo->exec("
        COMMENT ON COLUMN users.credential_type IS
        'Provider credential: MD (physician), DO (osteopath), NP (nurse practitioner), PA (physician assistant), RN (registered nurse), OTHER'
    ");

    $pdo->exec("
        COMMENT ON COLUMN users.supervising_physician_id IS
        'For PAs: References the supervising physician user ID for billing compliance'
    ");

    echo "\n=== Migration Complete ===\n";
    echo "\nCredential Types:\n";
    echo "  MD  - Medical Doctor (100% E/M reimbursement)\n";
    echo "  DO  - Doctor of Osteopathic Medicine (100% E/M reimbursement)\n";
    echo "  NP  - Nurse Practitioner (85-100% E/M reimbursement)\n";
    echo "  PA  - Physician Assistant (85% E/M reimbursement, requires supervision)\n";
    echo "  RN  - Registered Nurse (cannot bill E/M, can bill 99211)\n";
    echo "  OTHER - Other credential types\n";

    echo "\nNext Steps:\n";
    echo "1. Update user profiles to set credential_type\n";
    echo "2. For PAs: Set supervising_physician_id\n";
    echo "3. Update billing logic to check credentials before E/M billing\n";

} catch (PDOException $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
