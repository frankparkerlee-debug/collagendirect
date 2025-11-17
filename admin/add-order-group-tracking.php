<?php
/**
 * Add order_group_id and product_type columns to orders table
 * This allows multiple products per wound to be tracked as separate orders
 * while maintaining their relationship through the group ID
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Adding Order Group Tracking ===\n\n";

try {
  echo "Step 1: Adding order_group_id column...\n";
  $pdo->exec("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS order_group_id VARCHAR(64)
  ");
  echo "  ✓ order_group_id column added\n\n";

  echo "Step 2: Adding product_type column...\n";
  $pdo->exec("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS product_type VARCHAR(20) DEFAULT 'primary'
  ");
  echo "  ✓ product_type column added (values: 'primary', 'secondary', 'additional')\n\n";

  echo "Step 3: Adding wound_index column...\n";
  $pdo->exec("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS wound_index INTEGER DEFAULT 0
  ");
  echo "  ✓ wound_index column added (tracks which wound this product is for)\n\n";

  echo "Step 4: Setting order_group_id for existing orders...\n";
  $pdo->exec("
    UPDATE orders
    SET order_group_id = id,
        product_type = 'primary',
        wound_index = 0
    WHERE order_group_id IS NULL
  ");
  echo "  ✓ Existing orders updated with order_group_id = id (backward compatibility)\n\n";

  echo "Step 5: Creating index for order_group_id...\n";
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_group_id ON orders(order_group_id)");
  echo "  ✓ Index created for faster grouped order queries\n\n";

  echo "Step 6: Adding comment for documentation...\n";
  $pdo->exec("
    COMMENT ON COLUMN orders.order_group_id IS 'Groups related orders together (e.g., primary + secondary + additional products for same wound)';
    COMMENT ON COLUMN orders.product_type IS 'Type of product: primary, secondary, or additional';
    COMMENT ON COLUMN orders.wound_index IS 'Which wound in the wounds_data array this order is for (0-based index)';
  ");
  echo "  ✓ Column comments added\n\n";

  // Show summary
  $summary = $pdo->query("
    SELECT
      COUNT(*) as total_orders,
      COUNT(DISTINCT order_group_id) as total_order_groups
    FROM orders
  ")->fetch();

  echo "=== Migration Complete ===\n";
  echo "Total orders: {$summary['total_orders']}\n";
  echo "Total order groups: {$summary['total_order_groups']}\n\n";
  echo "✓ Orders can now be grouped to track multiple products per wound\n";
  echo "✓ Each order has product_type (primary/secondary/additional) and wound_index\n";
  echo "✓ Revenue reporting will now include all products ordered for each wound\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}
