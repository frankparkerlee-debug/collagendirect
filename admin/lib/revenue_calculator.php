<?php
/**
 * Revenue Calculator Library
 *
 * Unified revenue calculation logic used by both dashboard and revenue report.
 * Ensures consistent calculations across the entire platform.
 */
declare(strict_types=1);

/**
 * Calculate revenue for a single order
 *
 * @param array $order Order data with all relevant fields
 * @param array $rates Reimbursement rates keyed by HCPCS code
 * @param bool $includeSteps Whether to include calculation steps for debugging
 * @return array Revenue calculation result
 */
function calculate_order_revenue(array $order, array $rates = [], bool $includeSteps = false): array {
    $pieces_per_box = max(1, (int)($order['pieces_per_box'] ?? 10));
    $cost_per_box = (float)($order['cost_per_box'] ?? 0);
    $billedBy = $order['billed_by'] ?? 'collagen_direct';
    $isWholesale = ($billedBy === 'practice_dme');

    $steps = [];
    $revenue = 0;
    $totalBoxes = 0;
    $totalPieces = 0;
    $order_cost = 0;
    $cpt_rate = 0;

    if ($isWholesale) {
        // WHOLESALE CALCULATION
        // qty_per_change represents number of BOXES for wholesale orders
        $totalBoxes = max(1, (int)($order['qty_per_change'] ?? 1));
        $product_price_per_piece = (float)($order['product_price'] ?? 0);

        if ($includeSteps) {
            $steps[] = "Type: Wholesale";
            $steps[] = "Boxes ordered: {$totalBoxes}";
        }

        // Check for practice-specific custom pricing first
        $practice_custom_price = (float)($order['practice_custom_price'] ?? 0);

        if ($practice_custom_price > 0) {
            // Custom price is per piece, convert to per box
            $price_per_box = $practice_custom_price * $pieces_per_box;
            if ($includeSteps) {
                $steps[] = "Practice custom price: \$" . number_format($practice_custom_price, 2) . "/piece";
                $steps[] = "Price per box: \$" . number_format($price_per_box, 2);
            }
        } elseif ($product_price_per_piece > 0) {
            $price_per_box = $product_price_per_piece * $pieces_per_box;
            if ($includeSteps) {
                $steps[] = "Price per piece: \$" . number_format($product_price_per_piece, 2);
                $steps[] = "Price per box: \$" . number_format($price_per_box, 2);
            }
        } else {
            $price_per_box = (float)($order['price_wholesale'] ?? 0);
            if ($includeSteps) {
                $steps[] = "Using wholesale price: \$" . number_format($price_per_box, 2) . "/box";
            }
        }

        $revenue = $totalBoxes * $price_per_box;
        $order_cost = $totalBoxes * $cost_per_box;
        $totalPieces = $totalBoxes * $pieces_per_box;
        $cpt_rate = $price_per_box / max(1, $pieces_per_box);

    } else {
        // REFERRAL CALCULATION
        $fpw = (int)($order['frequency_per_week'] ?? 0);
        $qty = max(1, (int)($order['qty_per_change'] ?? 1));
        $days = (int)($order['duration_days'] ?? 0);
        $refills = max(0, (int)($order['refills_allowed'] ?? 0));

        // Try wounds_data if frequency is missing
        if ($fpw === 0 && !empty($order['wounds_data'])) {
            $wounds_data = json_decode($order['wounds_data'], true);
            if (is_array($wounds_data) && isset($wounds_data[0]['frequency_per_week'])) {
                $fpw = (int)$wounds_data[0]['frequency_per_week'];
            }
        }

        // Apply reasonable defaults only if truly missing
        if ($fpw === 0) $fpw = 1;
        if ($days === 0) $days = 30;

        $weeks = $days / 7.0;
        $total_pieces = $weeks * $fpw * $qty * (1 + $refills);
        $totalBoxes = (int)ceil($total_pieces / $pieces_per_box);
        $billable_pieces = $totalBoxes * $pieces_per_box;
        $totalPieces = $billable_pieces;

        if ($includeSteps) {
            $steps[] = "Type: Referral";
            $steps[] = "Duration: {$days} days ({$weeks} weeks)";
            $steps[] = "Frequency: {$fpw}x/week, Qty: {$qty}, Refills: {$refills}";
            $steps[] = "Total pieces needed: " . number_format($total_pieces, 1);
            $steps[] = "Boxes needed: {$totalBoxes} (rounded up)";
            $steps[] = "Billable pieces: {$billable_pieces}";
        }

        // Get CPT rate - prioritize reimbursement_rates table
        $cpt = $order['cpt_code'] ?? $order['cpt'] ?? $order['hcpcs_code'] ?? '';

        if (!empty($cpt) && isset($rates[$cpt]) && $rates[$cpt] > 0) {
            $cpt_rate = $rates[$cpt];
            if ($includeSteps) {
                $steps[] = "Medicare rate ({$cpt}): \$" . number_format($cpt_rate, 2) . "/piece";
            }
        } else {
            // Fallback pricing
            $price_per_box = (float)($order['product_price'] ?? 0);
            if ($price_per_box <= 0) {
                $price_per_box = (float)($order['price_wholesale'] ?? 0);
            }
            if ($price_per_box > 0 && $pieces_per_box > 0) {
                $cpt_rate = $price_per_box / $pieces_per_box;
                if ($includeSteps) {
                    $steps[] = "Fallback rate: \$" . number_format($cpt_rate, 2) . "/piece";
                }
            }
        }

        $revenue = $billable_pieces * $cpt_rate;
        $order_cost = $totalBoxes * $cost_per_box;
    }

    if ($includeSteps) {
        $steps[] = "Revenue: \$" . number_format($revenue, 2);
        $steps[] = "Cost: \$" . number_format($order_cost, 2);
        $steps[] = "Profit: \$" . number_format($revenue - $order_cost, 2);
    }

    return [
        'revenue' => $revenue,
        'cost' => $order_cost,
        'profit' => $revenue - $order_cost,
        'boxes' => $totalBoxes,
        'pieces' => $totalPieces,
        'cpt_rate' => $cpt_rate,
        'is_wholesale' => $isWholesale,
        'calculation_steps' => $steps
    ];
}

/**
 * Load reimbursement rates from database
 */
function load_reimbursement_rates(PDO $pdo): array {
    $rates = [];
    try {
        $stmt = $pdo->query("SELECT hcpcs_code, medicare_allowable FROM reimbursement_rates WHERE medicare_allowable > 0");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rates[$row['hcpcs_code']] = (float)$row['medicare_allowable'];
        }
    } catch (Throwable $e) {
        error_log("[revenue_calculator] Could not load rates: " . $e->getMessage());
    }
    return $rates;
}

/**
 * Get comprehensive revenue metrics for dashboard
 *
 * @param PDO $pdo Database connection
 * @param string $dateFrom Start date (Y-m-d)
 * @param string $dateTo End date (Y-m-d)
 * @param string|null $physicianId Filter by physician
 * @return array Comprehensive metrics
 */
function get_revenue_metrics(PDO $pdo, string $dateFrom = '', string $dateTo = '', ?string $physicianId = null): array {
    $rates = load_reimbursement_rates($pdo);

    // Build query
    $where = "o.status NOT IN ('rejected', 'cancelled', 'draft')";
    $params = [];

    if ($dateFrom !== '') {
        $where .= " AND o.created_at >= :date_from";
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where .= " AND o.created_at <= :date_to";
        $params['date_to'] = $dateTo . ' 23:59:59';
    }
    if ($physicianId !== null && $physicianId !== '') {
        $where .= " AND o.user_id = :physician_id";
        $params['physician_id'] = $physicianId;
    }

    // Fetch orders with all needed fields
    $sql = "
        SELECT
            o.id,
            o.created_at,
            o.status,
            o.billed_by,
            o.product_price,
            o.frequency_per_week,
            o.duration_days,
            o.refills_allowed,
            o.qty_per_change,
            o.wounds_data,
            o.insurer_name,
            o.icd10_primary,
            o.icd10_secondary,
            o.user_id,
            o.patient_id,
            o.product_id,
            COALESCE(pr.hcpcs_code, o.cpt) AS cpt_code,
            pr.name AS product_name,
            pr.pieces_per_box,
            pr.price_wholesale,
            COALESCE(pp.cost_per_box, pr.cost_per_box, 0) AS cost_per_box,
            pp.custom_price AS practice_custom_price,
            u.practice_name,
            u.first_name AS phys_first,
            u.last_name AS phys_last
        FROM orders o
        LEFT JOIN products pr ON pr.id = o.product_id
        LEFT JOIN practice_pricing pp ON pp.user_id = o.user_id AND pp.product_id = o.product_id
        LEFT JOIN users u ON u.id = o.user_id
        WHERE {$where}
        ORDER BY o.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize metrics
    $metrics = [
        'total_orders' => 0,
        'total_revenue' => 0,
        'total_cost' => 0,
        'total_profit' => 0,
        'total_boxes' => 0,

        'wholesale' => [
            'orders' => 0,
            'revenue' => 0,
            'cost' => 0,
            'profit' => 0,
            'boxes' => 0
        ],
        'referral' => [
            'orders' => 0,
            'revenue' => 0,
            'cost' => 0,
            'profit' => 0,
            'boxes' => 0
        ],

        // High-impact metrics
        'payor_mix' => [],       // Insurance breakdown
        'product_mix' => [],     // Product breakdown
        'icd_codes' => [],       // ICD-10 usage
        'cpt_codes' => [],       // CPT/HCPCS codes
        'physician_revenue' => [], // Revenue by physician
        'monthly_trend' => [],   // Monthly revenue trend
        'status_breakdown' => [], // Orders by status

        'orders' => []  // Detailed order data for reports
    ];

    foreach ($orders as $order) {
        $calc = calculate_order_revenue($order, $rates);

        $metrics['total_orders']++;
        $metrics['total_revenue'] += $calc['revenue'];
        $metrics['total_cost'] += $calc['cost'];
        $metrics['total_profit'] += $calc['profit'];
        $metrics['total_boxes'] += $calc['boxes'];

        // Wholesale vs Referral
        $type = $calc['is_wholesale'] ? 'wholesale' : 'referral';
        $metrics[$type]['orders']++;
        $metrics[$type]['revenue'] += $calc['revenue'];
        $metrics[$type]['cost'] += $calc['cost'];
        $metrics[$type]['profit'] += $calc['profit'];
        $metrics[$type]['boxes'] += $calc['boxes'];

        // Payor mix
        $payor = $order['insurer_name'] ?: 'Unknown';
        if (!isset($metrics['payor_mix'][$payor])) {
            $metrics['payor_mix'][$payor] = ['orders' => 0, 'revenue' => 0, 'boxes' => 0];
        }
        $metrics['payor_mix'][$payor]['orders']++;
        $metrics['payor_mix'][$payor]['revenue'] += $calc['revenue'];
        $metrics['payor_mix'][$payor]['boxes'] += $calc['boxes'];

        // Product mix
        $product = $order['product_name'] ?: 'Unknown';
        if (!isset($metrics['product_mix'][$product])) {
            $metrics['product_mix'][$product] = ['orders' => 0, 'revenue' => 0, 'boxes' => 0];
        }
        $metrics['product_mix'][$product]['orders']++;
        $metrics['product_mix'][$product]['revenue'] += $calc['revenue'];
        $metrics['product_mix'][$product]['boxes'] += $calc['boxes'];

        // ICD codes
        $icd = $order['icd10_primary'] ?: 'Not specified';
        if (!isset($metrics['icd_codes'][$icd])) {
            $metrics['icd_codes'][$icd] = ['orders' => 0, 'revenue' => 0];
        }
        $metrics['icd_codes'][$icd]['orders']++;
        $metrics['icd_codes'][$icd]['revenue'] += $calc['revenue'];

        // CPT/HCPCS codes
        $cpt = $order['cpt_code'] ?: 'Unknown';
        if (!isset($metrics['cpt_codes'][$cpt])) {
            $metrics['cpt_codes'][$cpt] = ['orders' => 0, 'revenue' => 0, 'rate' => $calc['cpt_rate']];
        }
        $metrics['cpt_codes'][$cpt]['orders']++;
        $metrics['cpt_codes'][$cpt]['revenue'] += $calc['revenue'];

        // Physician revenue
        $physKey = $order['user_id'];
        $physName = trim(($order['phys_first'] ?? '') . ' ' . ($order['phys_last'] ?? '')) ?: 'Unknown';
        $practiceName = $order['practice_name'] ?: $physName;
        if (!isset($metrics['physician_revenue'][$physKey])) {
            $metrics['physician_revenue'][$physKey] = [
                'name' => $physName,
                'practice' => $practiceName,
                'orders' => 0,
                'revenue' => 0,
                'wholesale_revenue' => 0,
                'referral_revenue' => 0
            ];
        }
        $metrics['physician_revenue'][$physKey]['orders']++;
        $metrics['physician_revenue'][$physKey]['revenue'] += $calc['revenue'];
        if ($calc['is_wholesale']) {
            $metrics['physician_revenue'][$physKey]['wholesale_revenue'] += $calc['revenue'];
        } else {
            $metrics['physician_revenue'][$physKey]['referral_revenue'] += $calc['revenue'];
        }

        // Monthly trend
        $month = date('Y-m', strtotime($order['created_at']));
        if (!isset($metrics['monthly_trend'][$month])) {
            $metrics['monthly_trend'][$month] = [
                'orders' => 0,
                'revenue' => 0,
                'wholesale' => 0,
                'referral' => 0
            ];
        }
        $metrics['monthly_trend'][$month]['orders']++;
        $metrics['monthly_trend'][$month]['revenue'] += $calc['revenue'];
        $metrics['monthly_trend'][$month][$type] += $calc['revenue'];

        // Status breakdown
        $status = $order['status'] ?: 'unknown';
        if (!isset($metrics['status_breakdown'][$status])) {
            $metrics['status_breakdown'][$status] = ['orders' => 0, 'revenue' => 0];
        }
        $metrics['status_breakdown'][$status]['orders']++;
        $metrics['status_breakdown'][$status]['revenue'] += $calc['revenue'];

        // Store detailed order data
        $metrics['orders'][] = array_merge($order, [
            'calculated_revenue' => $calc['revenue'],
            'calculated_cost' => $calc['cost'],
            'calculated_profit' => $calc['profit'],
            'calculated_boxes' => $calc['boxes'],
            'order_type' => $calc['is_wholesale'] ? 'Wholesale' : 'Referral',
            'calculation_steps' => $calc['calculation_steps']
        ]);
    }

    // Sort breakdowns by revenue (descending)
    uasort($metrics['payor_mix'], fn($a, $b) => $b['revenue'] <=> $a['revenue']);
    uasort($metrics['product_mix'], fn($a, $b) => $b['revenue'] <=> $a['revenue']);
    uasort($metrics['icd_codes'], fn($a, $b) => $b['revenue'] <=> $a['revenue']);
    uasort($metrics['cpt_codes'], fn($a, $b) => $b['revenue'] <=> $a['revenue']);
    uasort($metrics['physician_revenue'], fn($a, $b) => $b['revenue'] <=> $a['revenue']);
    ksort($metrics['monthly_trend']); // Sort by month

    return $metrics;
}

/**
 * Get quick dashboard metrics (optimized for speed)
 */
function get_dashboard_metrics(PDO $pdo): array {
    // Get current month metrics
    $currentMonth = get_revenue_metrics($pdo, date('Y-m-01'), date('Y-m-d'));

    // Get last month for comparison
    $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
    $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
    $lastMonth = get_revenue_metrics($pdo, $lastMonthStart, $lastMonthEnd);

    // Get YTD metrics
    $ytd = get_revenue_metrics($pdo, date('Y-01-01'), date('Y-m-d'));

    // Calculate changes
    $revenueChange = $lastMonth['total_revenue'] > 0
        ? (($currentMonth['total_revenue'] - $lastMonth['total_revenue']) / $lastMonth['total_revenue']) * 100
        : 0;

    $ordersChange = $lastMonth['total_orders'] > 0
        ? (($currentMonth['total_orders'] - $lastMonth['total_orders']) / $lastMonth['total_orders']) * 100
        : 0;

    return [
        'current_month' => $currentMonth,
        'last_month' => $lastMonth,
        'ytd' => $ytd,
        'revenue_change_pct' => $revenueChange,
        'orders_change_pct' => $ordersChange,

        // Top metrics for quick glance
        'top_payors' => array_slice($currentMonth['payor_mix'], 0, 5, true),
        'top_products' => array_slice($currentMonth['product_mix'], 0, 5, true),
        'top_physicians' => array_slice($currentMonth['physician_revenue'], 0, 5, true)
    ];
}
