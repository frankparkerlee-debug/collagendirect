<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

echo "=== Fixing Patient State Column Conflict ===\n\n";

try {
  // Step 1: Add address_state column for geographical state
  echo "Step 1: Adding address_state column...\n";
  $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS address_state VARCHAR(2)");
  echo "  ✓ address_state column added\n\n";

  // Step 2: Migrate existing state data to address_state if it looks like a state abbreviation
  echo "Step 2: Migrating address state data...\n";
  $pdo->exec("
    UPDATE patients
    SET address_state = state
    WHERE LENGTH(state) = 2
      AND state ~ '^[A-Z]{2}$'
      AND state NOT IN ('pending', 'approved', 'not_covered', 'need_info', 'active', 'inactive')
  ");
  echo "  ✓ Migrated state abbreviations to address_state\n\n";

  // Step 3: Set state to 'pending' for patients where it was a state abbreviation
  echo "Step 3: Setting authorization status to 'pending' for affected patients...\n";
  $pdo->exec("
    UPDATE patients
    SET state = 'pending'
    WHERE address_state IS NOT NULL
      AND state = address_state
  ");
  echo "  ✓ Authorization status set to 'pending'\n\n";

  // Step 4: Set default to 'pending' for any NULL authorization status
  echo "Step 4: Setting default authorization status for NULL values...\n";
  $pdo->exec("
    UPDATE patients
    SET state = 'pending'
    WHERE state IS NULL OR state = ''
  ");
  echo "  ✓ Default authorization status applied\n\n";

  // Step 5: Add comments for clarity
  echo "Step 5: Adding column comments...\n";
  $pdo->exec("COMMENT ON COLUMN patients.state IS 'Authorization status: pending, approved, not_covered, need_info, active, inactive'");
  $pdo->exec("COMMENT ON COLUMN patients.address_state IS 'US state abbreviation for patient address (e.g., CA, NY, TX)'");
  echo "  ✓ Comments added\n\n";

  // Step 6: Show summary
  $counts = $pdo->query("
    SELECT
      COUNT(*) FILTER (WHERE address_state IS NOT NULL) as with_address,
      COUNT(*) FILTER (WHERE state = 'pending') as pending_status,
      COUNT(*) as total
    FROM patients
  ")->fetch();

  echo "=== Migration Complete ===\n";
  echo "Total patients: {$counts['total']}\n";
  echo "With address state: {$counts['with_address']}\n";
  echo "With 'pending' status: {$counts['pending_status']}\n\n";
  echo "✓ The 'state' column is now exclusively for authorization status\n";
  echo "✓ The 'address_state' column is now for geographical state abbreviations\n";
  echo "✓ New patients will default to 'pending' authorization status\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}
