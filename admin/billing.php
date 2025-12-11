<?php
// /public/admin/billing.php — Billing with View links + Order PDF + new revenue model (PHP 7+)
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/revenue_calculator.php';
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
// NOTE: patches_per_week_text moved to revenue_calculator.php for unified calculations

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
    return '/uploads' . $suffix;
  }
  if (preg_match('~/(public/)?uploads/.*$~', $abs, $m)) return '/' . ltrim($m[0], '/');
  return '/uploads/' . basename($abs);
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
$from = isset($_GET['from']) ? trim($_GET['from']) : date('Y-m-d', strtotime('-6 months'));
$to   = isset($_GET['to'])   ? trim($_GET['to'])   : date('Y-m-d');
$phys = isset($_GET['phys']) ? trim($_GET['phys']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$productFilter = isset($_GET['product_id']) ? trim($_GET['product_id']) : '';
$cptFilter = isset($_GET['cpt_code']) ? trim($_GET['cpt_code']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$archiveFilter = isset($_GET['archive']) ? trim($_GET['archive']) : 'billable'; // billable (default), archived, all

// Check if soft delete columns exist
$hasOrderDeletedAt = has_column($pdo, 'orders', 'deleted_at');
$hasPatientDeletedAt = has_column($pdo, 'patients', 'deleted_at');

// Check if review_status column exists (match revenue_calculator logic)
$hasReviewStatus = has_column($pdo, 'orders', 'review_status');

$where  = "o.created_at BETWEEN :from AND (:to::date + INTERVAL '1 day') AND o.status NOT IN ('rejected','cancelled','draft')";
$params = ['from'=>$from, 'to'=>$to];

// Also exclude draft review_status if column exists (match revenue_calculator logic)
if ($hasReviewStatus) {
  $where .= " AND (o.review_status IS NULL OR o.review_status != 'draft')";
}

// Archive filter - exclude soft-deleted orders and patients by default
if ($archiveFilter === 'billable') {
  // Show only non-deleted orders with non-deleted patients (default)
  if ($hasOrderDeletedAt) {
    $where .= " AND o.deleted_at IS NULL";
  }
  if ($hasPatientDeletedAt) {
    $where .= " AND p.deleted_at IS NULL";
  }
} elseif ($archiveFilter === 'archived') {
  // Show only deleted orders OR orders with deleted patients
  $archiveConditions = [];
  if ($hasOrderDeletedAt) {
    $archiveConditions[] = "o.deleted_at IS NOT NULL";
  }
  if ($hasPatientDeletedAt) {
    $archiveConditions[] = "p.deleted_at IS NOT NULL";
  }
  if (!empty($archiveConditions)) {
    $where .= " AND (" . implode(" OR ", $archiveConditions) . ")";
  }
}
// 'all' shows everything without archive filtering

if ($phys !== '') {
  $where .= " AND o.user_id = :phys";
  $params['phys'] = $phys;
}

if ($search !== '') {
  $where .= " AND (p.first_name ILIKE :search OR p.last_name ILIKE :search OR o.id ILIKE :search_id)";
  $params['search'] = '%' . $search . '%';
  $params['search_id'] = '%' . $search . '%';
}

if ($productFilter !== '') {
  $where .= " AND o.product_id = :product_id";
  $params['product_id'] = $productFilter;
}

if ($statusFilter !== '') {
  $where .= " AND o.status = :status";
  $params['status'] = $statusFilter;
}

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

// Get products list for filter dropdown
$products = [];
if ($hasProducts) {
  try {
    $products = $pdo->query("SELECT id, name, size FROM products WHERE active=TRUE ORDER BY name, size")->fetchAll();
  } catch (Throwable $e) {
    error_log("[products] " . $e->getMessage());
  }
}

// Get physician list for filter dropdown
$physicians = [];
try {
  if ($adminRole === 'superadmin' || $adminRole === 'manufacturer') {
    $stmt = $pdo->query("SELECT id, first_name, last_name, practice_name FROM users WHERE role IN ('physician', 'practice_admin') ORDER BY first_name, last_name");
    $physicians = $stmt->fetchAll();
  } else {
    $stmt = $pdo->prepare("
      SELECT u.id, u.first_name, u.last_name, u.practice_name
      FROM users u
      INNER JOIN admin_physicians ap ON ap.physician_user_id = u.id
      WHERE ap.admin_id = ?
      ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$adminId]);
    $physicians = $stmt->fetchAll();
  }
} catch (Throwable $e) {
  error_log("[physicians-list] " . $e->getMessage());
}

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

  // Add CPT code filter if products table exists
  if ($cptFilter !== '' && $hasProducts) {
    $where .= " AND pr.$hcpcsCol ILIKE :cpt_code";
    $params['cpt_code'] = '%' . $cptFilter . '%';
  }

  // Group multi-product orders together by order_group_id
  // For orders without a group, treat each as its own group
  $sql = "
    WITH grouped_orders AS (
      SELECT
        o.id,
        o.user_id,
        o.product_id,
        o.patient_id,
        o.created_at,
        o.frequency,
        o.frequency_per_week,
        o.qty_per_change,
        o.duration_days,
        o.refills_allowed,
        ".($hasShipRem?"o.shipments_remaining,":"")."
        o.product_price,
        o.rx_note_name AS tracking,
        o.rx_note_mime AS carrier,
        o.insurer_name,
        o.member_id,
        o.group_id,
        o.payer_phone,
        o.rx_note_path,
        o.product_type,
        o.order_group_id,
        o.billed_by,
        o.wounds_data,
        pp.custom_price AS practice_custom_price,
        p.ins_card_path,
        p.id_card_path,
        p.notes_path,
        p.first_name,
        p.last_name,
        p.dob
        ".($hasProducts?", pr.name AS prod_name, pr.size AS prod_size, pr.sku, COALESCE(pr.$hcpcsCol, o.cpt) AS cpt_code, pr.price_admin, pr.pieces_per_box, pr.price_wholesale, o.product":"")."
      FROM orders o
      LEFT JOIN patients p ON p.id = o.patient_id
      ".($hasProducts?"LEFT JOIN products pr ON pr.id = o.product_id":"")."
      LEFT JOIN practice_pricing pp ON pp.user_id = o.user_id AND pp.product_id = o.product_id
      WHERE $where
    )
    SELECT
      -- Use the primary order ID as the main ID (or first order in group)
      MIN(id) as id,
      user_id,
      patient_id,
      created_at,
      frequency,
      frequency_per_week,
      qty_per_change,
      duration_days,
      refills_allowed,
      ".($hasShipRem?"MAX(shipments_remaining) as shipments_remaining,":"")."
      SUM(product_price) as product_price,
      MAX(tracking) as tracking,
      MAX(carrier) as carrier,
      insurer_name,
      member_id,
      group_id,
      payer_phone,
      MAX(rx_note_path) as rx_note_path,
      MAX(ins_card_path) as ins_card_path,
      MAX(id_card_path) as id_card_path,
      MAX(notes_path) as notes_path,
      MAX(billed_by) as billed_by,
      MAX(wounds_data) as wounds_data,
      MAX(practice_custom_price) as practice_custom_price,
      first_name,
      last_name,
      dob,
      -- Aggregate product information
      ".($hasProducts?"STRING_AGG(DISTINCT prod_name || COALESCE(' ' || prod_size, ''), ', ' ORDER BY prod_name || COALESCE(' ' || prod_size, '')) as prod_name,
      STRING_AGG(DISTINCT sku, ', ' ORDER BY sku) as sku,
      STRING_AGG(DISTINCT cpt_code, ', ' ORDER BY cpt_code) as cpt_code,
      AVG(price_admin) as price_admin,
      AVG(pieces_per_box) as pieces_per_box,
      AVG(price_wholesale) as price_wholesale,
      STRING_AGG(DISTINCT product, ', ' ORDER BY product) as product":"'1' as prod_name, '' as sku, '' as cpt_code, 0 as price_admin, 10 as pieces_per_box, 0 as price_wholesale, '' as product")."
    FROM grouped_orders
    GROUP BY
      COALESCE(order_group_id, id::text),
      user_id,
      patient_id,
      created_at,
      frequency,
      frequency_per_week,
      qty_per_change,
      duration_days,
      refills_allowed,
      insurer_name,
      member_id,
      group_id,
      payer_phone,
      first_name,
      last_name,
      dob
    ORDER BY created_at DESC
  ";
  error_log("[billing-debug] Full SQL: " . $sql);
  $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
  error_log("[billing-debug] Found " . count($rows) . " rows after query");
} catch (Throwable $e) {
  error_log("[billing-data] ERROR: ".$e->getMessage());
  error_log("[billing-data] Stack trace: ".$e->getTraceAsString());
  $rows = [];
}

/* CPT rate map - use SHARED function from revenue_calculator.php */
$rates = load_reimbursement_rates($pdo);

/**
 * Get product count and revenue using the SHARED revenue calculator
 * This ensures billing page uses the exact same calculation as dashboard and revenue report
 */
function get_billing_calculation($row, $rates, $hasProducts) {
  // Map billing row fields to the format expected by calculate_order_revenue
  $order = [
    'billed_by' => $row['billed_by'] ?? 'collagen_direct',
    'pieces_per_box' => $row['pieces_per_box'] ?? 10,
    'cost_per_box' => $row['cost_per_box'] ?? 0,
    'qty_per_change' => $row['qty_per_change'] ?? 1,
    'product_price' => $row['product_price'] ?? 0,
    'practice_custom_price' => $row['practice_custom_price'] ?? 0,
    'price_wholesale' => $row['price_wholesale'] ?? 0,
    'frequency_per_week' => $row['frequency_per_week'] ?? 0,
    'frequency' => $row['frequency'] ?? '',
    'duration_days' => $row['duration_days'] ?? 0,
    'refills_allowed' => $row['refills_allowed'] ?? 0,
    'wounds_data' => $row['wounds_data'] ?? '',
    'cpt_code' => $row['cpt_code'] ?? '',
    'cpt' => $row['cpt'] ?? '',
    'hcpcs_code' => $row['hcpcs_code'] ?? '',
  ];

  // Use the SHARED calculate_order_revenue function from revenue_calculator.php
  $calc = calculate_order_revenue($order, $rates, false);

  // Get product name for display
  $prodName = ($hasProducts && !empty($row['prod_name'])) ? $row['prod_name'] : ($row['product'] ?? 'collagen');

  return [
    'boxes' => $calc['boxes'],
    'actual_pieces' => $calc['pieces'],
    'is_wholesale' => $calc['is_wholesale'],
    'product_name' => $prodName,
    'formatted' => $calc['boxes'] . ' box' . ($calc['boxes'] !== 1 ? 'es' : '') . ' of ' . $prodName,
    'revenue' => $calc['revenue'],
    'cost' => $calc['cost'],
    'profit' => $calc['profit'],
    'cpt_rate' => $calc['cpt_rate']
  ];
}

/* ================= View ================= */
include __DIR__.'/_header.php';
?>
<div>
  <h2 class="text-lg font-semibold mb-4">Billing</h2>

  <!-- Enhanced Filter Form -->
  <div class="bg-white border rounded-lg p-4 mb-4 shadow-sm">
    <form method="get" action="" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
      <div>
        <label class="text-xs text-slate-500 mb-1 block">Search</label>
        <input type="text" name="search" value="<?=e($search)?>" placeholder="Patient name or Order ID" class="w-full border rounded px-3 py-1.5 text-sm">
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Date From</label>
        <input type="date" name="from" value="<?=e($from)?>" class="w-full border rounded px-3 py-1.5 text-sm">
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Date To</label>
        <input type="date" name="to" value="<?=e($to)?>" class="w-full border rounded px-3 py-1.5 text-sm">
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Physician</label>
        <select name="phys" class="w-full border rounded px-3 py-1.5 text-sm">
          <option value="">All Physicians</option>
          <?php foreach ($physicians as $p): ?>
            <option value="<?=e($p['id'])?>" <?=$phys===(string)$p['id']?'selected':''?>>
              <?=e($p['first_name'] . ' ' . $p['last_name'])?><?=$p['practice_name']?' ('.e($p['practice_name']).')':''?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($hasProducts): ?>
      <div>
        <label class="text-xs text-slate-500 mb-1 block">Product</label>
        <select name="product_id" class="w-full border rounded px-3 py-1.5 text-sm">
          <option value="">All Products</option>
          <?php foreach ($products as $pr): ?>
            <option value="<?=$pr['id']?>" <?=$productFilter===$pr['id']?'selected':''?>>
              <?=e($pr['name'].($pr['size']?' ('.$pr['size'].')':''))?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">CPT/HCPCS Code</label>
        <input type="text" name="cpt_code" value="<?=e($cptFilter)?>" placeholder="Code" class="w-full border rounded px-3 py-1.5 text-sm">
      </div>
      <?php endif; ?>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Status</label>
        <select name="status" class="w-full border rounded px-3 py-1.5 text-sm">
          <option value="">Active Orders</option>
          <option value="pending" <?=$statusFilter==='pending'?'selected':''?>>Pending</option>
          <option value="approved" <?=$statusFilter==='approved'?'selected':''?>>Approved</option>
          <option value="in_transit" <?=$statusFilter==='in_transit'?'selected':''?>>In Transit</option>
          <option value="delivered" <?=$statusFilter==='delivered'?'selected':''?>>Delivered</option>
        </select>
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">View</label>
        <select name="archive" class="w-full border rounded px-3 py-1.5 text-sm">
          <option value="billable" <?=$archiveFilter==='billable'?'selected':''?>>Billable Only</option>
          <option value="archived" <?=$archiveFilter==='archived'?'selected':''?>>Archived Only</option>
          <option value="all" <?=$archiveFilter==='all'?'selected':''?>>All Orders</option>
        </select>
      </div>

      <div class="flex items-end gap-2 <?=$hasProducts?'':'md:col-span-3 lg:col-span-2'?>">
        <button type="submit" class="px-4 py-1.5 bg-brand text-white rounded text-sm hover:bg-brand/90 transition-colors">
          Apply Filters
        </button>
        <?php if ($search || $phys || $productFilter || $cptFilter || $statusFilter || $archiveFilter !== 'billable' || $from !== date('Y-m-d', strtotime('-6 months')) || $to !== date('Y-m-d')): ?>
          <a href="/admin/billing.php" class="px-4 py-1.5 bg-slate-100 text-slate-700 rounded text-sm hover:bg-slate-200 transition-colors">
            Clear
          </a>
        <?php endif; ?>
      </div>
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
            <th class="py-2">Product Count</th>
            <th class="py-2">CPT</th>
            <th class="py-2">Revenue</th>
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

          // Get product count (boxes) and revenue using SHARED calculator
          // This ensures billing uses the EXACT same calculation as dashboard and revenue report
          $billingCalc = get_billing_calculation($row, $rates, $hasProducts);
          $productCount = $billingCalc; // backwards compatible - has 'boxes', 'actual_pieces', etc.
          $rev = $billingCalc['revenue'];
          $total += $rev;

          $pid      = (string)($row['patient_id'] ?? '');
          $oid      = (string)($row['id'] ?? '');
          $fullname = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));
          // Use document paths from database instead of filesystem scan
          // Use rx_note_path from orders table (visit note for this specific order)
          $noteLinks = !empty($row['rx_note_path']) ? [$row['rx_note_path']] : [];
          $idLinks   = !empty($row['id_card_path']) ? [$row['id_card_path']] : [];
          $insLinks  = !empty($row['ins_card_path']) ? [$row['ins_card_path']] : [];

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
              // Try wounds_data JSON if frequency_per_week is 0
              if ($fpwDisp <= 0 && !empty($row['wounds_data'])) {
                $wd = json_decode($row['wounds_data'], true);
                if (is_array($wd) && isset($wd[0]['frequency_per_week'])) {
                  $fpwDisp = (int)$wd[0]['frequency_per_week'];
                }
              }
              // Fallback to text frequency field
              if ($fpwDisp <= 0) $fpwDisp = patches_per_week_text(isset($row['frequency'])?$row['frequency']:null);
              if ($fpwDisp === 0) $fpwDisp = 1;
              echo e($fpwDisp.'×/week');
            ?>
          </td>
          <td class="py-2">
            <span class="font-medium"><?= e((string)$productCount['boxes']) ?> box<?= $productCount['boxes'] !== 1 ? 'es' : '' ?></span>
            <br><span class="text-[11px] text-slate-500"><?= e((string)$productCount['product_name']) ?></span>
          </td>
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
