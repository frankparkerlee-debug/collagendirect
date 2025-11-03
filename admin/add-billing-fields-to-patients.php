<?php
/**
 * Add Billing Fields to Patients Table
 *
 * Adds insurance and provider information needed for billing export
 * Run via: https://collagendirect.health/admin/add-billing-fields-to-patients.php
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Adding Billing Fields to Patients Table ===\n\n";

try {
    // Check if columns already exist
    echo "Step 1: Checking existing columns...\n";
    $checkStmt = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'patients'
        AND column_name IN (
            'insurance_company', 'insurance_id', 'group_number',
            'sex', 'npi'
        )
    ");
    $existingCols = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  Found existing columns: " . implode(', ', $existingCols) . "\n\n";

    // Add columns if they don't exist
    echo "Step 2: Adding missing billing fields...\n";

    $columnsToAdd = [
        'sex' => "ALTER TABLE patients ADD COLUMN sex VARCHAR(1) DEFAULT 'U'",
        'insurance_company' => "ALTER TABLE patients ADD COLUMN insurance_company VARCHAR(255)",
        'insurance_id' => "ALTER TABLE patients ADD COLUMN insurance_id VARCHAR(100)",
        'group_number' => "ALTER TABLE patients ADD COLUMN group_number VARCHAR(100)"
    ];

    foreach ($columnsToAdd as $col => $sql) {
        if (!in_array($col, $existingCols)) {
            $pdo->exec($sql);
            echo "  ✓ Added column: {$col}\n";
        } else {
            echo "  - Column already exists: {$col}\n";
        }
    }

    // Add column comments for documentation
    echo "\nStep 3: Adding column comments...\n";
    $pdo->exec("COMMENT ON COLUMN patients.sex IS 'Patient biological sex: M=Male, F=Female, U=Unknown'");
    $pdo->exec("COMMENT ON COLUMN patients.insurance_company IS 'Primary insurance company name'");
    $pdo->exec("COMMENT ON COLUMN patients.insurance_id IS 'Insurance member/policy ID'");
    $pdo->exec("COMMENT ON COLUMN patients.group_number IS 'Insurance group/employer number'");
    echo "  ✓ Comments added\n";

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✓ SUCCESS! Billing fields added to patients table\n\n";

    echo "New Fields:\n";
    echo "  - sex (VARCHAR 1): Patient biological sex\n";
    echo "  - insurance_company (VARCHAR 255): Insurance provider name\n";
    echo "  - insurance_id (VARCHAR 100): Member/policy ID\n";
    echo "  - group_number (VARCHAR 100): Group/employer number\n\n";

    echo "These fields will be included in billing export CSV.\n";
    echo "Update patient records to populate insurance information.\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
