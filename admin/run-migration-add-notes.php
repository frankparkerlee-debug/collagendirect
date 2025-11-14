<?php
/**
 * Run migration to add notes column to orders table
 *
 * Usage: Access via browser at /admin/run-migration-add-notes.php
 * or run via CLI: php admin/run-migration-add-notes.php
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== Adding notes column to orders table ===\n\n";

try {
  // Check if column already exists
  $checkStmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders'
    AND column_name = 'notes'
  ");

  $exists = $checkStmt->fetch();

  if ($exists) {
    echo "✓ Notes column already exists in orders table\n";
  } else {
    echo "Adding notes column...\n";

    // Add notes column
    $pdo->exec("ALTER TABLE orders ADD COLUMN notes TEXT");
    echo "✓ Added notes column to orders table\n";

    // Add index
    $pdo->exec("CREATE INDEX idx_orders_notes ON orders(notes) WHERE notes IS NOT NULL");
    echo "✓ Added index on notes column\n";

    // Add comment
    $pdo->exec("COMMENT ON COLUMN orders.notes IS 'Optional notes for the order, especially used for wholesale orders to include special instructions'");
    echo "✓ Added column comment\n";
  }

  echo "\n✓ Migration completed successfully!\n";

} catch (PDOException $e) {
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  http_response_code(500);
  exit(1);
}
