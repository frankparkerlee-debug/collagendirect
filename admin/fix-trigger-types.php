<?php
/**
 * Fix database trigger type mismatch
 * The orders.id is VARCHAR but order_status_changes.order_id is INTEGER
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../api/db.php';

echo "=== Fixing Trigger Type Mismatch ===\n\n";

try {
  // First, check the actual data type of orders.id
  $stmt = $pdo->query("
    SELECT data_type
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = 'id'
  ");
  $ordersIdType = $stmt->fetchColumn();
  echo "orders.id type: $ordersIdType\n";

  // Check order_status_changes.order_id type
  $stmt = $pdo->query("
    SELECT data_type
    FROM information_schema.columns
    WHERE table_name = 'order_status_changes' AND column_name = 'order_id'
  ");
  $statusOrderIdType = $stmt->fetchColumn();
  echo "order_status_changes.order_id type: $statusOrderIdType\n\n";

  // Drop and recreate the trigger with proper type casting
  echo "Recreating trigger with type cast...\n";

  $sql = "
    -- Drop existing trigger
    DROP TRIGGER IF EXISTS trigger_log_status_change ON orders;

    -- Recreate function with proper type casting
    CREATE OR REPLACE FUNCTION log_order_status_change()
    RETURNS TRIGGER AS \$\$
    BEGIN
      IF (TG_OP = 'UPDATE' AND OLD.status IS DISTINCT FROM NEW.status) THEN
        INSERT INTO order_status_changes (
          order_id,
          old_status,
          new_status,
          changed_by,
          tracking_code,
          carrier
        ) VALUES (
          CAST(NEW.id AS INTEGER),  -- Cast VARCHAR to INTEGER
          OLD.status,
          NEW.status,
          NEW.reviewed_by,
          NEW.rx_note_name,
          NEW.rx_note_mime
        );
      END IF;
      RETURN NEW;
    END;
    \$\$ LANGUAGE plpgsql;

    -- Recreate trigger
    CREATE TRIGGER trigger_log_status_change
      AFTER UPDATE ON orders
      FOR EACH ROW
      EXECUTE FUNCTION log_order_status_change();
  ";

  $pdo->exec($sql);
  echo "✓ Trigger recreated successfully!\n\n";

  // Verify
  $stmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.triggers
    WHERE trigger_name = 'trigger_log_status_change'
  ");
  $result = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($result['count'] > 0) {
    echo "✓ Trigger verified and working\n";
  } else {
    echo "✗ Trigger not found\n";
  }

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  exit(1);
}
