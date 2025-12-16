<?php
/**
 * Migration: Recalculate Order Revenue
 *
 * This migration recalculates stored revenue values for all orders
 * to fix any incorrect values from previous backfills.
 *
 * Run: php admin/migrations/recalculate_order_revenue.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/revenue_calculator.php';

echo "=== Migration: Recalculate Order Revenue ===\n\n";

// Load reimbursement rates
echo "Step 1: Loading reimbursement rates...\n";
$rates = load_reimbursement_rates($pdo);
echo "  Loaded " . count($rates) . " HCPCS rates:\n";
foreach ($rates as $code => $rate) {
    echo "    $code: \$" . number_format($rate, 2) . "/piece\n";
}

// Get ALL orders (we're recalculating everything)
echo "\nStep 2: Fetching all orders...\n";

$stmt = $pdo->query("
    SELECT
        o.id,
        o.billed_by,
        o.product_price,
        o.frequency_per_week,
        o.duration_days,
        o.refills_allowed,
        o.qty_per_change,
        o.wounds_data,
        o.cpt,
        o.expected_revenue as old_expected_revenue,
        o.boxes_to_ship as old_boxes_to_ship,
        p.pieces_per_box,
        p.cost_per_box,
        p.price_wholesale,
        p.hcpcs_code,
        p.hcpcs_code as cpt_code,
        p.medicare_allowable,
        u.account_type,
        pp.custom_price as practice_custom_price,
        pp.cost_per_box as practice_cost_per_box
    FROM orders o
    LEFT JOIN products p ON p.id = o.product_id
    LEFT JOIN users u ON u.id = o.user_id
    LEFT JOIN practice_pricing pp ON pp.user_id = o.user_id AND pp.product_id = o.product_id
    WHERE o.status NOT IN ('rejected', 'cancelled', 'draft')
    ORDER BY o.created_at DESC
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($orders);
echo "  Found $total orders to recalculate\n";

$updateStmt = $pdo->prepare("
    UPDATE orders SET
        total_pieces = ?,
        boxes_to_ship = ?,
        billable_pieces = ?,
        expected_revenue = ?,
        expected_cost = ?,
        cpt_rate_used = ?,
        price_per_box_used = ?
    WHERE id = ?
");

$success = 0;
$changed = 0;
$errors = 0;
$totalOldRevenue = 0;
$totalNewRevenue = 0;

echo "\nStep 3: Recalculating orders...\n";

foreach ($orders as $order) {
    try {
        // Force fresh calculation by removing stored values
        $orderForCalc = $order;
        unset($orderForCalc['expected_revenue']);
        unset($orderForCalc['old_expected_revenue']);
        unset($orderForCalc['boxes_to_ship']);
        unset($orderForCalc['old_boxes_to_ship']);
        unset($orderForCalc['total_pieces']);
        unset($orderForCalc['billable_pieces']);
        unset($orderForCalc['expected_cost']);
        unset($orderForCalc['cpt_rate_used']);

        // Calculate fresh
        $calc = calculate_order_revenue($orderForCalc, $rates);

        // Determine price per box used
        $pricePerBoxUsed = null;
        if ($calc['is_wholesale']) {
            $pricePerBoxUsed = $calc['boxes'] > 0 ? $calc['revenue'] / $calc['boxes'] : 0;
        }

        // Track changes
        $oldRevenue = (float)($order['old_expected_revenue'] ?? 0);
        $newRevenue = $calc['revenue'];
        $totalOldRevenue += $oldRevenue;
        $totalNewRevenue += $newRevenue;

        // Check if value changed significantly (more than $1)
        if (abs($newRevenue - $oldRevenue) > 1) {
            $changed++;
            echo "  Order {$order['id']}: \$" . number_format($oldRevenue, 2) . " → \$" . number_format($newRevenue, 2);
            if ($oldRevenue > 0) {
                $pctChange = (($newRevenue - $oldRevenue) / $oldRevenue) * 100;
                echo " (" . ($pctChange > 0 ? '+' : '') . number_format($pctChange, 1) . "%)";
            }
            echo "\n";
        }

        $updateStmt->execute([
            $calc['pieces'],
            $calc['boxes'],
            $calc['pieces'], // billable_pieces = pieces
            $calc['revenue'],
            $calc['cost'],
            $calc['cpt_rate'],
            $pricePerBoxUsed,
            $order['id']
        ]);

        $success++;
    } catch (Throwable $e) {
        $errors++;
        if ($errors <= 5) {
            echo "  ✗ Error updating order {$order['id']}: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Total orders processed: $success\n";
echo "Orders with significant changes: $changed\n";
echo "Errors: $errors\n";
echo "\nRevenue before: \$" . number_format($totalOldRevenue, 2) . "\n";
echo "Revenue after:  \$" . number_format($totalNewRevenue, 2) . "\n";
$diff = $totalNewRevenue - $totalOldRevenue;
echo "Difference:     \$" . ($diff >= 0 ? '+' : '') . number_format($diff, 2) . "\n";

echo "\n=== Migration Complete ===\n";
