<?php
/**
 * Sales Rep: My Clinics
 *
 * Shows all clinics/practices assigned to this sales rep.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

// Support both regular sales reps (assigned_rep_id) and employee reps (employee_rep_id)
$isEmployeeRep = !empty($admin['is_employee_rep']);
$repId = $admin['rep_id'] ?? null;
$employeeRepId = $isEmployeeRep ? (int)$admin['id'] : null;

if (!$isEmployeeRep && !$repId) {
  echo '<div class="card p-6"><p class="text-red-600">Sales rep profile not found.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

// Search filter
$search = trim($_GET['q'] ?? '');

// Get assigned clinics - query depends on rep type
// Regular reps see their own clinics; distributors see all their reps' clinics (downline scope).
$repColumn = $isEmployeeRep ? 'u.employee_rep_id = ?' : 'u.assigned_rep_id = ANY(?::text[])';
$repParam = $isEmployeeRep ? $employeeRepId : $repScopeArr;

$query = "
  SELECT u.id, u.email, u.first_name, u.last_name, u.practice_name, u.phone, u.address, u.city, u.state, u.zip,
         u.role, u.account_type, u.status, u.rep_assignment_date, u.rep_assigned_by,
         (SELECT COUNT(*) FROM patients p WHERE p.user_id = u.id) as patient_count,
         (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status NOT IN ('cancelled', 'rejected', 'draft')) as order_count
  FROM users u
  WHERE {$repColumn}
  AND (u.role IN ('physician', 'practice_admin') OR u.role IS NULL)
";
$params = [$repParam];

if ($search) {
  $query .= " AND (u.practice_name ILIKE ? OR u.first_name ILIKE ? OR u.last_name ILIKE ? OR u.email ILIKE ?)";
  $searchPattern = "%{$search}%";
  $params[] = $searchPattern;
  $params[] = $searchPattern;
  $params[] = $searchPattern;
  $params[] = $searchPattern;
}

$query .= " ORDER BY u.practice_name, u.last_name, u.first_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clinics = $stmt->fetchAll();
?>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">My Clinics</h2>
    <p class="text-gray-600 mt-1"><?= count($clinics) ?> clinic<?= count($clinics) !== 1 ? 's' : '' ?> assigned to you</p>
  </div>
  <div class="flex gap-3">
    <a href="/admin/rep/assignment-requests.php" class="btn">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
      Request Existing Clinic
    </a>
    <a href="/admin/rep/onboard-clinic.php" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
      Onboard New Clinic
    </a>
  </div>
</div>

<!-- Search -->
<div class="card p-4 mb-6">
  <form method="GET" class="flex gap-4">
    <div class="flex-1">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by clinic name, physician name, or email..." class="w-full">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search): ?>
      <a href="/admin/rep/clinics.php" class="btn">Clear</a>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($clinics)): ?>
  <div class="card p-8 text-center">
    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
    </svg>
    <h3 class="text-lg font-medium text-gray-900 mb-2">No Clinics Yet</h3>
    <p class="text-gray-500 mb-4">Start building your portfolio by onboarding new clinics or requesting assignment to existing ones.</p>
    <div class="flex justify-center gap-3">
      <a href="/admin/rep/onboard-clinic.php" class="btn btn-primary">Onboard New Clinic</a>
      <a href="/admin/rep/assignment-requests.php" class="btn">Request Assignment</a>
    </div>
  </div>
<?php else: ?>
  <!-- Clinics Table -->
  <div class="card overflow-hidden">
    <table>
      <thead>
        <tr>
          <th>Clinic / Physician</th>
          <th>Contact</th>
          <th>Location</th>
          <th>Type</th>
          <th>Patients</th>
          <th>Orders</th>
          <th>Assigned</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clinics as $clinic): ?>
          <tr>
            <td>
              <div class="font-medium text-gray-900">
                <?= htmlspecialchars($clinic['practice_name'] ?: $clinic['first_name'] . ' ' . $clinic['last_name']) ?>
              </div>
              <?php if ($clinic['practice_name'] && ($clinic['first_name'] || $clinic['last_name'])): ?>
                <div class="text-xs text-gray-500"><?= htmlspecialchars($clinic['first_name'] . ' ' . $clinic['last_name']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="text-sm"><?= htmlspecialchars($clinic['email']) ?></div>
              <?php if ($clinic['phone']): ?>
                <div class="text-xs text-gray-500"><?= htmlspecialchars($clinic['phone']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($clinic['city'] || $clinic['state']): ?>
                <div class="text-sm"><?= htmlspecialchars(trim($clinic['city'] . ', ' . $clinic['state'], ', ')) ?></div>
              <?php else: ?>
                <span class="text-gray-400">-</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                <?= $clinic['role'] === 'practice_admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>
              ">
                <?= $clinic['role'] === 'practice_admin' ? 'Practice' : 'Physician' ?>
              </span>
              <?php if ($clinic['account_type'] === 'wholesale' || $clinic['account_type'] === 'both'): ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 ml-1">
                  Wholesale
                </span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <span class="font-medium"><?= $clinic['patient_count'] ?></span>
            </td>
            <td class="text-center">
              <span class="font-medium"><?= $clinic['order_count'] ?></span>
            </td>
            <td>
              <div class="text-sm"><?= $clinic['rep_assignment_date'] ? date('M j, Y', strtotime($clinic['rep_assignment_date'])) : '-' ?></div>
              <div class="text-xs text-gray-500">
                <?php
                switch ($clinic['rep_assigned_by']) {
                  case 'self_onboard': echo 'Onboarded'; break;
                  case 'admin_assign': echo 'Admin assigned'; break;
                  case 'approved_request': echo 'Request approved'; break;
                  default: echo '-';
                }
                ?>
              </div>
            </td>
            <td>
              <a href="/admin/rep/clinic-detail.php?id=<?= urlencode($clinic['id']) ?>" class="btn text-sm">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
