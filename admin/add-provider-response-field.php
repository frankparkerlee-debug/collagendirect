<?php
/**
 * Add provider_response field to patients table
 * Allows physicians to respond to manufacturer status comments
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../api/db.php';

echo "=== Adding Provider Response Fields ===\n\n";

try {
  echo "Step 1: Checking if columns exist...\n";

  $stmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'patients'
    AND column_name IN ('provider_response', 'provider_response_at', 'provider_response_by')
    ORDER BY column_name
  ");
  $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

  if (count($existing) > 0) {
    echo "  Found existing columns: " . implode(', ', $existing) . "\n\n";
  } else {
    echo "  No columns found yet\n\n";
  }

  echo "Step 2: Adding columns to patients table...\n";

  $pdo->exec("
    ALTER TABLE patients
      ADD COLUMN IF NOT EXISTS provider_response TEXT,
      ADD COLUMN IF NOT EXISTS provider_response_at TIMESTAMP,
      ADD COLUMN IF NOT EXISTS provider_response_by VARCHAR(64);
  ");

  echo "  ✓ Columns added\n\n";

  echo "Step 3: Creating index...\n";

  $pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_patients_provider_response
      ON patients(provider_response_at)
      WHERE provider_response IS NOT NULL;
  ");

  echo "  ✓ Index created\n\n";

  echo "Step 4: Adding column comments...\n";

  $pdo->exec("
    COMMENT ON COLUMN patients.provider_response IS 'Physician response to manufacturer status_comment';
    COMMENT ON COLUMN patients.provider_response_at IS 'When physician submitted their response';
    COMMENT ON COLUMN patients.provider_response_by IS 'User ID of physician who responded';
  ");

  echo "  ✓ Comments added\n\n";

  echo "=== Migration Complete ===\n";
  echo "Physicians can now respond to manufacturer comments about patient authorization.\n";
  echo "These responses will be visible to admin users in the patient management interface.\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}
