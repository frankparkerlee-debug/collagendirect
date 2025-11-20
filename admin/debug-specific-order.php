<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

$order_id = 'e37a26e5fd53b5774c56e518b20d18e1';

echo "=== DEBUGGING ORDER: $order_id ===\n\n";

// Get order details
$stmt = $pdo->prepare("
  SELECT
    o.*,
    pr.name as product_name,
    pr.pieces_per_box,
    pr.price_admin,
    pr.hcpcs_code
  FROM orders o
  LEFT JOIN products pr ON pr.id = o.product_id
  WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  echo "ERROR: Order not found!\n";
  exit;
}

echo "=== ORDER DATA ===\n";
echo "Product: " . ($order['product'] ?: 'NULL') . "\n";
echo "Product ID: " . ($order['product_id'] ?: 'NULL') . "\n";
echo "Status: " . $order['status'] . "\n";
echo "Billed By: " . ($order['billed_by'] ?: 'NULL (defaults to collagen_direct)') . "\n";
echo "\n";

echo "=== CRITICAL FIELDS ===\n";
echo "frequency (text): '" . ($order['frequency'] ?: 'NULL') . "'\n";
echo "frequency_per_week: " . ($order['frequency_per_week'] ?: 'NULL') . "\n";
echo "duration_days: " . ($order['duration_days'] ?: 'NULL') . "\n";
echo "qty_per_change: " . ($order['qty_per_change'] ?: 'NULL') . "\n";
echo "refills_allowed: " . ($order['refills_allowed'] ?: 'NULL') . "\n";
echo "\n";

echo "=== PRODUCT DATA ===\n";
echo "Product Name: " . ($order['product_name'] ?: 'NULL') . "\n";
echo "Pieces Per Box: " . ($order['pieces_per_box'] ?: 'NULL') . "\n";
echo "Price Admin: $" . ($order['price_admin'] ?: '0') . "\n";
echo "Product Price (order): $" . ($order['product_price'] ?: '0') . "\n";
echo "HCPCS Code: " . ($order['hcpcs_code'] ?: 'NULL') . "\n";
echo "\n";

// Now apply the EXACT calculation from admin/index.php with fallbacks
echo "=== APPLYING REVENUE CALCULATION (with fallbacks) ===\n";

function patches_per_week(?string $f): int {
  $f = strtolower(trim((string)$f));
  if ($f==='daily') return 7;
  if ($f==='every other day') return 4;
  if ($f==='weekly') return 1;
  if (preg_match('/(\d+)\s*x\s*\/?\s*week/', $f, $m)) return max(1,(int)$m[1]);
  if (preg_match('/(\d+)\s*x\s*per\s*week/', $f, $m)) return max(1,(int)$m[1]);
  return 1;
}

$billedBy = $order['billed_by'] ?? 'collagen_direct';
$isWholesale = ($billedBy === 'practice_dme');
echo "Is Wholesale: " . ($isWholesale ? 'YES' : 'NO') . "\n\n";

// Step 1: Get frequency with fallbacks
$fpw = (int)($order['frequency_per_week'] ?? 0);
echo "Step 1a - frequency_per_week from DB: $fpw\n";

if ($fpw === 0 && !empty($order['frequency'])) {
  $fpw = patches_per_week($order['frequency']);
  echo "Step 1b - parsed from frequency text: $fpw\n";
}

if ($fpw === 0) {
  $fpw = 7;
  echo "Step 1c - using default fallback: $fpw\n";
}

echo "FINAL frequency_per_week: $fpw\n\n";

// Step 2: Get duration with fallbacks
$days = max(0, (int)($order['duration_days'] ?? 0));
echo "Step 2a - duration_days from DB: $days\n";

if ($days === 0) {
  $days = 30;
  echo "Step 2b - using default fallback: $days\n";
}

echo "FINAL duration_days: $days\n\n";

// Step 3: Other values
$qty = max(1, (int)($order['qty_per_change'] ?? 1));
$refills = max(0, (int)($order['refills_allowed'] ?? 0));
$pieces_per_box = max(1, (int)($order['pieces_per_box'] ?? 10));

echo "qty_per_change: $qty\n";
echo "refills_allowed: $refills\n";
echo "pieces_per_box: $pieces_per_box\n\n";

// Step 4: Calculate pieces and boxes
echo "=== CALCULATION ===\n";
$weeks = $days / 7.0;
echo "Weeks: $weeks ($days / 7.0)\n";

$total_pieces = $weeks * $fpw * $qty * (1 + $refills);
echo "Total Pieces: $total_pieces ($weeks × $fpw × $qty × " . (1 + $refills) . ")\n";

$total_boxes = (int)ceil($total_pieces / $pieces_per_box);
echo "Total Boxes: $total_boxes (ceil($total_pieces / $pieces_per_box))\n\n";

// Step 5: Calculate revenue
echo "=== REVENUE CALCULATION ===\n";

if ($isWholesale) {
  echo "WHOLESALE calculation:\n";
  $price_per_box = (float)($order['product_price'] ?? 0);
  if ($price_per_box <= 0) $price_per_box = 150.0;
  echo "  Price per box: $$price_per_box\n";
  $revenue = $total_boxes * $price_per_box;
  echo "  Revenue: $$revenue ($total_boxes boxes × \$$price_per_box)\n";
} else {
  echo "REFERRAL calculation:\n";
  $cpt_rate_per_piece = 0.0;
  $cpt = $order['hcpcs_code'] ?? '';

  // Check if we have CPT rates (we don't have reimbursement_rates table)
  echo "  CPT Code: " . ($cpt ?: 'NULL') . "\n";

  $product_price = (float)($order['price_admin'] ?? 0);
  echo "  Price admin from product: $$product_price\n";

  if ($product_price > 0) {
    $cpt_rate_per_piece = $product_price / $pieces_per_box;
    echo "  CPT rate per piece: $" . number_format($cpt_rate_per_piece, 2) . " ($$product_price / $pieces_per_box)\n";
  } else {
    $cpt_rate_per_piece = 150.0 / $pieces_per_box;
    echo "  CPT rate per piece (fallback): $" . number_format($cpt_rate_per_piece, 2) . " (\$150 / $pieces_per_box)\n";
  }

  $revenue = $total_pieces * $cpt_rate_per_piece;
  echo "  Revenue: $" . number_format($revenue, 2) . " ($total_pieces pieces × $" . number_format($cpt_rate_per_piece, 2) . ")\n";
}

echo "\n=== FINAL RESULT ===\n";
echo "Total Revenue: $" . number_format($revenue, 2) . "\n";
