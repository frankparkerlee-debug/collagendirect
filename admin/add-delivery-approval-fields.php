<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Adding Delivery Approval & AOB Fields ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
  // Check if columns exist and add them if not
  $columnsToAdd = [
    'aob_viewed_at' => 'TIMESTAMP NULL',
    'aob_signed_at' => 'TIMESTAMP NULL',
    'aob_signature_ip' => 'VARCHAR(64) NULL',
    'aob_signature_user_agent' => 'TEXT NULL',
    'patient_name_snapshot' => 'VARCHAR(255) NULL',
    'patient_dob_snapshot' => 'DATE NULL',
    'patient_address_snapshot' => 'TEXT NULL',
    'order_product_snapshot' => 'VARCHAR(255) NULL',
    'order_physician_snapshot' => 'VARCHAR(255) NULL',
    'order_physician_npi_snapshot' => 'VARCHAR(20) NULL',
    'order_date_snapshot' => 'DATE NULL',
    'confirmation_method' => 'VARCHAR(50) NULL'
  ];

  $existingColumns = [];
  $columnsQuery = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'delivery_confirmations'
  ");
  while ($row = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
    $existingColumns[] = $row['column_name'];
  }

  echo "Existing columns: " . implode(', ', $existingColumns) . "\n\n";

  foreach ($columnsToAdd as $column => $definition) {
    if (in_array($column, $existingColumns)) {
      echo "  - Column '$column' already exists\n";
    } else {
      $pdo->exec("ALTER TABLE delivery_confirmations ADD COLUMN $column $definition");
      echo "  + Added column '$column'\n";
    }
  }

  echo "\n=== Migration Complete ===\n";
  echo "\nNew fields added for compliance tracking:\n";
  echo "- AOB viewing and signing timestamps\n";
  echo "- Patient info snapshot at time of signing\n";
  echo "- Order/physician info snapshot\n";
  echo "- IP address and user agent for audit\n";

} catch (Throwable $e) {
  echo "\n! Error: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
