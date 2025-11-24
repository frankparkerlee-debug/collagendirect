<?php
/**
 * Debug revenue calculation for a specific order
 */
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

$orderId = $_GET['order'] ?? 'd01b7c6a5a3641794062d49ec53a8baf';

echo "=== REVENUE CALCULATION DEBUG ===\n";
echo "Order ID: $orderId\n\n";

// Get order data
$stmt = $pdo->prepare("
  SELECT
    o.*,
    pr.name AS product_name,
    pr.hcpcs_code,
    pr.pieces_per_box,
    pr.price_wholesale,
    COALESCE(pp.cost_per_box, pr.cost_per_box, 0) AS cost_per_box,
    pp.custom_price AS practice_custom_price,
    pt.first_name,
    pt.last_name
  FROM orders o
  LEFT JOIN products pr ON pr.id = o.product_id
  LEFT JOIN patients pt ON pt.id = o.patient_id
  LEFT JOIN practice_pricing pp ON pp.user_id = o.user_id AND pp.product_id = o.product_id
  WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  die("Order not found. Checking if this is an order group...\n\n");
}

$isWholesale = ($order['billed_by'] ?? 'collagen_direct') === 'practice_dme';
$pieces_per_box = max(1, (int)($order['pieces_per_box'] ?? 10));

echo "Patient: {$order['first_name']} {$order['last_name']}\n";
echo "Product: {$order['product_name']}\n";
echo "Order Type: " . ($isWholesale ? 'WHOLESALE' : 'REFERRAL') . "\n";
echo "Pieces per box: {$pieces_per_box}\n\n";

echo str_repeat("=", 80) . "\n";
echo "RAW ORDER DATA\n";
echo str_repeat("=", 80) . "\n";
echo "frequency_per_week: " . ($order['frequency_per_week'] ?? 'NULL') . "\n";
echo "duration_days: " . ($order['duration_days'] ?? 'NULL') . "\n";
echo "qty_per_change: " . ($order['qty_per_change'] ?? 'NULL') . "\n";
echo "refills_allowed: " . ($order['refills_allowed'] ?? 'NULL') . "\n";
echo "product_price: " . ($order['product_price'] ?? 'NULL') . "\n";
echo "price_wholesale: " . ($order['price_wholesale'] ?? 'NULL') . "\n";
echo "cost_per_box: " . ($order['cost_per_box'] ?? 'NULL') . "\n\n";

echo str_repeat("=", 80) . "\n";
echo "CALCULATION STEPS\n";
echo str_repeat("=", 80) . "\n";

if ($isWholesale) {
  echo "WHOLESALE CALCULATION:\n";
  $totalBoxes = max(1, (int)($order['qty_per_change'] ?? 1));
  $product_price_per_piece = (float)($order['product_price'] ?? 0);

  echo "Step 1: qty_per_change = $totalBoxes (this is number of boxes for wholesale)\n";

  if ($product_price_per_piece > 0) {
    $price_per_box = $product_price_per_piece * $pieces_per_box;
    echo "Step 2: product_price ($product_price_per_piece/piece) × pieces_per_box ($pieces_per_box) = \$$price_per_box per box\n";
  } else {
    $price_per_box = (float)($order['price_wholesale'] ?? 150.0);
    echo "Step 2: Using default wholesale price: \$$price_per_box per box\n";
  }

  $revenue = $totalBoxes * $price_per_box;
  echo "Step 3: Revenue = $totalBoxes boxes × \$$price_per_box = \$$revenue\n";

} else {
  echo "REFERRAL CALCULATION:\n";

  $fpw = (int)($order['frequency_per_week'] ?? 0);
  $qty = max(1, (int)($order['qty_per_change'] ?? 1));
  $days = (int)($order['duration_days'] ?? 0);
  $refills = (int)($order['refills_allowed'] ?? 0);

  echo "Step 1: Extract values\n";
  echo "  - frequency_per_week = $fpw\n";
  echo "  - qty_per_change = $qty\n";
  echo "  - duration_days = $days\n";
  echo "  - refills_allowed = $refills\n";

  // Apply defaults (OLD BUGGY LOGIC)
  if ($fpw === 0) {
    echo "  - WARNING: frequency_per_week is 0, defaulting to 7 (BUGGY!)\n";
    $fpw = 7;
  }
  if ($days === 0) {
    echo "  - WARNING: duration_days is 0, defaulting to 30\n";
    $days = 30;
  }

  $weeks = $days / 7.0;
  echo "\nStep 2: Calculate weeks = $days days ÷ 7 = $weeks weeks\n";

  $total_pieces = $weeks * $fpw * $qty * (1 + $refills);
  echo "\nStep 3: Calculate total pieces\n";
  echo "  = $weeks weeks × $fpw changes/week × $qty pieces/change × " . (1 + $refills) . " (1+refills)\n";
  echo "  = $total_pieces pieces\n";

  $totalBoxes = (int)ceil($total_pieces / $pieces_per_box);
  echo "\nStep 4: Calculate boxes needed\n";
  echo "  = ceil($total_pieces pieces ÷ $pieces_per_box pieces/box)\n";
  echo "  = $totalBoxes boxes\n";

  $billable_pieces = $totalBoxes * $pieces_per_box;
  echo "\nStep 5: Billable pieces = $totalBoxes boxes × $pieces_per_box pieces/box = $billable_pieces pieces\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "RESULT\n";
echo str_repeat("=", 80) . "\n";
echo "Boxes: $totalBoxes\n";
