<?php
// admin/revenue-report.php - Comprehensive revenue verification and reporting
declare(strict_types=1);

require_once __DIR__ . '/db.php';
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
$auth      = __DIR__ . '/auth.php';      if (is_file($auth)) require_once $auth;
if (function_exists('require_admin')) require_admin();

// Get current admin user and role
$admin = current_admin();
$adminRole = $admin['role'] ?? '';

/* ================= Polyfills / safety ================= */
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* ================= Filters ================= */
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-01'); // Default to start of current month
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d'); // Default to today
$physicianId = isset($_GET['physician']) ? trim($_GET['physician']) : '';
$orderType = isset($_GET['order_type']) ? trim($_GET['order_type']) : 'all'; // all, wholesale, referral
$exportFormat = isset($_GET['export']) ? trim($_GET['export']) : ''; // csv, excel

/* ================= Check for required tables ================= */
$hasProducts = false;
$hasRates = false;
try {
  $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename IN ('products', 'reimbursement_rates')")->fetchAll(PDO::FETCH_COLUMN);
  $hasProducts = in_array('products', $tables);
  $hasRates = in_array('reimbursement_rates', $tables);
} catch (Throwable $e) {
  error_log("Could not check for tables: " . $e->getMessage());
}

// Load reimbursement rates
$rates = [];
if ($hasRates) {
  try {
    $stmt = $pdo->query("SELECT hcpcs_code, medicare_allowable FROM reimbursement_rates");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $rates[$row['hcpcs_code']] = (float)$row['medicare_allowable'];
    }
  } catch (Throwable $e) {
    error_log("Could not load reimbursement rates: " . $e->getMessage());
  }
}

/* ================= Build Query ================= */
$where = "o.status NOT IN ('rejected', 'cancelled', 'draft')";
$params = [];

// Date range filter
if ($dateFrom !== '') {
  $where .= " AND o.created_at >= :date_from";
  $params['date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
  $where .= " AND o.created_at <= :date_to";
  $params['date_to'] = $dateTo . ' 23:59:59';
}

// Physician filter
if ($physicianId !== '') {
  $where .= " AND o.user_id = :physician_id";
  $params['physician_id'] = $physicianId;
}

// Order type filter
if ($orderType === 'wholesale') {
  $where .= " AND o.billed_by = 'practice_dme'";
} elseif ($orderType === 'referral') {
  $where .= " AND (o.billed_by IS NULL OR o.billed_by = 'collagen_direct')";
}

/* ================= Fetch Orders ================= */
$orderQuery = "
  SELECT
    o.id,
    o.created_at,
    o.patient_id,
    o.user_id,
    o.product_id,
    o.product_price,
    o.status,
    o.order_group_id,
    o.frequency_per_week,
    o.duration_days,
    o.refills_allowed,
    o.qty_per_change,
    o.billed_by,
    o.wounds_data,
    pt.first_name AS patient_first,
    pt.last_name AS patient_last,
    u.first_name AS phys_first,
    u.last_name AS phys_last,
    u.practice_name,
    pp.custom_price AS practice_custom_price,
    " . ($hasProducts ? "pr.name AS product_name, pr.hcpcs_code AS cpt_code, pr.pieces_per_box, pr.price_wholesale, COALESCE(pp.cost_per_box, pr.cost_per_box, 0) AS cost_per_box" : "'Unknown' AS product_name, '' AS cpt_code, 10 AS pieces_per_box, 0 AS price_wholesale, 0 AS cost_per_box") . "
  FROM orders o
  LEFT JOIN patients pt ON pt.id = o.patient_id
  LEFT JOIN users u ON u.id = o.user_id
  " . ($hasProducts ? "LEFT JOIN products pr ON pr.id = o.product_id" : "") . "
  LEFT JOIN practice_pricing pp ON pp.user_id = o.user_id AND pp.product_id = o.product_id
  WHERE " . $where . "
  ORDER BY
    COALESCE(o.order_group_id, o.id) DESC,
    o.created_at DESC,
    o.product_id ASC
";

$stmt = $pdo->prepare($orderQuery);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= Group Orders by order_group_id ================= */
$grouped_orders = [];
foreach ($orders as $order) {
  // Use order_group_id if available, otherwise use order id (single-product orders)
  $key = !empty($order['order_group_id']) ? $order['order_group_id'] : $order['id'];

  if (!isset($grouped_orders[$key])) {
    $grouped_orders[$key] = [
      'is_group' => !empty($order['order_group_id']),
      'group_id' => $order['order_group_id'],
      'orders' => []
    ];
  }

  $grouped_orders[$key]['orders'][] = $order;
}

/* ================= Calculate Revenue for Each Order/Group ================= */
$reportData = [];
$totalWholesaleRevenue = 0;
$totalReferralRevenue = 0;
$totalWholesaleBoxes = 0;
$totalReferralBoxes = 0;
$totalWholesaleOrders = 0;
$totalReferralOrders = 0;
$totalWholesaleCost = 0;
$totalReferralCost = 0;

foreach ($grouped_orders as $group) {
  $is_multi_product = $group['is_group'];
  $orders_in_group = $group['orders'];

  // Use first order for shared data (patient, physician, date, etc.)
  $first_order = $orders_in_group[0];

  $patient_name = trim(($first_order['patient_first'] ?? '') . ' ' . ($first_order['patient_last'] ?? ''));
  $physician_name = trim(($first_order['phys_first'] ?? '') . ' ' . ($first_order['phys_last'] ?? ''));
  $date = $first_order['created_at'];
  $billedBy = $first_order['billed_by'] ?? 'collagen_direct';
  $isWholesale = ($billedBy === 'practice_dme');

  // Calculate totals across all products in the group
  $products_detail = [];
  $group_total_boxes = 0;
  $group_total_revenue = 0;
  $group_total_cost = 0;
  $all_calculation_steps = [];

  foreach ($orders_in_group as $order) {
    $pieces_per_box = max(1, (int)($order['pieces_per_box'] ?? 10));
    $cost_per_box = (float)($order['cost_per_box'] ?? 0);

    $calculationSteps = [];
    $revenue = 0;
    $totalBoxes = 0;
    $order_cost = 0;

    if ($isWholesale) {
      // WHOLESALE CALCULATION
      $totalBoxes = max(1, (int)($order['qty_per_change'] ?? 1));
      $product_price_per_piece = (float)($order['product_price'] ?? 0);

      $calculationSteps[] = "Product: " . ($order['product_name'] ?? 'Unknown');
      $calculationSteps[] = "Wholesale Order";
      $calculationSteps[] = "Boxes ordered: {$totalBoxes}";

      if ($product_price_per_piece > 0) {
        $price_per_box = $product_price_per_piece * $pieces_per_box;
        $calculationSteps[] = "Price per piece: $" . number_format($product_price_per_piece, 2);
        $calculationSteps[] = "Pieces per box: {$pieces_per_box}";
        $calculationSteps[] = "Price per box: $" . number_format($price_per_box, 2);
      } else {
        // Use wholesale price from products table (already fetched in query)
        $price_per_box = (float)($order['price_wholesale'] ?? 0);
        if ($price_per_box > 0) {
          $calculationSteps[] = "Using wholesale price: $" . number_format($price_per_box, 2) . "/box (from product table)";
        } else {
          $calculationSteps[] = "No pricing available - revenue = $0";
        }
      }

      $revenue = $totalBoxes * $price_per_box;
      $order_cost = $totalBoxes * $cost_per_box;
      $calculationSteps[] = "Revenue: {$totalBoxes} × $" . number_format($price_per_box, 2) . " = $" . number_format($revenue, 2);
      $calculationSteps[] = "Cost: {$totalBoxes} × $" . number_format($cost_per_box, 2) . " = $" . number_format($order_cost, 2);

    } else {
      // REFERRAL CALCULATION
      // Don't apply dangerous defaults - use actual values from order
      $fpw = (int)($order['frequency_per_week'] ?? 0);
      $qty = max(1, (int)($order['qty_per_change'] ?? 1));
      $days = (int)($order['duration_days'] ?? 0);

      // For multi-product orders, if frequency_per_week is NULL, try to get it from wounds_data JSON
      if ($fpw === 0 && !empty($first_order['wounds_data'])) {
        $wounds_data = json_decode($first_order['wounds_data'], true);
        if (is_array($wounds_data) && isset($wounds_data[0]['frequency_per_week'])) {
          $fpw = (int)$wounds_data[0]['frequency_per_week'];
          error_log("INFO: Order {$order['id']} using frequency_per_week from wounds_data: {$fpw}");
        }
      }

      // Only apply defaults if values are truly missing
      if ($fpw === 0) {
        error_log("WARNING: Order {$order['id']} has frequency_per_week = 0, using 1");
        $fpw = 1;
      }
      if ($days === 0) {
        error_log("WARNING: Order {$order['id']} has duration_days = 0, using 30");
        $days = 30;
      }

      $refills = max(0, (int)($order['refills_allowed'] ?? 0));

      $weeks = $days / 7.0;
      $total_pieces = $weeks * $fpw * $qty * (1 + $refills);
      $totalBoxes = (int)ceil($total_pieces / $pieces_per_box);
      $billable_pieces = $totalBoxes * $pieces_per_box;

      $calculationSteps[] = "Product: " . ($order['product_name'] ?? 'Unknown');
      $calculationSteps[] = "Referral Order";
      $calculationSteps[] = "Duration: {$days} days ({$weeks} weeks)";
      $calculationSteps[] = "Frequency: {$fpw}×/week";
      $calculationSteps[] = "Qty per change: {$qty} pieces";
      $calculationSteps[] = "Refills: {$refills}";
      $calculationSteps[] = "Total pieces needed: " . number_format($total_pieces, 2);
      $calculationSteps[] = "Pieces per box: {$pieces_per_box}";
      $calculationSteps[] = "Boxes needed: {$totalBoxes} (rounded up)";
      $calculationSteps[] = "Billable pieces: {$billable_pieces} (box increments)";

      // Get CPT rate
      $cpt_rate_per_piece = 0.0;
      $cpt = $order['cpt_code'] ?? '';
      if ($hasRates && $cpt && isset($rates[$cpt]) && $rates[$cpt] > 0) {
        $cpt_rate_per_piece = $rates[$cpt];
        $calculationSteps[] = "Medicare rate ({$cpt}): $" . number_format($cpt_rate_per_piece, 2) . "/piece";
      } else {
        // Try order's stored price first
        $price_per_box = (float)($order['product_price'] ?? 0);

        // Fall back to product's current wholesale price
        if ($price_per_box <= 0) {
          $price_per_box = (float)($order['price_wholesale'] ?? 0);
        }

        if ($price_per_box > 0 && $pieces_per_box > 0) {
          $cpt_rate_per_piece = $price_per_box / $pieces_per_box;
          $calculationSteps[] = "Estimated rate: $" . number_format($cpt_rate_per_piece, 2) . "/piece (from " . ($order['product_price'] > 0 ? 'order' : 'product table') . ")";
        } else {
          $cpt_rate_per_piece = 0.0;
          $calculationSteps[] = "No pricing available - revenue = $0";
        }
      }

      $revenue = $billable_pieces * $cpt_rate_per_piece;
      $order_cost = $totalBoxes * $cost_per_box;
      $calculationSteps[] = "Revenue: {$billable_pieces} × $" . number_format($cpt_rate_per_piece, 2) . " = $" . number_format($revenue, 2);
      $calculationSteps[] = "Cost: {$totalBoxes} × $" . number_format($cost_per_box, 2) . " = $" . number_format($order_cost, 2);
    }

    // Store per-product details
    $products_detail[] = [
      'product_name' => $order['product_name'] ?? 'Unknown',
      'boxes' => $totalBoxes,
      'cost' => $order_cost,
      'revenue' => $revenue
    ];

    $group_total_boxes += $totalBoxes;
    $group_total_revenue += $revenue;
    $group_total_cost += $order_cost;

    // Add separator between products
    if (count($orders_in_group) > 1) {
      $calculationSteps[] = str_repeat('-', 40);
    }
    $all_calculation_steps = array_merge($all_calculation_steps, $calculationSteps);
  }

  // Update totals
  if ($isWholesale) {
    $totalWholesaleRevenue += $group_total_revenue;
    $totalWholesaleBoxes += $group_total_boxes;
    $totalWholesaleOrders++;
    $totalWholesaleCost += $group_total_cost;
  } else {
    $totalReferralRevenue += $group_total_revenue;
    $totalReferralBoxes += $group_total_boxes;
    $totalReferralOrders++;
    $totalReferralCost += $group_total_cost;
  }

  // Add ONE row per group
  $reportData[] = [
    'order_id' => $first_order['id'], // Use first order's ID for easy reference
    'is_multi_product' => $is_multi_product,
    'date' => $date,
    'patient_name' => $patient_name,
    'physician_name' => $physician_name,
    'practice_name' => $first_order['practice_name'] ?? '',
    'products' => $products_detail,
    'product_name' => $is_multi_product
      ? count($orders_in_group) . ' products'
      : ($first_order['product_name'] ?? 'Unknown'),
    'order_type' => $isWholesale ? 'Wholesale' : 'Referral',
    'boxes' => $group_total_boxes,
    'cost_per_box' => null, // N/A for groups with multiple products
    'total_cost' => $group_total_cost,
    'revenue' => $group_total_revenue,
    'profit' => $group_total_revenue - $group_total_cost,
    'calculation_steps' => $all_calculation_steps,
    'status' => $first_order['status']
  ];
}

/* ================= Export Handling ================= */
if ($exportFormat === 'csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="revenue-report-' . date('Y-m-d') . '.csv"');

  $output = fopen('php://output', 'w');

  // CSV Headers
  fputcsv($output, ['Order ID', 'Date', 'Patient', 'Physician', 'Practice', 'Product', 'Type', 'Boxes', 'Cost/Box', 'Total Cost', 'Revenue', 'Profit', 'Status']);

  // CSV Data
  foreach ($reportData as $row) {
    if ($row['is_multi_product']) {
      // For multi-product orders, export main row with combined totals
      fputcsv($output, [
        $row['order_id'],
        substr($row['date'], 0, 10),
        $row['patient_name'],
        $row['physician_name'],
        $row['practice_name'],
        $row['product_name'] . ' (Multi-Product Order)',
        $row['order_type'],
        $row['boxes'],
        'N/A',
        number_format($row['total_cost'], 2),
        number_format($row['revenue'], 2),
        number_format($row['profit'], 2),
        $row['status']
      ]);

      // Export individual product details as indented sub-rows
      foreach ($row['products'] as $p) {
        fputcsv($output, [
          '',
          '',
          '',
          '',
          '',
          '  → ' . $p['product_name'],
          '',
          $p['boxes'],
          '',
          number_format($p['cost'], 2),
          number_format($p['revenue'], 2),
          '',
          ''
        ]);
      }
    } else {
      // Single product order
      fputcsv($output, [
        $row['order_id'],
        substr($row['date'], 0, 10),
        $row['patient_name'],
        $row['physician_name'],
        $row['practice_name'],
        $row['product_name'],
        $row['order_type'],
        $row['boxes'],
        $row['cost_per_box'] !== null ? number_format($row['cost_per_box'], 2) : 'N/A',
        number_format($row['total_cost'], 2),
        number_format($row['revenue'], 2),
        number_format($row['profit'], 2),
        $row['status']
      ]);
    }
  }

  // Summary rows
  fputcsv($output, []);
  fputcsv($output, ['SUMMARY']);
  fputcsv($output, ['Total Wholesale Orders', $totalWholesaleOrders, '', '', '', '', '', $totalWholesaleBoxes, '', number_format($totalWholesaleCost, 2), number_format($totalWholesaleRevenue, 2), number_format($totalWholesaleRevenue - $totalWholesaleCost, 2)]);
  fputcsv($output, ['Total Referral Orders', $totalReferralOrders, '', '', '', '', '', $totalReferralBoxes, '', number_format($totalReferralCost, 2), number_format($totalReferralRevenue, 2), number_format($totalReferralRevenue - $totalReferralCost, 2)]);
  fputcsv($output, ['GRAND TOTAL', ($totalWholesaleOrders + $totalReferralOrders), '', '', '', '', '', ($totalWholesaleBoxes + $totalReferralBoxes), '', number_format($totalWholesaleCost + $totalReferralCost, 2), number_format($totalWholesaleRevenue + $totalReferralRevenue, 2), number_format(($totalWholesaleRevenue + $totalReferralRevenue) - ($totalWholesaleCost + $totalReferralCost), 2)]);

  fclose($output);
  exit;
}

/* ================= Get Physician List for Filter ================= */
$physicians = [];
try {
  $stmt = $pdo->query("SELECT id, first_name, last_name, practice_name FROM users WHERE role IN ('physician', 'practice_admin') ORDER BY practice_name, first_name, last_name");
  $physicians = $stmt->fetchAll();
} catch (Throwable $e) {
  error_log("[revenue-report] " . $e->getMessage());
}

/* ================= View ================= */
include __DIR__.'/_header.php';
?>

<div class="max-w-7xl">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h2 class="text-2xl font-bold text-slate-900">Revenue Report & Verification</h2>
      <p class="text-sm text-slate-600 mt-1">Detailed revenue calculations and order-level verification</p>
    </div>
    <div class="flex gap-2">
      <a href="?<?=http_build_query(array_merge($_GET, ['export' => 'csv']))?>" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition flex items-center gap-2">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
        Export CSV
      </a>
    </div>
  </div>

  <!-- Filters -->
  <div class="bg-white border rounded-lg p-4 mb-6 shadow-sm">
    <form method="get" action="" class="grid grid-cols-1 md:grid-cols-5 gap-3">
      <div>
        <label class="text-xs text-slate-500 mb-1 block">Date From</label>
        <input type="date" name="date_from" value="<?=e($dateFrom)?>" class="w-full border rounded px-3 py-1.5 text-sm">
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Date To</label>
        <input type="date" name="date_to" value="<?=e($dateTo)?>" class="w-full border rounded px-3 py-1.5 text-sm">
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Physician/Practice</label>
        <select name="physician" class="w-full border rounded px-3 py-1.5 text-sm">
          <option value="">All Physicians</option>
          <?php foreach ($physicians as $p): ?>
            <option value="<?=e($p['id'])?>" <?=$physicianId==(string)$p['id']?'selected':''?>>
              <?=e($p['practice_name'] ?: ($p['first_name'] . ' ' . $p['last_name']))?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Order Type</label>
        <select name="order_type" class="w-full border rounded px-3 py-1.5 text-sm">
          <option value="all" <?=$orderType==='all'?'selected':''?>>All Types</option>
          <option value="wholesale" <?=$orderType==='wholesale'?'selected':''?>>Wholesale Only</option>
          <option value="referral" <?=$orderType==='referral'?'selected':''?>>Referral Only</option>
        </select>
      </div>

      <div class="flex items-end">
        <button type="submit" class="w-full px-4 py-1.5 bg-brand text-white rounded text-sm hover:bg-brand/90 transition-colors">
          Apply Filters
        </button>
      </div>
    </form>
  </div>

  <!-- Summary Cards -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white border rounded-lg p-4 shadow-sm">
      <div class="text-xs text-slate-500 mb-1">Total Orders</div>
      <div class="text-2xl font-bold text-slate-900"><?=count($reportData)?></div>
    </div>

    <div class="bg-white border rounded-lg p-4 shadow-sm">
      <div class="text-xs text-slate-500 mb-1">Wholesale Revenue</div>
      <div class="text-2xl font-bold text-blue-600">$<?=number_format($totalWholesaleRevenue, 2)?></div>
      <div class="text-xs text-slate-500 mt-1"><?=$totalWholesaleOrders?> orders, <?=$totalWholesaleBoxes?> boxes</div>
    </div>

    <div class="bg-white border rounded-lg p-4 shadow-sm">
      <div class="text-xs text-slate-500 mb-1">Referral Revenue</div>
      <div class="text-2xl font-bold text-green-600">$<?=number_format($totalReferralRevenue, 2)?></div>
      <div class="text-xs text-slate-500 mt-1"><?=$totalReferralOrders?> orders, <?=$totalReferralBoxes?> boxes</div>
    </div>

    <div class="bg-white border rounded-lg p-4 shadow-sm">
      <div class="text-xs text-slate-500 mb-1">Total Revenue</div>
      <div class="text-2xl font-bold text-slate-900">$<?=number_format($totalWholesaleRevenue + $totalReferralRevenue, 2)?></div>
      <div class="text-xs text-slate-500 mt-1"><?=count($reportData)?> total orders</div>
    </div>
  </div>

  <!-- Detailed Report Table -->
  <section class="bg-white border rounded-lg shadow-sm">
    <div class="p-4 border-b">
      <h3 class="font-semibold text-slate-900">Detailed Order-by-Order Breakdown</h3>
      <p class="text-xs text-slate-500 mt-1">Click any row to see full calculation details</p>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b">
          <tr class="text-left">
            <th class="py-2 px-4 font-medium">Order ID</th>
            <th class="py-2 px-4 font-medium">Date</th>
            <th class="py-2 px-4 font-medium">Patient</th>
            <th class="py-2 px-4 font-medium">Physician/Practice</th>
            <th class="py-2 px-4 font-medium">Product</th>
            <th class="py-2 px-4 font-medium">Type</th>
            <th class="py-2 px-4 font-medium text-right">Boxes</th>
            <th class="py-2 px-4 font-medium text-right">Cost/Box</th>
            <th class="py-2 px-4 font-medium text-right">Total Cost</th>
            <th class="py-2 px-4 font-medium text-right">Revenue</th>
            <th class="py-2 px-4 font-medium text-right">Profit</th>
            <th class="py-2 px-4 font-medium">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($reportData)): ?>
            <tr>
              <td colspan="12" class="py-8 text-center text-slate-500">
                No orders found for the selected filters
              </td>
            </tr>
          <?php else: ?>
            <?php foreach($reportData as $idx => $row): ?>
              <tr class="border-t hover:bg-slate-50 cursor-pointer" onclick="toggleCalculation('calc-<?=$idx?>')">
                <td class="py-2 px-4 font-mono text-xs"><?=e($row['order_id'])?></td>
                <td class="py-2 px-4 text-xs"><?=e(substr($row['date'], 0, 10))?></td>
                <td class="py-2 px-4"><?=e($row['patient_name'])?></td>
                <td class="py-2 px-4">
                  <div class="text-xs"><?=e($row['physician_name'])?></div>
                  <?php if ($row['practice_name']): ?>
                    <div class="text-[10px] text-slate-500"><?=e($row['practice_name'])?></div>
                  <?php endif; ?>
                </td>
                <td class="py-2 px-4 text-xs">
                  <?php if ($row['is_multi_product']): ?>
                    <details class="cursor-pointer">
                      <summary class="font-medium text-blue-600"><?=e($row['product_name'])?></summary>
                      <ul class="mt-1 ml-2 text-[10px] text-slate-600 space-y-0.5">
                        <?php foreach ($row['products'] as $p): ?>
                          <li>• <?=e($p['product_name'])?> - <?=$p['boxes']?> boxes</li>
                        <?php endforeach; ?>
                      </ul>
                    </details>
                  <?php else: ?>
                    <?=e($row['product_name'])?>
                  <?php endif; ?>
                </td>
                <td class="py-2 px-4">
                  <span class="inline-block px-2 py-0.5 rounded text-[10px] font-medium <?=$row['order_type']==='Wholesale'?'bg-blue-100 text-blue-700':'bg-green-100 text-green-700'?>">
                    <?=e($row['order_type'])?>
                  </span>
                </td>
                <td class="py-2 px-4 text-right font-medium"><?=$row['boxes']?></td>
                <td class="py-2 px-4 text-right text-xs text-slate-600">
                  <?php if ($row['cost_per_box'] !== null): ?>
                    $<?=number_format($row['cost_per_box'], 2)?>
                  <?php else: ?>
                    <span class="text-slate-400">-</span>
                  <?php endif; ?>
                </td>
                <td class="py-2 px-4 text-right font-medium text-orange-600">$<?=number_format($row['total_cost'], 2)?></td>
                <td class="py-2 px-4 text-right font-medium">$<?=number_format($row['revenue'], 2)?></td>
                <td class="py-2 px-4 text-right font-medium text-green-600">$<?=number_format($row['profit'], 2)?></td>
                <td class="py-2 px-4">
                  <span class="inline-block px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600">
                    <?=e(ucfirst($row['status']))?>
                  </span>
                </td>
              </tr>
              <tr id="calc-<?=$idx?>" class="hidden bg-slate-50 border-t">
                <td colspan="12" class="py-3 px-4">
                  <div class="bg-white rounded border p-3">
                    <h4 class="font-semibold text-xs text-slate-700 mb-2">Calculation Details:</h4>
                    <ul class="space-y-1 text-xs text-slate-600 font-mono">
                      <?php foreach ($row['calculation_steps'] as $step): ?>
                        <li><?=e($step)?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <?php if (!empty($reportData)): ?>
          <tfoot class="bg-slate-50 border-t-2 border-slate-300 font-semibold">
            <tr>
              <td colspan="6" class="py-3 px-4 text-right">TOTAL:</td>
              <td class="py-3 px-4 text-right"><?=$totalWholesaleBoxes + $totalReferralBoxes?></td>
              <td class="py-3 px-4 text-right"></td>
              <td class="py-3 px-4 text-right text-orange-600">$<?=number_format($totalWholesaleCost + $totalReferralCost, 2)?></td>
              <td class="py-3 px-4 text-right">$<?=number_format($totalWholesaleRevenue + $totalReferralRevenue, 2)?></td>
              <td class="py-3 px-4 text-right text-green-600">$<?=number_format(($totalWholesaleRevenue + $totalReferralRevenue) - ($totalWholesaleCost + $totalReferralCost), 2)?></td>
              <td class="py-3 px-4"></td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </section>

  <!-- Methodology -->
  <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
    <h4 class="font-semibold text-blue-900 mb-2">Revenue Calculation Methodology</h4>
    <div class="text-sm text-blue-800 space-y-2">
      <div>
        <strong>Wholesale Orders:</strong>
        <ul class="list-disc list-inside ml-4 mt-1 text-xs">
          <li>Revenue = Boxes Ordered × Practice-Specific Price Per Box</li>
          <li>Practice-specific pricing includes custom pricing and discount percentages</li>
          <li>Wholesale orders are one-time purchases (not subscription-based)</li>
        </ul>
      </div>
      <div>
        <strong>Referral Orders:</strong>
        <ul class="list-disc list-inside ml-4 mt-1 text-xs">
          <li>Calculate total pieces needed: (Duration ÷ 7) × Frequency × Qty × (1 + Refills)</li>
          <li>Round to box increments: Billable Pieces = Boxes Needed × Pieces Per Box</li>
          <li>Revenue = Billable Pieces × Medicare Allowable Rate Per Piece</li>
          <li>Example: 15 pieces needed, 10/box → Bill for 20 pieces (2 boxes)</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
function toggleCalculation(id) {
  const row = document.getElementById(id);
  if (row) {
    row.classList.toggle('hidden');
  }
}
</script>

<?php include __DIR__.'/_footer.php'; ?>
