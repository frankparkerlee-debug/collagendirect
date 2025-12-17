<?php
/**
 * Debug endpoint to view order calculation details
 * Usage: /admin/api/debug-order.php?id=4c001e24
 */
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/revenue_calculator.php';

header('Content-Type: application/json');

$orderId = $_GET['id'] ?? '';
if (!$orderId) {
    echo json_encode(['error' => 'Missing order ID']);
    exit;
}

// Get order with all relevant fields
$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.order_number,
        o.status,
        o.billed_by,
        o.payment_type,
        o.frequency_per_week,
        o.qty_per_change,
        o.duration_days,
        o.refills_allowed,
        o.product_price,
        o.cpt,
        o.total_pieces,
        o.boxes_to_ship,
        o.billable_pieces,
        o.expected_revenue,
        o.expected_cost,
        o.cpt_rate_used,
        o.wounds_data,
        p.pieces_per_box,
        p.cost_per_box,
        p.price_wholesale,
        p.hcpcs_code,
        p.medicare_allowable,
        p.name as product_name,
        u.account_type
    FROM orders o
    LEFT JOIN products p ON p.id = o.product_id
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.id LIKE ?
    LIMIT 1
");
$stmt->execute([$orderId . '%']);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['error' => 'Order not found']);
    exit;
}

// Load rates
$rates = load_reimbursement_rates($pdo);

// Get rate for this order's HCPCS
$hcpcs = $order['hcpcs_code'] ?? $order['cpt'] ?? '';
$rateForCode = $rates[$hcpcs] ?? null;

// Force fresh calculation (remove stored values)
$orderForCalc = $order;
unset($orderForCalc['expected_revenue']);
unset($orderForCalc['boxes_to_ship']);
unset($orderForCalc['total_pieces']);
unset($orderForCalc['billable_pieces']);
unset($orderForCalc['expected_cost']);
unset($orderForCalc['cpt_rate_used']);

$freshCalc = calculate_order_revenue($orderForCalc, $rates, true);

// Manual calculation for verification
$fpw = (int)($order['frequency_per_week'] ?? 0);
$qty = max(1, (int)($order['qty_per_change'] ?? 1));
$days = (int)($order['duration_days'] ?? 0);
$refills = (int)($order['refills_allowed'] ?? 0);
$ppb = max(1, (int)($order['pieces_per_box'] ?? 10));

if ($fpw === 0) $fpw = 1;
if ($days === 0) $days = 30;

$weeks = $days / 7.0;
$totalPieces = $weeks * $fpw * $qty * (1 + $refills);
$billablePieces = (int)ceil($totalPieces);
$boxes = (int)ceil($totalPieces / $ppb);

$medicareRate = (float)($order['medicare_allowable'] ?? 0);
$rate = $medicareRate > 0 ? $medicareRate : ((float)$order['product_price'] / $ppb);
$manualRevenue = $billablePieces * $rate;

echo json_encode([
    'order_id' => $order['id'],
    'order_number' => $order['order_number'],
    'product_name' => $order['product_name'],
    'hcpcs_code' => $hcpcs,

    'stored_values' => [
        'expected_revenue' => $order['expected_revenue'],
        'expected_cost' => $order['expected_cost'],
        'boxes_to_ship' => $order['boxes_to_ship'],
        'total_pieces' => $order['total_pieces'],
        'billable_pieces' => $order['billable_pieces'],
        'cpt_rate_used' => $order['cpt_rate_used']
    ],

    'order_parameters' => [
        'frequency_per_week' => $order['frequency_per_week'],
        'qty_per_change' => $order['qty_per_change'],
        'duration_days' => $order['duration_days'],
        'refills_allowed' => $order['refills_allowed'],
        'billed_by' => $order['billed_by'],
        'payment_type' => $order['payment_type'],
        'account_type' => $order['account_type']
    ],

    'product_data' => [
        'pieces_per_box' => $order['pieces_per_box'],
        'medicare_allowable' => $order['medicare_allowable'],
        'product_price' => $order['product_price']
    ],

    'rate_lookup' => [
        'hcpcs' => $hcpcs,
        'rate_from_db' => $rateForCode
    ],

    'fresh_calculation' => [
        'revenue' => $freshCalc['revenue'],
        'cost' => $freshCalc['cost'],
        'boxes' => $freshCalc['boxes'],
        'pieces' => $freshCalc['pieces'],
        'cpt_rate' => $freshCalc['cpt_rate'],
        'is_wholesale' => $freshCalc['is_wholesale'],
        'steps' => $freshCalc['calculation_steps']
    ],

    'manual_verification' => [
        'formula' => "($days days / 7) × $fpw freq × $qty qty × (1 + $refills refills)",
        'weeks' => $weeks,
        'total_pieces_raw' => $totalPieces,
        'billable_pieces' => $billablePieces,
        'boxes_needed' => $boxes,
        'rate_used' => $rate,
        'calculated_revenue' => $manualRevenue
    ],

    'wounds_data' => $order['wounds_data'] ? json_decode($order['wounds_data'], true) : null
], JSON_PRETTY_PRINT);
