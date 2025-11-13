<?php
/**
 * Migration: Add billed_by column to orders table
 *
 * This enables the dual billing workflow:
 * - 'collagen_direct': Insurance orders (full documentation, Medicare rates)
 * - 'practice_dme': Wholesale orders (simplified workflow, wholesale pricing)
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== ADDING billed_by COLUMN TO ORDERS TABLE ===\n\n";

try {
  global $pdo;

  // Check if column already exists
  $stmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name='orders' AND column_name='billed_by'
  ");

  if ($stmt->rowCount() > 0) {
    echo "✓ Column 'billed_by' already exists in orders table.\n\n";

    // Show current distribution
    $dist = $pdo->query("
      SELECT billed_by, COUNT(*) as count
      FROM orders
      GROUP BY billed_by
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "Current distribution:\n";
    foreach ($dist as $row) {
      echo "  {$row['billed_by']}: {$row['count']} orders\n";
    }
    exit(0);
  }

  // Add the column
  echo "Adding 'billed_by' column...\n";
  $pdo->exec("
    ALTER TABLE orders
    ADD COLUMN billed_by VARCHAR(50) DEFAULT 'collagen_direct' NOT NULL
  ");

  echo "✓ Column added successfully.\n\n";

  // Verify
  $stmt = $pdo->query("
    SELECT column_name, data_type, column_default, is_nullable
    FROM information_schema.columns
    WHERE table_name='orders' AND column_name='billed_by'
  ");

  $col = $stmt->fetch(PDO::FETCH_ASSOC);
  echo "Column details:\n";
  echo "  Name: {$col['column_name']}\n";
  echo "  Type: {$col['data_type']}\n";
  echo "  Default: {$col['column_default']}\n";
  echo "  Nullable: {$col['is_nullable']}\n\n";

  // Count orders
  $count = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
  echo "✓ All {$count} existing orders defaulted to 'collagen_direct' (insurance billing).\n\n";

  echo "=== MIGRATION COMPLETE ===\n\n";
  echo "Next steps:\n";
  echo "1. Update order creation logic to set billed_by based on billing route selection\n";
  echo "2. Wholesale orders (billed_by='practice_dme') will appear in /admin/wholesale-orders.php\n";
  echo "3. Insurance orders (billed_by='collagen_direct') will continue using existing workflow\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
