<?php
/**
 * Sales Rep Dashboard
 *
 * Overview of assigned clinics, recent orders, and commission metrics.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

// Get rep_id from session
$repId = $admin['rep_id'] ?? null;
if (!$repId) {
  echo '<div class="card p-6"><p class="text-red-600">Sales rep profile not found.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

// Get assigned clinics count
$clinicsStmt = $pdo->prepare("
  SELECT COUNT(DISTINCT u.id) as count
  FROM users u
  WHERE u.assigned_rep_id = ?
  AND (u.role IN ('physician', 'practice_admin') OR u.role IS NULL)
");
$clinicsStmt->execute([$repId]);
$clinicsCount = (int)$clinicsStmt->fetch()['count'];

// Get total patients from assigned clinics
$patientsStmt = $pdo->prepare("
  SELECT COUNT(DISTINCT p.id) as count
  FROM patients p
  JOIN users u ON u.id = p.user_id
  WHERE u.assigned_rep_id = ?
");
$patientsStmt->execute([$repId]);
$patientsCount = (int)$patientsStmt->fetch()['count'];

// Get orders this month from assigned clinics
$ordersStmt = $pdo->prepare("
  SELECT COUNT(o.id) as count
  FROM orders o
  JOIN users u ON u.id = o.user_id
  WHERE u.assigned_rep_id = ?
  AND o.created_at >= DATE_TRUNC('month', CURRENT_DATE)
  AND o.status NOT IN ('cancelled', 'rejected', 'draft')
");
$ordersStmt->execute([$repId]);
$ordersThisMonth = (int)$ordersStmt->fetch()['count'];

// Get pending assignment requests
$requestsStmt = $pdo->prepare("
  SELECT COUNT(id) as count
  FROM rep_assignment_requests
  WHERE rep_id = ? AND status = 'pending'
");
$requestsStmt->execute([$repId]);
$pendingRequests = (int)$requestsStmt->fetch()['count'];

// Get commission summary
$commissionStmt = $pdo->prepare("
  SELECT
    COALESCE(SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END), 0) as pending,
    COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END), 0) as paid_total,
    COALESCE(SUM(CASE WHEN status = 'paid' AND created_at >= DATE_TRUNC('month', CURRENT_DATE) THEN commission_amount ELSE 0 END), 0) as paid_this_month
  FROM rep_commission_ledger
  WHERE rep_id = ?
");
$commissionStmt->execute([$repId]);
$commission = $commissionStmt->fetch();

// Get recent orders from assigned clinics
$recentOrdersStmt = $pdo->prepare("
  SELECT o.id, o.status, o.created_at, o.product,
         p.first_name as patient_first, p.last_name as patient_last,
         u.practice_name, u.first_name as phys_first, u.last_name as phys_last
  FROM orders o
  JOIN patients p ON p.id = o.patient_id
  JOIN users u ON u.id = o.user_id
  WHERE u.assigned_rep_id = ?
  AND o.status NOT IN ('draft')
  ORDER BY o.created_at DESC
  LIMIT 10
");
$recentOrdersStmt->execute([$repId]);
$recentOrders = $recentOrdersStmt->fetchAll();

// Get recent activity (clinics added)
$recentClinicsStmt = $pdo->prepare("
  SELECT u.id, u.practice_name, u.first_name, u.last_name, u.email, u.rep_assignment_date, u.role
  FROM users u
  WHERE u.assigned_rep_id = ?
  ORDER BY u.rep_assignment_date DESC
  LIMIT 5
");
$recentClinicsStmt->execute([$repId]);
$recentClinics = $recentClinicsStmt->fetchAll();
?>

<!-- Welcome Section -->
<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Welcome back, <?= htmlspecialchars(explode(' ', $admin['name'])[0] ?? 'Rep') ?></h2>
  <p class="text-gray-600 mt-1">Here's your sales performance overview</p>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
  <!-- Assigned Clinics -->
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Assigned Clinics</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $clinicsCount ?></p>
      </div>
      <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
        </svg>
      </div>
    </div>
    <a href="/admin/rep/clinics.php" class="text-sm text-brand hover:underline mt-3 inline-block">View all clinics &rarr;</a>
  </div>

  <!-- Orders This Month -->
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Orders This Month</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $ordersThisMonth ?></p>
      </div>
      <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
      </div>
    </div>
    <a href="/admin/rep/orders.php" class="text-sm text-brand hover:underline mt-3 inline-block">View all orders &rarr;</a>
  </div>

  <!-- Pending Commissions -->
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Pending Commissions</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">$<?= number_format((float)$commission['pending'], 2) ?></p>
      </div>
      <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
      </div>
    </div>
    <a href="/admin/rep/commissions.php" class="text-sm text-brand hover:underline mt-3 inline-block">View ledger &rarr;</a>
  </div>

  <!-- Pending Requests -->
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Pending Requests</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $pendingRequests ?></p>
      </div>
      <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
      </div>
    </div>
    <a href="/admin/rep/assignment-requests.php" class="text-sm text-brand hover:underline mt-3 inline-block">View requests &rarr;</a>
  </div>
</div>

<!-- Commission Summary -->
<div class="card p-6 mb-8">
  <h3 class="text-lg font-semibold text-gray-900 mb-4">Commission Summary</h3>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="text-center p-4 bg-gray-50 rounded-lg">
      <p class="text-sm text-gray-500 mb-1">Paid This Month</p>
      <p class="text-xl font-bold text-green-600">$<?= number_format((float)$commission['paid_this_month'], 2) ?></p>
    </div>
    <div class="text-center p-4 bg-gray-50 rounded-lg">
      <p class="text-sm text-gray-500 mb-1">Pending Payout</p>
      <p class="text-xl font-bold text-yellow-600">$<?= number_format((float)$commission['pending'], 2) ?></p>
    </div>
    <div class="text-center p-4 bg-gray-50 rounded-lg">
      <p class="text-sm text-gray-500 mb-1">Total Earned (All Time)</p>
      <p class="text-xl font-bold text-gray-900">$<?= number_format((float)$commission['paid_total'], 2) ?></p>
    </div>
  </div>
</div>

<!-- Two Column Layout -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <!-- Recent Orders -->
  <div class="card">
    <div class="p-4 border-b border-gray-100">
      <h3 class="text-lg font-semibold text-gray-900">Recent Orders</h3>
    </div>
    <div class="p-4">
      <?php if (empty($recentOrders)): ?>
        <p class="text-gray-500 text-sm">No orders yet from your assigned clinics.</p>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($recentOrders as $order): ?>
            <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
              <div>
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
        <a href="/admin/rep/orders.php" class="block text-center text-sm text-brand hover:underline mt-4">View all orders &rarr;</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recently Added Clinics -->
  <div class="card">
    <div class="p-4 border-b border-gray-100">
      <h3 class="text-lg font-semibold text-gray-900">Recently Added Clinics</h3>
    </div>
    <div class="p-4">
      <?php if (empty($recentClinics)): ?>
        <p class="text-gray-500 text-sm">No clinics assigned yet.</p>
        <a href="/admin/rep/onboard-clinic.php" class="btn btn-primary mt-4 w-full justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          Onboard Your First Clinic
        </a>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($recentClinics as $clinic): ?>
            <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
              <div>
                <p class="text-sm font-medium text-gray-900">
                  <?= htmlspecialchars($clinic['practice_name'] ?: $clinic['first_name'] . ' ' . $clinic['last_name']) ?>
                </p>
                <p class="text-xs text-gray-500">
                  <?= htmlspecialchars($clinic['email']) ?>
                </p>
              </div>
              <div class="text-right">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                  <?= $clinic['role'] === 'practice_admin' ? 'Practice' : 'Physician' ?>
                </span>
                <p class="text-xs text-gray-400 mt-1">
                  <?= $clinic['rep_assignment_date'] ? date('M j', strtotime($clinic['rep_assignment_date'])) : 'N/A' ?>
                </p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <a href="/admin/rep/clinics.php" class="block text-center text-sm text-brand hover:underline mt-4">View all clinics &rarr;</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="card p-6 mt-6">
  <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <a href="/admin/rep/onboard-clinic.php" class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
      <svg class="w-8 h-8 text-brand mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
      </svg>
      <span class="text-sm font-medium text-gray-700">New Clinic</span>
    </a>
    <a href="/admin/rep/add-physician.php" class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
      <svg class="w-8 h-8 text-brand mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
      </svg>
      <span class="text-sm font-medium text-gray-700">Add Physician</span>
    </a>
    <a href="/admin/rep/create-wholesale-order.php" class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
      <svg class="w-8 h-8 text-brand mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
      </svg>
      <span class="text-sm font-medium text-gray-700">Wholesale Order</span>
    </a>
    <a href="/admin/rep/assignment-requests.php" class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
      <svg class="w-8 h-8 text-brand mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
      </svg>
      <span class="text-sm font-medium text-gray-700">Request Clinic</span>
    </a>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
