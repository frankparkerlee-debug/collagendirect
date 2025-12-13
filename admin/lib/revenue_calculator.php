<?php
/**
 * Revenue Calculator Library
 *
 * Unified revenue calculation logic used by both dashboard and revenue report.
 * Ensures consistent calculations across the entire platform.
 */
declare(strict_types=1);

/**
 * Convert text frequency to patches per week
 * Used by both billing.php and revenue calculations
 */
function patches_per_week_text($f): int {
    $f = strtolower(trim((string)$f));
    if ($f === 'daily') return 7;
    if ($f === 'every other day') return 4;
    if ($f === 'weekly') return 1;
    if (preg_match('/(\d+)\s*x\s*\/?\s*week/', $f, $m)) return max(1, (int)$m[1]);
    if (preg_match('/(\d+)\s*x\s*per\s*week/', $f, $m)) return max(1, (int)$m[1]);
    return 1;
}

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
    $accountType = $order['account_type'] ?? '';
    // Identify wholesale by billed_by OR by user's account_type
    $isWholesale = ($billedBy === 'practice_dme' || in_array($accountType, ['wholesale', 'dme_wholesale']));

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
        // Billable pieces = actual pieces needed, NOT boxes × pieces_per_box
        // Insurance reimburses for actual pieces used, not full boxes
        $billable_pieces = (int)ceil($total_pieces);
        $totalPieces = $billable_pieces;

        if ($includeSteps) {
            $steps[] = "Type: Referral";
            $steps[] = "Duration: {$days} days (" . number_format($weeks, 2) . " weeks)";
            $steps[] = "Frequency: {$fpw}x/week, Qty: {$qty}, Refills: {$refills}";
            $steps[] = "Pieces needed: " . number_format($total_pieces, 1) . " → {$billable_pieces} billable";
            $steps[] = "Boxes to ship: {$totalBoxes} (rounded up from " . number_format($total_pieces, 1) . " pieces)";
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
 * Rates are stored on the products table in the medicare_allowable column
 */
function load_reimbursement_rates(PDO $pdo): array {
    $rates = [];
    try {
        // Medicare allowable rates are stored per-product in the products table
        // We key by HCPCS code (which is hcpcs_code on products)
        $stmt = $pdo->query("
            SELECT DISTINCT hcpcs_code, medicare_allowable
            FROM products
            WHERE hcpcs_code IS NOT NULL
              AND hcpcs_code != ''
              AND medicare_allowable IS NOT NULL
              AND medicare_allowable > 0
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rates[$row['hcpcs_code']] = (float)$row['medicare_allowable'];
        }
    } catch (Throwable $e) {
        error_log("[revenue_calculator] Could not load rates from products table: " . $e->getMessage());
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
 * @param int|null $salesRepId Filter by sales rep (admin_users.id)
 * @return array Comprehensive metrics
 */
function get_revenue_metrics(PDO $pdo, string $dateFrom = '', string $dateTo = '', ?string $physicianId = null, ?int $salesRepId = null): array {
    $rates = load_reimbursement_rates($pdo);

    // Check if review_status column exists
    $hasReviewStatus = false;
    try {
        $checkCol = $pdo->query("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name='orders' AND column_name='review_status'");
        $hasReviewStatus = (int)($checkCol->fetch()['c'] ?? 0) > 0;
    } catch (Throwable $e) {
        // Ignore - assume column doesn't exist
    }

    // Check if soft delete columns exist
    $hasOrderDeletedAt = false;
    $hasPatientDeletedAt = false;
    try {
        $checkCol = $pdo->query("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name='orders' AND column_name='deleted_at'");
        $hasOrderDeletedAt = (int)($checkCol->fetch()['c'] ?? 0) > 0;
        $checkCol = $pdo->query("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name='patients' AND column_name='deleted_at'");
        $hasPatientDeletedAt = (int)($checkCol->fetch()['c'] ?? 0) > 0;
    } catch (Throwable $e) {
        // Ignore - assume columns don't exist
    }

    // Build query - match dashboard's logic: exclude rejected, cancelled, draft status
    // Also exclude orders where review_status is 'draft' (same as dashboard) - if column exists
    // Include ALL orders (both wholesale and referral) in revenue metrics
    $where = "o.status NOT IN ('rejected', 'cancelled', 'draft')";
    if ($hasReviewStatus) {
        $where .= " AND (o.review_status IS NULL OR o.review_status != 'draft')";
    }
    // Exclude soft-deleted orders and patients (only show billable orders)
    if ($hasOrderDeletedAt) {
        $where .= " AND o.deleted_at IS NULL";
    }
    if ($hasPatientDeletedAt) {
        $where .= " AND pt.deleted_at IS NULL";
    }
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
    if ($salesRepId !== null) {
        $where .= " AND o.user_id IN (SELECT physician_user_id FROM admin_physicians WHERE admin_id = :sales_rep_id)";
        $params['sales_rep_id'] = $salesRepId;
    }

    // Check if products table exists (match dashboard pattern)
    $hasProducts = false;
    try {
        $checkProducts = $pdo->query("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.TABLES WHERE table_name='products'");
        $hasProducts = (int)($checkProducts->fetch()['c'] ?? 0) > 0;
    } catch (Throwable $e) {
        // Ignore
    }

    // Check if wounds_data column exists
    $hasWoundsData = false;
    try {
        $checkWounds = $pdo->query("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name='orders' AND column_name='wounds_data'");
        $hasWoundsData = (int)($checkWounds->fetch()['c'] ?? 0) > 0;
    } catch (Throwable $e) {
        // Ignore
    }

    // Fetch orders with all needed fields - use conditional column selection like dashboard
    $sql = "
        SELECT
            o.id,
            o.order_group_id,
            o.created_at,
            o.status,
            o.billed_by,
            o.product_price,
            o.frequency_per_week,
            o.duration_days,
            o.refills_allowed,
            o.qty_per_change,
            " . ($hasWoundsData ? "o.wounds_data," : "NULL AS wounds_data,") . "
            o.insurer_name,
            o.icd10_primary,
            o.icd10_secondary,
            o.user_id,
            o.patient_id,
            pt.first_name AS patient_first,
            pt.last_name AS patient_last,
            o.product_id,
            " . ($hasProducts
                ? "COALESCE(pr.hcpcs_code, o.cpt) AS cpt_code,
                   pr.name AS product_name,
                   COALESCE(pr.pieces_per_box, 10) AS pieces_per_box,
                   pr.price_wholesale,
                   COALESCE(pp.cost_per_box, pr.cost_per_box, 0) AS cost_per_box,"
                : "o.cpt AS cpt_code,
                   COALESCE(o.product, 'Unknown') AS product_name,
                   10 AS pieces_per_box,
                   0 AS price_wholesale,
                   0 AS cost_per_box,") . "
            pp.custom_price AS practice_custom_price,
            u.practice_name,
            u.first_name AS phys_first,
            u.last_name AS phys_last,
            u.account_type,
            ap.admin_id AS sales_rep_id,
            au.name AS sales_rep_name
        FROM orders o
        LEFT JOIN patients pt ON pt.id = o.patient_id
        " . ($hasProducts ? "LEFT JOIN products pr ON pr.id = o.product_id" : "") . "
        LEFT JOIN practice_pricing pp ON pp.user_id = o.user_id AND pp.product_id = o.product_id
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN admin_physicians ap ON ap.physician_user_id = o.user_id
        LEFT JOIN admin_users au ON au.id = ap.admin_id AND au.role IN ('sales', 'admin', 'employee')
        WHERE {$where}
        ORDER BY o.created_at DESC
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("[revenue_calculator] Query returned " . count($orders) . " orders for date range: $dateFrom to $dateTo");
    } catch (Throwable $e) {
        error_log("[revenue_calculator] SQL Error: " . $e->getMessage());
        error_log("[revenue_calculator] SQL Query: " . $sql);
        $orders = [];
    }

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
        'sales_rep_revenue' => [], // Revenue by sales rep
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

        // Payor mix - Wholesale orders are always "Cash" (practice pays directly)
        if ($calc['is_wholesale']) {
            $payor = 'Cash (Wholesale)';
        } else {
            $payor = $order['insurer_name'] ?: 'Unknown';
        }
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

        // ICD codes - Extract from wounds_data for per-wound diagnosis tracking
        $woundIcds = [];
        $woundCount = 1;

        // Try to get ICD codes from wounds_data first (contains per-wound diagnoses)
        if (!empty($order['wounds_data'])) {
            $wounds = json_decode($order['wounds_data'], true);
            if (is_array($wounds)) {
                $woundCount = count($wounds);
                foreach ($wounds as $wound) {
                    if (!empty($wound['icd10_primary'])) {
                        $woundIcds[] = $wound['icd10_primary'];
                    }
                    if (!empty($wound['icd10_secondary'])) {
                        $woundIcds[] = $wound['icd10_secondary'];
                    }
                }
            }
        }

        // Fallback to order-level ICD codes if wounds_data doesn't have them
        if (empty($woundIcds)) {
            if (!empty($order['icd10_primary'])) {
                $woundIcds[] = $order['icd10_primary'];
            }
            if (!empty($order['icd10_secondary'])) {
                $woundIcds[] = $order['icd10_secondary'];
            }
        }

        // If still no ICD codes, mark as not specified
        if (empty($woundIcds)) {
            $woundIcds[] = 'Not specified';
        }

        // Calculate per-wound revenue share
        $revenuePerWound = $woundCount > 0 ? $calc['revenue'] / $woundCount : $calc['revenue'];
        $boxesPerWound = $woundCount > 0 ? $calc['boxes'] / $woundCount : $calc['boxes'];

        // Track each ICD code (unique per order to avoid double counting)
        $uniqueIcds = array_unique($woundIcds);
        foreach ($uniqueIcds as $icd) {
            if (!isset($metrics['icd_codes'][$icd])) {
                $metrics['icd_codes'][$icd] = ['orders' => 0, 'wounds' => 0, 'revenue' => 0, 'boxes' => 0];
            }
            $metrics['icd_codes'][$icd]['orders']++;
            $metrics['icd_codes'][$icd]['wounds'] += count(array_keys($woundIcds, $icd));
            $metrics['icd_codes'][$icd]['revenue'] += $revenuePerWound * count(array_keys($woundIcds, $icd));
            $metrics['icd_codes'][$icd]['boxes'] += $boxesPerWound * count(array_keys($woundIcds, $icd));
        }

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

        // Sales rep revenue
        $repId = $order['sales_rep_id'] ?? null;
        $repName = $order['sales_rep_name'] ?: 'Unassigned';
        if ($repId) {
            if (!isset($metrics['sales_rep_revenue'][$repId])) {
                $metrics['sales_rep_revenue'][$repId] = [
                    'name' => $repName,
                    'orders' => 0,
                    'revenue' => 0,
                    'wholesale_revenue' => 0,
                    'referral_revenue' => 0,
                    'physicians' => []
                ];
            }
            $metrics['sales_rep_revenue'][$repId]['orders']++;
            $metrics['sales_rep_revenue'][$repId]['revenue'] += $calc['revenue'];
            if ($calc['is_wholesale']) {
                $metrics['sales_rep_revenue'][$repId]['wholesale_revenue'] += $calc['revenue'];
            } else {
                $metrics['sales_rep_revenue'][$repId]['referral_revenue'] += $calc['revenue'];
            }
            // Track unique physicians for this rep
            if (!in_array($order['user_id'], $metrics['sales_rep_revenue'][$repId]['physicians'])) {
                $metrics['sales_rep_revenue'][$repId]['physicians'][] = $order['user_id'];
            }
        } else {
            // Track unassigned revenue
            if (!isset($metrics['sales_rep_revenue']['unassigned'])) {
                $metrics['sales_rep_revenue']['unassigned'] = [
                    'name' => 'Unassigned',
                    'orders' => 0,
                    'revenue' => 0,
                    'wholesale_revenue' => 0,
                    'referral_revenue' => 0,
                    'physicians' => []
                ];
            }
            $metrics['sales_rep_revenue']['unassigned']['orders']++;
            $metrics['sales_rep_revenue']['unassigned']['revenue'] += $calc['revenue'];
            if ($calc['is_wholesale']) {
                $metrics['sales_rep_revenue']['unassigned']['wholesale_revenue'] += $calc['revenue'];
            } else {
                $metrics['sales_rep_revenue']['unassigned']['referral_revenue'] += $calc['revenue'];
            }
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
    uasort($metrics['sales_rep_revenue'], fn($a, $b) => $b['revenue'] <=> $a['revenue']);
    ksort($metrics['monthly_trend']); // Sort by month

    return $metrics;
}

/**
 * Get Practice Value Metrics (Photo Reviews / E/M Billing)
 *
 * This tracks value delivered to practices through photo reviews.
 * This revenue goes to the PRACTICE, not CollagenDirect.
 * It's tracked separately to show value delivered without inflating our revenue.
 *
 * @param PDO $pdo Database connection
 * @param string $dateFrom Start date (Y-m-d)
 * @param string $dateTo End date (Y-m-d)
 * @param string|null $physicianId Filter by physician
 * @return array Practice value metrics
 */
function get_practice_value_metrics(PDO $pdo, string $dateFrom = '', string $dateTo = '', ?string $physicianId = null): array {
    $where = "be.status != 'cancelled'";
    $params = [];

    if ($dateFrom !== '') {
        $where .= " AND be.encounter_date >= :date_from";
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where .= " AND be.encounter_date <= :date_to";
        $params['date_to'] = $dateTo . ' 23:59:59';
    }
    if ($physicianId !== null && $physicianId !== '') {
        $where .= " AND be.physician_id = :physician_id";
        $params['physician_id'] = $physicianId;
    }

    $metrics = [
        'total_encounters' => 0,
        'total_charges' => 0,
        'encounters_by_cpt' => [],
        'encounters_by_assessment' => [],
        'encounters_by_physician' => [],
        'monthly_trend' => [],
        'exported_count' => 0,
        'pending_count' => 0
    ];

    try {
        // Check if billable_encounters table exists
        $tableCheck = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'billable_encounters')");
        if (!$tableCheck->fetchColumn()) {
            return $metrics;
        }

        $sql = "
            SELECT
                be.id,
                be.encounter_date,
                be.cpt_code,
                be.charge_amount,
                be.assessment,
                be.status,
                be.exported,
                be.physician_id,
                u.first_name AS phys_first,
                u.last_name AS phys_last,
                u.practice_name
            FROM billable_encounters be
            LEFT JOIN users u ON u.id = be.physician_id
            WHERE {$where}
            ORDER BY be.encounter_date DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $encounters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($encounters as $enc) {
            $metrics['total_encounters']++;
            $metrics['total_charges'] += (float)($enc['charge_amount'] ?? 0);

            if ($enc['exported']) {
                $metrics['exported_count']++;
            }
            if ($enc['status'] === 'pending') {
                $metrics['pending_count']++;
            }

            // By CPT code
            $cpt = $enc['cpt_code'] ?: 'Unknown';
            if (!isset($metrics['encounters_by_cpt'][$cpt])) {
                $metrics['encounters_by_cpt'][$cpt] = ['count' => 0, 'charges' => 0];
            }
            $metrics['encounters_by_cpt'][$cpt]['count']++;
            $metrics['encounters_by_cpt'][$cpt]['charges'] += (float)($enc['charge_amount'] ?? 0);

            // By assessment
            $assessment = $enc['assessment'] ?: 'Unknown';
            if (!isset($metrics['encounters_by_assessment'][$assessment])) {
                $metrics['encounters_by_assessment'][$assessment] = ['count' => 0, 'charges' => 0];
            }
            $metrics['encounters_by_assessment'][$assessment]['count']++;
            $metrics['encounters_by_assessment'][$assessment]['charges'] += (float)($enc['charge_amount'] ?? 0);

            // By physician
            $physId = $enc['physician_id'];
            $physName = trim(($enc['phys_first'] ?? '') . ' ' . ($enc['phys_last'] ?? '')) ?: 'Unknown';
            if (!isset($metrics['encounters_by_physician'][$physId])) {
                $metrics['encounters_by_physician'][$physId] = [
                    'name' => $physName,
                    'practice' => $enc['practice_name'] ?: $physName,
                    'count' => 0,
                    'charges' => 0
                ];
            }
            $metrics['encounters_by_physician'][$physId]['count']++;
            $metrics['encounters_by_physician'][$physId]['charges'] += (float)($enc['charge_amount'] ?? 0);

            // Monthly trend
            $month = date('Y-m', strtotime($enc['encounter_date']));
            if (!isset($metrics['monthly_trend'][$month])) {
                $metrics['monthly_trend'][$month] = ['count' => 0, 'charges' => 0];
            }
            $metrics['monthly_trend'][$month]['count']++;
            $metrics['monthly_trend'][$month]['charges'] += (float)($enc['charge_amount'] ?? 0);
        }

        // Sort
        uasort($metrics['encounters_by_cpt'], fn($a, $b) => $b['charges'] <=> $a['charges']);
        uasort($metrics['encounters_by_assessment'], fn($a, $b) => $b['charges'] <=> $a['charges']);
        uasort($metrics['encounters_by_physician'], fn($a, $b) => $b['charges'] <=> $a['charges']);
        ksort($metrics['monthly_trend']);

    } catch (Throwable $e) {
        error_log("[revenue_calculator] Practice value query error: " . $e->getMessage());
    }

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

        // Top metrics for quick glance - use YTD data for meaningful business insights
        // (Dashboard drill-down links go to YTD revenue report)
        'top_payors' => array_slice($ytd['payor_mix'], 0, 5, true),
        'top_products' => array_slice($ytd['product_mix'], 0, 5, true),
        'top_physicians' => array_slice($ytd['physician_revenue'], 0, 5, true)
    ];
}
