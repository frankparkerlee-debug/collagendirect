<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Creating Delivery Confirmations Table ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
  // Check if table already exists
  $checkTable = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'delivery_confirmations'
    )
  ")->fetchColumn();

  if ($checkTable) {
    echo "✓ Table 'delivery_confirmations' already exists\n";
    echo "Checking structure...\n\n";
  } else {
    echo "Creating 'delivery_confirmations' table...\n\n";

    $pdo->exec("
      CREATE TABLE delivery_confirmations (
        id SERIAL PRIMARY KEY,
        order_id VARCHAR(64) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
        patient_phone VARCHAR(20) NOT NULL,
        patient_email VARCHAR(255),
        confirmation_token VARCHAR(64) NOT NULL UNIQUE,
        sms_sent_at TIMESTAMP NULL,
        sms_delivered_at TIMESTAMP NULL,
        sms_status VARCHAR(50) NULL,
        sms_sid VARCHAR(100) NULL,
        confirmed_at TIMESTAMP NULL,
        confirmed_ip VARCHAR(64) NULL,
        confirmed_user_agent TEXT NULL,
        reminder_sent_at TIMESTAMP NULL,
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT NOW(),
        updated_at TIMESTAMP NOT NULL DEFAULT NOW()
      )
    ");

    echo "✓ Table 'delivery_confirmations' created successfully\n";

    // Create indexes separately (PostgreSQL syntax)
    echo "Creating indexes...\n";

    $pdo->exec("CREATE INDEX idx_delivery_conf_order_id ON delivery_confirmations(order_id)");
    echo "  ✓ Index on order_id created\n";

    $pdo->exec("CREATE INDEX idx_delivery_conf_token ON delivery_confirmations(confirmation_token)");
    echo "  ✓ Index on confirmation_token created\n";

    $pdo->exec("CREATE INDEX idx_delivery_conf_status ON delivery_confirmations(confirmed_at, sms_sent_at)");
    echo "  ✓ Index on confirmation status created\n\n";
  }

  // Show table structure
  echo "Table structure:\n";
  echo "----------------------------------------\n";
  $columns = $pdo->query("
    SELECT column_name, data_type, character_maximum_length, is_nullable, column_default
    FROM information_schema.columns
    WHERE table_name = 'delivery_confirmations'
    ORDER BY ordinal_position
  ")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($columns as $col) {
    $type = $col['data_type'];
    if ($col['character_maximum_length']) {
      $type .= "({$col['character_maximum_length']})";
    }
    $nullable = $col['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
    $default = $col['column_default'] ? " DEFAULT {$col['column_default']}" : '';
    echo "  {$col['column_name']}: {$type} {$nullable}{$default}\n";
  }

  echo "\n✓ Migration complete!\n";
  echo "\nUsage:\n";
  echo "- SMS will be sent 2-3 days after order is marked as 'delivered'\n";
  echo "- Patient clicks confirmation link in SMS\n";
  echo "- Confirmation timestamp recorded for compliance/audit\n";
  echo "- Admin can view confirmation status in orders dashboard\n";

} catch (Throwable $e) {
  echo "\n✗ Error: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}

echo "\n=== Migration Complete ===\n";
