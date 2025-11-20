<?php
/**
 * Migration: Add order_number column to orders table
 * This allows grouping wholesale orders by their invoice number (WS-YYYYMMDD-XXX)
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

echo "=== ADDING ORDER_NUMBER COLUMN ===\n\n";

try {
  // Check if column already exists
  $checkCol = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders'
      AND column_name = 'order_number'
  ")->fetchColumn();

  if ($checkCol) {
    echo "✓ Column 'order_number' already exists in orders table.\n\n";
    echo "Migration complete!\n";
    exit(0);
  }

  echo "Adding 'order_number' column to orders table...\n";

  $pdo->exec("
    ALTER TABLE orders
    ADD COLUMN order_number VARCHAR(50) DEFAULT NULL
  ");

  echo "✓ Column added successfully.\n\n";

  echo "Creating index on order_number...\n";
  $pdo->exec("CREATE INDEX idx_orders_order_number ON orders(order_number)");
  echo "✓ Index created.\n\n";

  echo "Migration complete!\n";
  echo "\nNext steps:\n";
  echo "- Existing wholesale orders will have NULL order_number\n";
  echo "- New wholesale orders will have order_number populated automatically\n";
  echo "- You can backfill existing orders if needed\n";

} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
