<?php
/**
 * Test revenue report grouping logic
 */
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

echo "=== TESTING REVENUE REPORT GROUPING ===\n\n";

// Test with John Smith's order group
$groupId = 'e2a7e9d28048c4cc6d3454ebd65bb3f2';

echo "Looking for order group: $groupId\n\n";

// Fetch orders in the group
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
    o.billed_by
  FROM orders o
  LEFT JOIN products pr ON pr.id = o.product_id
  WHERE o.order_group_id = ?
  ORDER BY o.product_id
");
$stmt->execute([$groupId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
  die("ERROR: No orders found with group ID $groupId\n");
}

echo "✓ Found " . count($orders) . " orders in group\n\n";

// Simulate grouping logic
$grouped_orders = [];
foreach ($orders as $order) {
  $key = !empty($order['order_group_id']) ? $order['order_group_id'] : $order['id'];

  if (!isset($grouped_orders[$key])) {
    $grouped_orders[$key] = [
      'is_group' => !empty($order['order_group_id']),
      'group_id' => $order['order_group_id'],
      'orders' => []
    ];
  }

  $grouped_orders[$key]['orders'][] = $order;
}

echo "✓ Grouped into " . count($grouped_orders) . " group(s)\n\n";

// Process each group
foreach ($grouped_orders as $group) {
  echo str_repeat("=", 80) . "\n";
  echo "GROUP: " . ($group['group_id'] ?? 'SINGLE ORDER') . "\n";
  echo "Multi-product: " . ($group['is_group'] ? 'YES' : 'NO') . "\n";
  echo "Products: " . count($group['orders']) . "\n";
  echo str_repeat("=", 80) . "\n\n";

  $group_total_boxes = 0;
  $products_detail = [];

  foreach ($group['orders'] as $order) {
    $pieces_per_box = max(1, (int)($order['pieces_per_box'] ?? 10));

    // REFERRAL calculation
    $fpw = (int)($order['frequency_per_week'] ?? 0);
    $qty = max(1, (int)($order['qty_per_change'] ?? 1));
    $days = (int)($order['duration_days'] ?? 0);
    $refills = max(0, (int)($order['refills_allowed'] ?? 0));

    if ($fpw === 0) $fpw = 1;
    if ($days === 0) $days = 30;

    $weeks = $days / 7.0;
    $total_pieces = $weeks * $fpw * $qty * (1 + $refills);
    $totalBoxes = (int)ceil($total_pieces / $pieces_per_box);

    echo "Product: {$order['product_name']}\n";
    echo "  Pieces per box: {$pieces_per_box}\n";
    echo "  Frequency: {$fpw}×/week\n";
    echo "  Duration: {$days} days ({$weeks} weeks)\n";
    echo "  Qty per change: {$qty}\n";
    echo "  Total pieces: {$total_pieces}\n";
    echo "  Boxes needed: {$totalBoxes}\n\n";

    $group_total_boxes += $totalBoxes;
    $products_detail[] = [
      'product_name' => $order['product_name'],
      'boxes' => $totalBoxes
    ];
  }

  echo str_repeat("-", 80) . "\n";
  echo "GROUP TOTALS:\n";
  echo "  Total boxes across all products: {$group_total_boxes}\n";
  echo "  Display as: " . ($group['is_group'] ? count($group['orders']) . " products" : $products_detail[0]['product_name']) . "\n";
  echo str_repeat("-", 80) . "\n\n";

  if ($group['is_group']) {
    echo "Product breakdown:\n";
    foreach ($products_detail as $p) {
      echo "  • {$p['product_name']}: {$p['boxes']} boxes\n";
    }
    echo "\n";
  }
}

echo "\n✓ TEST COMPLETE\n";
echo "\nExpected behavior:\n";
echo "- John Smith's order should show as ONE row with '3 products'\n";
echo "- Clicking should expand to show individual products and box counts\n";
echo "- Total boxes should be sum of all 3 products\n";
