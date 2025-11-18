<?php
/**
 * Fix Order Groups Migration
 * Cleans up orphaned order_group_id values before adding foreign key constraint
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Fixing Order Groups Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // Step 1: Check if order_groups table exists
  echo "Step 1: Checking if order_groups table exists...\n";

  $tableExists = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.tables
    WHERE table_name = 'order_groups'
  ")->fetch();

  if ($tableExists['count'] == 0) {
    echo "  ✗ order_groups table doesn't exist yet\n";
    echo "  Please run run-migration-add-order-groups.php first\n\n";
    $pdo->rollBack();
    exit(1);
  }
  echo "  ✓ order_groups table exists\n\n";

  // Step 2: Check if order_group_id column exists
  echo "Step 2: Checking for orphaned order_group_id values...\n";

  $check = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.columns
    WHERE table_name = 'orders'
    AND column_name = 'order_group_id'
  ")->fetch();

  if ($check['count'] > 0) {
    // Check for orphaned values
    $orphaned = $pdo->query("
      SELECT COUNT(*) as count
      FROM orders o
      WHERE o.order_group_id IS NOT NULL
      AND NOT EXISTS (
        SELECT 1 FROM order_groups og WHERE og.id = o.order_group_id
      )
    ")->fetch();

    echo "  Found {$orphaned['count']} orders with orphaned order_group_id values\n";

    if ($orphaned['count'] > 0) {
      echo "  Clearing orphaned order_group_id values...\n";
      $pdo->exec("
        UPDATE orders
        SET order_group_id = NULL
        WHERE order_group_id IS NOT NULL
        AND NOT EXISTS (
          SELECT 1 FROM order_groups og WHERE og.id = order_group_id
        )
      ");
      echo "  ✓ Orphaned values cleared\n\n";
    } else {
      echo "  ✓ No orphaned values found\n\n";
    }
  } else {
    echo "  ✓ order_group_id column doesn't exist yet (will be created by main migration)\n\n";
  }

  // Step 3: Drop existing foreign key if it exists
  echo "Step 3: Dropping existing foreign key constraint if exists...\n";
  $pdo->exec("
    DO $$
    BEGIN
      IF EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_orders_group'
      ) THEN
        ALTER TABLE orders DROP CONSTRAINT fk_orders_group;
      END IF;
    END $$;
  ");
  echo "  ✓ Existing constraint dropped (if existed)\n\n";

  // Step 4: Now add the foreign key constraint
  echo "Step 4: Adding foreign key constraint...\n";
  $pdo->exec("
    ALTER TABLE orders
    ADD CONSTRAINT fk_orders_group
    FOREIGN KEY (order_group_id)
    REFERENCES order_groups(id)
    ON DELETE CASCADE
  ");
  echo "  ✓ Foreign key constraint added successfully\n\n";

  // Step 5: Add index
  echo "Step 5: Adding index on order_group_id...\n";
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_group ON orders(order_group_id)");
  echo "  ✓ Index created\n\n";

  $pdo->commit();

  echo "=== Migration Fix Complete ===\n";
  echo "✓ All orphaned order_group_id values cleared\n";
  echo "✓ Foreign key constraint successfully added\n";
  echo "✓ Database is ready for order groups functionality\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration fix failed:\n";
  echo "  Error: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}
