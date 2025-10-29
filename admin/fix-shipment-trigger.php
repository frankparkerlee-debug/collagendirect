<?php
/**
 * Fix order_status_changes trigger to use correct column names
 *
 * Issue: Trigger references NEW.tracking_number and NEW.carrier
 * but the actual columns in orders table are rx_note_name and rx_note_mime
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../api/db.php';

echo "=== Fixing Shipment Tracking Trigger ===\n\n";

try {
  echo "Step 1: Checking current columns in orders table...\n";

  $stmt = $pdo->query("
    SELECT column_name, data_type
    FROM information_schema.columns
    WHERE table_name = 'orders'
    AND column_name IN ('tracking_number', 'carrier', 'rx_note_name', 'rx_note_mime')
    ORDER BY column_name
  ");
  $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($columns as $col) {
    echo "  - {$col['column_name']}: {$col['data_type']}\n";
  }
  echo "\n";

  // Step 2: Drop and recreate the trigger with correct column names
  echo "Step 2: Recreating trigger function with correct column names...\n";

  $sql = "
    -- Drop existing trigger
    DROP TRIGGER IF EXISTS trigger_log_status_change ON orders;

    -- Drop old function if exists
    DROP FUNCTION IF EXISTS log_order_status_change();

    -- Create corrected function using rx_note_name and rx_note_mime
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
          NEW.id,
          OLD.status,
          NEW.status,
          NEW.reviewed_by,
          NEW.rx_note_name,  -- Tracking number stored in rx_note_name
          NEW.rx_note_mime   -- Carrier stored in rx_note_mime
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

  // Step 3: Verify
  echo "Step 3: Verifying trigger...\n";
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
  echo "The trigger now correctly uses:\n";
  echo "- rx_note_name for tracking number\n";
  echo "- rx_note_mime for carrier\n";
  echo "\nYou can now save shipping information without errors.\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}
