<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

$groupId = $_GET['group'] ?? 'e2a7e9d28048c4cc6d3454ebd65bb3f2';

echo "=== CHECKING ORDER GROUP: $groupId ===\n\n";

$stmt = $pdo->prepare("
  SELECT
    o.id,
    o.order_group_id,
    o.product_id,
    pr.name AS product_name,
    pr.pieces_per_box,
    o.frequency_per_week,
    o.duration_days,
    o.qty_per_change,
    o.refills_allowed,
    o.billed_by,
    pt.first_name,
    pt.last_name
  FROM orders o
  LEFT JOIN products pr ON pr.id = o.product_id
  LEFT JOIN patients pt ON pt.id = o.patient_id
  WHERE o.order_group_id = ?
  ORDER BY o.created_at, o.product_id
");
$stmt->execute([$groupId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
  die("No orders found with group ID: $groupId\n");
}

echo "Patient: {$orders[0]['first_name']} {$orders[0]['last_name']}\n";
echo "Order Type: " . ($orders[0]['billed_by'] === 'practice_dme' ? 'WHOLESALE' : 'REFERRAL') . "\n";
echo "Total Products: " . count($orders) . "\n\n";

echo str_repeat("=", 100) . "\n";
foreach ($orders as $idx => $order) {
  echo "PRODUCT " . ($idx + 1) . ":\n";
  echo str_repeat("-", 100) . "\n";
  echo "Order ID: {$order['id']}\n";
  echo "Product: {$order['product_name']}\n";
  echo "Pieces per box: {$order['pieces_per_box']}\n";
  echo "Frequency per week: " . ($order['frequency_per_week'] ?? 'NULL') . "\n";
  echo "Duration days: " . ($order['duration_days'] ?? 'NULL') . "\n";
  echo "Qty per change: " . ($order['qty_per_change'] ?? 'NULL') . "\n";
  echo "Refills allowed: " . ($order['refills_allowed'] ?? 'NULL') . "\n";

  // Calculate boxes
  $fpw = (int)($order['frequency_per_week'] ?? 0);
  $qty = max(1, (int)($order['qty_per_change'] ?? 1));
  $days = (int)($order['duration_days'] ?? 0);
  $refills = max(0, (int)($order['refills_allowed'] ?? 0));
  $pieces_per_box = max(1, (int)($order['pieces_per_box'] ?? 10));

  if ($fpw === 0) $fpw = 1; // Fallback
  if ($days === 0) $days = 30; // Fallback

  $weeks = $days / 7.0;
  $total_pieces = $weeks * $fpw * $qty * (1 + $refills);
  $totalBoxes = (int)ceil($total_pieces / $pieces_per_box);

  echo "\nCALCULATION:\n";
  echo "  Weeks: $weeks\n";
  echo "  Total pieces: $total_pieces\n";
  echo "  Boxes needed: $totalBoxes\n";
  echo "\n";
}
