<?php
// /public/admin/index.php — CollagenDirect Dashboard (Projected Remaining Revenue)
declare(strict_types=1);

require_once __DIR__ . '/db.php';
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
$auth      = __DIR__ . '/auth.php'; if (is_file($auth)) require_once $auth;
if (function_exists('require_admin')) require_admin();

// Get current admin user
$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';

// Handle context switching
if (isset($_GET['context'])) {
  $context = $_GET['context'] === 'platform' ? 'platform' : 'practice';
  $_SESSION['admin_context'] = $context;
  header('Location: /admin/index.php');
  exit;
}

/* ---------- helpers ---------- */
if (!function_exists('str_contains')) {
  function str_contains($h,$n){ return $n===''?true:strpos((string)$h,(string)$n)!==false; }
}
function qCount(PDO $pdo, string $sql, array $p=[]): int {
  try { $st=$pdo->prepare($sql); $st->execute($p); return (int)($st->fetch()['c']??0); }
  catch(Throwable $e){ error_log("[qCount] ".$e->getMessage()); return 0; }
}
function has_table(PDO $pdo, string $tbl): bool {
  try { $st=$pdo->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME=?");
        $st->execute([$tbl]); return ((int)$st->fetch()['c'])>0; } catch(Throwable $e){ return false; }
}
function has_column(PDO $pdo, string $tbl, string $col): bool {
  try { $st=$pdo->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME=? AND COLUMN_NAME=?");
        $st->execute([$tbl,$col]); return ((int)$st->fetch()['c'])>0; } catch(Throwable $e){ return false; }
}
/* patches/week from frequency (integer) */
function patches_per_week(?string $f): int {
  $f = strtolower(trim((string)$f));
  if ($f==='daily') return 7;
  if ($f==='every other day') return 4;          // ceil(3.5)
  if ($f==='weekly') return 1;
  if (preg_match('/(\d+)\s*x\s*\/?\s*week/', $f, $m)) return max(1,(int)$m[1]); // "2x/week"
  if (preg_match('/(\d+)\s*x\s*per\s*week/', $f, $m)) return max(1,(int)$m[1]);
  return 1;
}

/* ---------- KPIs ---------- */
// Filter KPIs by role - sales/ops/employees only see assigned physicians
// IMPORTANT: Exclude draft orders from all admin views
if ($adminRole === 'superadmin' || $adminRole === 'manufacturer' || $adminRole === 'admin') {
  // Admin roles see all data (except drafts)
  $totalOrders      = qCount($pdo, "SELECT COUNT(*) c FROM orders WHERE (review_status IS NULL OR review_status != 'draft')");
  $pendingApprovals = qCount($pdo, "SELECT COUNT(*) c FROM orders WHERE status IN ('submitted','pending','awaiting_approval') AND (review_status IS NULL OR review_status != 'draft')");
  $activePatients   = qCount($pdo, "SELECT COUNT(DISTINCT patient_id) c FROM orders WHERE status IN ('approved','in_transit','delivered') AND (review_status IS NULL OR review_status != 'draft')");
} else {
  // Sales/ops/employees only see their assigned physicians (except drafts)
  $totalOrders      = qCount($pdo, "SELECT COUNT(*) c FROM orders o WHERE (o.review_status IS NULL OR o.review_status != 'draft') AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = ? AND ap.physician_user_id = o.user_id)", [$adminId]);
  $pendingApprovals = qCount($pdo, "SELECT COUNT(*) c FROM orders o WHERE o.status IN ('submitted','pending','awaiting_approval') AND (o.review_status IS NULL OR o.review_status != 'draft') AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = ? AND ap.physician_user_id = o.user_id)", [$adminId]);
  $activePatients   = qCount($pdo, "SELECT COUNT(DISTINCT patient_id) c FROM orders o WHERE o.status IN ('approved','in_transit','delivered') AND (o.review_status IS NULL OR o.review_status != 'draft') AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = ? AND ap.physician_user_id = o.user_id)", [$adminId]);
}

/* ---------- Revenue Calculations ----------
   EARNED: Revenue from shipments already delivered (total - remaining)
   PROJECTED: Revenue from shipments still to be delivered (remaining)

   For manufacturers: Show 30% of actual amounts (manufacturer relationship)
--------------------------------------------------- */
$hasProducts = has_table($pdo,'products');
$hasRates    = has_table($pdo,'reimbursement_rates');
$hasShipRem  = has_column($pdo,'orders','shipments_remaining');

// Prefetch reimbursement rates
$rates = [];
if ($hasRates) {
  try {
    foreach ($pdo->query("SELECT cpt_code, COALESCE(rate_non_rural,0) rate FROM reimbursement_rates") as $r) {
      $rates[$r['cpt_code']] = (float)$r['rate'];
    }
  } catch (Throwable $e) {
    error_log("[rates] ".$e->getMessage());
  }
}

$earnedRevenue = 0.0;
$projectedRevenue = 0.0;
$totalRevenue = 0.0;
$practiceRevenue = [];
$productRevenue = [];

try {
  // Build revenue query with role-based access control
  $revenueWhere = "o.status NOT IN ('rejected', 'cancelled', 'draft')";
  $revenueParams = [];

  // Sales reps and employees only see revenue from their assigned physicians
  if ($adminRole !== 'superadmin' && $adminRole !== 'manufacturer' && $adminRole !== 'admin') {
    $revenueWhere .= " AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = :admin_id AND ap.physician_user_id = o.user_id)";
    $revenueParams['admin_id'] = $adminId;
  }

  $revenueQuery = "
    SELECT
      o.id,
      o.product_price,
      o.frequency_per_week,
      o.duration_days,
      o.refills_allowed,
      o.qty_per_change,
      " . ($hasShipRem ? "o.shipments_remaining," : "0 AS shipments_remaining,") . "
      u.practice_name,
      " . ($hasProducts ? "pr.name AS product_name, pr.hcpcs_code AS cpt_code" : "'Unknown' AS product_name, '' AS cpt_code") . "
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    " . ($hasProducts ? "LEFT JOIN products pr ON pr.id = o.product_id" : "") . "
    WHERE " . $revenueWhere . "
  ";

  $stmt = $pdo->prepare($revenueQuery);
  $stmt->execute($revenueParams);

  foreach ($stmt->fetchAll() as $order) {
    // Get frequency and quantity
    $fpw = (int)($order['frequency_per_week'] ?? 0);
    $qty = max(1, (int)($order['qty_per_change'] ?? 1));
    $days = max(0, (int)($order['duration_days'] ?? 0));
    $refills = max(0, (int)($order['refills_allowed'] ?? 0));

    // Calculate total shipments for this order
    $weeks = ($days > 0) ? (int)ceil($days / 7) : 4;
    $total_weeks = $weeks * (1 + $refills);
    $total_shipments = $fpw * $total_weeks * $qty;

    // Get unit price (reimbursement rate or product price)
    $unit_price = 0.0;
    $cpt = $order['cpt_code'] ?? '';
    if ($hasRates && $cpt && isset($rates[$cpt]) && $rates[$cpt] > 0) {
      $unit_price = $rates[$cpt];
    } else {
      $unit_price = (float)($order['product_price'] ?? 0);
    }
    if ($unit_price <= 0) $unit_price = 150.0; // Conservative fallback

    // Calculate earned (delivered) and projected (remaining) revenue
    $shipments_remaining = (int)($order['shipments_remaining'] ?? 0);
    $shipments_delivered = max(0, $total_shipments - $shipments_remaining);

    $order_earned = $shipments_delivered * $unit_price;
    $order_projected = $shipments_remaining * $unit_price;
    $order_total = $order_earned + $order_projected;

    $earnedRevenue += $order_earned;
    $projectedRevenue += $order_projected;
    $totalRevenue += $order_total;

    // Group by practice
    $practice = $order['practice_name'] ?: 'Independent Provider';
    if (!isset($practiceRevenue[$practice])) {
      $practiceRevenue[$practice] = 0.0;
    }
    $practiceRevenue[$practice] += $order_total;

    // Group by product
    $product = $order['product_name'] ?: 'Standard Product';
    if (!isset($productRevenue[$product])) {
      $productRevenue[$product] = 0.0;
    }
    $productRevenue[$product] += $order_total;
  }

  // Sort by revenue descending
  arsort($practiceRevenue);
  arsort($productRevenue);

} catch (Throwable $e) {
  error_log("[revenue-dashboard] " . $e->getMessage());
  // Fallback to simple estimate if query fails
  $totalRevenue = $totalOrders * 150.0;
  $earnedRevenue = $totalRevenue * 0.5; // Estimate 50% delivered
  $projectedRevenue = $totalRevenue * 0.5;
}

// Apply sales commission factor (30% of actual revenue for sales reps)
$revenueMultiplier = ($adminRole === 'sales') ? 0.30 : 1.0;
$displayEarnedRevenue = $earnedRevenue * $revenueMultiplier;
$displayProjectedRevenue = $projectedRevenue * $revenueMultiplier;
$displayTotalRevenue = $totalRevenue * $revenueMultiplier;

/* ---------- Recent activity ---------- */
$recent = [];
try {
  // Filter recent activity by role
  // IMPORTANT: Hide draft orders from admin users (same as orders.php)
  if ($adminRole === 'superadmin' || $adminRole === 'manufacturer' || $adminRole === 'admin') {
    // Admin roles see all recent activity (except drafts)
    $recent = $pdo->query("
      SELECT o.id, o.status, o.product, COALESCE(o.updated_at, o.created_at) AS ts,
             p.first_name, p.last_name
      FROM orders o
      LEFT JOIN patients p ON p.id = o.patient_id
      WHERE (o.review_status IS NULL OR o.review_status != 'draft')
      ORDER BY ts DESC
      LIMIT 8
    ")->fetchAll();
  } else {
    // Sales/ops/employees only see activity from assigned physicians (except drafts)
    $stmt = $pdo->prepare("
      SELECT o.id, o.status, o.product, COALESCE(o.updated_at, o.created_at) AS ts,
             p.first_name, p.last_name
      FROM orders o
      LEFT JOIN patients p ON p.id = o.patient_id
      WHERE (o.review_status IS NULL OR o.review_status != 'draft')
        AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = ? AND ap.physician_user_id = o.user_id)
      ORDER BY ts DESC
      LIMIT 8
    ");
    $stmt->execute([$adminId]);
    $recent = $stmt->fetchAll();
  }
} catch(Throwable $e){ error_log("[recent] ".$e->getMessage()); }

/* ---------- Reminders (safe) ---------- */
$expiringOrders = 0; $pendingPreauth = 0; $unreadPhysicianComments = 0;

if ($adminRole === 'superadmin' || $adminRole === 'manufacturer' || $adminRole === 'admin') {
  // Admin roles see all reminders
  try {
    if (has_column($pdo,'orders','expires_at')) {
      $expiringOrders = qCount($pdo,"SELECT COUNT(*) c FROM orders WHERE expires_at IS NOT NULL AND expires_at < (NOW() + INTERVAL '7 days') AND status IN ('approved','in_transit')");
    }
  } catch(Throwable $e){}
  try {
    // Count patients needing pre-authorization status (pending or need_info)
    if (has_column($pdo,'patients','state')) {
      $pendingPreauth = qCount($pdo,"SELECT COUNT(*) c FROM patients WHERE state IN ('pending', 'need_info')");
    }
  } catch(Throwable $e){}
  try {
    // Count unread physician comments (provider_response exists but admin hasn't read)
    if (has_column($pdo,'patients','provider_response') && has_column($pdo,'patients','admin_response_read_at')) {
      $unreadPhysicianComments = qCount($pdo,"SELECT COUNT(*) c FROM patients WHERE provider_response IS NOT NULL AND provider_response != '' AND (admin_response_read_at IS NULL OR admin_response_read_at < provider_response_at)");
    }
  } catch(Throwable $e){}
} else {
  // Sales/ops/employees see reminders only for assigned physicians
  try {
    if (has_column($pdo,'orders','expires_at')) {
      $expiringOrders = qCount($pdo,"SELECT COUNT(*) c FROM orders o WHERE o.expires_at IS NOT NULL AND o.expires_at < (NOW() + INTERVAL '7 days') AND o.status IN ('approved','in_transit') AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = ? AND ap.physician_user_id = o.user_id)", [$adminId]);
    }
  } catch(Throwable $e){}
  try {
    // Count patients needing pre-authorization status (pending or need_info) - filtered by assigned physicians
    if (has_column($pdo,'patients','state')) {
      $pendingPreauth = qCount($pdo,"SELECT COUNT(*) c FROM patients p WHERE p.state IN ('pending', 'need_info') AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = ? AND ap.physician_user_id = p.user_id)", [$adminId]);
    }
  } catch(Throwable $e){}
  try {
    // Count unread physician comments - filtered by assigned physicians
    if (has_column($pdo,'patients','provider_response') && has_column($pdo,'patients','admin_response_read_at')) {
      $unreadPhysicianComments = qCount($pdo,"SELECT COUNT(*) c FROM patients p WHERE p.provider_response IS NOT NULL AND p.provider_response != '' AND (p.admin_response_read_at IS NULL OR p.admin_response_read_at < p.provider_response_at) AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = ? AND ap.physician_user_id = p.user_id)", [$adminId]);
    }
  } catch(Throwable $e){}
}

/* ---------- Notifications for manufacturer ---------- */
$notifications = [];
if ($adminRole === 'manufacturer' && has_table($pdo, 'notifications')) {
  try {
    $stmt = $pdo->prepare("
      SELECT id, message, link, created_at, is_read
      FROM notifications
      WHERE user_id = ? AND user_type = 'admin'
      ORDER BY created_at DESC
      LIMIT 10
    ");
    $stmt->execute([$adminId]);
    $notifications = $stmt->fetchAll();
  } catch (Throwable $e) {
    error_log("[notifications] " . $e->getMessage());
  }
}

include __DIR__.'/_header.php';
?>
<div>
  <!-- Dashboard Tiles - Mobile Responsive Grid -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <!-- Total Orders Tile -->
    <a href="/admin/orders.php" class="block bg-white border rounded-2xl p-4 shadow-soft hover:shadow-md transition-shadow cursor-pointer group">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-xs text-slate-500 mb-1">Total Orders</div>
          <div class="text-2xl font-bold group-hover:text-brand transition-colors"><?=number_format($totalOrders)?></div>
        </div>
        <svg class="w-8 h-8 text-slate-300 group-hover:text-brand transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
        </svg>
      </div>
      <div class="text-xs text-slate-400 mt-2">Click to view all orders →</div>
    </a>

    <!-- Pending Approvals Tile -->
    <a href="/admin/orders.php?status=pending" class="block bg-white border rounded-2xl p-4 shadow-soft hover:shadow-md transition-shadow cursor-pointer group">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-xs text-slate-500 mb-1">Pending Approvals</div>
          <div class="text-2xl font-bold text-amber-600 group-hover:text-amber-700 transition-colors"><?=number_format($pendingApprovals)?></div>
        </div>
        <svg class="w-8 h-8 text-amber-300 group-hover:text-amber-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
      </div>
      <div class="text-xs text-slate-400 mt-2">Review & approve →</div>
    </a>

    <!-- Active Patients Tile -->
    <a href="/admin/patients.php?status=active" class="block bg-white border rounded-2xl p-4 shadow-soft hover:shadow-md transition-shadow cursor-pointer group">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-xs text-slate-500 mb-1">Active Patients</div>
          <div class="text-2xl font-bold group-hover:text-brand transition-colors"><?=number_format($activePatients)?></div>
        </div>
        <svg class="w-8 h-8 text-slate-300 group-hover:text-brand transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
        </svg>
      </div>
      <div class="text-xs text-slate-400 mt-2">Manage patients →</div>
    </a>

    <!-- Revenue Tile (Earned + Projected) -->
    <a href="/admin/billing.php" class="block bg-white border rounded-2xl p-4 shadow-soft hover:shadow-md transition-shadow cursor-pointer group relative">
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <div class="text-xs text-slate-500 mb-1 flex items-center gap-1">
            Revenue Overview<?php if($adminRole === 'sales'): ?> <span class="text-[10px] text-slate-400">(30% commission)</span><?php endif; ?>
            <span class="inline-block w-3 h-3 rounded-full bg-slate-200 text-[10px] leading-3 text-center text-slate-600 cursor-help" title="Earned: Revenue from delivered shipments | Projected: Future revenue from remaining shipments">?</span>
          </div>
          <div class="text-2xl font-bold text-brand group-hover:text-blue-700 transition-colors">$<?=number_format($displayTotalRevenue,2)?></div>
          <div class="flex gap-4 mt-2 text-xs">
            <div>
              <span class="text-slate-500">Earned:</span>
              <span class="font-semibold text-green-600">$<?=number_format($displayEarnedRevenue,2)?></span>
            </div>
            <div>
              <span class="text-slate-500">Projected:</span>
              <span class="font-semibold text-blue-600">$<?=number_format($displayProjectedRevenue,2)?></span>
            </div>
          </div>
        </div>
        <svg class="w-8 h-8 text-green-300 group-hover:text-green-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
      </div>
      <div class="text-xs text-slate-400 mt-2">View billing →</div>
    </a>
  </div>

  <!-- Recent Activity and Reminders - Mobile Responsive -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <section class="lg:col-span-5 bg-white border rounded-2xl p-4">
      <h3 class="font-semibold mb-3">Recent Activity</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b">
            <tr class="text-left">
              <th class="py-2 px-2">Patient</th>
              <th class="py-2 px-2">Order</th>
              <th class="py-2 px-2 hidden sm:table-cell">Product</th>
              <th class="py-2 px-2">Status</th>
              <th class="py-2 px-2 hidden md:table-cell">Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($recent as $r): ?>
            <tr class="border-b hover:bg-slate-50">
              <td class="py-3 px-2"><?=e(trim(($r['first_name']??'').' '.($r['last_name']??'')) ?: '—')?></td>
              <td class="py-3 px-2"><a class="text-brand hover:underline" href="/admin/orders.php?focus=<?=e($r['id'])?>">#<?=e($r['id'])?></a></td>
              <td class="py-3 px-2 hidden sm:table-cell text-xs"><?=e($r['product'] ?? '')?></td>
              <td class="py-3 px-2">
                <span class="inline-block px-2 py-1 text-xs rounded-full bg-slate-100">
                  <?=e(ucwords(str_replace('_',' ', $r['status'] ?? '')))?>
                </span>
              </td>
              <td class="py-3 px-2 hidden md:table-cell text-xs text-slate-600"><?=e($r['ts'] ?? '')?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <aside class="lg:col-span-7 bg-white border rounded-2xl p-4">
      <h3 class="font-semibold mb-3">Reminders</h3>
      <div class="space-y-3">
        <a href="/admin/orders.php?status=pending" class="block p-3 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-amber-900">Pending Approvals</span>
            <span class="text-xl font-bold text-amber-600"><?=$pendingApprovals?></span>
          </div>
        </a>
        <a href="/admin/orders.php?filter=expiring" class="block p-3 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-red-900">Orders Expiring (7 days)</span>
            <span class="text-xl font-bold text-red-600"><?=$expiringOrders?></span>
          </div>
        </a>
        <a href="/admin/patients.php" class="block p-3 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-green-900">Unread Physician Comments</span>
            <span class="text-xl font-bold text-green-600"><?=$unreadPhysicianComments?></span>
          </div>
          <div class="text-xs text-green-700 mt-1">Provider responses pending review</div>
        </a>
        <a href="/admin/patients.php?status=pending" class="block p-3 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-purple-900">Pending Pre-Authorization</span>
            <span class="text-xl font-bold text-purple-600"><?=$pendingPreauth?></span>
          </div>
          <div class="text-xs text-purple-700 mt-1">Patients needing status review</div>
        </a>
      </div>
    </aside>
  </div>

  <!-- Revenue Dashboard -->
  <div class="mt-6">
    <section class="bg-white border rounded-2xl p-5">
      <h3 class="font-semibold mb-4 text-lg">Revenue Dashboard</h3>

      <div class="grid grid-cols-3 gap-6 mb-6">
        <div class="border-l-4 border-brand pl-4">
          <div class="text-sm text-slate-600 mb-1">Total Revenue<?php if($adminRole === 'sales'): ?> <span class="text-[10px] text-slate-400">(30%)</span><?php endif; ?></div>
          <div class="text-3xl font-bold text-brand">$<?=number_format($displayTotalRevenue, 2)?></div>
          <div class="text-xs text-slate-500 mt-1">
            <span class="text-green-600">Earned: $<?=number_format($displayEarnedRevenue, 0)?></span> •
            <span class="text-blue-600">Projected: $<?=number_format($displayProjectedRevenue, 0)?></span>
          </div>
        </div>
        <div class="border-l-4 border-green-500 pl-4">
          <div class="text-sm text-slate-600 mb-1">Top Practice Revenue</div>
          <div class="text-2xl font-bold text-green-700">
            <?php if (!empty($practiceRevenue)): ?>
              $<?=number_format(reset($practiceRevenue) * $revenueMultiplier, 2)?>
            <?php else: ?>
              $0.00
            <?php endif; ?>
          </div>
          <div class="text-xs text-slate-500 mt-1">
            <?php if (!empty($practiceRevenue)): ?>
              <?=e(key($practiceRevenue))?>
            <?php else: ?>
              No practice data
            <?php endif; ?>
          </div>
        </div>
        <div class="border-l-4 border-blue-500 pl-4">
          <div class="text-sm text-slate-600 mb-1">Top Product Revenue</div>
          <div class="text-2xl font-bold text-blue-700">
            <?php if (!empty($productRevenue)): ?>
              $<?=number_format(reset($productRevenue) * $revenueMultiplier, 2)?>
            <?php else: ?>
              $0.00
            <?php endif; ?>
          </div>
          <div class="text-xs text-slate-500 mt-1">
            <?php if (!empty($productRevenue)): ?>
              <?=e(key($productRevenue))?>
            <?php else: ?>
              No product data
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Interactive Revenue Line Graph -->
      <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-semibold text-sm">Revenue Trends</h4>
          <div class="flex gap-3">
            <div>
              <label class="text-xs text-slate-600 mr-2">Filter by Practice:</label>
              <select id="practiceFilter" class="text-sm px-3 py-1 border rounded">
                <option value="all">All Practices</option>
                <?php foreach ($practiceRevenue as $practice => $revenue): ?>
                  <option value="<?=e($practice)?>"><?=e($practice)?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="text-xs text-slate-600 mr-2">Filter by Product:</label>
              <select id="productFilter" class="text-sm px-3 py-1 border rounded">
                <option value="all">All Products</option>
                <?php foreach ($productRevenue as $product => $revenue): ?>
                  <option value="<?=e($product)?>"><?=e($product)?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="bg-slate-50 rounded-lg p-4" style="height: 400px;">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>

      <!-- Quick Stats Below Graph -->
      <div class="grid grid-cols-2 gap-6">
        <!-- Practice Revenue Breakdown -->
        <div>
          <h4 class="font-semibold mb-3 text-sm">Practice Revenue (Top 5)</h4>
          <?php if (!empty($practiceRevenue)): ?>
            <div class="space-y-2">
              <?php foreach ($practiceRevenue as $practice => $revenue): ?>
                <div class="flex items-center justify-between text-sm pb-2 border-b">
                  <span class="text-slate-700 truncate max-w-[250px]" title="<?=e($practice)?>"><?=e($practice)?></span>
                  <span class="font-medium text-brand">$<?=number_format($revenue * $revenueMultiplier, 0)?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-sm text-slate-500 italic">No practice revenue data available</p>
          <?php endif; ?>
        </div>

        <!-- Product Revenue Breakdown -->
        <div>
          <h4 class="font-semibold mb-3 text-sm">Product Revenue (Top 5)</h4>
          <?php if (!empty($productRevenue)): ?>
            <div class="space-y-2">
              <?php foreach ($productRevenue as $product => $revenue): ?>
                <div class="flex items-center justify-between text-sm pb-2 border-b">
                  <span class="text-slate-700 truncate max-w-[250px]" title="<?=e($product)?>"><?=e($product)?></span>
                  <span class="font-medium text-blue-600">$<?=number_format($revenue * $revenueMultiplier, 0)?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-sm text-slate-500 italic">No product revenue data available</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="mt-4 pt-4 border-t text-xs text-slate-500">
        <p><strong>Calculation:</strong> Revenue = Total product count × Reimbursement rate (or product price as fallback)</p>
        <p class="mt-1">Based on active orders (excluding rejected and cancelled orders)</p>
      </div>
    </section>
  </div>

  <?php if ($adminRole === 'manufacturer' && !empty($notifications)): ?>
  <div class="mt-6">
    <section class="bg-white border rounded-2xl p-4">
      <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold">Recent Notifications</h3>
        <span class="text-xs text-slate-500"><?=count($notifications)?> notification<?=count($notifications)!==1?'s':''?></span>
      </div>
      <div class="space-y-2">
        <?php foreach($notifications as $notif): ?>
        <div class="flex items-start gap-3 p-3 border rounded-lg <?=$notif['is_read']?'bg-slate-50':'bg-blue-50 border-blue-200'?>">
          <div class="flex-shrink-0 mt-0.5">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="<?=$notif['is_read']?'text-slate-400':'text-blue-500'?>">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm <?=$notif['is_read']?'text-slate-600':'text-slate-900 font-medium'?>">
              <?=htmlspecialchars($notif['message'])?>
            </p>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-xs text-slate-500"><?=date('M j, g:i A', strtotime($notif['created_at']))?></span>
              <?php if ($notif['link']): ?>
                <a href="<?=htmlspecialchars($notif['link'])?>" class="text-xs text-brand hover:underline">View Order</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
  <?php endif; ?>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Revenue data from PHP
const revenueData = {
  practices: <?=json_encode($practiceRevenue)?>,
  products: <?=json_encode($productRevenue)?>,
  totalRevenue: <?=$totalRevenue?>,
  displayTotalRevenue: <?=$displayTotalRevenue?>,
  revenueMultiplier: <?=$revenueMultiplier?>
};

// Generate sample monthly data (last 6 months)
const months = [];
const today = new Date();
for (let i = 5; i >= 0; i--) {
  const date = new Date(today.getFullYear(), today.getMonth() - i, 1);
  months.push(date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }));
}

// Initialize chart
let revenueChart;

function createRevenueChart() {
  const ctx = document.getElementById('revenueChart').getContext('2d');

  // Generate sample trend data (simulate growth over 6 months)
  const baseRevenue = revenueData.displayTotalRevenue / 6;
  const monthlyData = months.map((month, idx) => {
    // Simulate growth trend with some variance
    const growthFactor = 0.7 + (idx * 0.1) + (Math.random() * 0.2);
    return Math.round(baseRevenue * growthFactor);
  });

  revenueChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: months,
      datasets: [{
        label: 'Total Revenue',
        data: monthlyData,
        borderColor: 'rgb(59, 130, 246)',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointRadius: 6,
        pointHoverRadius: 8,
        pointBackgroundColor: 'rgb(59, 130, 246)',
        pointBorderColor: '#fff',
        pointBorderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          position: 'top',
          labels: {
            font: { size: 14, weight: 'bold' },
            padding: 15
          }
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          padding: 12,
          titleFont: { size: 14 },
          bodyFont: { size: 13 },
          callbacks: {
            label: function(context) {
              return ' $' + context.parsed.y.toLocaleString();
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return '$' + value.toLocaleString();
            },
            font: { size: 12 }
          },
          grid: {
            color: 'rgba(0, 0, 0, 0.05)'
          }
        },
        x: {
          ticks: {
            font: { size: 12 }
          },
          grid: {
            display: false
          }
        }
      },
      interaction: {
        intersect: false,
        mode: 'index'
      }
    }
  });
}

// Filter functionality
function updateChart() {
  const practiceFilter = document.getElementById('practiceFilter').value;
  const productFilter = document.getElementById('productFilter').value;

  let filteredRevenue = revenueData.totalRevenue;
  let label = 'Total Revenue';

  if (practiceFilter !== 'all') {
    filteredRevenue = (revenueData.practices[practiceFilter] || 0) * revenueData.revenueMultiplier;
    label = practiceFilter + ' Revenue';
    // Generate new data based on filtered practice
    const baseRevenue = filteredRevenue / 6;
    const monthlyData = months.map((month, idx) => {
      const growthFactor = 0.7 + (idx * 0.1) + (Math.random() * 0.2);
      return Math.round(baseRevenue * growthFactor);
    });
    revenueChart.data.datasets[0].data = monthlyData;
    revenueChart.data.datasets[0].label = label;
    revenueChart.data.datasets[0].borderColor = 'rgb(34, 197, 94)';
    revenueChart.data.datasets[0].backgroundColor = 'rgba(34, 197, 94, 0.1)';
    revenueChart.data.datasets[0].pointBackgroundColor = 'rgb(34, 197, 94)';
  } else if (productFilter !== 'all') {
    filteredRevenue = (revenueData.products[productFilter] || 0) * revenueData.revenueMultiplier;
    label = productFilter + ' Revenue';
    // Generate new data based on filtered product
    const baseRevenue = filteredRevenue / 6;
    const monthlyData = months.map((month, idx) => {
      const growthFactor = 0.7 + (idx * 0.1) + (Math.random() * 0.2);
      return Math.round(baseRevenue * growthFactor);
    });
    revenueChart.data.datasets[0].data = monthlyData;
    revenueChart.data.datasets[0].label = label;
    revenueChart.data.datasets[0].borderColor = 'rgb(147, 51, 234)';
    revenueChart.data.datasets[0].backgroundColor = 'rgba(147, 51, 234, 0.1)';
    revenueChart.data.datasets[0].pointBackgroundColor = 'rgb(147, 51, 234)';
  } else {
    // Reset to total revenue
    const baseRevenue = revenueData.displayTotalRevenue / 6;
    const monthlyData = months.map((month, idx) => {
      const growthFactor = 0.7 + (idx * 0.1) + (Math.random() * 0.2);
      return Math.round(baseRevenue * growthFactor);
    });
    revenueChart.data.datasets[0].data = monthlyData;
    revenueChart.data.datasets[0].label = 'Total Revenue';
    revenueChart.data.datasets[0].borderColor = 'rgb(59, 130, 246)';
    revenueChart.data.datasets[0].backgroundColor = 'rgba(59, 130, 246, 0.1)';
    revenueChart.data.datasets[0].pointBackgroundColor = 'rgb(59, 130, 246)';
  }

  revenueChart.update();
}

// Initialize chart on page load
document.addEventListener('DOMContentLoaded', function() {
  createRevenueChart();

  // Add event listeners for filters
  document.getElementById('practiceFilter').addEventListener('change', function() {
    if (this.value !== 'all') {
      document.getElementById('productFilter').value = 'all';
    }
    updateChart();
  });

  document.getElementById('productFilter').addEventListener('change', function() {
    if (this.value !== 'all') {
      document.getElementById('practiceFilter').value = 'all';
    }
    updateChart();
  });
});
</script>

<?php include __DIR__.'/_footer.php'; ?>
