<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== DASHBOARD REVENUE DIAGNOSTIC ===\n\n";

// Run the exact same query as the dashboard
function has_table(PDO $pdo, string $tbl): bool {
  try {
    $st=$pdo->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME=?");
    $st->execute([$tbl]);
    return ((int)$st->fetch()['c'])>0;
  } catch(Throwable $e){
    return false;
  }
}

function has_column(PDO $pdo, string $tbl, string $col): bool {
  try {
    $st=$pdo->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME=? AND COLUMN_NAME=?");
    $st->execute([$tbl,$col]);
    return ((int)$st->fetch()['c'])>0;
  } catch(Throwable $e){
    return false;
  }
}

$hasProducts = has_table($pdo,'products');
$hasRates    = has_table($pdo,'reimbursement_rates');
$hasShipRem  = has_column($pdo,'orders','shipments_remaining');

echo "Schema Check:\n";
echo "- products table: " . ($hasProducts ? 'YES' : 'NO') . "\n";
echo "- reimbursement_rates table: " . ($hasRates ? 'YES' : 'NO') . "\n";
echo "- shipments_remaining column: " . ($hasShipRem ? 'YES' : 'NO') . "\n\n";

// Prefetch reimbursement rates
$rates = [];
if ($hasRates) {
  try {
    foreach ($pdo->query("SELECT cpt_code, COALESCE(rate_non_rural,0) rate FROM reimbursement_rates") as $r) {
      $rates[$r['cpt_code']] = (float)$r['rate'];
    }
    echo "Loaded " . count($rates) . " CPT reimbursement rates\n\n";
  } catch (Throwable $e) {
    echo "Error loading rates: " . $e->getMessage() . "\n\n";
  }
}

$earnedRevenue = 0.0;
$projectedRevenue = 0.0;
$totalRevenue = 0.0;
$referralRevenue = 0.0;
$wholesaleRevenue = 0.0;

try {
  $revenueWhere = "o.status NOT IN ('rejected', 'cancelled', 'draft')";

  $revenueQuery = "
    SELECT
      o.id,
      o.product_price,
      o.frequency,
      o.frequency_per_week,
      o.duration_days,
      o.refills_allowed,
      o.qty_per_change,
      o.billed_by,
      " . ($hasShipRem ? "o.shipments_remaining," : "0 AS shipments_remaining,") . "
      u.practice_name,
      " . ($hasProducts ? "pr.name AS product_name, pr.hcpcs_code AS cpt_code, pr.pieces_per_box, pr.price_admin" : "'Unknown' AS product_name, '' AS cpt_code, 10 AS pieces_per_box, 0 AS price_admin") . "
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    " . ($hasProducts ? "LEFT JOIN products pr ON pr.id = o.product_id" : "") . "
    WHERE " . $revenueWhere . "
  ";

  $stmt = $pdo->prepare($revenueQuery);
  $stmt->execute();

  $orders = $stmt->fetchAll();
  echo "Found " . count($orders) . " orders (excluding rejected/cancelled/draft)\n\n";

  $wholesaleOrders = 0;
  $referralOrders = 0;

  // Helper function
  function patches_per_week(?string $f): int {
    $f = strtolower(trim((string)$f));
    if ($f==='daily') return 7;
    if ($f==='every other day') return 4;
    if ($f==='weekly') return 1;
    if (preg_match('/(\d+)\s*x\s*\/?\s*week/', $f, $m)) return max(1,(int)$m[1]);
    if (preg_match('/(\d+)\s*x\s*per\s*week/', $f, $m)) return max(1,(int)$m[1]);
    return 1;
  }

  foreach ($orders as $order) {
    $billedBy = $order['billed_by'] ?? 'collagen_direct';
    $isWholesale = ($billedBy === 'practice_dme');

    if ($isWholesale) {
      $wholesaleOrders++;
    } else {
      $referralOrders++;
    }

    // Apply fallback logic (NEW - matches admin/index.php)
    $fpw = (int)($order['frequency_per_week'] ?? 0);
    if ($fpw === 0 && !empty($order['frequency'])) {
      $fpw = patches_per_week($order['frequency']);
    }
    if ($fpw === 0) {
      $fpw = 7; // Default: daily changes
    }

    $days = max(0, (int)($order['duration_days'] ?? 0));
    if ($days === 0) {
      $days = 30; // Default: 30 days
    }

    $qty = max(1, (int)($order['qty_per_change'] ?? 1));
    $refills = max(0, (int)($order['refills_allowed'] ?? 0));
    $pieces_per_box = max(1, (int)($order['pieces_per_box'] ?? 10));

    // Calculate total pieces and boxes
    $weeks = $days / 7.0;
    $total_pieces = $weeks * $fpw * $qty * (1 + $refills);
    $total_boxes = (int)ceil($total_pieces / $pieces_per_box);

    if ($isWholesale) {
      // WHOLESALE: Revenue = Boxes × Price Per Box
      $price_per_box = (float)($order['product_price'] ?? 0);
      if ($price_per_box <= 0) $price_per_box = 150.0;
      $boxes_remaining = 0;
      $boxes_delivered = $total_boxes;
      $order_total = $total_boxes * $price_per_box;
    } else {
      // REFERRAL: Revenue = Pieces × CPT Rate Per Piece
      $cpt_rate_per_piece = 0.0;
      $cpt = $order['cpt_code'] ?? '';
      if ($hasRates && $cpt && isset($rates[$cpt]) && $rates[$cpt] > 0) {
        $cpt_rate_per_piece = $rates[$cpt];
      } else {
        $product_price = (float)($order['price_admin'] ?? 0);
        if ($product_price > 0) {
          $cpt_rate_per_piece = $product_price / $pieces_per_box;
        } else {
          $cpt_rate_per_piece = 150.0 / $pieces_per_box;
        }
      }
      $order_total = $total_pieces * $cpt_rate_per_piece;
      $boxes_remaining = (int)($order['shipments_remaining'] ?? 0);
      $boxes_delivered = max(0, $total_boxes - $boxes_remaining);
    }

    // Split revenue between earned and projected
    if ($total_boxes > 0) {
      $delivered_ratio = $boxes_delivered / $total_boxes;
      $remaining_ratio = $boxes_remaining / $total_boxes;
      $order_earned = $order_total * $delivered_ratio;
      $order_projected = $order_total * $remaining_ratio;
    } else {
      $order_earned = 0.0;
      $order_projected = 0.0;
      $order_total = 0.0;
    }

    $earnedRevenue += $order_earned;
    $projectedRevenue += $order_projected;
    $totalRevenue += $order_total;

    if ($isWholesale) {
      $wholesaleRevenue += $order_total;
    } else {
      $referralRevenue += $order_total;
    }
  }

  echo "Order Breakdown:\n";
  echo "- Wholesale orders: $wholesaleOrders\n";
  echo "- Referral orders: $referralOrders\n\n";

  echo "=== REVENUE RESULTS ===\n";
  echo "Total Revenue: $" . number_format($totalRevenue, 2) . "\n";
  echo "  - Earned: $" . number_format($earnedRevenue, 2) . "\n";
  echo "  - Projected: $" . number_format($projectedRevenue, 2) . "\n\n";
  echo "Wholesale Revenue: $" . number_format($wholesaleRevenue, 2) . "\n";
  echo "Referral Revenue: $" . number_format($referralRevenue, 2) . "\n\n";

  if ($referralRevenue == 0 && $referralOrders > 0) {
    echo "⚠️ PROBLEM: Referral orders exist but revenue is $0.00\n";
    echo "Checking first referral order in detail...\n\n";

    // Get one referral order for debugging
    foreach ($orders as $order) {
      $billedBy = $order['billed_by'] ?? 'collagen_direct';
      if ($billedBy !== 'practice_dme') {
        echo "Sample Referral Order:\n";
        echo "  Order ID: " . substr($order['id'], 0, 8) . "...\n";
        echo "  billed_by: " . ($order['billed_by'] ?: 'NULL') . "\n";
        echo "  frequency_per_week: " . ($order['frequency_per_week'] ?? 'NULL') . "\n";
        echo "  duration_days: " . ($order['duration_days'] ?? 'NULL') . "\n";
        echo "  qty_per_change: " . ($order['qty_per_change'] ?? 'NULL') . "\n";
        echo "  product_price: " . ($order['product_price'] ?? 'NULL') . "\n";
        echo "  pieces_per_box: " . ($order['pieces_per_box'] ?? 'NULL') . "\n";
        break;
      }
    }
  }

} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}
