<?php
/**
 * Employee Sales Rep - Managed Distributors
 *
 * Lists all distributors managed by this employee sales rep.
 * Shows their clinics, orders, and revenue for override commission tracking.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

$adminId = $admin['id'];

// Get override commission rate
$overrideRate = get_employee_rep_rate($pdo, $adminId, 'distributor_override');

// Get all managed distributors with stats
$distributorsStmt = $pdo->prepare("
  SELECT sr.id, sr.company_name, sr.status, sr.approved_date,
         u.first_name, u.last_name, u.email, u.phone,
         (SELECT COUNT(*) FROM users u2 WHERE u2.assigned_rep_id = sr.id AND u2.role IN ('physician', 'practice_admin')) as clinic_count,
         (SELECT COUNT(*) FROM orders o
          JOIN users u3 ON u3.id = o.user_id
          WHERE u3.assigned_rep_id = sr.id
          AND o.status NOT IN ('draft', 'cancelled', 'rejected')) as total_orders,
         (SELECT COALESCE(SUM(o.expected_revenue), 0) FROM orders o
          JOIN users u3 ON u3.id = o.user_id
          WHERE u3.assigned_rep_id = sr.id
          AND o.status NOT IN ('draft', 'cancelled', 'rejected')) as total_revenue,
         (SELECT COUNT(*) FROM orders o
          JOIN users u3 ON u3.id = o.user_id
          WHERE u3.assigned_rep_id = sr.id
          AND o.created_at >= DATE_TRUNC('month', CURRENT_DATE)
          AND o.status NOT IN ('draft', 'cancelled', 'rejected')) as orders_this_month
  FROM sales_reps sr
  JOIN users u ON u.id = sr.user_id
  WHERE sr.managed_by_admin_id = ?
  ORDER BY sr.status ASC, u.last_name, u.first_name
");
$distributorsStmt->execute([$adminId]);
$distributors = $distributorsStmt->fetchAll();

// Calculate totals
$totalClinics = 0;
$totalOrders = 0;
$totalRevenue = 0;
foreach ($distributors as $d) {
  $totalClinics += (int)$d['clinic_count'];
  $totalOrders += (int)$d['total_orders'];
  $totalRevenue += (float)$d['total_revenue'];
}
$potentialOverride = $totalRevenue * $overrideRate;
?>

<div class="mb-6 flex items-center justify-between">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">My Distributors</h2>
    <p class="text-gray-600 mt-1">Distributors you manage and earn <?= number_format($overrideRate * 100, 0) ?>% override on</p>
  </div>
  <div class="text-sm text-gray-500">
    <?= count($distributors) ?> distributors
  </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
  <div class="card p-4">
    <p class="text-sm text-gray-500">Total Clinics</p>
    <p class="text-2xl font-bold text-gray-900"><?= $totalClinics ?></p>
  </div>
  <div class="card p-4">
    <p class="text-sm text-gray-500">Total Orders</p>
    <p class="text-2xl font-bold text-gray-900"><?= $totalOrders ?></p>
  </div>
  <div class="card p-4">
    <p class="text-sm text-gray-500">Total Revenue</p>
    <p class="text-2xl font-bold text-green-600">$<?= number_format($totalRevenue, 0) ?></p>
  </div>
  <div class="card p-4 bg-purple-50">
    <p class="text-sm text-purple-600">Potential Override</p>
    <p class="text-2xl font-bold text-purple-700">$<?= number_format($potentialOverride, 0) ?></p>
  </div>
</div>

<?php if (empty($distributors)): ?>
  <div class="card p-6 text-center">
    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
    </svg>
    <p class="text-gray-500">No distributors assigned yet.</p>
    <p class="text-sm text-gray-400 mt-2">Contact your manager to get distributors assigned to you.</p>
  </div>
<?php else: ?>
  <div class="card overflow-hidden">
    <table class="w-full">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left py-3 px-4">Distributor</th>
          <th class="text-left py-3 px-4">Contact</th>
          <th class="text-center py-3 px-4">Status</th>
          <th class="text-right py-3 px-4">Clinics</th>
          <th class="text-right py-3 px-4">Orders (MTD)</th>
          <th class="text-right py-3 px-4">Total Revenue</th>
          <th class="text-right py-3 px-4">Your Override</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($distributors as $dist): ?>
          <?php $distOverride = (float)$dist['total_revenue'] * $overrideRate; ?>
          <tr class="border-t border-gray-100 hover:bg-gray-50">
            <td class="py-4 px-4">
              <div class="font-medium text-gray-900">
                <?= htmlspecialchars($dist['company_name'] ?: $dist['first_name'] . ' ' . $dist['last_name']) ?>
              </div>
              <?php if ($dist['company_name']): ?>
                <div class="text-xs text-gray-400"><?= htmlspecialchars($dist['first_name'] . ' ' . $dist['last_name']) ?></div>
              <?php endif; ?>
            </td>
            <td class="py-4 px-4">
              <div class="text-sm text-gray-600"><?= htmlspecialchars($dist['email']) ?></div>
              <?php if ($dist['phone']): ?>
                <div class="text-xs text-gray-400"><?= htmlspecialchars($dist['phone']) ?></div>
              <?php endif; ?>
            </td>
            <td class="py-4 px-4 text-center">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                <?= $dist['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                <?= ucfirst($dist['status']) ?>
              </span>
            </td>
            <td class="py-4 px-4 text-right">
              <span class="font-medium text-gray-900"><?= (int)$dist['clinic_count'] ?></span>
            </td>
            <td class="py-4 px-4 text-right">
              <span class="font-medium text-gray-900"><?= (int)$dist['orders_this_month'] ?></span>
              <span class="text-xs text-gray-400">/ <?= (int)$dist['total_orders'] ?> total</span>
            </td>
            <td class="py-4 px-4 text-right">
              <span class="font-medium text-green-600">$<?= number_format((float)$dist['total_revenue'], 0) ?></span>
            </td>
            <td class="py-4 px-4 text-right">
              <span class="font-medium text-purple-600">$<?= number_format($distOverride, 0) ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
