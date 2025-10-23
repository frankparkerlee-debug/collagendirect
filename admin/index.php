<?php
// /public/admin/index.php — CollagenDirect Dashboard (Projected Remaining Revenue)
declare(strict_types=1);

require_once __DIR__ . '/db.php';
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
$auth      = __DIR__ . '/auth.php'; if (is_file($auth) && function_exists('require_admin')) require_admin();

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
    $expiringOrders = qCount($pdo,"SELECT COUNT(*) c FROM orders WHERE expires_at IS NOT NULL AND expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY) AND status IN ('approved','in_transit')");
  }
} catch(Throwable $e){}
try {
  if (has_column($pdo,'orders','shipped_at') && has_column($pdo,'orders','delivered_at')) {
    $delayedShipments = qCount($pdo,"SELECT COUNT(*) c FROM orders WHERE status='in_transit' AND shipped_at IS NOT NULL AND delivered_at IS NULL AND shipped_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
  }
} catch(Throwable $e){}

include __DIR__.'/_header.php';
?>
<div class="max-w-[1200px] mx-auto">
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
        <thead class="text-left text-slate-500"><tr><th class="py-2">Patient</th><th>Order</th><th>Product</th><th>Status</th><th>Updated</th></tr></thead>
        <tbody>
          <?php foreach($recent as $r): ?>
          <tr class="border-t">
            <td class="py-2"><?=e(trim(($r['first_name']??'').' '.($r['last_name']??'')) ?: '—')?></td>
            <td><a class="text-brand hover:underline" href="/admin/orders.php?focus=<?=e($r['id'])?>">#<?=e($r['id'])?></a></td>
            <td><?=e($r['product'] ?? '')?></td>
            <td><?=e(ucwords(str_replace('_',' ', $r['status'] ?? '')))?></td>
            <td><?=e($r['ts'] ?? '')?></td>
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
</div>
<?php include __DIR__.'/_footer.php'; ?>
