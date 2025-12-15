<?php
/**
 * Migration: Fix rep_commission_rates column name
 *
 * The original migration created 'created_by' but code uses 'set_by'.
 * This adds the set_by column if it doesn't exist.
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
  require_once __DIR__ . '/db_cli.php';
}

echo "=== Fix rep_commission_rates: Add set_by column ===\n\n";

try {
  // Check if set_by column exists
  $checkStmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM information_schema.columns
    WHERE table_name = 'rep_commission_rates' AND column_name = 'set_by'
  ");
  $checkStmt->execute();
  $exists = (int)$checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

  if ($exists) {
    echo "✓ Column set_by already exists, skipping.\n";
    return;
  }

  // Add set_by column
  echo "1. Adding set_by column to rep_commission_rates...\n";
  $pdo->exec("ALTER TABLE rep_commission_rates ADD COLUMN set_by VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL");
  echo "   ✓ Added set_by column\n";

  // Copy data from created_by if it exists
  $checkCreatedBy = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM information_schema.columns
    WHERE table_name = 'rep_commission_rates' AND column_name = 'created_by'
  ");
  $checkCreatedBy->execute();
  $createdByExists = (int)$checkCreatedBy->fetch(PDO::FETCH_ASSOC)['count'] > 0;

  if ($createdByExists) {
    echo "2. Copying data from created_by to set_by...\n";
    $pdo->exec("UPDATE rep_commission_rates SET set_by = created_by WHERE set_by IS NULL");
    echo "   ✓ Copied existing data\n";
  }

  echo "\n✓ Migration completed successfully!\n";

} catch (PDOException $e) {
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  throw $e;
}
