<?php
// /public/admin/billing.php — Billing with View links + Order PDF + new revenue model (PHP 7+)
declare(strict_types=1);

require_once __DIR__ . '/db.php';
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
$auth      = __DIR__ . '/auth.php';      if (is_file($auth)) require_once $auth;
if (function_exists('require_admin')) require_admin();

// Get current admin user and role
$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';

/* ================= Polyfills / safety ================= */
if (!function_exists('str_contains')) {
  function str_contains($h, $n){ return $n === '' ? true : strpos((string)$h, (string)$n) !== false; }
}
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* ================= Helpers ================= */
function has_table(PDO $pdo, $t) {
  try {
    // PostgreSQL doesn't have DATABASE(), use current_schema() or just check table_name
    $st=$pdo->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.TABLES WHERE table_name=?");
    $st->execute([$t]); $r=$st->fetch(); return (int)($r['c']??0) > 0;
  } catch (Throwable $e) { error_log($e->getMessage()); return false; }
}
function has_column(PDO $pdo, $tbl, $col) {
  try {
    // PostgreSQL doesn't have DATABASE(), use current_schema() or just check table_name
    $st=$pdo->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name=? AND column_name=?");
    $st->execute([$tbl,$col]); $r=$st->fetch(); return (int)($r['c']??0) > 0;
  } catch (Throwable $e) { error_log($e->getMessage()); return false; }
}
function patches_per_week_text($f) {
  $f=strtolower(trim((string)$f));
  if ($f==='daily') return 7;
  if ($f==='every other day') return 4;
  if ($f==='weekly') return 1;
  if (preg_match('/(\d+)\s*x\s*\/?\s*week/', $f, $m)) return max(1,(int)$m[1]);
  if (preg_match('/(\d+)\s*x\s*per\s*week/', $f, $m)) return max(1,(int)$m[1]);
  return 1;
}

/* ---------- robust uploads root & linking ---------- */
function uploads_root_abs() {
  // Check /public/uploads first (where orders.create.php saves files)
  $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
  if ($docRoot) {
    $publicUploads = realpath($docRoot . '/public/uploads');
    if ($publicUploads && is_dir($publicUploads)) {
      return rtrim($publicUploads, '/');
    }
    $uploads = realpath($docRoot . '/uploads');
    if ($uploads && is_dir($uploads)) {
      return rtrim($uploads, '/');
    }
  }
  // Fallback to relative path
  $relative = realpath(__DIR__ . '/../uploads');
  if ($relative && is_dir($relative)) {
    return rtrim($relative, '/');
  }
  return rtrim(__DIR__ . '/../uploads', '/');
}
function uploads_rel($abs) {
  $root = uploads_root_abs();
  if ($root && strpos($abs, $root) === 0) {
    $suffix = substr($abs, strlen($root)); // /ids/file.pdf
    return '/public/uploads' . $suffix;
  }
  if (preg_match('~/(public/)?uploads/.*$~', $abs, $m)) return '/' . ltrim($m[0], '/');
  return '/public/uploads/' . basename($abs);
}
function list_bucket_files_abs($bucket) {
  $dir = uploads_root_abs() . '/' . trim($bucket, '/');
  if (!is_dir($dir)) return [];
  $files = glob($dir.'/*'); if (!$files) $files = [];
  $files = array_values(array_filter($files, 'is_file'));
  usort($files, function($a,$b){ $ma=@filemtime($a)?:0; $mb=@filemtime($b)?:0; return $mb <=> $ma; });
  return $files;
}
function find_bucket_files($bucket, $tokens, $limit=6) {
  $absFiles = list_bucket_files_abs($bucket);
  if (!$absFiles) return [];
  $tokens = array_values(array_filter(array_map('strval', $tokens)));
  foreach ($tokens as &$t) $t = strtolower($t);
  $hits = [];
  foreach ($absFiles as $abs) {
    $name = strtolower(basename($abs));
    $ok=false; foreach ($tokens as $t){ if($t!=='' && strpos($name,$t)!==false){ $ok=true; break; } }
    if ($ok) $hits[] = uploads_rel($abs);
    if (count($hits) >= $limit) break;
  }
  if (!$hits) { foreach (array_slice($absFiles,0,min($limit,3)) as $abs) $hits[] = uploads_rel($abs); }
  return $hits;
}
function render_view_link($paths, $empty='—') {
  if (!$paths) return '<span class="text-slate-400">'.$empty.'</span>';
  $csrf = $_SESSION['csrf'] ?? '';
  $first = $paths[0];
  $url = '/admin/file.dl.php?p=' . rawurlencode($first) . '&mode=view&csrf=' . rawurlencode($csrf);
  $extra = count($paths) - 1;
  $bubble = $extra > 0 ? ' <span class="ml-1 inline-block text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600">+'.(int)$extra.'</span>' : '';
  return '<a class="text-brand underline" target="_blank" href="'.e($url).'">View</a>'.$bubble;
}

/* ================= Filters ================= */
// Default to last 6 months instead of just current month
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-6 months'));
$to   = isset($_GET['to'])   ? $_GET['to']   : date('Y-m-d');
$phys = isset($_GET['phys']) ? $_GET['phys'] : '';
$where  = "o.created_at BETWEEN :from AND (:to::date + INTERVAL '1 day') AND o.status NOT IN ('rejected','cancelled')";
$params = ['from'=>$from, 'to'=>$to];
if ($phys!==''){ $where.=" AND o.user_id=:phys"; $params['phys']=$phys; }

// Role-based access control
if ($adminRole === 'superadmin' || $adminRole === 'manufacturer') {
  // Superadmin and manufacturer see all orders - no additional filter
} else {
  // Employees only see orders from assigned physicians
  $where .= " AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = :admin_id AND ap.physician_user_id = o.user_id)";
  $params['admin_id'] = $adminId;
}

$hasProducts = has_table($pdo,'products');
$hasRates    = has_table($pdo,'reimbursement_rates');
$hasShipRem  = has_column($pdo,'orders','shipments_remaining');

/* ================= Data ================= */
try {
  // Debug logging
  error_log("[billing-debug] Admin Role: " . ($adminRole ?: 'NONE'));
  error_log("[billing-debug] Admin ID: " . ($adminId ?: 'NONE'));
  error_log("[billing-debug] WHERE clause: " . $where);
  error_log("[billing-debug] Params: " . json_encode($params));

  // Check total orders in database
  $totalCheck = $pdo->query("SELECT COUNT(*) as cnt FROM orders")->fetch();
  error_log("[billing-debug] Total orders in database: " . ($totalCheck['cnt'] ?? 0));

  // Check orders NOT rejected/cancelled
  $activeCheck = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status NOT IN ('rejected','cancelled')")->fetch();
  error_log("[billing-debug] Active orders (not rejected/cancelled): " . ($activeCheck['cnt'] ?? 0));

  // Check which HCPCS/CPT column exists in products table
  $hcpcsCol = 'cpt_code'; // default
  if ($hasProducts) {
    $prodCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'products'")->fetchAll(PDO::FETCH_COLUMN);
    $hcpcsCol = in_array('hcpcs_code', $prodCols) ? 'hcpcs_code' : 'cpt_code';
  }

  $sql = "
    SELECT
      o.id, o.user_id, o.patient_id, o.product_id, o.product, o.frequency,
      o.frequency_per_week, o.qty_per_change, o.duration_days, o.refills_allowed,
      ".($hasShipRem?"o.shipments_remaining,":"")."
      o.product_price, o.created_at, o.rx_note_name AS tracking, o.rx_note_mime AS carrier,
      o.insurer_name, o.member_id, o.group_id, o.payer_phone,
      p.first_name, p.last_name, p.dob
      ".($hasProducts?", pr.name AS prod_name, pr.size AS prod_size, pr.sku, pr.$hcpcsCol AS cpt_code, pr.price_admin":"")."
    FROM orders o
    LEFT JOIN patients p ON p.id=o.patient_id
    ".($hasProducts?"LEFT JOIN products pr ON pr.id=o.product_id":"")."
    WHERE $where
    ORDER BY o.created_at DESC
  ";
  error_log("[billing-debug] Full SQL: " . $sql);
  $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
  error_log("[billing-debug] Found " . count($rows) . " rows after query");
} catch (Throwable $e) {
  error_log("[billing-data] ERROR: ".$e->getMessage());
  error_log("[billing-data] Stack trace: ".$e->getTraceAsString());
  $rows = [];
}

/* CPT rate map */
$rates = [];
if ($hasRates) {
  try {
    foreach($pdo->query("SELECT cpt_code, COALESCE(rate_non_rural,0) rate FROM reimbursement_rates") as $r){
      $rates[$r['cpt_code']] = (float)$r['rate'];
    }
  } catch (Throwable $e) { error_log("[rates] ".$e->getMessage()); }
}

/* Projected revenue with new quantity model */
function projected_rev($row, $rates, $hasProducts, $hasShipRem) {
  // frequency per week (prefer numeric; fallback to legacy text)
  $fpw = (int)($row['frequency_per_week'] ?? 0);
  if ($fpw <= 0) $fpw = patches_per_week_text(isset($row['frequency'])?$row['frequency']:null);

  $qty  = max(1, (int)($row['qty_per_change'] ?? 1));
  $days = max(0, (int)($row['duration_days'] ?? 0));
  $ref  = max(0, (int)($row['refills_allowed'] ?? 0));

  // total authorized weeks across all fills
  $weeks_authorized = ($days > 0) ? (int)ceil($days / 7) : 0;
  if ($weeks_authorized <= 0) $weeks_authorized = 4; // conservative default
  $weeks_authorized_all = $weeks_authorized * (1 + $ref);

  // remaining window to fulfill
  $shipRem = $hasShipRem ? (int)($row['shipments_remaining'] ?? 0) : 0;
  $remaining_weeks = $shipRem > 0 ? min($shipRem, $weeks_authorized_all) : $weeks_authorized_all;

  // billable units
  $units_remaining = $remaining_weeks * $fpw * $qty;

  // unit rate: CPT rate preferred, else product price
  $unit = 0.0;
  if ($hasProducts && !empty($row['cpt_code']) && isset($rates[$row['cpt_code']]) && $rates[$row['cpt_code']] > 0) {
    $unit = (float)$rates[$row['cpt_code']];
  } else {
    $unit = (float)($row['product_price'] ?? 0);
  }
  return $unit > 0 ? $unit * $units_remaining : 0.0;
}

/* ================= View ================= */
include __DIR__.'/_header.php';
?>
<div>
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">Billing</h2>
    <form class="flex items-center gap-2" method="get">
      <input type="date" name="from" value="<?=e($from)?>">
      <input type="date" name="to" value="<?=e($to)?>">
      <input type="text" name="phys" placeholder="Physician User ID (optional)" value="<?=e($phys)?>" style="width: 200px;">
      <button class="btn btn-primary" type="submit">Filter</button>
    </form>
  </div>

  <section class="card p-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Patient</th>
            <th class="py-2">Order</th>
            <th class="py-2">Product</th>
            <th class="py-2">Freq</th>
            <th class="py-2">Shipments Remaining</th>
            <th class="py-2">CPT</th>
            <th class="py-2">Projected Revenue</th>
            <th class="py-2">Notes</th>
            <th class="py-2">ID</th>
            <th class="py-2">Insurance Card</th>
            <th class="py-2">Order PDF</th>
            <?php if ($adminRole === 'manufacturer'): ?>
            <th class="py-2">Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
      <tbody>
        <?php $total=0.0; foreach($rows as $row):
          $prodLabel = ($hasProducts && !empty($row['prod_name']))
                        ? ($row['prod_name'].(!empty($row['prod_size'])?(" ".$row['prod_size']):""))
                        : ($row['product'] ?? '');
          $rev = projected_rev($row, $rates, $hasProducts, $hasShipRem); $total += $rev;

          $pid      = (string)($row['patient_id'] ?? '');
          $oid      = (string)($row['id'] ?? '');
          $fullname = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));
          $slug     = preg_replace('/[^a-z0-9]+/i','_', strtolower($fullname));
          $tokens   = array_filter([$pid, $oid, $slug]);

          $noteLinks = find_bucket_files('notes',     $tokens);
          $idLinks   = find_bucket_files('ids',       $tokens);
          $insLinks  = find_bucket_files('insurance', $tokens);

          $orderUrl = '/admin/order.pdf.php?id=' . rawurlencode($row['id']) . '&csrf=' . rawurlencode($_SESSION['csrf'] ?? '');
          $downloadAllUrl = '/admin/download-all.php?id=' . rawurlencode($row['id']) . '&csrf=' . rawurlencode($_SESSION['csrf'] ?? '');
        ?>
        <tr class="border-t">
          <td class="py-2">
            <?=e($fullname ?: '—')?> <br>
            <span class="text-[11px] text-slate-500">DOB: <?=e($row['dob'] ?? '—')?></span>
          </td>
          <td class="py-2">#<?=e($row['id'])?><br><span class="text-xs text-slate-500"><?=e(substr((string)$row['created_at'],0,10))?></span></td>
          <td class="py-2"><?=e($prodLabel)?><br><span class="text-xs text-slate-500"><?=e(($row['sku'] ?? '') ?: '')?></span></td>
          <td class="py-2">
            <?php
              $fpwDisp = (int)($row['frequency_per_week'] ?? 0);
              if ($fpwDisp <= 0) $fpwDisp = patches_per_week_text(isset($row['frequency'])?$row['frequency']:null);
              echo e($fpwDisp.'×/week');
            ?>
          </td>
          <td class="py-2"><?= $hasShipRem ? e((string)($row['shipments_remaining'] ?? 0)) : '—' ?></td>
          <td class="py-2"><?=e($row['cpt_code'] ?? '—')?></td>
          <td class="py-2 font-semibold">$<?=number_format($rev,2)?></td>
          <td class="py-2"><?=render_view_link($noteLinks)?></td>
          <td class="py-2"><?=render_view_link($idLinks)?></td>
          <td class="py-2"><?=render_view_link($insLinks)?></td>
          <td class="py-2"><a class="text-brand underline" target="_blank" href="<?=e($orderUrl)?>">View</a></td>
          <?php if ($adminRole === 'manufacturer'): ?>
          <td class="py-2">
            <a class="btn btn-primary text-xs" href="<?=e($downloadAllUrl)?>" title="Download all documents as ZIP">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
              </svg>
              Download All
            </a>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <tr class="border-t font-semibold" style="background: #f9fafb;">
          <td class="py-2" colspan="6">Total (Filtered)</td>
          <td class="py-2">$<?=number_format($total,2)?></td>
          <td class="py-2" colspan="<?=$adminRole==='manufacturer'?'5':'4'?>"></td>
        </tr>
      </tbody>
    </table>
    </div>
  </section>
</div>
<?php include __DIR__.'/_footer.php'; ?>
