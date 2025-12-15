<?php
// /public/admin/billing.php — Billing with View links + Order PDF + new revenue model (PHP 7+)
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/revenue_calculator.php';
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
$auth      = __DIR__ . '/auth.php';      if (is_file($auth)) require_once $auth;
if (function_exists('require_admin')) require_admin();

// Sales reps cannot access billing
if (function_exists('deny_sales_rep')) deny_sales_rep();

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
// Helper to render a view link for uploaded documents
function render_doc_link($path, $empty = '—') {
  if (!$path) return '<span class="text-slate-400">' . $empty . '</span>';
  $csrf = $_SESSION['csrf'] ?? '';
  $url = '/admin/file.dl.php?p=' . rawurlencode($path) . '&mode=view&csrf=' . rawurlencode($csrf);
  return '<a class="text-brand underline text-xs" target="_blank" href="' . e($url) . '">View</a>';
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
// Default to YTD to match revenue report
$from = isset($_GET['from']) ? trim($_GET['from']) : date('Y-01-01');
$to   = isset($_GET['to'])   ? trim($_GET['to'])   : date('Y-m-d');
$phys = isset($_GET['phys']) ? trim($_GET['phys']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$productFilter = isset($_GET['product_id']) ? trim($_GET['product_id']) : '';
$cptFilter = isset($_GET['cpt_code']) ? trim($_GET['cpt_code']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$archiveFilter = isset($_GET['archive']) ? trim($_GET['archive']) : 'billable'; // billable (default), archived, all

$hasProducts = has_table($pdo,'products');

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

/* ================= Data - Use SHARED get_revenue_metrics() ================= */
// This ensures billing uses the EXACT same data source as dashboard and revenue report
try {
  error_log("[billing] Using get_revenue_metrics() with date range: $from to $to, physician: " . ($phys ?: 'ALL'));
  $metrics = get_revenue_metrics($pdo, $from, $to, $phys ?: null, null);
  $rows = $metrics['orders']; // Get the detailed orders array
  error_log("[billing] get_revenue_metrics returned " . count($rows) . " orders");

  // Apply client-side filters that get_revenue_metrics doesn't support
  if ($search !== '') {
    $searchLower = strtolower($search);
    $rows = array_filter($rows, function($row) use ($searchLower) {
      $patientName = strtolower(trim(($row['patient_first'] ?? '') . ' ' . ($row['patient_last'] ?? '')));
      $orderId = strtolower($row['id'] ?? '');
      return strpos($patientName, $searchLower) !== false || strpos($orderId, $searchLower) !== false;
    });
  }

  if ($productFilter !== '') {
    $rows = array_filter($rows, fn($row) => ($row['product_id'] ?? '') === $productFilter);
  }

  if ($cptFilter !== '') {
    $cptFilterLower = strtolower($cptFilter);
    $rows = array_filter($rows, function($row) use ($cptFilterLower) {
      $cpt = strtolower($row['cpt_code'] ?? '');
      return strpos($cpt, $cptFilterLower) !== false;
    });
  }

  if ($statusFilter !== '') {
    $rows = array_filter($rows, fn($row) => ($row['status'] ?? '') === $statusFilter);
  }

  // Re-index array after filtering
  $rows = array_values($rows);

} catch (Throwable $e) {
  error_log("[billing] ERROR: " . $e->getMessage());
  error_log("[billing] Stack trace: " . $e->getTraceAsString());
  $rows = [];
}

/* CPT rate map - use SHARED function from revenue_calculator.php */
$rates = load_reimbursement_rates($pdo);

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

  <?php
  // Group orders by order_group_id (multi-product orders) or by patient+date (single orders)
  // This allows billing team to see all line items for a patient order together
  $groupedOrders = [];
  foreach ($rows as $row) {
    // Use order_group_id if available, otherwise create a group key from patient_id + date
    $groupKey = $row['order_group_id'] ?? null;
    if (empty($groupKey)) {
      // For single-product orders without a group, use patient_id + date as the grouping key
      $orderDate = date('Y-m-d', strtotime($row['created_at']));
      $groupKey = 'single_' . ($row['patient_id'] ?? 'unknown') . '_' . $orderDate . '_' . $row['id'];
    }
    if (!isset($groupedOrders[$groupKey])) {
      $groupedOrders[$groupKey] = [
        'group_id' => $groupKey,
        'patient_id' => $row['patient_id'] ?? '',
        'patient_name' => trim(($row['patient_first'] ?? '') . ' ' . ($row['patient_last'] ?? '')),
        'practice_name' => $row['practice_name'] ?? '',
        'insurer_name' => $row['insurer_name'] ?: ($row['insurance_provider'] ?? ''),
        'created_at' => $row['created_at'],
        'order_type' => $row['order_type'] ?? 'Referral',
        'items' => [],
        'total_revenue' => 0,
        'total_boxes' => 0,
        'statuses' => [],
        'primary_order_id' => $row['id'], // First order ID in group (for PDF link)
        // Patient document paths (from patients table)
        'id_card_path' => $row['id_card_path'] ?? null,
        'ins_card_path' => $row['ins_card_path'] ?? null,
        'rx_note_path' => $row['rx_note_path'] ?? null,
      ];
    }
    $groupedOrders[$groupKey]['items'][] = $row;
    $groupedOrders[$groupKey]['total_revenue'] += (float)($row['calculated_revenue'] ?? 0);
    $groupedOrders[$groupKey]['total_boxes'] += (int)($row['calculated_boxes'] ?? 1);
    $groupedOrders[$groupKey]['statuses'][$row['status'] ?? 'unknown'] = true;
  }
  ?>

  <section class="card p-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b bg-slate-50">
          <tr class="text-left text-xs text-slate-600">
            <th class="py-2 px-2">Patient</th>
            <th class="py-2 px-2">Order</th>
            <th class="py-2 px-2">Products</th>
            <th class="py-2 px-2">Total Boxes</th>
            <th class="py-2 px-2">Order Total</th>
            <th class="py-2 px-2">Practice</th>
            <th class="py-2 px-2">Insurance</th>
            <th class="py-2 px-2">ID Card</th>
            <th class="py-2 px-2">Ins. Card</th>
            <th class="py-2 px-2">Visit Note</th>
            <th class="py-2 px-2">Status</th>
            <th class="py-2 px-2">Order PDF</th>
            <?php if ($adminRole === 'manufacturer'): ?>
            <th class="py-2 px-2">Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
      <tbody>
        <?php $total=0.0; foreach($groupedOrders as $group):
          $total += $group['total_revenue'];
          $itemCount = count($group['items']);
          $orderUrl = '/admin/order.pdf.php?id=' . rawurlencode($group['primary_order_id']) . '&csrf=' . rawurlencode($_SESSION['csrf'] ?? '');
          $downloadAllUrl = '/admin/download-all.php?id=' . rawurlencode($group['primary_order_id']) . '&csrf=' . rawurlencode($_SESSION['csrf'] ?? '');

          // Determine display order number - use order_group_id if it's a real group, otherwise short UUID
          $displayOrderNum = str_starts_with($group['group_id'], 'single_')
            ? substr($group['primary_order_id'], 0, 8)
            : substr($group['group_id'], 0, 8);

          // Combine statuses for display
          $statusList = array_keys($group['statuses']);
          $statusDisplay = count($statusList) === 1 ? $statusList[0] : implode(', ', $statusList);
        ?>
        <!-- Order Group Header Row -->
        <tr class="border-t bg-slate-50/50 cursor-pointer hover:bg-slate-100" onclick="toggleOrderDetails('<?=e($group['group_id'])?>')">
          <td class="py-2 px-2">
            <strong><?=e($group['patient_name'] ?: '—')?></strong><br>
            <span class="text-[11px] text-slate-500"><?=e($group['order_type'])?></span>
          </td>
          <td class="py-2 px-2">
            <span class="font-medium">#<?=e($displayOrderNum)?></span><br>
            <span class="text-xs text-slate-500"><?=e(date('Y-m-d', strtotime($group['created_at'])))?></span>
          </td>
          <td class="py-2 px-2">
            <?php if ($itemCount === 1): ?>
              <?=e($group['items'][0]['product_name'] ?? 'Unknown')?>
              <span class="text-xs text-slate-500">(<?=e($group['items'][0]['cpt_code'] ?? '—')?>)</span>
            <?php else: ?>
              <span class="text-brand font-medium"><?=$itemCount?> products</span>
              <svg class="inline-block ml-1 w-4 h-4 text-slate-400 transition-transform" id="arrow-<?=e($group['group_id'])?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
              </svg>
            <?php endif; ?>
          </td>
          <td class="py-2 px-2">
            <span class="font-medium"><?= e((string)$group['total_boxes']) ?> box<?= $group['total_boxes'] !== 1 ? 'es' : '' ?></span>
          </td>
          <td class="py-2 px-2 font-semibold">$<?=number_format($group['total_revenue'],2)?></td>
          <td class="py-2 px-2"><?=e($group['practice_name'] ?: '—')?></td>
          <td class="py-2 px-2"><?=e($group['insurer_name'] ?: '—')?></td>
          <td class="py-2 px-2"><?=render_doc_link($group['id_card_path'])?></td>
          <td class="py-2 px-2"><?=render_doc_link($group['ins_card_path'])?></td>
          <td class="py-2 px-2"><?=render_doc_link($group['rx_note_path'])?></td>
          <td class="py-2 px-2"><?=e($statusDisplay)?></td>
          <td class="py-2 px-2"><a class="text-brand underline" target="_blank" href="<?=e($orderUrl)?>" onclick="event.stopPropagation()">View</a></td>
          <?php if ($adminRole === 'manufacturer'): ?>
          <td class="py-2 px-2">
            <a class="btn btn-primary text-xs" href="<?=e($downloadAllUrl)?>" title="Download all documents as ZIP" onclick="event.stopPropagation()">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
              </svg>
              All
            </a>
          </td>
          <?php endif; ?>
        </tr>
        <?php if ($itemCount > 1): ?>
        <!-- Expandable Line Items (hidden by default) -->
        <?php foreach ($group['items'] as $idx => $row):
          $rev = (float)($row['calculated_revenue'] ?? 0);
          $boxes = (int)($row['calculated_boxes'] ?? 1);
          $fpwDisp = (int)($row['frequency_per_week'] ?? 0);
          if ($fpwDisp <= 0 && !empty($row['wounds_data'])) {
            $wd = json_decode($row['wounds_data'], true);
            if (is_array($wd) && isset($wd[0]['frequency_per_week'])) {
              $fpwDisp = (int)$wd[0]['frequency_per_week'];
            }
          }
          if ($fpwDisp === 0) $fpwDisp = 1;
        ?>
        <tr class="border-t border-slate-100 bg-white hidden" data-order-group="<?=e($group['group_id'])?>">
          <td class="py-1.5 px-2 pl-6 text-slate-500 text-xs">└</td>
          <td class="py-1.5 px-2 text-xs text-slate-500"><?=e(substr($row['id'], 0, 8))?></td>
          <td class="py-1.5 px-2">
            <?=e($row['product_name'] ?? 'Unknown')?>
            <span class="text-xs text-slate-500">(<?=e($row['cpt_code'] ?? '—')?>)</span>
          </td>
          <td class="py-1.5 px-2 text-sm"><?=$boxes?> box<?=$boxes !== 1 ? 'es' : ''?> <span class="text-slate-400">@ <?=$fpwDisp?>×/wk</span></td>
          <td class="py-1.5 px-2 text-sm">$<?=number_format($rev,2)?></td>
          <td class="py-1.5 px-2" colspan="<?=$adminRole==='manufacturer'?'8':'7'?>"></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php endforeach; ?>
        <tr class="border-t font-semibold" style="background: #f9fafb;">
          <td class="py-2" colspan="4">Total (<?=count($groupedOrders)?> orders, <?=count($rows)?> line items)</td>
          <td class="py-2">$<?=number_format($total,2)?></td>
          <td class="py-2" colspan="<?=$adminRole==='manufacturer'?'8':'7'?>"></td>
        </tr>
      </tbody>
    </table>
    </div>
  </section>

  <script>
  function toggleOrderDetails(groupId) {
    const rows = document.querySelectorAll(`tr[data-order-group="${groupId}"]`);
    const arrow = document.getElementById(`arrow-${groupId}`);
    rows.forEach(row => {
      row.classList.toggle('hidden');
    });
    if (arrow) {
      arrow.classList.toggle('rotate-180');
    }
  }
  </script>
</div>
<?php include __DIR__.'/_footer.php'; ?>
