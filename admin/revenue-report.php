<?php
/**
 * Revenue Report - Comprehensive Financial Analytics
 *
 * High-impact metrics for tracking:
 * - Payor mix (insurance breakdown)
 * - Reimbursement rates and CPT analysis
 * - Product performance
 * - Volume trends
 * - ICD-10 code distribution
 * - Physician/practice performance
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/revenue_calculator.php';
if (function_exists('require_admin')) require_admin();

$admin = current_admin();
$adminRole = $admin['role'] ?? '';

// Escape helper
if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* ================= Filters ================= */
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$physicianId = $_GET['physician'] ?? '';
$salesRepId = isset($_GET['sales_rep']) && $_GET['sales_rep'] !== '' ? (int)$_GET['sales_rep'] : null;
$orderType = $_GET['order_type'] ?? 'all';
$exportFormat = $_GET['export'] ?? '';
$viewMode = $_GET['view'] ?? 'summary'; // summary, detailed, export

/* ================= Get Metrics ================= */
$metrics = get_revenue_metrics($pdo, $dateFrom, $dateTo, $physicianId ?: null, $salesRepId);

// Filter by order type if specified
if ($orderType === 'wholesale') {
    $metrics['orders'] = array_filter($metrics['orders'], fn($o) => $o['order_type'] === 'Wholesale');
} elseif ($orderType === 'referral') {
    $metrics['orders'] = array_filter($metrics['orders'], fn($o) => $o['order_type'] === 'Referral');
}

/* ================= CSV Export ================= */
if ($exportFormat === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="revenue-report-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Summary section
    fputcsv($output, ['REVENUE REPORT - ' . $dateFrom . ' to ' . $dateTo]);
    fputcsv($output, []);
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Orders', $metrics['total_orders']]);
    fputcsv($output, ['Total Revenue', '$' . number_format($metrics['total_revenue'], 2)]);
    fputcsv($output, ['Total Cost', '$' . number_format($metrics['total_cost'], 2)]);
    fputcsv($output, ['Total Profit', '$' . number_format($metrics['total_profit'], 2)]);
    fputcsv($output, ['Profit Margin', $metrics['total_revenue'] > 0 ? number_format(($metrics['total_profit'] / $metrics['total_revenue']) * 100, 1) . '%' : 'N/A']);
    fputcsv($output, []);
    fputcsv($output, ['Wholesale Orders', $metrics['wholesale']['orders']]);
    fputcsv($output, ['Wholesale Revenue', '$' . number_format($metrics['wholesale']['revenue'], 2)]);
    fputcsv($output, ['Referral Orders', $metrics['referral']['orders']]);
    fputcsv($output, ['Referral Revenue', '$' . number_format($metrics['referral']['revenue'], 2)]);
    fputcsv($output, []);

    // Payor Mix
    fputcsv($output, ['PAYOR MIX']);
    fputcsv($output, ['Insurance', 'Orders', 'Revenue', '% of Revenue']);
    foreach ($metrics['payor_mix'] as $payor => $data) {
        $pct = $metrics['total_revenue'] > 0 ? ($data['revenue'] / $metrics['total_revenue']) * 100 : 0;
        fputcsv($output, [$payor, $data['orders'], '$' . number_format($data['revenue'], 2), number_format($pct, 1) . '%']);
    }
    fputcsv($output, []);

    // Product Mix
    fputcsv($output, ['PRODUCT MIX']);
    fputcsv($output, ['Product', 'Orders', 'Boxes', 'Revenue', '% of Revenue']);
    foreach ($metrics['product_mix'] as $product => $data) {
        $pct = $metrics['total_revenue'] > 0 ? ($data['revenue'] / $metrics['total_revenue']) * 100 : 0;
        fputcsv($output, [$product, $data['orders'], $data['boxes'], '$' . number_format($data['revenue'], 2), number_format($pct, 1) . '%']);
    }
    fputcsv($output, []);

    // CPT/HCPCS Analysis
    fputcsv($output, ['CPT/HCPCS ANALYSIS']);
    fputcsv($output, ['Code', 'Orders', 'Revenue', 'Medicare Rate']);
    foreach ($metrics['cpt_codes'] as $cpt => $data) {
        fputcsv($output, [$cpt, $data['orders'], '$' . number_format($data['revenue'], 2), '$' . number_format($data['rate'], 2)]);
    }
    fputcsv($output, []);

    // ICD-10 Distribution
    fputcsv($output, ['ICD-10 DISTRIBUTION']);
    fputcsv($output, ['Code', 'Orders', 'Wounds', 'Boxes', 'Revenue']);
    foreach ($metrics['icd_codes'] as $icd => $data) {
        fputcsv($output, [
            $icd,
            $data['orders'],
            $data['wounds'] ?? $data['orders'],
            number_format($data['boxes'] ?? 0, 0),
            '$' . number_format($data['revenue'], 2)
        ]);
    }
    fputcsv($output, []);

    // Physician Performance
    fputcsv($output, ['PHYSICIAN/PRACTICE PERFORMANCE']);
    fputcsv($output, ['Practice', 'Physician', 'Orders', 'Total Revenue', 'Wholesale', 'Referral']);
    foreach ($metrics['physician_revenue'] as $data) {
        fputcsv($output, [
            $data['practice'],
            $data['name'],
            $data['orders'],
            '$' . number_format($data['revenue'], 2),
            '$' . number_format($data['wholesale_revenue'], 2),
            '$' . number_format($data['referral_revenue'], 2)
        ]);
    }
    fputcsv($output, []);

    // Sales Rep Performance
    fputcsv($output, ['SALES REP PERFORMANCE']);
    fputcsv($output, ['Sales Rep', 'Orders', 'Physicians', 'Total Revenue', 'Wholesale', 'Referral']);
    foreach ($metrics['sales_rep_revenue'] as $data) {
        fputcsv($output, [
            $data['name'],
            $data['orders'],
            count($data['physicians']),
            '$' . number_format($data['revenue'], 2),
            '$' . number_format($data['wholesale_revenue'], 2),
            '$' . number_format($data['referral_revenue'], 2)
        ]);
    }
    fputcsv($output, []);

    // Detailed Orders
    fputcsv($output, ['DETAILED ORDERS']);
    fputcsv($output, ['Order ID', 'Date', 'Patient', 'Practice', 'Product', 'Type', 'Boxes', 'Cost', 'Revenue', 'Profit', 'Insurance', 'ICD-10', 'Status']);
    foreach ($metrics['orders'] as $order) {
        $patientName = trim(($order['patient_first'] ?? '') . ' ' . ($order['patient_last'] ?? ''));
        fputcsv($output, [
            $order['id'],
            date('Y-m-d', strtotime($order['created_at'])),
            $patientName ?: 'N/A',
            $order['practice_name'] ?: 'N/A',
            $order['product_name'] ?: 'Unknown',
            $order['order_type'],
            $order['calculated_boxes'],
            '$' . number_format($order['calculated_cost'], 2),
            '$' . number_format($order['calculated_revenue'], 2),
            '$' . number_format($order['calculated_profit'], 2),
            $order['insurer_name'] ?: 'Unknown',
            $order['icd10_primary'] ?: 'N/A',
            $order['status']
        ]);
    }

    fclose($output);
    exit;
}

/* ================= Get Physicians for Filter ================= */
$physicians = [];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, practice_name FROM users WHERE role IN ('physician', 'practice_admin') ORDER BY practice_name, first_name");
    $physicians = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log("[revenue-report] " . $e->getMessage());
}

/* ================= Get Sales Reps for Filter ================= */
$salesReps = [];
try {
    $stmt = $pdo->query("SELECT id, name, email FROM admin_users WHERE role IN ('sales', 'admin', 'employee') ORDER BY name");
    $salesReps = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log("[revenue-report] " . $e->getMessage());
}

/* ================= View ================= */
include __DIR__ . '/_header.php';
?>

<style>
.metric-card { transition: transform 0.2s, box-shadow 0.2s; }
.metric-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
.chart-bar { transition: width 0.5s ease-out; }
.tab-active { border-bottom: 2px solid #0d9488; color: #0d9488; }
</style>

<div class="max-w-7xl">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Revenue Analytics</h2>
            <p class="text-sm text-slate-600 mt-1">Comprehensive financial reporting and analysis</p>
        </div>
        <div class="flex gap-2">
            <a href="?<?=http_build_query(array_merge($_GET, ['export' => 'csv']))?>"
               class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
                Export CSV
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white border rounded-xl p-4 mb-6 shadow-sm">
        <form method="get" class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div>
                <label class="text-xs text-slate-500 mb-1 block">Date From</label>
                <input type="date" name="date_from" value="<?=e($dateFrom)?>" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500 mb-1 block">Date To</label>
                <input type="date" name="date_to" value="<?=e($dateTo)?>" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500 mb-1 block">Physician/Practice</label>
                <select name="physician" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="">All Physicians</option>
                    <?php foreach ($physicians as $p): ?>
                        <option value="<?=e($p['id'])?>" <?=$physicianId===$p['id']?'selected':''?>>
                            <?=e($p['practice_name'] ?: ($p['first_name'] . ' ' . $p['last_name']))?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500 mb-1 block">Sales Rep</label>
                <select name="sales_rep" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="">All Sales Reps</option>
                    <?php foreach ($salesReps as $rep): ?>
                        <option value="<?=e($rep['id'])?>" <?=$salesRepId===(int)$rep['id']?'selected':''?>>
                            <?=e($rep['name'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500 mb-1 block">Order Type</label>
                <select name="order_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="all" <?=$orderType==='all'?'selected':''?>>All Types</option>
                    <option value="wholesale" <?=$orderType==='wholesale'?'selected':''?>>Wholesale Only</option>
                    <option value="referral" <?=$orderType==='referral'?'selected':''?>>Referral Only</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-brand text-white rounded-lg text-sm hover:bg-brand/90 transition">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- KPI Cards Row 1 -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="metric-card bg-white border rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total Revenue</span>
                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-slate-900">$<?=number_format($metrics['total_revenue'], 0)?></div>
            <div class="text-xs text-slate-500 mt-1"><?=$metrics['total_orders']?> orders</div>
        </div>

        <div class="metric-card bg-white border rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Gross Profit</span>
                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-emerald-600">$<?=number_format($metrics['total_profit'], 0)?></div>
            <?php $margin = $metrics['total_revenue'] > 0 ? ($metrics['total_profit'] / $metrics['total_revenue']) * 100 : 0; ?>
            <div class="text-xs text-slate-500 mt-1"><?=number_format($margin, 1)?>% margin</div>
        </div>

        <div class="metric-card bg-white border rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Wholesale</span>
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-blue-600">$<?=number_format($metrics['wholesale']['revenue'], 0)?></div>
            <div class="text-xs text-slate-500 mt-1"><?=$metrics['wholesale']['orders']?> orders, <?=$metrics['wholesale']['boxes']?> boxes</div>
        </div>

        <div class="metric-card bg-white border rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Referral</span>
                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-purple-600">$<?=number_format($metrics['referral']['revenue'], 0)?></div>
            <div class="text-xs text-slate-500 mt-1"><?=$metrics['referral']['orders']?> orders, <?=$metrics['referral']['boxes']?> boxes</div>
        </div>
    </div>

    <!-- Analytics Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Payor Mix -->
        <div class="bg-white border rounded-xl shadow-sm">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-slate-900">Payor Mix</h3>
                <p class="text-xs text-slate-500 mt-0.5">Revenue by insurance provider</p>
            </div>
            <div class="p-4">
                <?php if (empty($metrics['payor_mix'])): ?>
                    <p class="text-slate-500 text-sm text-center py-4">No data available</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php
                        $maxPayorRevenue = max(array_column($metrics['payor_mix'], 'revenue')) ?: 1;
                        $i = 0;
                        foreach (array_slice($metrics['payor_mix'], 0, 8, true) as $payor => $data):
                            $pct = ($data['revenue'] / $maxPayorRevenue) * 100;
                            $revPct = $metrics['total_revenue'] > 0 ? ($data['revenue'] / $metrics['total_revenue']) * 100 : 0;
                        ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium text-slate-700 truncate" title="<?=e($payor)?>"><?=e(strlen($payor) > 25 ? substr($payor, 0, 25) . '...' : $payor)?></span>
                                <span class="text-slate-600">$<?=number_format($data['revenue'], 0)?> <span class="text-slate-400">(<?=number_format($revPct, 1)?>%)</span></span>
                            </div>
                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="chart-bar h-full bg-gradient-to-r from-teal-500 to-teal-400 rounded-full" style="width: <?=$pct?>%"></div>
                            </div>
                            <div class="text-xs text-slate-400 mt-0.5"><?=$data['orders']?> orders</div>
                        </div>
                        <?php $i++; endforeach; ?>
                    </div>
                    <?php if (count($metrics['payor_mix']) > 8): ?>
                        <p class="text-xs text-slate-400 mt-3 text-center">+ <?=count($metrics['payor_mix']) - 8?> more payors</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Product Mix -->
        <div class="bg-white border rounded-xl shadow-sm">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-slate-900">Product Performance</h3>
                <p class="text-xs text-slate-500 mt-0.5">Revenue by product</p>
            </div>
            <div class="p-4">
                <?php if (empty($metrics['product_mix'])): ?>
                    <p class="text-slate-500 text-sm text-center py-4">No data available</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php
                        $maxProductRevenue = max(array_column($metrics['product_mix'], 'revenue')) ?: 1;
                        foreach (array_slice($metrics['product_mix'], 0, 8, true) as $product => $data):
                            $pct = ($data['revenue'] / $maxProductRevenue) * 100;
                            $revPct = $metrics['total_revenue'] > 0 ? ($data['revenue'] / $metrics['total_revenue']) * 100 : 0;
                        ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium text-slate-700 truncate" title="<?=e($product)?>"><?=e(strlen($product) > 25 ? substr($product, 0, 25) . '...' : $product)?></span>
                                <span class="text-slate-600">$<?=number_format($data['revenue'], 0)?> <span class="text-slate-400">(<?=number_format($revPct, 1)?>%)</span></span>
                            </div>
                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="chart-bar h-full bg-gradient-to-r from-blue-500 to-blue-400 rounded-full" style="width: <?=$pct?>%"></div>
                            </div>
                            <div class="text-xs text-slate-400 mt-0.5"><?=$data['boxes']?> boxes</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CPT/HCPCS Analysis -->
        <div class="bg-white border rounded-xl shadow-sm">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-slate-900">CPT/HCPCS Analysis</h3>
                <p class="text-xs text-slate-500 mt-0.5">Reimbursement by billing code</p>
            </div>
            <div class="p-4 overflow-x-auto">
                <?php if (empty($metrics['cpt_codes'])): ?>
                    <p class="text-slate-500 text-sm text-center py-4">No data available</p>
                <?php else: ?>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-slate-500 border-b">
                                <th class="pb-2 font-medium">Code</th>
                                <th class="pb-2 font-medium text-right">Orders</th>
                                <th class="pb-2 font-medium text-right">Revenue</th>
                                <th class="pb-2 font-medium text-right">Rate/Unit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach (array_slice($metrics['cpt_codes'], 0, 10, true) as $cpt => $data): ?>
                            <tr>
                                <td class="py-2 font-mono text-xs font-medium"><?=e($cpt)?></td>
                                <td class="py-2 text-right"><?=$data['orders']?></td>
                                <td class="py-2 text-right font-medium">$<?=number_format($data['revenue'], 0)?></td>
                                <td class="py-2 text-right text-slate-600">$<?=number_format($data['rate'], 2)?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ICD-10 Distribution -->
        <div class="bg-white border rounded-xl shadow-sm">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-slate-900">ICD-10 Distribution</h3>
                <p class="text-xs text-slate-500 mt-0.5">Wound diagnoses - tracks primary ICD per wound</p>
            </div>
            <div class="p-4 overflow-x-auto">
                <?php if (empty($metrics['icd_codes'])): ?>
                    <p class="text-slate-500 text-sm text-center py-4">No data available</p>
                <?php else: ?>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-slate-500 border-b">
                                <th class="pb-2 font-medium">ICD-10</th>
                                <th class="pb-2 font-medium text-right">Orders</th>
                                <th class="pb-2 font-medium text-right">Wounds</th>
                                <th class="pb-2 font-medium text-right">Boxes</th>
                                <th class="pb-2 font-medium text-right">Revenue</th>
                                <th class="pb-2 font-medium text-right">% of Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach (array_slice($metrics['icd_codes'], 0, 15, true) as $icd => $data):
                                $pct = $metrics['total_revenue'] > 0 ? ($data['revenue'] / $metrics['total_revenue']) * 100 : 0;
                            ?>
                            <tr>
                                <td class="py-2 font-mono text-xs font-medium"><?=e($icd)?></td>
                                <td class="py-2 text-right"><?=$data['orders']?></td>
                                <td class="py-2 text-right text-purple-600"><?=$data['wounds'] ?? $data['orders']?></td>
                                <td class="py-2 text-right"><?=number_format($data['boxes'] ?? 0, 0)?></td>
                                <td class="py-2 text-right font-medium">$<?=number_format($data['revenue'], 0)?></td>
                                <td class="py-2 text-right text-slate-600"><?=number_format($pct, 1)?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sales Rep Performance -->
    <div class="bg-white border rounded-xl shadow-sm mb-6">
        <div class="p-4 border-b">
            <h3 class="font-semibold text-slate-900">Sales Rep Performance</h3>
            <p class="text-xs text-slate-500 mt-0.5">Revenue breakdown by sales representative</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs text-slate-500">
                        <th class="py-3 px-4 font-medium">Sales Rep</th>
                        <th class="py-3 px-4 font-medium text-right">Orders</th>
                        <th class="py-3 px-4 font-medium text-right">Physicians</th>
                        <th class="py-3 px-4 font-medium text-right">Total Revenue</th>
                        <th class="py-3 px-4 font-medium text-right">Wholesale</th>
                        <th class="py-3 px-4 font-medium text-right">Referral</th>
                        <th class="py-3 px-4 font-medium text-right">% of Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($metrics['sales_rep_revenue'])): ?>
                        <tr><td colspan="7" class="py-8 text-center text-slate-500">No sales rep data available</td></tr>
                    <?php else: ?>
                        <?php foreach ($metrics['sales_rep_revenue'] as $repId => $data):
                            $pct = $metrics['total_revenue'] > 0 ? ($data['revenue'] / $metrics['total_revenue']) * 100 : 0;
                            $isUnassigned = $repId === 'unassigned';
                        ?>
                        <tr class="hover:bg-slate-50 <?=$isUnassigned ? 'bg-amber-50' : ''?>">
                            <td class="py-3 px-4 font-medium <?=$isUnassigned ? 'text-amber-700' : ''?>">
                                <?php if (!$isUnassigned): ?>
                                    <a href="?<?=http_build_query(array_merge($_GET, ['sales_rep' => $repId]))?>" class="text-brand hover:underline">
                                        <?=e($data['name'])?>
                                    </a>
                                <?php else: ?>
                                    <?=e($data['name'])?>
                                    <span class="text-xs text-amber-600 ml-1">(not assigned to a rep)</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-right"><?=$data['orders']?></td>
                            <td class="py-3 px-4 text-right"><?=count($data['physicians'])?></td>
                            <td class="py-3 px-4 text-right font-semibold">$<?=number_format($data['revenue'], 0)?></td>
                            <td class="py-3 px-4 text-right text-blue-600">$<?=number_format($data['wholesale_revenue'], 0)?></td>
                            <td class="py-3 px-4 text-right text-purple-600">$<?=number_format($data['referral_revenue'], 0)?></td>
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full <?=$isUnassigned ? 'bg-amber-400' : 'bg-orange-500'?> rounded-full" style="width: <?=min($pct, 100)?>%"></div>
                                    </div>
                                    <span class="text-slate-600"><?=number_format($pct, 1)?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Physician/Practice Performance -->
    <div class="bg-white border rounded-xl shadow-sm mb-6">
        <div class="p-4 border-b">
            <h3 class="font-semibold text-slate-900">Physician/Practice Performance</h3>
            <p class="text-xs text-slate-500 mt-0.5">Revenue breakdown by provider</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs text-slate-500">
                        <th class="py-3 px-4 font-medium">Practice</th>
                        <th class="py-3 px-4 font-medium">Physician</th>
                        <th class="py-3 px-4 font-medium text-right">Orders</th>
                        <th class="py-3 px-4 font-medium text-right">Total Revenue</th>
                        <th class="py-3 px-4 font-medium text-right">Wholesale</th>
                        <th class="py-3 px-4 font-medium text-right">Referral</th>
                        <th class="py-3 px-4 font-medium text-right">% of Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($metrics['physician_revenue'])): ?>
                        <tr><td colspan="7" class="py-8 text-center text-slate-500">No data available</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($metrics['physician_revenue'], 0, 15, true) as $data):
                            $pct = $metrics['total_revenue'] > 0 ? ($data['revenue'] / $metrics['total_revenue']) * 100 : 0;
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-medium"><?=e($data['practice'])?></td>
                            <td class="py-3 px-4 text-slate-600"><?=e($data['name'])?></td>
                            <td class="py-3 px-4 text-right"><?=$data['orders']?></td>
                            <td class="py-3 px-4 text-right font-semibold">$<?=number_format($data['revenue'], 0)?></td>
                            <td class="py-3 px-4 text-right text-blue-600">$<?=number_format($data['wholesale_revenue'], 0)?></td>
                            <td class="py-3 px-4 text-right text-purple-600">$<?=number_format($data['referral_revenue'], 0)?></td>
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-teal-500 rounded-full" style="width: <?=min($pct, 100)?>%"></div>
                                    </div>
                                    <span class="text-slate-600"><?=number_format($pct, 1)?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detailed Orders Table -->
    <div class="bg-white border rounded-xl shadow-sm">
        <div class="p-4 border-b flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-slate-900">Detailed Order Breakdown</h3>
                <p class="text-xs text-slate-500 mt-0.5">Click any row to see calculation details</p>
            </div>
            <span class="text-xs text-slate-500"><?=count($metrics['orders'])?> orders</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs text-slate-500">
                        <th class="py-2 px-4 font-medium">Order ID</th>
                        <th class="py-2 px-4 font-medium">Date</th>
                        <th class="py-2 px-4 font-medium">Practice</th>
                        <th class="py-2 px-4 font-medium">Product</th>
                        <th class="py-2 px-4 font-medium">Type</th>
                        <th class="py-2 px-4 font-medium">Insurance</th>
                        <th class="py-2 px-4 font-medium text-right">Boxes</th>
                        <th class="py-2 px-4 font-medium text-right">Cost</th>
                        <th class="py-2 px-4 font-medium text-right">Revenue</th>
                        <th class="py-2 px-4 font-medium text-right">Profit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($metrics['orders'])): ?>
                        <tr><td colspan="10" class="py-8 text-center text-slate-500">No orders found</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($metrics['orders'], 0, 100) as $idx => $order): ?>
                        <tr class="hover:bg-slate-50 cursor-pointer" onclick="toggleCalc('calc-<?=$idx?>')">
                            <td class="py-2 px-4 font-mono text-xs"><?=e(substr($order['id'], 0, 8))?></td>
                            <td class="py-2 px-4 text-xs"><?=date('m/d/Y', strtotime($order['created_at']))?></td>
                            <td class="py-2 px-4 text-xs"><?=e($order['practice_name'] ?: 'N/A')?></td>
                            <td class="py-2 px-4 text-xs"><?=e($order['product_name'] ?: 'Unknown')?></td>
                            <td class="py-2 px-4">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-medium <?=$order['order_type']==='Wholesale'?'bg-blue-100 text-blue-700':'bg-purple-100 text-purple-700'?>">
                                    <?=$order['order_type']?>
                                </span>
                            </td>
                            <td class="py-2 px-4 text-xs truncate max-w-32" title="<?=e($order['insurer_name'])?>"><?=e($order['insurer_name'] ?: 'Unknown')?></td>
                            <td class="py-2 px-4 text-right"><?=$order['calculated_boxes']?></td>
                            <td class="py-2 px-4 text-right text-orange-600">$<?=number_format($order['calculated_cost'], 2)?></td>
                            <td class="py-2 px-4 text-right font-medium">$<?=number_format($order['calculated_revenue'], 2)?></td>
                            <td class="py-2 px-4 text-right font-medium text-green-600">$<?=number_format($order['calculated_profit'], 2)?></td>
                        </tr>
                        <tr id="calc-<?=$idx?>" class="hidden bg-slate-50">
                            <td colspan="10" class="py-3 px-4">
                                <div class="bg-white rounded border p-3 text-xs">
                                    <h4 class="font-semibold text-slate-700 mb-2">Calculation Details:</h4>
                                    <ul class="space-y-0.5 font-mono text-slate-600">
                                        <?php foreach ($order['calculation_steps'] as $step): ?>
                                            <li><?=e($step)?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($metrics['orders'])): ?>
                <tfoot class="bg-slate-100 border-t-2 border-slate-300 font-semibold">
                    <tr>
                        <td colspan="6" class="py-3 px-4 text-right">TOTALS:</td>
                        <td class="py-3 px-4 text-right"><?=$metrics['total_boxes']?></td>
                        <td class="py-3 px-4 text-right text-orange-600">$<?=number_format($metrics['total_cost'], 2)?></td>
                        <td class="py-3 px-4 text-right">$<?=number_format($metrics['total_revenue'], 2)?></td>
                        <td class="py-3 px-4 text-right text-green-600">$<?=number_format($metrics['total_profit'], 2)?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php if (count($metrics['orders']) > 100): ?>
            <div class="p-4 text-center border-t bg-slate-50">
                <p class="text-xs text-slate-500">Showing first 100 orders. Export CSV for complete data.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Methodology -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
        <h4 class="font-semibold text-blue-900 mb-2">Revenue Calculation Methodology</h4>
        <div class="text-sm text-blue-800 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <strong>Wholesale Orders:</strong>
                <ul class="list-disc list-inside ml-2 mt-1 text-xs space-y-1">
                    <li>Revenue = Boxes x Price Per Box</li>
                    <li>Uses practice-specific custom pricing when available</li>
                    <li>Falls back to product wholesale price</li>
                </ul>
            </div>
            <div>
                <strong>Referral Orders:</strong>
                <ul class="list-disc list-inside ml-2 mt-1 text-xs space-y-1">
                    <li>Pieces = (Days / 7) x Frequency x Qty x (1 + Refills)</li>
                    <li>Boxes = ceil(Pieces / Pieces Per Box)</li>
                    <li>Revenue = Billable Pieces x Medicare Allowable Rate</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCalc(id) {
    const row = document.getElementById(id);
    if (row) row.classList.toggle('hidden');
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>
