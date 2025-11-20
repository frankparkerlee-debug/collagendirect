<?php
/**
 * Migration: Add payment tracking columns to orders table
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== Adding Payment Tracking Columns ===\n\n";

try {
  // Check if columns exist
  $checkColumns = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders'
      AND column_name IN ('amount_due', 'amount_paid', 'balance_due')
  ")->fetchAll(PDO::FETCH_COLUMN);

  $hasAmountDue = in_array('amount_due', $checkColumns);
  $hasAmountPaid = in_array('amount_paid', $checkColumns);
  $hasBalanceDue = in_array('balance_due', $checkColumns);

  echo "Current status:\n";
  echo "  - amount_due: " . ($hasAmountDue ? "EXISTS" : "MISSING") . "\n";
  echo "  - amount_paid: " . ($hasAmountPaid ? "EXISTS" : "MISSING") . "\n";
  echo "  - balance_due: " . ($hasBalanceDue ? "EXISTS" : "MISSING") . "\n\n";

  // Add missing columns
  $columnsAdded = 0;

  if (!$hasAmountDue) {
    echo "Adding amount_due column...\n";
    $pdo->exec("ALTER TABLE orders ADD COLUMN amount_due NUMERIC(10,2) DEFAULT 0");
    $columnsAdded++;
    echo "✓ amount_due column added\n\n";
  }

  if (!$hasAmountPaid) {
    echo "Adding amount_paid column...\n";
    $pdo->exec("ALTER TABLE orders ADD COLUMN amount_paid NUMERIC(10,2) DEFAULT 0");
    $columnsAdded++;
    echo "✓ amount_paid column added\n\n";
  }

  if (!$hasBalanceDue) {
    echo "Adding balance_due column...\n";
    $pdo->exec("ALTER TABLE orders ADD COLUMN balance_due NUMERIC(10,2) DEFAULT 0");
    $columnsAdded++;
    echo "✓ balance_due column added\n\n";
  }

  if ($columnsAdded === 0) {
    echo "✓ All payment tracking columns already exist. No changes needed.\n";
  } else {
    echo "✓ Migration complete! Added $columnsAdded column(s).\n";
  }

  // Initialize payment tracking for existing wholesale orders
  echo "\nInitializing payment tracking for existing wholesale orders...\n";

  $updateStmt = $pdo->query("
    UPDATE orders o
    SET
      amount_due = COALESCE(
        o.qty_per_change * o.product_price * COALESCE(
          (SELECT pieces_per_box FROM products WHERE id = o.product_id),
          10
        ),
        0
      ),
      balance_due = COALESCE(
        o.qty_per_change * o.product_price * COALESCE(
          (SELECT pieces_per_box FROM products WHERE id = o.product_id),
          10
        ),
        0
      )
    WHERE o.billed_by = 'practice_dme'
      AND o.amount_due = 0
      AND o.balance_due = 0
      AND o.qty_per_change > 0
  ");

  $updatedRows = $updateStmt->rowCount();
  echo "✓ Initialized payment tracking for $updatedRows wholesale order(s)\n";

  echo "\n✓✓✓ All done! ✓✓✓\n";

} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
