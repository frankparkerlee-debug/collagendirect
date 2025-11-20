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
      o.frequency_per_week,
      o.duration_days,
      o.refills_allowed,
      o.qty_per_change,
      o.billed_by,
      " . ($hasShipRem ? "o.shipments_remaining," : "0 AS shipments_remaining,") . "
      u.practice_name,
      " . ($hasProducts ? "pr.name AS product_name, pr.hcpcs_code AS cpt_code, pr.pieces_per_box" : "'Unknown' AS product_name, '' AS cpt_code, 10 AS pieces_per_box") . "
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

  foreach ($orders as $order) {
    $billedBy = $order['billed_by'] ?? 'collagen_direct';
    $isWholesale = ($billedBy === 'practice_dme');

    if ($isWholesale) {
      $wholesaleOrders++;
    } else {
      $referralOrders++;
    }

    $fpw = (int)($order['frequency_per_week'] ?? 0);
    $qty = max(1, (int)($order['qty_per_change'] ?? 1));
    $days = max(0, (int)($order['duration_days'] ?? 0));
    $refills = max(0, (int)($order['refills_allowed'] ?? 0));
    $pieces_per_box = max(1, (int)($order['pieces_per_box'] ?? 10));

    if ($isWholesale) {
      $total_boxes = $qty;
      $price_per_piece = (float)($order['product_price'] ?? 0);
      $price_per_box = $price_per_piece * $pieces_per_box;
      $boxes_remaining = 0;
      $boxes_delivered = $total_boxes;
    } else {
      $changes_per_day = $fpw / 7.0;
      $total_changes = $changes_per_day * $days * (1 + $refills);
      $total_pieces_needed = $total_changes * $qty;
      $total_boxes = (int)ceil($total_pieces_needed / $pieces_per_box);

      $price_per_box = 0.0;
      $cpt = $order['cpt_code'] ?? '';
      if ($hasRates && $cpt && isset($rates[$cpt]) && $rates[$cpt] > 0) {
        $price_per_box = $rates[$cpt];
      } else {
        $price_per_box = (float)($order['product_price'] ?? 0);
      }
      if ($price_per_box <= 0) $price_per_box = 150.0;

      $boxes_remaining = (int)($order['shipments_remaining'] ?? 0);
      $boxes_delivered = max(0, $total_boxes - $boxes_remaining);
    }

    $order_earned = $boxes_delivered * $price_per_box;
    $order_projected = $boxes_remaining * $price_per_box;
    $order_total = $order_earned + $order_projected;

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
