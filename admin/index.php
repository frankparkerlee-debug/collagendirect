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
  try { $st=$pdo->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
        $st->execute([$tbl]); return ((int)$st->fetch()['c'])>0; } catch(Throwable $e){ return false; }
}
function has_column(PDO $pdo, string $tbl, string $col): bool {
  try { $st=$pdo->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
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
$totalOrders      = qCount($pdo, "SELECT COUNT(*) c FROM orders");
$pendingApprovals = qCount($pdo, "SELECT COUNT(*) c FROM orders WHERE status IN ('submitted','pending','awaiting_approval')");
$activePatients   = qCount($pdo, "SELECT COUNT(DISTINCT patient_id) c FROM orders WHERE status IN ('approved','in_transit','delivered')");

/* ---------- Projected Remaining Revenue ----------
   Only count orders NOT in ('rejected','cancelled').
   Patches = patches_per_week(frequency) * shipments_remaining
   Reimbursement = reimbursement_rates.rate_non_rural by CPT (via products.cpt_code)
   Fallback: orders.product_price (if no rate).
--------------------------------------------------- */
$hasProducts = has_table($pdo,'products');
$hasRates    = has_table($pdo,'reimbursement_rates');
$hasShipRem  = has_column($pdo,'orders','shipments_remaining');

$projectedRemaining = 0.0;
try {
  $sql = "SELECT o.status, o.frequency, ".($hasShipRem?"o.shipments_remaining,":"")." o.product_price ".
         ($hasProducts?", pr.cpt_code ":"").
         "FROM orders o ".
         ($hasProducts?"LEFT JOIN products pr ON pr.id=o.product_id ":"").
         "WHERE o.status NOT IN ('rejected','cancelled')";
  $stmt = $pdo->query($sql);
  // Prefetch rate map if present
  $rates = [];
  if ($hasRates) {
    foreach ($pdo->query("SELECT cpt_code, COALESCE(rate_non_rural,0) rate FROM reimbursement_rates") as $r) {
      $rates[$r['cpt_code']] = (float)$r['rate'];
    }
  }

  foreach ($stmt as $row) {
    $shipRem = $hasShipRem ? (int)($row['shipments_remaining'] ?? 0) : 0;
    if ($shipRem <= 0) continue; // nothing left to ship, no revenue remaining

    $ppw = patches_per_week($row['frequency'] ?? '');
    $patches = $ppw * $shipRem;

    // reimbursement per patch
    $unit = 0.0;
    if ($hasProducts && !empty($row['cpt_code']) && isset($rates[$row['cpt_code']]) && $rates[$row['cpt_code']] > 0) {
      $unit = $rates[$row['cpt_code']];
    } else {
      $unit = (float)($row['product_price'] ?? 0); // fallback
    }
    if ($unit <= 0) continue;

    $projectedRemaining += $unit * $patches;
  }
} catch (Throwable $e) {
  error_log("[projectedRevenue] ".$e->getMessage());
}

/* ---------- Revenue Dashboard ----------
   Calculate total revenue, practice revenue, and product revenue
   Revenue = total product count × reimbursement rate
--------------------------------------------------- */
$totalRevenue = 0.0;
$practiceRevenue = [];
$productRevenue = [];

try {
  // Simplified: assume hcpcs_code exists, fall back to cpt_code on error
  $hcpcsCol = 'hcpcs_code';

  // Get all orders with products and calculate revenue
  $sql = "SELECT
            o.id, o.user_id, o.product, o.frequency, o.product_price,
            o.frequency_per_week, o.qty_per_change, o.duration_days, o.refills_allowed,
            ".($hasShipRem?"o.shipments_remaining,":"")."
            ".($hasProducts?"pr.$hcpcsCol, pr.name AS product_name,":"")."
            u.first_name AS phys_first, u.last_name AS phys_last, u.practice_name
          FROM orders o
          ".($hasProducts?"LEFT JOIN products pr ON pr.id = o.product_id":"")."
          LEFT JOIN users u ON u.id = o.user_id
          WHERE o.status NOT IN ('rejected', 'cancelled')";

  $stmt = $pdo->query($sql);

  // Prefetch rate map
  $rates = [];
  if ($hasRates) {
    foreach ($pdo->query("SELECT cpt_code, COALESCE(rate_non_rural,0) rate FROM reimbursement_rates") as $r) {
      $rates[$r['cpt_code']] = (float)$r['rate'];
    }
  }

  foreach ($stmt as $row) {
    // Calculate patches/units for this order
    $fpw = (int)($row['frequency_per_week'] ?? 0);
    if ($fpw <= 0) $fpw = patches_per_week($row['frequency'] ?? '');

    $qty = max(1, (int)($row['qty_per_change'] ?? 1));
    $days = max(0, (int)($row['duration_days'] ?? 0));
    $ref = max(0, (int)($row['refills_allowed'] ?? 0));

    $weeks_authorized = ($days > 0) ? (int)ceil($days / 7) : 4;
    $weeks_total = $weeks_authorized * (1 + $ref);

    $shipRem = $hasShipRem ? (int)($row['shipments_remaining'] ?? 0) : 0;
    $remaining_weeks = $shipRem > 0 ? min($shipRem, $weeks_total) : $weeks_total;

    $units = $remaining_weeks * $fpw * $qty;

    // Get unit price (reimbursement rate or product price)
    $unitPrice = 0.0;
    $cptCode = $hasProducts ? ($row[$hcpcsCol] ?? null) : null;
    if ($hasProducts && !empty($cptCode) && isset($rates[$cptCode]) && $rates[$cptCode] > 0) {
      $unitPrice = $rates[$cptCode];
    } else {
      $unitPrice = (float)($row['product_price'] ?? 0);
    }

    if ($unitPrice <= 0 || $units <= 0) continue;

    $orderRevenue = $unitPrice * $units;
    $totalRevenue += $orderRevenue;

    // Practice revenue breakdown
    $practiceName = $row['practice_name'] ?? 'Unknown Practice';
    if (!isset($practiceRevenue[$practiceName])) {
      $practiceRevenue[$practiceName] = 0.0;
    }
    $practiceRevenue[$practiceName] += $orderRevenue;

    // Product revenue breakdown
    $productName = ($hasProducts && !empty($row['product_name'])) ? $row['product_name'] : ($row['product'] ?? 'Unknown Product');
    if (!isset($productRevenue[$productName])) {
      $productRevenue[$productName] = 0.0;
    }
    $productRevenue[$productName] += $orderRevenue;
  }

  // Sort by revenue descending
  arsort($practiceRevenue);
  arsort($productRevenue);

  // Keep top 5 for display
  $practiceRevenue = array_slice($practiceRevenue, 0, 5, true);
  $productRevenue = array_slice($productRevenue, 0, 5, true);

} catch (Throwable $e) {
  error_log("[revenue-dashboard] " . $e->getMessage());
}

/* ---------- Recent activity ---------- */
$recent = [];
try {
  $recent = $pdo->query("
    SELECT o.id, o.status, o.product, COALESCE(o.updated_at, o.created_at) AS ts,
           p.first_name, p.last_name
    FROM orders o
    LEFT JOIN patients p ON p.id = o.patient_id
    ORDER BY ts DESC
    LIMIT 8
  ")->fetchAll();
} catch(Throwable $e){ error_log("[recent] ".$e->getMessage()); }

/* ---------- Reminders (safe) ---------- */
$expiringOrders = 0; $delayedShipments = 0;
try {
  if (has_column($pdo,'orders','expires_at')) {
    $expiringOrders = qCount($pdo,"SELECT COUNT(*) c FROM orders WHERE expires_at IS NOT NULL AND expires_at < (NOW() + INTERVAL '7 days') AND status IN ('approved','in_transit')");
  }
} catch(Throwable $e){}
try {
  if (has_column($pdo,'orders','shipped_at') && has_column($pdo,'orders','delivered_at')) {
    $delayedShipments = qCount($pdo,"SELECT COUNT(*) c FROM orders WHERE status='in_transit' AND shipped_at IS NOT NULL AND delivered_at IS NULL AND shipped_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
  }
} catch(Throwable $e){}

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
  <div class="grid grid-cols-4 gap-4 mb-8">
    <div class="bg-white border rounded-2xl p-4 shadow-soft">
      <div class="text-xs text-slate-500">Total Orders</div>
      <div class="text-2xl font-bold"><?=number_format($totalOrders)?></div>
    </div>
    <div class="bg-white border rounded-2xl p-4 shadow-soft">
      <div class="text-xs text-slate-500">Pending Approvals</div>
      <div class="text-2xl font-bold text-amber-600"><?=number_format($pendingApprovals)?></div>
    </div>
    <div class="bg-white border rounded-2xl p-4 shadow-soft">
      <div class="text-xs text-slate-500">Active Patients</div>
      <div class="text-2xl font-bold"><?=number_format($activePatients)?></div>
    </div>
    <div class="bg-white border rounded-2xl p-4 shadow-soft">
      <div class="text-xs text-slate-500">Projected Remaining Revenue</div>
      <div class="text-2xl font-bold text-brand">$<?=number_format($projectedRemaining,2)?></div>
    </div>
  </div>

  <div class="grid grid-cols-12 gap-6">
    <section class="col-span-7 bg-white border rounded-2xl p-4">
      <h3 class="font-semibold mb-3">Recent Activity</h3>
      <table class="w-full text-sm">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Patient</th>
            <th class="py-2">Order</th>
            <th class="py-2">Product</th>
            <th class="py-2">Status</th>
            <th class="py-2">Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($recent as $r): ?>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3"><?=e(trim(($r['first_name']??'').' '.($r['last_name']??'')) ?: '—')?></td>
            <td class="py-3"><a class="text-brand hover:underline" href="/admin/orders.php?focus=<?=e($r['id'])?>">#<?=e($r['id'])?></a></td>
            <td class="py-3"><?=e($r['product'] ?? '')?></td>
            <td class="py-3"><?=e(ucwords(str_replace('_',' ', $r['status'] ?? '')))?></td>
            <td class="py-3"><?=e($r['ts'] ?? '')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <aside class="col-span-5 bg-white border rounded-2xl p-4">
      <h3 class="font-semibold mb-3">Reminders</h3>
      <ul class="text-sm space-y-2">
        <li><span class="font-medium">Pending Approvals:</span> <?=$pendingApprovals?></li>
        <li><span class="font-medium">Orders expiring (7 days):</span> <?=$expiringOrders?></li>
        <li><span class="font-medium">Shipments delayed (&gt; 7 days):</span> <?=$delayedShipments?></li>
      </ul>
    </aside>
  </div>

  <!-- Revenue Dashboard -->
  <div class="mt-6">
    <section class="bg-white border rounded-2xl p-5">
      <h3 class="font-semibold mb-4 text-lg">Revenue Dashboard</h3>

      <div class="grid grid-cols-3 gap-6 mb-6">
        <div class="border-l-4 border-brand pl-4">
          <div class="text-sm text-slate-600 mb-1">Total Revenue</div>
          <div class="text-3xl font-bold text-brand">$<?=number_format($totalRevenue, 2)?></div>
          <div class="text-xs text-slate-500 mt-1">All orders (not rejected/cancelled)</div>
        </div>
        <div class="border-l-4 border-green-500 pl-4">
          <div class="text-sm text-slate-600 mb-1">Top Practice Revenue</div>
          <div class="text-2xl font-bold text-green-700">
            <?php if (!empty($practiceRevenue)): ?>
              $<?=number_format(reset($practiceRevenue), 2)?>
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
              $<?=number_format(reset($productRevenue), 2)?>
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

      <div class="grid grid-cols-2 gap-6">
        <!-- Practice Revenue Breakdown -->
        <div>
          <h4 class="font-semibold mb-3 text-sm">Practice Revenue (Top 5)</h4>
          <?php if (!empty($practiceRevenue)): ?>
            <div class="space-y-2">
              <?php foreach ($practiceRevenue as $practice => $revenue): ?>
                <div class="flex items-center justify-between text-sm pb-2 border-b">
                  <span class="text-slate-700 truncate max-w-[250px]" title="<?=e($practice)?>"><?=e($practice)?></span>
                  <span class="font-medium text-brand whitespace-nowrap ml-2">$<?=number_format($revenue, 2)?></span>
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
                  <span class="font-medium text-blue-600 whitespace-nowrap ml-2">$<?=number_format($revenue, 2)?></span>
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
<?php include __DIR__.'/_footer.php'; ?>
