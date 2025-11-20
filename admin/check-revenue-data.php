<?php
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== REVENUE DATA DIAGNOSTIC ===\n\n";

// Check sample orders
echo "Sample orders:\n";
$stmt = $pdo->query("
  SELECT
    o.id,
    o.product,
    o.frequency_per_week,
    o.qty_per_change,
    o.duration_days,
    o.billed_by,
    o.product_price,
    o.shipments_remaining,
    o.status,
    pr.pieces_per_box,
    pr.hcpcs_code,
    pr.price_admin
  FROM orders o
  LEFT JOIN products pr ON pr.id = o.product_id
  WHERE o.status NOT IN ('draft', 'rejected', 'cancelled')
  ORDER BY o.created_at DESC
  LIMIT 10
");

foreach ($stmt->fetchAll() as $order) {
  echo "\nOrder ID: " . $order['id'] . "\n";
  echo "  Product: " . ($order['product'] ?: 'N/A') . "\n";
  echo "  Frequency/week: " . ($order['frequency_per_week'] ?: 'NULL') . "\n";
  echo "  Qty per change: " . ($order['qty_per_change'] ?: 'NULL') . "\n";
  echo "  Duration days: " . ($order['duration_days'] ?: 'NULL') . "\n";
  echo "  Billed by: " . ($order['billed_by'] ?: 'NULL') . "\n";
  echo "  Product price: $" . ($order['product_price'] ?: '0') . "\n";
  echo "  Shipments remaining: " . ($order['shipments_remaining'] ?? 'NULL') . "\n";
  echo "  Status: " . ($order['status'] ?: 'N/A') . "\n";
  echo "  Pieces per box: " . ($order['pieces_per_box'] ?: 'NULL') . "\n";
  echo "  HCPCS/CPT: " . ($order['hcpcs_code'] ?: 'NULL') . "\n";
  echo "  Price admin: $" . ($order['price_admin'] ?: '0') . "\n";

  // Calculate revenue
  $fpw = (int)($order['frequency_per_week'] ?? 0);
  $qty = max(1, (int)($order['qty_per_change'] ?? 1));
  $days = max(0, (int)($order['duration_days'] ?? 0));
  $pieces_per_box = max(1, (int)($order['pieces_per_box'] ?? 10));

  if ($fpw > 0 && $days > 0) {
    $weeks = $days / 7.0;
    $total_pieces = $weeks * $fpw * $qty;
    $total_boxes = ceil($total_pieces / $pieces_per_box);

    echo "  CALCULATED:\n";
    echo "    Total pieces: " . number_format($total_pieces, 2) . "\n";
    echo "    Total boxes: " . $total_boxes . "\n";

    $isWholesale = ($order['billed_by'] ?? 'collagen_direct') === 'practice_dme';
    if ($isWholesale) {
      $price_per_box = (float)($order['product_price'] ?? 0);
      $revenue = $total_boxes * $price_per_box;
      echo "    Revenue (wholesale): $" . number_format($revenue, 2) . " (" . $total_boxes . " boxes × $" . $price_per_box . ")\n";
    } else {
      $price_per_piece = (float)($order['price_admin'] ?? 0) / $pieces_per_box;
      $revenue = $total_pieces * $price_per_piece;
      echo "    Revenue (referral): $" . number_format($revenue, 2) . " (" . number_format($total_pieces, 2) . " pieces × $" . number_format($price_per_piece, 2) . ")\n";
    }
  } else {
    echo "  ERROR: Missing frequency_per_week or duration_days - cannot calculate revenue\n";
  }
}

echo "\n\n=== PRODUCTS TABLE ===\n";
$products = $pdo->query("SELECT id, name, pieces_per_box, price_admin, hcpcs_code FROM products WHERE active = TRUE LIMIT 5")->fetchAll();
foreach ($products as $p) {
  echo "Product: " . $p['name'] . "\n";
  echo "  Pieces/box: " . ($p['pieces_per_box'] ?: 'NULL') . "\n";
  echo "  Price admin: $" . ($p['price_admin'] ?: '0') . "\n";
  echo "  HCPCS: " . ($p['hcpcs_code'] ?: 'NULL') . "\n\n";
}

echo "\n=== SUMMARY ===\n";
$total_orders = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status NOT IN ('draft', 'rejected', 'cancelled')")->fetch()['cnt'];
$orders_with_freq = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status NOT IN ('draft', 'rejected', 'cancelled') AND frequency_per_week IS NOT NULL AND frequency_per_week > 0")->fetch()['cnt'];
$orders_with_duration = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status NOT IN ('draft', 'rejected', 'cancelled') AND duration_days IS NOT NULL AND duration_days > 0")->fetch()['cnt'];

echo "Total active orders: " . $total_orders . "\n";
echo "Orders with frequency_per_week: " . $orders_with_freq . "\n";
echo "Orders with duration_days: " . $orders_with_duration . "\n";
echo "Orders missing critical data: " . ($total_orders - min($orders_with_freq, $orders_with_duration)) . "\n";
