<?php
/**
 * Fix order_status_changes trigger
 *
 * Issues:
 * 1. order_id should be VARCHAR not INTEGER (orders.id is VARCHAR)
 * 2. Trigger was incorrectly mapping rx_note columns to tracking/carrier
 * 3. Need to use proper tracking_number and carrier columns from orders table
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../api/db.php';

echo "=== Fixing Order Status Changes Trigger ===\n\n";

try {
  echo "Step 1: Checking current schema...\n";

  // Check orders.id type
  $stmt = $pdo->query("
    SELECT data_type
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = 'id'
  ");
  $ordersIdType = $stmt->fetchColumn();
  echo "  orders.id type: $ordersIdType\n";

  // Check order_status_changes.order_id type
  $stmt = $pdo->query("
    SELECT data_type
    FROM information_schema.columns
    WHERE table_name = 'order_status_changes' AND column_name = 'order_id'
  ");
  $statusOrderIdType = $stmt->fetchColumn();
  echo "  order_status_changes.order_id type: $statusOrderIdType\n\n";

  // Step 2: Alter order_status_changes.order_id to VARCHAR if needed
  if ($statusOrderIdType !== 'character varying') {
    echo "Step 2: Converting order_status_changes.order_id to VARCHAR...\n";
    $pdo->exec("
      ALTER TABLE order_status_changes
      ALTER COLUMN order_id TYPE VARCHAR(64);
    ");
    echo "  ✓ Column type converted\n\n";
  } else {
    echo "Step 2: order_status_changes.order_id already VARCHAR\n\n";
  }

  // Step 3: Drop and recreate the trigger with correct logic
  echo "Step 3: Recreating trigger function...\n";

  $sql = "
    -- Drop existing trigger
    DROP TRIGGER IF EXISTS trigger_log_status_change ON orders;

    -- Drop old function if exists
    DROP FUNCTION IF EXISTS log_order_status_change();

    -- Create corrected function
    CREATE OR REPLACE FUNCTION log_order_status_change()
    RETURNS TRIGGER AS \$\$
    BEGIN
      -- Only log when status actually changes
      IF (TG_OP = 'UPDATE' AND OLD.status IS DISTINCT FROM NEW.status) THEN
        INSERT INTO order_status_changes (
          order_id,
          old_status,
          new_status,
          changed_by,
          tracking_code,
          carrier
        ) VALUES (
          NEW.id,  -- No cast needed, both VARCHAR now
          OLD.status,
          NEW.status,
          NEW.reviewed_by,
          NEW.tracking_number,  -- Correct column for tracking
          NEW.carrier           -- Correct column for carrier
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
  echo "  ✓ Trigger function recreated\n";
  echo "  ✓ Trigger recreated\n\n";

  // Step 4: Verify
  echo "Step 4: Verifying trigger...\n";
  $stmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.triggers
    WHERE trigger_name = 'trigger_log_status_change'
  ");
  $result = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($result['count'] > 0) {
    echo "  ✓ Trigger verified and active\n\n";
  } else {
    echo "  ✗ Trigger not found!\n\n";
    exit(1);
  }

  echo "=== Fix Complete ===\n";
  echo "The trigger now:\n";
  echo "- Uses VARCHAR for order_id (matching orders.id)\n";
  echo "- Uses tracking_number and carrier columns correctly\n";
  echo "- Will log status changes properly\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  exit(1);
}
