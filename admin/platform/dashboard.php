<?php
/**
 * Platform Admin Dashboard - Business Health Snapshot
 *
 * Actionable metrics for quick decision-making:
 * - Revenue snapshot (current month, last month, YTD)
 * - Month-over-month trends
 * - Top performers (payors, products, physicians)
 * - Order pipeline status
 */
declare(strict_types=1);

require __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/revenue_calculator.php';
require_admin();

$admin = current_admin();
$isSuperAdmin = in_array(($admin['role'] ?? ''), ['owner', 'superadmin', 'manufacturer']);

if (!$isSuperAdmin) {
    header('Location: /admin/index.php');
    exit;
}

// Get comprehensive metrics
$dashboardMetrics = get_dashboard_metrics($pdo);
$currentMonth = $dashboardMetrics['current_month'];
$lastMonth = $dashboardMetrics['last_month'];
$ytd = $dashboardMetrics['ytd'];

// Calculate pipeline metrics
try {
    $pipelineStmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM orders
        WHERE status NOT IN ('rejected', 'cancelled')
          AND created_at >= NOW() - INTERVAL '90 days'
        GROUP BY status
    ");
    $pipeline = [];
    while ($row = $pipelineStmt->fetch(PDO::FETCH_ASSOC)) {
        $pipeline[$row['status']] = (int)$row['count'];
    }
} catch (Throwable $e) {
    $pipeline = [];
}

// Get recent activity
try {
    $recentStmt = $pdo->query("
        SELECT o.id, o.created_at, o.status, o.billed_by,
               p.first_name, p.last_name,
               u.practice_name
        FROM orders o
        LEFT JOIN patients p ON p.id = o.patient_id
        LEFT JOIN users u ON u.id = o.user_id
        WHERE o.status NOT IN ('rejected', 'cancelled')
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recentOrders = [];
}

// Platform counts
try {
    $totalPhysicians = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('physician', 'practice_admin')")->fetchColumn();
    $totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $activeOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('rejected', 'cancelled', 'delivered')")->fetchColumn();
} catch (Throwable $e) {
    $totalPhysicians = $totalPatients = $activeOrders = 0;
}

include __DIR__ . '/../_header.php';
?>

<style>
.metric-card { transition: transform 0.2s, box-shadow 0.2s; }
.metric-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
.trend-up { color: #10b981; }
.trend-down { color: #ef4444; }
.pulse { animation: pulse 2s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
</style>

<div class="max-w-7xl">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Business Dashboard</h1>
        <p class="text-sm text-slate-600 mt-1">Real-time business health snapshot</p>
    </div>

    <!-- Revenue KPIs - Top Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Current Month Revenue -->
        <div class="metric-card bg-gradient-to-br from-teal-500 to-teal-600 rounded-2xl p-5 shadow-lg text-white">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-teal-100 uppercase tracking-wide">This Month</span>
                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold">$<?=number_format($currentMonth['total_revenue'], 0)?></div>
            <div class="flex items-center gap-2 mt-2 text-sm">
                <?php if ($dashboardMetrics['revenue_change_pct'] >= 0): ?>
                    <span class="flex items-center gap-1 px-2 py-0.5 bg-white/20 rounded-full">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        <?=number_format(abs($dashboardMetrics['revenue_change_pct']), 1)?>%
                    </span>
                <?php else: ?>
                    <span class="flex items-center gap-1 px-2 py-0.5 bg-red-500/30 rounded-full">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        <?=number_format(abs($dashboardMetrics['revenue_change_pct']), 1)?>%
                    </span>
                <?php endif; ?>
                <span class="text-teal-100">vs last month</span>
            </div>
        </div>

        <!-- Last Month -->
        <div class="metric-card bg-white border rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Last Month</span>
                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-slate-900">$<?=number_format($lastMonth['total_revenue'], 0)?></div>
            <div class="text-xs text-slate-500 mt-1"><?=$lastMonth['total_orders']?> orders</div>
        </div>

        <!-- YTD -->
        <div class="metric-card bg-white border rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Year to Date</span>
                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-slate-900">$<?=number_format($ytd['total_revenue'], 0)?></div>
            <div class="text-xs text-slate-500 mt-1"><?=$ytd['total_orders']?> orders total</div>
        </div>

        <!-- Profit Margin -->
        <div class="metric-card bg-white border rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Profit Margin</span>
                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <?php $margin = $currentMonth['total_revenue'] > 0 ? ($currentMonth['total_profit'] / $currentMonth['total_revenue']) * 100 : 0; ?>
            <div class="text-2xl font-bold <?=$margin >= 40 ? 'text-green-600' : ($margin >= 20 ? 'text-amber-600' : 'text-red-600')?>">
                <?=number_format($margin, 1)?>%
            </div>
            <div class="text-xs text-slate-500 mt-1">$<?=number_format($currentMonth['total_profit'], 0)?> profit</div>
        </div>
    </div>

    <!-- Channel Mix + Pipeline -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Channel Mix (Wholesale vs Referral) -->
        <div class="bg-white border rounded-2xl p-5 shadow-sm">
            <h3 class="font-semibold text-slate-900 mb-4">Revenue by Channel</h3>
            <div class="space-y-4">
                <!-- Wholesale -->
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-medium text-blue-700">Wholesale</span>
                        <span class="text-slate-600">$<?=number_format($currentMonth['wholesale']['revenue'], 0)?></span>
                    </div>
                    <?php $wholesalePct = $currentMonth['total_revenue'] > 0 ? ($currentMonth['wholesale']['revenue'] / $currentMonth['total_revenue']) * 100 : 0; ?>
                    <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-500 to-blue-400 rounded-full transition-all" style="width: <?=$wholesalePct?>%"></div>
                    </div>
                    <div class="text-xs text-slate-500 mt-1"><?=$currentMonth['wholesale']['orders']?> orders (<?=number_format($wholesalePct, 0)?>%)</div>
                </div>
                <!-- Referral -->
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-medium text-purple-700">Referral</span>
                        <span class="text-slate-600">$<?=number_format($currentMonth['referral']['revenue'], 0)?></span>
                    </div>
                    <?php $referralPct = $currentMonth['total_revenue'] > 0 ? ($currentMonth['referral']['revenue'] / $currentMonth['total_revenue']) * 100 : 0; ?>
                    <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-purple-500 to-purple-400 rounded-full transition-all" style="width: <?=$referralPct?>%"></div>
                    </div>
                    <div class="text-xs text-slate-500 mt-1"><?=$currentMonth['referral']['orders']?> orders (<?=number_format($referralPct, 0)?>%)</div>
                </div>
            </div>
        </div>

        <!-- Order Pipeline -->
        <div class="bg-white border rounded-2xl p-5 shadow-sm">
            <h3 class="font-semibold text-slate-900 mb-4">Order Pipeline (90 days)</h3>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-amber-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-amber-600"><?=$pipeline['submitted'] ?? 0?></div>
                    <div class="text-xs text-amber-700">Pending</div>
                </div>
                <div class="bg-blue-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-blue-600"><?=$pipeline['approved'] ?? 0?></div>
                    <div class="text-xs text-blue-700">Approved</div>
                </div>
                <div class="bg-purple-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-purple-600"><?=$pipeline['shipped'] ?? 0?></div>
                    <div class="text-xs text-purple-700">Shipped</div>
                </div>
                <div class="bg-green-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-green-600"><?=$pipeline['delivered'] ?? 0?></div>
                    <div class="text-xs text-green-700">Delivered</div>
                </div>
            </div>
            <?php $pendingCount = ($pipeline['submitted'] ?? 0); ?>
            <?php if ($pendingCount > 5): ?>
                <div class="mt-3 p-2 bg-amber-100 rounded-lg text-xs text-amber-800 flex items-center gap-2">
                    <span class="pulse">!</span>
                    <span><?=$pendingCount?> orders awaiting approval</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Platform Stats -->
        <div class="bg-white border rounded-2xl p-5 shadow-sm">
            <h3 class="font-semibold text-slate-900 mb-4">Platform Overview</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <span class="text-sm text-slate-600">Physicians</span>
                    </div>
                    <span class="text-lg font-bold text-slate-900"><?=number_format($totalPhysicians)?></span>
                </div>
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <span class="text-sm text-slate-600">Patients</span>
                    </div>
                    <span class="text-lg font-bold text-slate-900"><?=number_format($totalPatients)?></span>
                </div>
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <span class="text-sm text-slate-600">Active Orders</span>
                    </div>
                    <span class="text-lg font-bold text-slate-900"><?=number_format($activeOrders)?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Top Payors -->
        <div class="bg-white border rounded-2xl shadow-sm">
            <div class="p-4 border-b flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Top Payors</h3>
                <a href="/admin/revenue-report.php" class="text-xs text-brand hover:underline">View All</a>
            </div>
            <div class="p-4">
                <?php if (empty($dashboardMetrics['top_payors'])): ?>
                    <p class="text-slate-500 text-sm text-center py-4">No data yet</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($dashboardMetrics['top_payors'] as $payor => $data): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-700 truncate flex-1" title="<?=htmlspecialchars($payor)?>"><?=htmlspecialchars(strlen($payor) > 20 ? substr($payor, 0, 20) . '...' : $payor)?></span>
                            <span class="text-sm font-semibold text-slate-900 ml-2">$<?=number_format($data['revenue'], 0)?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Products -->
        <div class="bg-white border rounded-2xl shadow-sm">
            <div class="p-4 border-b flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Top Products</h3>
                <a href="/admin/revenue-report.php" class="text-xs text-brand hover:underline">View All</a>
            </div>
            <div class="p-4">
                <?php if (empty($dashboardMetrics['top_products'])): ?>
                    <p class="text-slate-500 text-sm text-center py-4">No data yet</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($dashboardMetrics['top_products'] as $product => $data): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-700 truncate flex-1" title="<?=htmlspecialchars($product)?>"><?=htmlspecialchars(strlen($product) > 20 ? substr($product, 0, 20) . '...' : $product)?></span>
                            <span class="text-sm font-semibold text-slate-900 ml-2">$<?=number_format($data['revenue'], 0)?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Physicians -->
        <div class="bg-white border rounded-2xl shadow-sm">
            <div class="p-4 border-b flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Top Practices</h3>
                <a href="/admin/revenue-report.php" class="text-xs text-brand hover:underline">View All</a>
            </div>
            <div class="p-4">
                <?php if (empty($dashboardMetrics['top_physicians'])): ?>
                    <p class="text-slate-500 text-sm text-center py-4">No data yet</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($dashboardMetrics['top_physicians'] as $data): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-700 truncate flex-1" title="<?=htmlspecialchars($data['practice'])?>"><?=htmlspecialchars(strlen($data['practice']) > 20 ? substr($data['practice'], 0, 20) . '...' : $data['practice'])?></span>
                            <span class="text-sm font-semibold text-slate-900 ml-2">$<?=number_format($data['revenue'], 0)?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity + Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Orders -->
        <div class="bg-white border rounded-2xl shadow-sm">
            <div class="p-4 border-b flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Recent Orders</h3>
                <a href="/admin/orders.php" class="text-xs text-brand hover:underline">View All</a>
            </div>
            <div class="divide-y divide-slate-100">
                <?php if (empty($recentOrders)): ?>
                    <div class="p-4 text-slate-500 text-sm text-center">No recent orders</div>
                <?php else: ?>
                    <?php foreach ($recentOrders as $order): ?>
                    <a href="/admin/orders.php?id=<?=htmlspecialchars($order['id'])?>" class="flex items-center justify-between p-4 hover:bg-slate-50 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full <?=$order['billed_by']==='practice_dme'?'bg-blue-100':'bg-purple-100'?> flex items-center justify-center">
                                <span class="text-xs font-medium <?=$order['billed_by']==='practice_dme'?'text-blue-600':'text-purple-600'?>">
                                    <?=$order['billed_by']==='practice_dme'?'W':'R'?>
                                </span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-slate-900"><?=htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))?></div>
                                <div class="text-xs text-slate-500"><?=htmlspecialchars($order['practice_name'] ?? 'Unknown')?></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-medium
                                <?php
                                switch($order['status']) {
                                    case 'submitted': echo 'bg-amber-100 text-amber-700'; break;
                                    case 'approved': echo 'bg-blue-100 text-blue-700'; break;
                                    case 'shipped': echo 'bg-purple-100 text-purple-700'; break;
                                    case 'delivered': echo 'bg-green-100 text-green-700'; break;
                                    default: echo 'bg-slate-100 text-slate-600';
                                }
                                ?>">
                                <?=ucfirst($order['status'])?>
                            </span>
                            <div class="text-xs text-slate-500 mt-1"><?=date('M j', strtotime($order['created_at']))?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white border rounded-2xl shadow-sm">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-slate-900">Quick Actions</h3>
            </div>
            <div class="p-4 space-y-2">
                <a href="/admin/orders.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition">
                    <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="font-medium text-slate-900">Review Pending Orders</div>
                        <div class="text-xs text-slate-500"><?=$pipeline['submitted'] ?? 0?> orders awaiting approval</div>
                    </div>
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
                <a href="/admin/revenue-report.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition">
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="font-medium text-slate-900">Revenue Analytics</div>
                        <div class="text-xs text-slate-500">Detailed financial reports</div>
                    </div>
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
                <a href="/admin/billing.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="font-medium text-slate-900">Billing Dashboard</div>
                        <div class="text-xs text-slate-500">Manage claims and reimbursements</div>
                    </div>
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
                <a href="/admin/delivery-audit.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition">
                    <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="font-medium text-slate-900">Delivery Audit</div>
                        <div class="text-xs text-slate-500">Compliance and AOB tracking</div>
                    </div>
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../_footer.php'; ?>
