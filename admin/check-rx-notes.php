<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Checking Prescription Notes in Orders ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
  // Check orders with rx_note_path
  $orders = $pdo->query("
    SELECT
      o.id,
      o.patient_id,
      o.rx_note_path,
      o.rx_note_name,
      o.rx_note_mime,
      o.created_at,
      p.first_name,
      p.last_name
    FROM orders o
    LEFT JOIN patients p ON p.id = o.patient_id
    WHERE o.rx_note_path IS NOT NULL
    ORDER BY o.created_at DESC
    LIMIT 20
  ")->fetchAll(PDO::FETCH_ASSOC);

  echo "Found " . count($orders) . " orders with prescription notes\n\n";

  if (count($orders) === 0) {
    echo "No orders have prescription notes uploaded.\n";
    echo "This is normal if physicians haven't uploaded any yet.\n\n";
    echo "To test:\n";
    echo "1. Go to physician portal\n";
    echo "2. Create a new order\n";
    echo "3. Upload a prescription note (rx_note field)\n";
    echo "4. Check admin/billing.php\n";
  } else {
    foreach ($orders as $o) {
      echo "========================================\n";
      echo "Order ID: {$o['id']}\n";
      echo "Patient: {$o['first_name']} {$o['last_name']}\n";
      echo "Created: {$o['created_at']}\n";
      echo "----------------------------------------\n";
      echo "DB rx_note_path: {$o['rx_note_path']}\n";
      echo "DB rx_note_name: " . ($o['rx_note_name'] ?? 'NULL') . "\n";
      echo "DB rx_note_mime: " . ($o['rx_note_mime'] ?? 'NULL') . "\n";

      if (!empty($o['rx_note_path'])) {
        // Check if file exists on persistent disk
        $persistentPath = '/var/data' . $o['rx_note_path'];
        $localPath = __DIR__ . '/../' . ltrim($o['rx_note_path'], '/');

        if (file_exists($persistentPath)) {
          echo "File Status: ✓ EXISTS on persistent disk\n";
          echo "File Path: $persistentPath\n";
          echo "File Size: " . filesize($persistentPath) . " bytes\n";
        } elseif (file_exists($localPath)) {
          echo "File Status: ✓ EXISTS on local disk\n";
          echo "File Path: $localPath\n";
          echo "File Size: " . filesize($localPath) . " bytes\n";
        } else {
          echo "File Status: ✗ NOT FOUND (expected for old uploads)\n";
          echo "Checked persistent: $persistentPath\n";
          echo "Checked local: $localPath\n";
        }
      }
      echo "\n";
    }
  }

  // Check orders without rx_note_path
  $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
  $ordersWithoutNotes = $totalOrders - count($orders);

  echo "========================================\n";
  echo "Summary:\n";
  echo "Total orders: $totalOrders\n";
  echo "Orders with prescription notes: " . count($orders) . "\n";
  echo "Orders without prescription notes: $ordersWithoutNotes\n";

} catch (Throwable $e) {
  echo "\n✗ Error: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== End of Check ===\n";
