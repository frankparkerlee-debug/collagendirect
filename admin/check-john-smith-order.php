<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

$orderId = $_GET['order'] ?? 'd01b7c6a5a3641794062d49ec53a8baf';

echo "=== CHECKING ORDER: $orderId ===\n\n";

$stmt = $pdo->prepare("
  SELECT
    o.*,
    pr.name AS product_name,
    pr.hcpcs_code,
    pr.pieces_per_box,
    pr.price_wholesale,
    pt.first_name,
    pt.last_name
  FROM orders o
  LEFT JOIN products pr ON pr.id = o.product_id
  LEFT JOIN patients pt ON pt.id = o.patient_id
  WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  die("Order not found\n");
}

echo "Patient: {$order['first_name']} {$order['last_name']}\n";
echo "Product: {$order['product_name']}\n";
echo "HCPCS: {$order['hcpcs_code']}\n\n";

echo "ORDER DETAILS:\n";
echo str_repeat("-", 80) . "\n";
foreach ($order as $key => $value) {
  if (!in_array($key, ['first_name', 'last_name', 'product_name', 'hcpcs_code'])) {
    echo "$key: " . ($value ?? 'NULL') . "\n";
  }
}

echo "\n\nKEY FIELDS FOR AI:\n";
echo str_repeat("-", 80) . "\n";
echo "rx_note_path: " . ($order['rx_note_path'] ?? 'NULL') . "\n";
echo "rx_note_name: " . ($order['rx_note_name'] ?? 'NULL') . "\n";
echo "rx_note_mime: " . ($order['rx_note_mime'] ?? 'NULL') . "\n";
echo "frequency_per_week: " . ($order['frequency_per_week'] ?? 'NULL') . "\n";
echo "duration_days: " . ($order['duration_days'] ?? 'NULL') . "\n";
echo "qty_per_change: " . ($order['qty_per_change'] ?? 'NULL') . "\n";

echo "\n\nKEY FIELDS FOR REVENUE:\n";
echo str_repeat("-", 80) . "\n";
echo "billed_by: " . ($order['billed_by'] ?? 'NULL') . "\n";
echo "product_price: " . ($order['product_price'] ?? 'NULL') . "\n";
echo "pieces_per_box: " . ($order['pieces_per_box'] ?? 'NULL') . "\n";
echo "price_wholesale: " . ($order['price_wholesale'] ?? 'NULL') . "\n";
