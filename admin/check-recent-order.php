<?php
require_once __DIR__ . '/db.php';

// Get the most recent order
$stmt = $pdo->prepare("
  SELECT id, created_at, rx_note_path, rx_note_name, additional_instructions, patient_id
  FROM orders
  ORDER BY created_at DESC
  LIMIT 1
");
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: text/plain');

if ($order) {
  echo "=== MOST RECENT ORDER ===\n";
  echo "Order ID: " . $order['id'] . "\n";
  echo "Created: " . $order['created_at'] . "\n";
  echo "Patient ID: " . $order['patient_id'] . "\n\n";

  echo "Visit Note Status:\n";
  echo "  rx_note_path: " . ($order['rx_note_path'] ?: 'NULL') . "\n";
  echo "  rx_note_name: " . ($order['rx_note_name'] ?: 'NULL') . "\n\n";

  echo "Debug Info (additional_instructions):\n";
  echo $order['additional_instructions'] ?: '(empty)';
  echo "\n\n";

  // Check all orders for this patient created at the same time (multi-product orders)
  $stmt2 = $pdo->prepare("
    SELECT id, product, product_type, rx_note_path, rx_note_name, additional_instructions, order_group_id
    FROM orders
    WHERE patient_id = ? AND created_at = ?
    ORDER BY product_type
  ");
  $stmt2->execute([$order['patient_id'], $order['created_at']]);
  $allOrders = $stmt2->fetchAll(PDO::FETCH_ASSOC);

  if (count($allOrders) > 1) {
    echo "=== MULTI-PRODUCT ORDER GROUP ===\n";
    echo "Found " . count($allOrders) . " orders for this patient at this timestamp:\n\n";

    foreach ($allOrders as $o) {
      echo "Order ID: " . substr($o['id'], 0, 8) . "...\n";
      echo "  Product: " . $o['product'] . "\n";
      echo "  Product Type: " . ($o['product_type'] ?: 'NULL') . "\n";
      echo "  Order Group ID: " . ($o['order_group_id'] ? substr($o['order_group_id'], 0, 8) . '...' : 'NULL') . "\n";
      echo "  Visit Note: " . ($o['rx_note_path'] ? 'YES' : 'NO') . "\n";
      echo "  Debug Info: " . (strpos($o['additional_instructions'] ?? '', 'DEBUG-') !== false ? 'YES' : 'NO') . "\n";
      echo "\n";
    }
  }
} else {
  echo "No orders found\n";
}
?>
