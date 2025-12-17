<?php
/**
 * Employee Sales Rep Dashboard
 *
 * Overview of direct accounts, managed distributors, and commission metrics.
 * Shows both direct and distributor-override earnings.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

$adminId = $admin['id'];

// Get direct accounts stats
$directStatsStmt = $pdo->prepare("
  SELECT
    COUNT(DISTINCT u.id) as clinic_count,
    COUNT(DISTINCT CASE WHEN u.role = 'physician' THEN u.id END) as physician_count
  FROM users u
  WHERE u.employee_rep_id = ?
  AND u.role IN ('physician', 'practice_admin')
");
$directStatsStmt->execute([$adminId]);
$directStats = $directStatsStmt->fetch();

// Get managed distributors stats
$distStatsStmt = $pdo->prepare("
  SELECT
    COUNT(DISTINCT sr.id) as distributor_count,
    COUNT(DISTINCT u2.id) as dist_clinic_count
  FROM sales_reps sr
  LEFT JOIN users u2 ON u2.assigned_rep_id = sr.id
  WHERE sr.managed_by_admin_id = ?
  AND sr.status = 'active'
");
$distStatsStmt->execute([$adminId]);
$distStats = $distStatsStmt->fetch();

// Get orders this month from direct accounts
$directOrdersStmt = $pdo->prepare("
  SELECT COUNT(o.id) as count, COALESCE(SUM(o.expected_revenue), 0) as revenue
  FROM orders o
  JOIN users u ON u.id = o.user_id
  WHERE u.employee_rep_id = ?
  AND o.created_at >= DATE_TRUNC('month', CURRENT_DATE)
  AND o.status NOT IN ('cancelled', 'rejected', 'draft')
");
$directOrdersStmt->execute([$adminId]);
$directOrders = $directOrdersStmt->fetch();

// Get orders this month from managed distributors' accounts
$distOrdersStmt = $pdo->prepare("
  SELECT COUNT(o.id) as count, COALESCE(SUM(o.expected_revenue), 0) as revenue
  FROM orders o
  JOIN users u ON u.id = o.user_id
  JOIN sales_reps sr ON sr.id = u.assigned_rep_id
  WHERE sr.managed_by_admin_id = ?
  AND o.created_at >= DATE_TRUNC('month', CURRENT_DATE)
  AND o.status NOT IN ('cancelled', 'rejected', 'draft')
");
$distOrdersStmt->execute([$adminId]);
$distOrders = $distOrdersStmt->fetch();

// Get commission rates
$directRate = get_employee_rep_rate($pdo, $adminId, 'direct');
$overrideRate = get_employee_rep_rate($pdo, $adminId, 'distributor_override');

// Get commission balance
$balance = get_employee_rep_balance($pdo, $adminId);

// Get recent direct account orders
$recentDirectOrdersStmt = $pdo->prepare("
  SELECT o.id, o.order_number, o.status, o.created_at, o.expected_revenue,
         p.first_name as patient_first, p.last_name as patient_last,
         u.practice_name, u.first_name as phys_first, u.last_name as phys_last
  FROM orders o
  JOIN patients p ON p.id = o.patient_id
  JOIN users u ON u.id = o.user_id
  WHERE u.employee_rep_id = ?
  AND o.status NOT IN ('draft')
  ORDER BY o.created_at DESC
  LIMIT 5
");
$recentDirectOrdersStmt->execute([$adminId]);
$recentDirectOrders = $recentDirectOrdersStmt->fetchAll();

// Get managed distributors with their stats
$distributorsStmt = $pdo->prepare("
  SELECT sr.id, sr.company_name, u.first_name, u.last_name, u.email,
         sr.status, sr.approved_date,
         (SELECT COUNT(*) FROM users u2 WHERE u2.assigned_rep_id = sr.id) as clinic_count,
         (SELECT COUNT(*) FROM orders o
          JOIN users u3 ON u3.id = o.user_id
          WHERE u3.assigned_rep_id = sr.id
          AND o.created_at >= DATE_TRUNC('month', CURRENT_DATE)
          AND o.status NOT IN ('cancelled', 'rejected', 'draft')) as orders_this_month
  FROM sales_reps sr
  JOIN users u ON u.id = sr.user_id
  WHERE sr.managed_by_admin_id = ?
  AND sr.status = 'active'
  ORDER BY u.last_name, u.first_name
  LIMIT 5
");
$distributorsStmt->execute([$adminId]);
$distributors = $distributorsStmt->fetchAll();

// Get recent direct accounts
$recentClinicStmt = $pdo->prepare("
  SELECT u.id, u.practice_name, u.first_name, u.last_name, u.email, u.role,
         u.rep_assignment_date
  FROM users u
  WHERE u.employee_rep_id = ?
  AND u.role IN ('physician', 'practice_admin')
  ORDER BY u.rep_assignment_date DESC NULLS LAST
  LIMIT 5
");
$recentClinicStmt->execute([$adminId]);
$recentClinics = $recentClinicStmt->fetchAll();
?>

<!-- Welcome Section -->
<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Welcome back, <?= htmlspecialchars(explode(' ', $admin['name'])[0] ?? 'Rep') ?></h2>
  <p class="text-gray-600 mt-1">Here's your sales performance overview</p>
</div>

<!-- Summary Cards Row 1 -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <!-- Direct Clinics -->
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Direct Clinics</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int)$directStats['clinic_count'] ?></p>
        <p class="text-xs text-gray-400"><?= (int)$directStats['physician_count'] ?> physicians</p>
      </div>
      <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
        </svg>
      </div>
    </div>
  </div>

  <!-- Managed Distributors -->
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">My Distributors</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int)$distStats['distributor_count'] ?></p>
        <p class="text-xs text-gray-400"><?= (int)$distStats['dist_clinic_count'] ?> clinics</p>
      </div>
      <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
      </div>
    </div>
  </div>

  <!-- Direct Orders This Month -->
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Direct Orders (MTD)</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int)$directOrders['count'] ?></p>
        <p class="text-xs text-gray-400">$<?= number_format((float)$directOrders['revenue'], 0) ?> revenue</p>
      </div>
      <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
      </div>
    </div>
  </div>

  <!-- Distributor Orders This Month -->
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Distributor Orders (MTD)</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int)$distOrders['count'] ?></p>
        <p class="text-xs text-gray-400">$<?= number_format((float)$distOrders['revenue'], 0) ?> revenue</p>
      </div>
      <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
        </svg>
      </div>
    </div>
  </div>
</div>

<!-- Commission Summary Card -->
<div class="card p-6 mb-6 bg-gradient-to-r from-indigo-50 to-purple-50 border-indigo-200">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-semibold text-gray-900">Commission Summary</h3>
    <div class="text-sm text-gray-500 space-x-4">
      <span>Direct: <strong><?= number_format($directRate * 100, 0) ?>%</strong></span>
      <span>Override: <strong><?= number_format($overrideRate * 100, 0) ?>%</strong></span>
    </div>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="text-center p-4 bg-white rounded-lg shadow-sm">
      <p class="text-sm text-gray-500 mb-1">Direct Commission (Lifetime)</p>
      <p class="text-xl font-bold text-blue-600">$<?= number_format((float)$balance['direct_earned'], 2) ?></p>
    </div>
    <div class="text-center p-4 bg-white rounded-lg shadow-sm">
      <p class="text-sm text-gray-500 mb-1">Override Commission (Lifetime)</p>
      <p class="text-xl font-bold text-purple-600">$<?= number_format((float)$balance['override_earned'], 2) ?></p>
    </div>
    <div class="text-center p-4 bg-indigo-600 text-white rounded-lg shadow-sm">
      <p class="text-sm text-indigo-100 mb-1">Total Earned</p>
      <p class="text-2xl font-bold">$<?= number_format((float)$balance['total_earned'], 2) ?></p>
    </div>
  </div>
  <div class="mt-4 text-center">
    <a href="/admin/employee-rep/commissions.php" class="text-sm text-indigo-600 hover:underline">View Commission Ledger &rarr;</a>
  </div>
</div>

<!-- Two Column Layout -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <!-- Recent Direct Orders -->
  <div class="card">
    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-900">Recent Direct Orders</h3>
      <span class="text-xs text-gray-400">From your direct accounts</span>
    </div>
    <div class="p-4">
      <?php if (empty($recentDirectOrders)): ?>
        <p class="text-gray-500 text-sm">No orders yet from your direct accounts.</p>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($recentDirectOrders as $order): ?>
            <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
              <div>
                <a href="/admin/employee-rep/orders.php?id=<?= htmlspecialchars($order['id']) ?>" class="text-xs font-medium text-indigo-600 hover:underline">
                  #<?= htmlspecialchars(substr($order['order_number'] ?: $order['id'], 0, 8)) ?>
                </a>
                <p class="text-sm font-medium text-gray-900">
                  <?= htmlspecialchars($order['patient_first'] . ' ' . $order['patient_last']) ?>
                </p>
                <p class="text-xs text-gray-500">
                  <?= htmlspecialchars($order['practice_name'] ?: $order['phys_first'] . ' ' . $order['phys_last']) ?>
                </p>
              </div>
              <div class="text-right">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                  <?php
                  switch ($order['status']) {
                    case 'delivered': echo 'bg-green-100 text-green-800'; break;
                    case 'shipped': echo 'bg-blue-100 text-blue-800'; break;
                    case 'approved': echo 'bg-teal-100 text-teal-800'; break;
                    case 'submitted': echo 'bg-yellow-100 text-yellow-800'; break;
                    default: echo 'bg-gray-100 text-gray-800';
                  }
                  ?>
                ">
                  <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                </span>
                <p class="text-xs text-gray-400 mt-1">
                  <?= date('M j', strtotime($order['created_at'])) ?>
                </p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <a href="/admin/employee-rep/orders.php" class="block text-center text-sm text-brand hover:underline mt-4">View all orders &rarr;</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Managed Distributors -->
  <div class="card">
    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-900">My Distributors</h3>
      <span class="text-xs text-gray-400">Earning <?= number_format($overrideRate * 100, 0) ?>% override</span>
    </div>
    <div class="p-4">
      <?php if (empty($distributors)): ?>
        <p class="text-gray-500 text-sm">No distributors assigned to you yet.</p>
        <p class="text-xs text-gray-400 mt-2">Contact your manager to get distributors assigned.</p>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($distributors as $dist): ?>
            <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
              <div>
                <a href="/admin/employee-rep/distributors.php?id=<?= htmlspecialchars($dist['id']) ?>" class="text-sm font-medium text-purple-600 hover:underline">
                  <?= htmlspecialchars($dist['company_name'] ?: $dist['first_name'] . ' ' . $dist['last_name']) ?>
                </a>
                <p class="text-xs text-gray-500">
                  <?= htmlspecialchars($dist['email']) ?>
                </p>
              </div>
              <div class="text-right">
                <p class="text-sm font-medium text-gray-900"><?= (int)$dist['clinic_count'] ?> clinics</p>
                <p class="text-xs text-gray-400"><?= (int)$dist['orders_this_month'] ?> orders MTD</p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <a href="/admin/employee-rep/distributors.php" class="block text-center text-sm text-brand hover:underline mt-4">View all distributors &rarr;</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent Direct Clinics -->
<?php if (!empty($recentClinics)): ?>
<div class="card p-6 mt-6">
  <h3 class="text-lg font-semibold text-gray-900 mb-4">Recently Added Direct Clinics</h3>
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead>
        <tr>
          <th class="text-left py-2">Clinic</th>
          <th class="text-left py-2">Type</th>
          <th class="text-right py-2">Added</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentClinics as $clinic): ?>
          <tr class="border-t border-gray-100">
            <td class="py-3">
              <a href="/admin/employee-rep/clinics.php?id=<?= htmlspecialchars($clinic['id']) ?>" class="font-medium text-indigo-600 hover:underline">
                <?= htmlspecialchars($clinic['practice_name'] ?: $clinic['first_name'] . ' ' . $clinic['last_name']) ?>
              </a>
              <p class="text-xs text-gray-400"><?= htmlspecialchars($clinic['email']) ?></p>
            </td>
            <td class="py-3">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                <?= $clinic['role'] === 'practice_admin' ? 'Practice' : 'Physician' ?>
              </span>
            </td>
            <td class="py-3 text-right text-sm text-gray-500">
              <?= $clinic['rep_assignment_date'] ? date('M j, Y', strtotime($clinic['rep_assignment_date'])) : '-' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="card p-6 mt-6">
  <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <a href="/admin/employee-rep/clinics.php" class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
      <svg class="w-8 h-8 text-blue-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
      </svg>
      <span class="text-sm font-medium text-gray-700">My Clinics</span>
    </a>
    <a href="/admin/employee-rep/distributors.php" class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
      <svg class="w-8 h-8 text-purple-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
      </svg>
      <span class="text-sm font-medium text-gray-700">My Distributors</span>
    </a>
    <a href="/admin/employee-rep/orders.php" class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
      <svg class="w-8 h-8 text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
      </svg>
      <span class="text-sm font-medium text-gray-700">View Orders</span>
    </a>
    <a href="/admin/employee-rep/commissions.php" class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
      <svg class="w-8 h-8 text-indigo-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      <span class="text-sm font-medium text-gray-700">Commissions</span>
    </a>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
