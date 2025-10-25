<?php
// /public/admin/billing.php — Billing with View links + Order PDF + new revenue model (PHP 7+)
declare(strict_types=1);

require_once __DIR__ . '/db.php';
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
$auth      = __DIR__ . '/auth.php';      if (is_file($auth) && function_exists('require_admin')) require_admin();

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
    $st=$pdo->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->execute([$t]); $r=$st->fetch(); return (int)($r['c']??0) > 0;
  } catch (Throwable $e) { error_log($e->getMessage()); return false; }
}
function has_column(PDO $pdo, $tbl, $col) {
  try {
    $st=$pdo->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
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
  $cands = [
    realpath(__DIR__ . '/../uploads'),
    isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT'] . '/public/uploads') : false,
    isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT'] . '/uploads') : false,
  ];
  foreach ($cands as $p) { if ($p && is_dir($p)) return rtrim($p, '/'); }
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
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to   = isset($_GET['to'])   ? $_GET['to']   : date('Y-m-d');
$phys = isset($_GET['phys']) ? $_GET['phys'] : '';
$where  = "o.created_at BETWEEN :from AND DATE_ADD(:to, INTERVAL 1 DAY) AND o.status NOT IN ('rejected','cancelled')";
$params = ['from'=>$from, 'to'=>$to];
if ($phys!==''){ $where.=" AND o.user_id=:phys"; $params['phys']=$phys; }

$hasProducts = has_table($pdo,'products');
$hasRates    = has_table($pdo,'reimbursement_rates');
$hasShipRem  = has_column($pdo,'orders','shipments_remaining');

/* ================= Data ================= */
try {
  $sql = "
    SELECT
      o.id, o.user_id, o.patient_id, o.product_id, o.product, o.frequency,
      o.frequency_per_week, o.qty_per_change, o.duration_days, o.refills_allowed,
      ".($hasShipRem?"o.shipments_remaining,":"")."
      o.product_price, o.created_at, o.rx_note_name AS tracking, o.rx_note_mime AS carrier,
      o.insurer_name, o.member_id, o.group_id, o.payer_phone,
      p.first_name, p.last_name, p.dob
      ".($hasProducts?", pr.name AS prod_name, pr.size AS prod_size, pr.sku, pr.cpt_code, pr.price_admin":"")."
    FROM orders o
    LEFT JOIN patients p ON p.id=o.patient_id
    ".($hasProducts?"LEFT JOIN products pr ON pr.id=o.product_id":"")."
    WHERE $where
    ORDER BY o.created_at DESC
  ";
  $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
} catch (Throwable $e) {
  error_log("[billing-data] ".$e->getMessage());
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
<div class="max-w-[1200px] mx-auto">
  <div class="flex items-center justify-between mb-4">
    <div class="text-xl font-semibold">Billing</div>
    <form class="flex items-center gap-2" method="get">
      <input type="date" name="from" class="border rounded px-2 py-1" value="<?=e($from)?>">
      <input type="date" name="to" class="border rounded px-2 py-1" value="<?=e($to)?>">
      <input type="text" name="phys" class="border rounded px-2 py-1" placeholder="Physician User ID (optional)" value="<?=e($phys)?>">
      <button class="bg-brand text-white rounded px-3 py-1">Filter</button>
    </form>
  </div>

  <div class="bg-white border rounded-2xl overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="text-left text-slate-500 bg-slate-50">
        <tr>
          <th class="py-2 px-3">Patient</th>
          <th class="px-3">Order</th>
          <th class="px-3">Product</th>
          <th class="px-3">Freq</th>
          <th class="px-3">Shipments Remaining</th>
          <th class="px-3">CPT</th>
          <th class="px-3">Projected Revenue</th>
          <th class="px-3">Notes</th>
          <th class="px-3">ID</th>
          <th class="px-3">Insurance Card</th>
          <th class="px-3">Order PDF</th>
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
        ?>
        <tr class="border-t align-top">
          <td class="py-2 px-3">
            <?=e($fullname ?: '—')?> <br>
            <span class="text-[11px] text-slate-500">DOB: <?=e($row['dob'] ?? '—')?></span>
          </td>
          <td class="px-3">#<?=e($row['id'])?><br><span class="text-[11px] text-slate-500"><?=e(substr((string)$row['created_at'],0,10))?></span></td>
          <td class="px-3"><?=e($prodLabel)?><br><span class="text-[11px] text-slate-500"><?=e(($row['sku'] ?? '') ?: '')?></span></td>
          <td class="px-3">
            <?php
              $fpwDisp = (int)($row['frequency_per_week'] ?? 0);
              if ($fpwDisp <= 0) $fpwDisp = patches_per_week_text(isset($row['frequency'])?$row['frequency']:null);
              echo e($fpwDisp.'×/week');
            ?>
          </td>
          <td class="px-3"><?= $hasShipRem ? e($row['shipments_remaining'] ?? 0) : '—' ?></td>
          <td class="px-3"><?=e($row['cpt_code'] ?? '—')?></td>
          <td class="px-3 font-semibold">$<?=number_format($rev,2)?></td>
          <td class="px-3"><?=render_view_link($noteLinks)?></td>
          <td class="px-3"><?=render_view_link($idLinks)?></td>
          <td class="px-3"><?=render_view_link($insLinks)?></td>
          <td class="px-3"><a class="text-brand underline" target="_blank" href="<?=e($orderUrl)?>">View</a></td>
        </tr>
        <?php endforeach; ?>
        <tr class="border-t bg-slate-50 font-semibold">
          <td class="py-2 px-3" colspan="6">Total (Filtered)</td>
          <td class="px-3">$<?=number_format($total,2)?></td>
          <td class="px-3" colspan="4"></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__.'/_footer.php'; ?>
