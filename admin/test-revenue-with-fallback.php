<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== TESTING REVENUE CALCULATION WITH FALLBACKS ===\n\n";

// Helper function (copied from index.php)
function patches_per_week(?string $f): int {
  $f = strtolower(trim((string)$f));
  if ($f==='daily') return 7;
  if ($f==='every other day') return 4;
  if ($f==='weekly') return 1;
  if (preg_match('/(\d+)\s*x\s*\/?\s*week/', $f, $m)) return max(1,(int)$m[1]);
  if (preg_match('/(\d+)\s*x\s*per\s*week/', $f, $m)) return max(1,(int)$m[1]);
  return 1;
}

// Get a referral order
$stmt = $pdo->query("
  SELECT
    o.id,
    o.product,
    o.frequency,
    o.frequency_per_week,
    o.duration_days,
    o.qty_per_change,
    o.billed_by,
    o.product_price,
    pr.pieces_per_box,
    pr.price_admin,
    pr.hcpcs_code
  FROM orders o
  LEFT JOIN products pr ON pr.id = o.product_id
  WHERE o.status NOT IN ('draft', 'rejected', 'cancelled')
  AND (o.billed_by IS NULL OR o.billed_by != 'practice_dme')
  LIMIT 1
");

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  echo "No referral orders found!\n";
  exit;
}

echo "Order ID: " . substr($order['id'], 0, 12) . "...\n";
echo "Product: " . $order['product'] . "\n\n";

echo "RAW DATA:\n";
echo "  frequency (text): '" . ($order['frequency'] ?: 'NULL') . "'\n";
echo "  frequency_per_week: " . ($order['frequency_per_week'] ?? 'NULL') . "\n";
echo "  duration_days: " . ($order['duration_days'] ?? 'NULL') . "\n";
echo "  qty_per_change: " . ($order['qty_per_change'] ?? 'NULL') . "\n";
echo "  pieces_per_box: " . ($order['pieces_per_box'] ?? 'NULL') . "\n";
echo "  product_price: $" . ($order['product_price'] ?? '0') . "\n";
echo "  price_admin: $" . ($order['price_admin'] ?? '0') . "\n\n";

// Apply fallback logic (FROM UPDATED admin/index.php)
$fpw = (int)($order['frequency_per_week'] ?? 0);
echo "FALLBACK LOGIC:\n";
echo "  Step 1 - frequency_per_week: $fpw\n";

if ($fpw === 0 && !empty($order['frequency'])) {
  $fpw = patches_per_week($order['frequency']);
  echo "  Step 2 - parsed from frequency text: $fpw\n";
}

if ($fpw === 0) {
  $fpw = 7;
  echo "  Step 3 - using default: $fpw\n";
}

$days = max(0, (int)($order['duration_days'] ?? 0));
echo "  Duration (raw): $days days\n";

if ($days === 0) {
  $days = 30;
  echo "  Duration (fallback): $days days\n";
}

$qty = max(1, (int)($order['qty_per_change'] ?? 1));
$pieces_per_box = max(1, (int)($order['pieces_per_box'] ?? 10));

echo "\nCALCULATION:\n";
$weeks = $days / 7.0;
echo "  Weeks: $weeks\n";

$total_pieces = $weeks * $fpw * $qty;
echo "  Total pieces: $total_pieces ($weeks weeks × $fpw fpw × $qty qty)\n";

$total_boxes = (int)ceil($total_pieces / $pieces_per_box);
echo "  Total boxes: $total_boxes (ceil($total_pieces / $pieces_per_box))\n";

// Calculate revenue (referral method)
$price_admin = (float)($order['price_admin'] ?? 0);
$cpt_rate_per_piece = $price_admin / $pieces_per_box;
echo "  CPT rate per piece: $" . number_format($cpt_rate_per_piece, 2) . " (\$" . $price_admin . " / $pieces_per_box)\n";

$revenue = $total_pieces * $cpt_rate_per_piece;
echo "\nFINAL REVENUE: $" . number_format($revenue, 2) . "\n";
echo "  Formula: $total_pieces pieces × \$" . number_format($cpt_rate_per_piece, 2) . " per piece\n";
