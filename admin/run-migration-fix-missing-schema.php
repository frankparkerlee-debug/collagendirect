<?php
/**
 * Migration: Fix missing tables and columns from production
 *
 * This migration adds:
 * 1. billable_encounters table
 * 2. address_state column to patients (rename from state)
 * 3. Any other missing schema elements
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== Fix Missing Schema Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Create billable_encounters table
  echo "1. Creating billable_encounters table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS billable_encounters (
      id VARCHAR(64) PRIMARY KEY,
      patient_id VARCHAR(64) NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
      physician_id VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      encounter_date TIMESTAMP NOT NULL,
      charge_amount DECIMAL(10,2) DEFAULT 0.00,
      billing_code VARCHAR(50),
      diagnosis_code VARCHAR(50),
      notes TEXT,
      status VARCHAR(50) DEFAULT 'pending',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
  ");
  echo "   ✓ Created billable_encounters table\n\n";

  // 2. Add address_state column to patients (if it doesn't exist)
  echo "2. Adding address_state column to patients table...\n";

  // Check if 'state' column exists and 'address_state' doesn't
  $checkState = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'patients'
    AND column_name IN ('state', 'address_state')
  ")->fetchAll(PDO::FETCH_COLUMN);

  $hasState = in_array('state', $checkState);
  $hasAddressState = in_array('address_state', $checkState);

  if ($hasState && !$hasAddressState) {
    // Rename 'state' to 'address_state' to match code expectations
    echo "   → Renaming 'state' to 'address_state'...\n";
    $pdo->exec("ALTER TABLE patients RENAME COLUMN state TO address_state");
    echo "   ✓ Renamed column\n";
  } elseif (!$hasAddressState) {
    // Add address_state if it doesn't exist at all
    echo "   → Adding address_state column...\n";
    $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS address_state VARCHAR(10)");
    echo "   ✓ Added address_state column\n";
  } else {
    echo "   - address_state column already exists\n";
  }

  // 3. Add address_zip column to patients (if needed)
  echo "\n3. Checking address_zip column...\n";
  $hasZip = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'patients'
    AND column_name = 'zip'
  ")->fetch();

  if ($hasZip) {
    echo "   ✓ zip column exists\n";
  } else {
    echo "   → Adding zip column...\n";
    $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS zip VARCHAR(15)");
    echo "   ✓ Added zip column\n";
  }

  // 4. Create indexes for billable_encounters
  echo "\n4. Creating indexes for billable_encounters...\n";
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_billable_encounters_patient ON billable_encounters(patient_id)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_billable_encounters_physician ON billable_encounters(physician_id)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_billable_encounters_date ON billable_encounters(encounter_date)");
  echo "   ✓ Created indexes\n\n";

  $pdo->commit();
  echo "✓ Migration completed successfully!\n\n";
  echo "Next steps:\n";
  echo "- billable_encounters table is now available for revenue tracking\n";
  echo "- patients.address_state column matches code expectations\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  http_response_code(500);
  exit(1);
}
