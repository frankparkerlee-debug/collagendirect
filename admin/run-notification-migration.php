<?php
/**
 * Run notification tables migration via web
 * URL: /admin/run-notification-migration.php
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

// Load database connection
require_once __DIR__ . '/../api/db.php';

echo "=== CollagenDirect Notification Tables Migration ===\n\n";

try {
  // SQL to create tables
  $sql = "
    -- Create order_delivery_confirmations table
    CREATE TABLE IF NOT EXISTS order_delivery_confirmations (
      id SERIAL PRIMARY KEY,
      order_id INTEGER NOT NULL,
      patient_email VARCHAR(255) NOT NULL,
      confirmation_token VARCHAR(64) NOT NULL UNIQUE,
      sent_at TIMESTAMP NOT NULL DEFAULT NOW(),
      confirmed_at TIMESTAMP NULL,
      confirmed_ip VARCHAR(64) NULL,
      reminder_sent_at TIMESTAMP NULL,
      created_at TIMESTAMP NOT NULL DEFAULT NOW()
    );

    CREATE INDEX IF NOT EXISTS idx_delivery_token ON order_delivery_confirmations(confirmation_token);
    CREATE INDEX IF NOT EXISTS idx_delivery_order ON order_delivery_confirmations(order_id);
    CREATE INDEX IF NOT EXISTS idx_delivery_sent_pending ON order_delivery_confirmations(sent_at, confirmed_at);

    -- Create order_status_changes table
    CREATE TABLE IF NOT EXISTS order_status_changes (
      id SERIAL PRIMARY KEY,
      order_id INTEGER NOT NULL,
      old_status VARCHAR(50) NULL,
      new_status VARCHAR(50) NOT NULL,
      changed_by VARCHAR(64) NULL,
      changed_at TIMESTAMP NOT NULL DEFAULT NOW(),
      notification_sent_at TIMESTAMP NULL,
      tracking_code VARCHAR(255) NULL,
      carrier VARCHAR(100) NULL,
      notes TEXT NULL
    );

    CREATE INDEX IF NOT EXISTS idx_status_order ON order_status_changes(order_id);
    CREATE INDEX IF NOT EXISTS idx_status_notification ON order_status_changes(notification_sent_at);
    CREATE INDEX IF NOT EXISTS idx_status_changed ON order_status_changes(changed_at);
    CREATE INDEX IF NOT EXISTS idx_status_new_status ON order_status_changes(new_status);

    -- Create function to automatically log status changes
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
          NEW.rx_note_name,  -- tracking stored in rx_note_name
          NEW.rx_note_mime   -- carrier stored in rx_note_mime
        );
      END IF;
      RETURN NEW;
    END;
    \$\$ LANGUAGE plpgsql;

    -- Create trigger on orders table
    DROP TRIGGER IF EXISTS trigger_log_status_change ON orders;
    CREATE TRIGGER trigger_log_status_change
      AFTER UPDATE ON orders
      FOR EACH ROW
      EXECUTE FUNCTION log_order_status_change();
  ";

  echo "Executing migration...\n";
  $pdo->exec($sql);

  echo "\n✓ Migration completed successfully!\n\n";

  // Verify tables were created
  echo "Verifying tables...\n";
  $tables = [
    'order_delivery_confirmations',
    'order_status_changes'
  ];

  foreach ($tables as $table) {
    $stmt = $pdo->prepare("
      SELECT COUNT(*) as count
      FROM information_schema.tables
      WHERE table_name = ?
    ");
    $stmt->execute([$table]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
      echo "  ✓ Table '$table' exists\n";

      // Count rows
      $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
      $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
      echo "    Current rows: $count\n";
    } else {
      echo "  ✗ Table '$table' NOT FOUND\n";
    }
  }

  // Verify trigger was created
  echo "\nVerifying trigger...\n";
  $stmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.triggers
    WHERE trigger_name = 'trigger_log_status_change'
  ");
  $result = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($result['count'] > 0) {
    echo "  ✓ Trigger 'trigger_log_status_change' exists\n";
  } else {
    echo "  ✗ Trigger 'trigger_log_status_change' NOT FOUND\n";
  }

  echo "\n=== Migration Complete ===\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
} catch (Exception $e) {
  echo "\n✗ Error:\n";
  echo "  " . $e->getMessage() . "\n";
  exit(1);
}
