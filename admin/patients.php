<?php
// admin/patients.php - Patient management with role-based access
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
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
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
    $suffix = substr($abs, strlen($root));
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

/* ================= POST Actions ================= */
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];

  if ($action === 'update_patient_status') {
    $patientId = $_POST['patient_id'] ?? '';
    $newStatus = $_POST['status'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    // Only superadmin and manufacturer can update patient status
    if ($adminRole === 'superadmin' || $adminRole === 'manufacturer') {
      $validStatuses = ['pending', 'approved', 'not_covered', 'need_info', 'active', 'inactive'];

      if (in_array($newStatus, $validStatuses) && $patientId) {
        $stmt = $pdo->prepare("
          UPDATE patients
          SET state = ?,
              status_comment = ?,
              status_updated_at = NOW(),
              status_updated_by = ?,
              updated_at = NOW()
          WHERE id = ?
        ");

        if ($stmt->execute([$newStatus, $comment, $adminId, $patientId])) {
          // If patient is rejected (not_covered), auto-reject all their pending/approved orders
          if ($newStatus === 'not_covered') {
            $rejectOrders = $pdo->prepare("
              UPDATE orders
              SET status = 'rejected',
                  updated_at = NOW()
              WHERE patient_id = ?
                AND status IN ('pending', 'approved')
            ");
            $rejectOrders->execute([$patientId]);
            $rejectedCount = $rejectOrders->rowCount();

            if ($rejectedCount > 0) {
              $msg = "Patient status updated to 'Not Covered'. {$rejectedCount} order(s) automatically rejected.";
            } else {
              $msg = "Patient status updated successfully";
            }
          } else {
            $msg = "Patient status updated successfully";
          }
          $msgType = 'success';
        } else {
          $msg = "Failed to update patient status";
          $msgType = 'error';
        }
      } else {
        $msg = "Invalid status or patient ID";
        $msgType = 'error';
      }
    } else {
      $msg = "Unauthorized: Only superadmin and manufacturer can update patient status";
      $msgType = 'error';
    }
  }
}

/* ================= Filters ================= */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$phys = isset($_GET['phys']) ? $_GET['phys'] : '';
$insProvider = isset($_GET['ins_provider']) ? trim($_GET['ins_provider']) : '';
$hasOrders = isset($_GET['has_orders']) ? $_GET['has_orders'] : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

/* ================= Data ================= */
try {
  $where = "1=1";
  $params = [];

  // Role-based access control
  if ($adminRole === 'superadmin' || $adminRole === 'manufacturer' || $adminRole === 'admin') {
    // Superadmin, admin, and manufacturer see all patients - no additional filter
  } else {
    // Sales, ops, and employees only see patients from assigned physicians
    $where .= " AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = :admin_id AND ap.physician_user_id = p.user_id)";
    $params['admin_id'] = $adminId;
  }

  // Search filter
  if ($search !== '') {
    $where .= " AND (p.first_name ILIKE :search OR p.last_name ILIKE :search OR p.email ILIKE :search OR p.phone ILIKE :search OR p.id ILIKE :search)";
    $params['search'] = '%' . $search . '%';
  }

  // Status filter (patients table uses 'state' not 'status')
  if ($status !== 'all' && $status !== '') {
    $where .= " AND p.state = :status";
    $params['status'] = $status;
  }

  // Physician filter
  if ($phys !== '') {
    $where .= " AND p.user_id = :phys";
    $params['phys'] = $phys;
  }

  // Insurance provider filter
  if ($insProvider !== '') {
    $where .= " AND p.insurance_provider ILIKE :ins_provider";
    $params['ins_provider'] = '%' . $insProvider . '%';
  }

  // Date range filters
  if ($dateFrom !== '') {
    $where .= " AND p.created_at >= :date_from";
    $params['date_from'] = $dateFrom . ' 00:00:00';
  }

  if ($dateTo !== '') {
    $where .= " AND p.created_at <= :date_to";
    $params['date_to'] = $dateTo . ' 23:59:59';
  }

  // Build HAVING clause for order filter
  $having = '';
  if ($hasOrders === 'yes') {
    $having = 'HAVING COUNT(DISTINCT o.id) > 0';
  } elseif ($hasOrders === 'no') {
    $having = 'HAVING COUNT(DISTINCT o.id) = 0';
  }

  // Check if new columns exist
  $hasProviderResponse = false;
  $hasReadTracking = false;
  try {
    $cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'patients' AND column_name IN ('provider_response', 'provider_response_at', 'admin_response_read_at', 'provider_comment_read_at')")->fetchAll(PDO::FETCH_COLUMN);
    $hasProviderResponse = in_array('provider_response', $cols) && in_array('provider_response_at', $cols);
    $hasReadTracking = in_array('admin_response_read_at', $cols);
  } catch (Throwable $e) {
    error_log("Could not check for new columns: " . $e->getMessage());
  }

  $providerResponseCols = $hasProviderResponse ? "p.provider_response, p.provider_response_at," : "NULL as provider_response, NULL as provider_response_at,";
  $readTrackingCol = $hasReadTracking ? "p.admin_response_read_at," : "NULL as admin_response_read_at,";

  $hasUnreadCalc = $hasProviderResponse && $hasReadTracking ?
    "CASE
      WHEN p.provider_response IS NOT NULL
        AND p.provider_response != ''
        AND (p.admin_response_read_at IS NULL OR p.admin_response_read_at < p.provider_response_at)
      THEN TRUE
      ELSE FALSE
    END" : "FALSE";

  $providerResponseGroup = $hasProviderResponse ? "p.provider_response, p.provider_response_at," : "";
  $readTrackingGroup = $hasReadTracking ? "p.admin_response_read_at," : "";

  $sql = "
    SELECT
      p.id, p.user_id, p.first_name, p.last_name, p.email, p.phone, p.dob,
      p.state, p.created_at,
      p.notes_path, p.ins_card_path, p.id_card_path,
      p.insurance_provider, p.insurance_member_id, p.insurance_group_id, p.insurance_payer_phone,
      p.status_comment, p.status_updated_at, $providerResponseCols $readTrackingCol
      u.first_name AS phys_first, u.last_name AS phys_last, u.practice_name,
      COUNT(DISTINCT o.id) AS order_count,
      MAX(o.created_at) AS last_order_date,
      $hasUnreadCalc as has_unread_response
    FROM patients p
    LEFT JOIN users u ON u.id = p.user_id
    LEFT JOIN orders o ON o.patient_id = p.id AND o.status NOT IN ('rejected','cancelled')
    WHERE $where
    GROUP BY p.id, p.user_id, p.first_name, p.last_name, p.email, p.phone, p.dob,
             p.state, p.created_at, p.notes_path, p.ins_card_path, p.id_card_path,
             p.insurance_provider, p.insurance_member_id, p.insurance_group_id, p.insurance_payer_phone,
             p.status_comment, p.status_updated_at, $providerResponseGroup $readTrackingGroup
             u.first_name, u.last_name, u.practice_name
    $having
    ORDER BY p.created_at DESC
  ";
  error_log("[patients-debug] Admin Role: " . ($adminRole ?: 'NONE'));
  error_log("[patients-debug] Admin ID: " . ($adminId ?: 'NONE'));
  error_log("[patients-debug] WHERE clause: " . $where);
  error_log("[patients-debug] Params: " . json_encode($params));
  error_log("[patients-debug] Full SQL: " . $sql);

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();

  error_log("[patients-debug] Found " . count($rows) . " rows");
} catch (Throwable $e) {
  error_log("[patients-data] ERROR: " . $e->getMessage());
  error_log("[patients-data] Stack trace: " . $e->getTraceAsString());
  $rows = [];
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

/* ================= View ================= */
include __DIR__.'/_header.php';
?>
<div>
  <?php if ($msg): ?>
    <div class="mb-4 p-4 rounded <?=$msgType==='success'?'bg-green-50 text-green-800 border border-green-200':'bg-red-50 text-red-800 border border-red-200'?>">
      <?=e($msg)?>
    </div>
  <?php endif; ?>

  <h2 class="text-lg font-semibold mb-4">Patients</h2>

  <!-- Enhanced Filter Form -->
  <div class="bg-white border rounded-lg p-4 mb-4 shadow-sm">
    <form method="get" action="" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
      <div>
        <label class="text-xs text-slate-500 mb-1 block">Search</label>
        <input type="text" name="search" value="<?=e($search)?>" placeholder="Name, email, phone, ID" class="w-full border rounded px-3 py-1.5 text-sm">
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Status</label>
        <select name="status" class="w-full border rounded px-3 py-1.5 text-sm">
          <option value="all" <?=$status==='all'?'selected':''?>>All Statuses</option>
          <option value="pending" <?=$status==='pending'?'selected':''?>>Pending</option>
          <option value="approved" <?=$status==='approved'?'selected':''?>>Approved</option>
          <option value="not_covered" <?=$status==='not_covered'?'selected':''?>>Not Covered</option>
          <option value="need_info" <?=$status==='need_info'?'selected':''?>>Need Info</option>
          <option value="active" <?=$status==='active'?'selected':''?>>Active</option>
          <option value="inactive" <?=$status==='inactive'?'selected':''?>>Inactive</option>
        </select>
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Physician</label>
        <select name="phys" class="w-full border rounded px-3 py-1.5 text-sm">
          <option value="">All Physicians</option>
          <?php foreach ($physicians as $p): ?>
            <option value="<?=e($p['id'])?>" <?=$phys==(string)$p['id']?'selected':''?>>
              <?=e($p['first_name'] . ' ' . $p['last_name'])?><?=$p['practice_name']?' ('.e($p['practice_name']).')':''?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Insurance Provider</label>
        <input type="text" name="ins_provider" value="<?=e($insProvider)?>" placeholder="Provider name" class="w-full border rounded px-3 py-1.5 text-sm">
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Has Orders</label>
        <select name="has_orders" class="w-full border rounded px-3 py-1.5 text-sm">
          <option value="">All</option>
          <option value="yes" <?=$hasOrders==='yes'?'selected':''?>>Has Orders</option>
          <option value="no" <?=$hasOrders==='no'?'selected':''?>>No Orders</option>
        </select>
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Date From</label>
        <input type="date" name="date_from" value="<?=e($dateFrom)?>" class="w-full border rounded px-3 py-1.5 text-sm">
      </div>

      <div>
        <label class="text-xs text-slate-500 mb-1 block">Date To</label>
        <input type="date" name="date_to" value="<?=e($dateTo)?>" class="w-full border rounded px-3 py-1.5 text-sm">
      </div>

      <div class="flex items-end gap-2 md:col-span-3 lg:col-span-5">
        <button type="submit" class="px-4 py-1.5 bg-brand text-white rounded text-sm hover:bg-brand/90 transition-colors">
          Apply Filters
        </button>
        <?php if ($search || $status !== 'all' || $phys || $insProvider || $hasOrders || $dateFrom || $dateTo): ?>
          <a href="/admin/patients.php" class="px-4 py-1.5 bg-slate-100 text-slate-700 rounded text-sm hover:bg-slate-200 transition-colors">
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
            <th class="py-2">DOB</th>
            <th class="py-2">Physician</th>
            <th class="py-2">Orders</th>
            <th class="py-2">Status</th>
            <th class="py-2">Notes</th>
            <th class="py-2">ID</th>
            <th class="py-2">Insurance Card</th>
            <th class="py-2">Update Status</th>
            <th class="py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="10" class="py-8 text-center text-slate-500">
                <div class="text-center">
                  <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="mx-auto mb-3 opacity-50">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                  </svg>
                  <p class="font-medium">No patients found</p>
                  <p class="text-sm mt-1">Try adjusting your filters</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach($rows as $row):
              $pid = (string)($row['id'] ?? '');
              $fullname = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));
              // Use document paths from database instead of filesystem scan
              $noteLinks = !empty($row['notes_path']) ? [$row['notes_path']] : [];
              $idLinks   = !empty($row['id_card_path']) ? [$row['id_card_path']] : [];
              $insLinks  = !empty($row['ins_card_path']) ? [$row['ins_card_path']] : [];

              $physName = trim(($row['phys_first'] ?? '').' '.($row['phys_last'] ?? ''));
              $practiceName = $row['practice_name'] ?? '';
              $orderCount = (int)($row['order_count'] ?? 0);
              $lastOrder = $row['last_order_date'] ?? null;
              $status = $row['state'] ?? 'pending'; // patients table uses 'state' column

              $statusColors = [
                'active' => 'bg-green-100 text-green-700',
                'pending' => 'bg-yellow-100 text-yellow-700',
                'inactive' => 'bg-slate-100 text-slate-600'
              ];
              $statusColor = $statusColors[$status] ?? 'bg-slate-100 text-slate-600';
            ?>
            <tr class="border-t hover:bg-slate-50" data-patient-id="<?=e($pid)?>">
              <td class="py-2">
                <div class="font-medium"><?=e($fullname ?: '—')?></div>
                <div class="text-[11px] text-slate-500">ID: <?=e($pid)?></div>
              </td>
              <td class="py-2 text-xs"><?=e($row['dob'] ?? '—')?></td>
              <td class="py-2">
                <div class="text-xs"><?=e($physName ?: '—')?></div>
                <?php if ($practiceName): ?>
                  <div class="text-[11px] text-slate-500"><?=e($practiceName)?></div>
                <?php endif; ?>
              </td>
              <td class="py-2">
                <div class="text-xs font-medium"><?=$orderCount?> order<?=$orderCount!==1?'s':''?></div>
                <?php if ($lastOrder): ?>
                  <div class="text-[11px] text-slate-500">Last: <?=e(substr($lastOrder,0,10))?></div>
                <?php endif; ?>
              </td>
              <td class="py-2">
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium <?=$statusColor?>">
                  <?=e(ucfirst($status))?>
                </span>
              </td>
              <td class="py-2"><?=render_view_link($noteLinks)?></td>
              <td class="py-2"><?=render_view_link($idLinks)?></td>
              <td class="py-2"><?=render_view_link($insLinks)?></td>
              <td class="py-2">
                <?php if ($adminRole === 'superadmin' || $adminRole === 'manufacturer'): ?>
                  <button onclick="openStatusDialog('<?=e($pid)?>', '<?=e($status)?>')" class="px-2 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700">
                    Update
                  </button>
                <?php else: ?>
                  <span class="text-slate-400 text-xs">—</span>
                <?php endif; ?>
              </td>
              <td class="py-2">
                <button
                  onclick="togglePatientDetails('<?=e($pid)?>')"
                  class="text-brand underline text-xs hover:text-brand-dark cursor-pointer relative"
                >
                  <?php if (!empty($row['has_unread_response']) && $row['has_unread_response']): ?>
                    <span style="position: absolute; top: -4px; right: -4px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; border: 2px solid white;"></span>
                  <?php endif; ?>
                  <span class="expand-text">View Details</span>
                  <span class="collapse-text hidden">Hide Details</span>
                </button>
              </td>
            </tr>
            <tr id="patient-details-<?=e($pid)?>" class="patient-details-row hidden border-t bg-slate-50">
              <td colspan="10" class="py-4 px-6">
                <!-- View Mode -->
                <div id="view-mode-<?=e($pid)?>" class="view-mode">
                  <div class="flex justify-between items-center mb-4">
                    <h4 class="font-semibold">Patient Details</h4>
                    <div class="flex gap-2">
                      <button onclick="enableEditMode('<?=e($pid)?>')" class="px-3 py-1 bg-brand text-white rounded text-xs hover:bg-brand-dark">
                        Edit Patient
                      </button>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <h4 class="font-semibold mb-3">Patient Information</h4>
                      <div class="space-y-2">
                        <div><span class="text-slate-600">Name:</span> <strong><?=e($fullname)?></strong></div>
                        <div><span class="text-slate-600">Email:</span> <?=e($row['email'] ?? '—')?></div>
                        <div><span class="text-slate-600">Phone:</span> <?=e($row['phone'] ?? '—')?></div>
                        <div><span class="text-slate-600">DOB:</span> <?=e($row['dob'] ?? '—')?></div>
                        <div><span class="text-slate-600">Patient ID:</span> <?=e($pid)?></div>
                      </div>
                    </div>
                    <div>
                      <h4 class="font-semibold mb-3">Provider Information</h4>
                      <div class="space-y-2">
                        <div><span class="text-slate-600">Physician:</span> <?=e($physName ?: '—')?></div>
                        <div><span class="text-slate-600">Practice:</span> <?=e($practiceName ?: '—')?></div>
                        <div><span class="text-slate-600">Status:</span> <span class="<?=$statusColor?> px-2 py-0.5 rounded text-xs"><?=e(ucfirst($status))?></span></div>
                      </div>
                    </div>
                    <div>
                      <h4 class="font-semibold mb-3">Order Summary</h4>
                      <div class="space-y-2">
                        <div><span class="text-slate-600">Total Orders:</span> <?=$orderCount?></div>
                        <?php if ($lastOrder): ?>
                          <div><span class="text-slate-600">Last Order:</span> <?=e(substr($lastOrder,0,10))?></div>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div>
                      <h4 class="font-semibold mb-3">Insurance Information</h4>
                      <div class="space-y-2">
                        <div><span class="text-slate-600">Provider:</span> <?=e($row['insurance_provider'] ?? '—')?></div>
                        <div><span class="text-slate-600">Member ID:</span> <?=e($row['insurance_member_id'] ?? '—')?></div>
                        <div><span class="text-slate-600">Group ID:</span> <?=e($row['insurance_group_id'] ?? '—')?></div>
                        <div><span class="text-slate-600">Payer Phone:</span> <?=e($row['insurance_payer_phone'] ?? '—')?></div>
                      </div>
                    </div>
                    <div>
                      <h4 class="font-semibold mb-3">Documents</h4>
                      <div class="space-y-2">
                        <div><span class="text-slate-600">Notes:</span> <?=render_view_link($noteLinks)?></div>
                        <div><span class="text-slate-600">ID Card:</span> <?=render_view_link($idLinks)?></div>
                        <div><span class="text-slate-600">Insurance:</span> <?=render_view_link($insLinks)?></div>
                      </div>
                    </div>
                  </div>

                  <!-- Communication Thread -->
                  <?php if (!empty($row['status_comment']) || !empty($row['provider_response'])): ?>
                    <div class="mt-6 border-t pt-4">
                      <h4 class="font-semibold mb-3">Communication Thread</h4>

                      <!-- Manufacturer Comment to Provider -->
                      <?php if (!empty($row['status_comment'])): ?>
                        <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
                          <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-blue-900">Manufacturer → Physician</span>
                            <?php if (!empty($row['status_updated_at'])): ?>
                              <span class="text-xs text-blue-700"><?=e(substr($row['status_updated_at'], 0, 16))?></span>
                            <?php endif; ?>
                          </div>
                          <div class="text-sm text-blue-900"><?=nl2br(e($row['status_comment']))?></div>
                        </div>
                      <?php endif; ?>

                      <!-- Provider Response -->
                      <?php if (!empty($row['provider_response'])): ?>
                        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded relative">
                          <?php if (!empty($row['has_unread_response']) && $row['has_unread_response']): ?>
                            <span class="absolute -top-2 -right-2 w-4 h-4 bg-red-500 rounded-full border-2 border-white" title="Unread response"></span>
                          <?php endif; ?>
                          <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-green-900">Physician → Manufacturer</span>
                            <?php if (!empty($row['provider_response_at'])): ?>
                              <span class="text-xs text-green-700"><?=e(substr($row['provider_response_at'], 0, 16))?></span>
                            <?php endif; ?>
                          </div>
                          <div class="text-sm text-green-900"><?=nl2br(e($row['provider_response']))?></div>
                        </div>

                        <!-- Reply Form (for manufacturers) -->
                        <?php if ($adminRole === 'superadmin' || $adminRole === 'manufacturer'): ?>
                          <form id="reply-form-<?=e($pid)?>" onsubmit="sendReply(event, '<?=e($pid)?>')" class="mt-3">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Reply to Physician:</label>
                            <textarea
                              name="reply_message"
                              rows="3"
                              class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-brand focus:border-transparent"
                              placeholder="Type your response here..."
                              required
                            ></textarea>
                            <button
                              type="submit"
                              class="mt-2 px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark transition"
                            >
                              Send Reply
                            </button>
                          </form>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                  </div>
                </div>

                <!-- Edit Mode -->
                <div id="edit-mode-<?=e($pid)?>" class="edit-mode hidden">
                  <div class="flex justify-between items-center mb-4">
                    <h4 class="font-semibold">Edit Patient Information</h4>
                    <button onclick="cancelEditMode('<?=e($pid)?>')" class="px-3 py-1 bg-slate-400 text-white rounded text-xs hover:bg-slate-500">
                      Cancel
                    </button>
                  </div>
                  <form id="edit-form-<?=e($pid)?>" onsubmit="savePatient(event, '<?=e($pid)?>')">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                      <div class="space-y-3">
                        <div>
                          <label class="block text-slate-600 mb-1">First Name</label>
                          <input type="text" name="first_name" value="<?=e($row['first_name'] ?? '')?>" class="w-full px-2 py-1 border rounded" required>
                        </div>
                        <div>
                          <label class="block text-slate-600 mb-1">Last Name</label>
                          <input type="text" name="last_name" value="<?=e($row['last_name'] ?? '')?>" class="w-full px-2 py-1 border rounded" required>
                        </div>
                        <div>
                          <label class="block text-slate-600 mb-1">Email</label>
                          <input type="email" name="email" value="<?=e($row['email'] ?? '')?>" class="w-full px-2 py-1 border rounded">
                        </div>
                        <div>
                          <label class="block text-slate-600 mb-1">Phone</label>
                          <input type="tel" name="phone" value="<?=e($row['phone'] ?? '')?>" class="w-full px-2 py-1 border rounded">
                        </div>
                      </div>
                      <div class="space-y-3">
                        <div>
                          <label class="block text-slate-600 mb-1">Date of Birth</label>
                          <input type="date" name="dob" value="<?=e($row['dob'] ?? '')?>" class="w-full px-2 py-1 border rounded">
                        </div>
                        <div>
                          <label class="block text-slate-600 mb-1">Status</label>
                          <select name="state" class="w-full px-2 py-1 border rounded">
                            <option value="new" <?=$status==='new'?'selected':''?>>New</option>
                            <option value="approved" <?=$status==='approved'?'selected':''?>>Approved</option>
                            <option value="active" <?=$status==='active'?'selected':''?>>Active</option>
                            <option value="no_coverage" <?=$status==='no_coverage'?'selected':''?>>No Coverage</option>
                            <option value="benefits_expired" <?=$status==='benefits_expired'?'selected':''?>>Benefits Expired</option>
                            <option value="pending" <?=$status==='pending'?'selected':''?>>Pending</option>
                            <option value="inactive" <?=$status==='inactive'?'selected':''?>>Inactive</option>
                          </select>
                        </div>
                      </div>
                      <div class="space-y-3">
                        <h4 class="font-semibold mb-2">Insurance Information</h4>
                        <div>
                          <label class="block text-slate-600 mb-1">Insurance Provider</label>
                          <input type="text" name="insurance_provider" value="<?=e($row['insurance_provider'] ?? '')?>" class="w-full px-2 py-1 border rounded" placeholder="e.g. Blue Cross Blue Shield">
                        </div>
                        <div>
                          <label class="block text-slate-600 mb-1">Member ID</label>
                          <input type="text" name="insurance_member_id" value="<?=e($row['insurance_member_id'] ?? '')?>" class="w-full px-2 py-1 border rounded">
                        </div>
                        <div>
                          <label class="block text-slate-600 mb-1">Group ID</label>
                          <input type="text" name="insurance_group_id" value="<?=e($row['insurance_group_id'] ?? '')?>" class="w-full px-2 py-1 border rounded">
                        </div>
                        <div>
                          <label class="block text-slate-600 mb-1">Payer Phone</label>
                          <input type="tel" name="insurance_payer_phone" value="<?=e($row['insurance_payer_phone'] ?? '')?>" class="w-full px-2 py-1 border rounded" placeholder="1-800-XXX-XXXX">
                        </div>
                      </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                      <button type="submit" class="px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark">
                        Save Changes
                      </button>
                      <button type="button" onclick="cancelEditMode('<?=e($pid)?>')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded hover:bg-slate-300">
                        Cancel
                      </button>
                    </div>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-4 text-sm text-slate-600">
      Showing <?=count($rows)?> patient<?=count($rows)!==1?'s':''?>
    </div>
  </section>
</div>

<script>
function togglePatientDetails(patientId) {
  const detailsRow = document.getElementById('patient-details-' + patientId);
  if (!detailsRow) {
    console.error('Patient details row not found for ID:', patientId);
    return;
  }

  const button = document.querySelector('[data-patient-id="' + patientId + '"]');
  if (!button) {
    console.error('Patient button not found for ID:', patientId);
    return;
  }

  const expandText = button.querySelector('.expand-text');
  const collapseText = button.querySelector('.collapse-text');

  if (detailsRow.classList.contains('hidden')) {
    // Close all other open details
    document.querySelectorAll('.patient-details-row').forEach(row => {
      row.classList.add('hidden');
    });
    document.querySelectorAll('.expand-text').forEach(el => el.classList.remove('hidden'));
    document.querySelectorAll('.collapse-text').forEach(el => el.classList.add('hidden'));
    // Reset all to view mode
    document.querySelectorAll('.edit-mode').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.view-mode').forEach(el => el.classList.remove('hidden'));

    // Open this one
    detailsRow.classList.remove('hidden');
    expandText.classList.add('hidden');
    collapseText.classList.remove('hidden');

    // Mark provider response as read by admin
    markResponseAsRead(patientId, button);
  } else {
    // Close this one
    detailsRow.classList.add('hidden');
    expandText.classList.remove('hidden');
    collapseText.classList.add('hidden');
  }
}

async function markResponseAsRead(patientId, button) {
  try {
    const formData = new FormData();
    formData.append('action', 'mark_response_read');
    formData.append('patient_id', patientId);

    await fetch('/api/admin/patients.php', {
      method: 'POST',
      body: formData
    });

    // Remove red dot indicator
    const redDot = button.querySelector('span[style*="background: #ef4444"]');
    if (redDot) {
      redDot.remove();
    }
  } catch (error) {
    console.error('Error marking response as read:', error);
  }
}

async function sendReply(event, patientId) {
  event.preventDefault();
  console.log('sendReply called with patientId:', patientId);
  const form = event.target;
  const formData = new FormData(form);
  formData.append('action', 'send_reply_to_provider');
  formData.append('patient_id', patientId);

  // Log what we're sending
  console.log('FormData contents:', {
    action: formData.get('action'),
    patient_id: formData.get('patient_id'),
    reply_message: formData.get('reply_message')
  });

  try {
    const response = await fetch('/api/admin/patients.php', {
      method: 'POST',
      body: formData
    });

    // Get the response text first to see what we're receiving
    const text = await response.text();
    console.log('Raw API response:', text);

    // Try to parse as JSON
    let result;
    try {
      result = JSON.parse(text);
    } catch (e) {
      console.error('JSON parse error. Response was:', text.substring(0, 500));
      alert('Server returned invalid response. Check console for details.');
      return;
    }

    if (result.ok) {
      // Clear the textarea
      const textarea = form.querySelector('textarea[name="reply_message"]');
      if (textarea) {
        textarea.value = '';
      }

      // Show success message without closing patient details
      alert('Reply sent successfully! The physician will be notified.');

      // Optionally reload just this patient's data to show the new comment
      // For now, just tell the user the reply was sent
      // You could reload the page with a hash to keep the patient open:
      // location.href = location.pathname + location.search + '#patient-' + patientId;
      // location.reload();
    } else {
      alert('Error: ' + (result.error || 'Failed to send reply'));
    }
  } catch (error) {
    console.error('Send reply error:', error);
    alert('Error sending reply: ' + error.message);
  }
}

function enableEditMode(patientId) {
  document.getElementById('view-mode-' + patientId).classList.add('hidden');
  document.getElementById('edit-mode-' + patientId).classList.remove('hidden');
}

function cancelEditMode(patientId) {
  document.getElementById('edit-mode-' + patientId).classList.add('hidden');
  document.getElementById('view-mode-' + patientId).classList.remove('hidden');
}

async function savePatient(event, patientId) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  formData.append('patient_id', patientId);

  try {
    const response = await fetch('/admin/update-patient.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.ok) {
      alert('Patient updated successfully!');
      location.reload(); // Reload to show updated data
    } else {
      alert('Error: ' + (result.error || 'Failed to update patient'));
    }
  } catch (error) {
    console.error('Save error:', error);
    alert('Error saving patient: ' + error.message);
  }
}

// Status update dialog functions
let currentStatusPatientId = null;

function openStatusDialog(patientId, currentStatus) {
  currentStatusPatientId = patientId;
  document.getElementById('status-patient-id').value = patientId;
  document.getElementById('status-select').value = currentStatus || 'pending';
  document.getElementById('status-comment').value = '';
  document.getElementById('status-dialog').showModal();
}

function closeStatusDialog() {
  document.getElementById('status-dialog').close();
}
</script>

<!-- Status Update Dialog -->
<dialog id="status-dialog" class="rounded-lg shadow-lg p-0" style="max-width: 500px; width: 90%;">
  <form method="post" class="p-6">
    <input type="hidden" name="action" value="update_patient_status">
    <input type="hidden" name="patient_id" id="status-patient-id">

    <h3 class="text-lg font-semibold mb-4">Update Patient Status</h3>

    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select name="status" id="status-select" class="w-full px-3 py-2 border rounded" required>
          <option value="pending">Pending Review</option>
          <option value="approved">Approved</option>
          <option value="not_covered">Not Covered</option>
          <option value="need_info">Need More Info</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Comment (visible to provider)</label>
        <textarea name="comment" id="status-comment" rows="4" class="w-full px-3 py-2 border rounded" placeholder="Enter explanation or instructions..."></textarea>
      </div>
    </div>

    <div class="flex gap-2 mt-6">
      <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
        Update Status
      </button>
      <button type="button" onclick="closeStatusDialog()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded hover:bg-slate-300">
        Cancel
      </button>
    </div>
  </form>
</dialog>

<?php include __DIR__.'/_footer.php'; ?>
