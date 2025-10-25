<?php
// admin/patients.php - Patient management with role-based access
declare(strict_types=1);

require_once __DIR__ . '/db.php';
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
$auth      = __DIR__ . '/auth.php';      if (is_file($auth) && function_exists('require_admin')) require_admin();

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
    $suffix = substr($abs, strlen($root));
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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$phys = isset($_GET['phys']) ? $_GET['phys'] : '';

/* ================= Data ================= */
try {
  $where = "1=1";
  $params = [];

  // Role-based access control
  if ($adminRole === 'superadmin' || $adminRole === 'manufacturer') {
    // Superadmin and manufacturer see all patients - no additional filter
  } else {
    // Employees only see patients from assigned physicians
    $where .= " AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = :admin_id AND ap.physician_user_id = p.user_id)";
    $params['admin_id'] = $adminId;
  }

  // Search filter
  if ($search !== '') {
    $where .= " AND (p.first_name ILIKE :search OR p.last_name ILIKE :search OR p.email ILIKE :search OR p.phone ILIKE :search)";
    $params['search'] = '%' . $search . '%';
  }

  // Status filter
  if ($status === 'active') {
    $where .= " AND p.status = 'active'";
  } elseif ($status === 'pending') {
    $where .= " AND p.status = 'pending'";
  }

  // Physician filter
  if ($phys !== '') {
    $where .= " AND p.user_id = :phys";
    $params['phys'] = $phys;
  }

  $sql = "
    SELECT
      p.id, p.user_id, p.first_name, p.last_name, p.email, p.phone, p.dob,
      p.status, p.created_at,
      u.first_name AS phys_first, u.last_name AS phys_last, u.practice_name,
      COUNT(DISTINCT o.id) AS order_count,
      MAX(o.created_at) AS last_order_date
    FROM patients p
    LEFT JOIN users u ON u.id = p.user_id
    LEFT JOIN orders o ON o.patient_id = p.id AND o.status NOT IN ('rejected','cancelled')
    WHERE $where
    GROUP BY p.id, p.user_id, p.first_name, p.last_name, p.email, p.phone, p.dob,
             p.status, p.created_at, u.first_name, u.last_name, u.practice_name
    ORDER BY p.created_at DESC
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();
} catch (Throwable $e) {
  error_log("[patients-data] " . $e->getMessage());
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
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">Patients</h2>
    <form class="flex items-center gap-2" method="get">
      <input type="text" name="search" placeholder="Search name, email, phone" value="<?=e($search)?>" style="width: 220px;">
      <select name="status">
        <option value="all" <?=$status==='all'?'selected':''?>>All Status</option>
        <option value="active" <?=$status==='active'?'selected':''?>>Active</option>
        <option value="pending" <?=$status==='pending'?'selected':''?>>Pending</option>
      </select>
      <select name="phys">
        <option value="">All Physicians</option>
        <?php foreach ($physicians as $p): ?>
          <option value="<?=e($p['id'])?>" <?=$phys==(string)$p['id']?'selected':''?>>
            <?=e($p['first_name'] . ' ' . $p['last_name'])?><?=$p['practice_name']?' ('.e($p['practice_name']).')':''?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary" type="submit">Filter</button>
      <?php if ($search || $status !== 'all' || $phys): ?>
        <a href="/admin/patients.php" class="btn">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <section class="card p-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Patient</th>
            <th class="py-2">Contact</th>
            <th class="py-2">DOB</th>
            <th class="py-2">Physician</th>
            <th class="py-2">Orders</th>
            <th class="py-2">Status</th>
            <th class="py-2">Notes</th>
            <th class="py-2">ID</th>
            <th class="py-2">Insurance Card</th>
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
              $slug = preg_replace('/[^a-z0-9]+/i','_', strtolower($fullname));
              $tokens = array_filter([$pid, $slug]);

              $noteLinks = find_bucket_files('notes', $tokens);
              $idLinks = find_bucket_files('ids', $tokens);
              $insLinks = find_bucket_files('insurance', $tokens);

              $physName = trim(($row['phys_first'] ?? '').' '.($row['phys_last'] ?? ''));
              $practiceName = $row['practice_name'] ?? '';
              $orderCount = (int)($row['order_count'] ?? 0);
              $lastOrder = $row['last_order_date'] ?? null;
              $status = $row['status'] ?? 'pending';

              $statusColors = [
                'active' => 'bg-green-100 text-green-700',
                'pending' => 'bg-yellow-100 text-yellow-700',
                'inactive' => 'bg-slate-100 text-slate-600'
              ];
              $statusColor = $statusColors[$status] ?? 'bg-slate-100 text-slate-600';
            ?>
            <tr class="border-t hover:bg-slate-50">
              <td class="py-2">
                <div class="font-medium"><?=e($fullname ?: '—')?></div>
                <div class="text-[11px] text-slate-500">ID: <?=e($pid)?></div>
              </td>
              <td class="py-2">
                <div class="text-xs"><?=e($row['email'] ?? '—')?></div>
                <div class="text-xs text-slate-500"><?=e($row['phone'] ?? '—')?></div>
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
                <a href="/portal/patient-detail.php?id=<?=e($pid)?>" class="text-brand underline text-xs" target="_blank">View Details</a>
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
<?php include __DIR__.'/_footer.php'; ?>
