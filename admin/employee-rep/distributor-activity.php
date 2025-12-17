<?php
/**
 * Employee Sales Rep - Distributor Activity
 *
 * Shows orders from managed distributors' accounts.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

$adminId = $admin['id'];

// Get override rate
$overrideRate = get_employee_rep_rate($pdo, $adminId, 'distributor_override');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Get total count
$countStmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM orders o
  JOIN users u ON u.id = o.user_id
  JOIN sales_reps sr ON sr.id = u.assigned_rep_id
  WHERE sr.managed_by_admin_id = ?
  AND o.status NOT IN ('draft')
");
$countStmt->execute([$adminId]);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);

// Get orders
$ordersStmt = $pdo->prepare("
  SELECT o.id, o.order_number, o.status, o.created_at, o.expected_revenue, o.product,
         p.first_name as patient_first, p.last_name as patient_last,
         u.practice_name, u.first_name as phys_first, u.last_name as phys_last,
         sr.company_name as dist_company,
         du.first_name as dist_first, du.last_name as dist_last
  FROM orders o
  JOIN patients p ON p.id = o.patient_id
  JOIN users u ON u.id = o.user_id
  JOIN sales_reps sr ON sr.id = u.assigned_rep_id
  JOIN users du ON du.id = sr.user_id
  WHERE sr.managed_by_admin_id = ?
  AND o.status NOT IN ('draft')
  ORDER BY o.created_at DESC
  LIMIT ? OFFSET ?
");
$ordersStmt->execute([$adminId, $perPage, $offset]);
$orders = $ordersStmt->fetchAll();
?>

<div class="mb-6 flex items-center justify-between">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Distributor Activity</h2>
    <p class="text-gray-600 mt-1">Orders from your managed distributors' accounts (<?= number_format($overrideRate * 100, 0) ?>% override)</p>
  </div>
  <div class="text-sm text-gray-500">
    <?= $totalOrders ?> total orders
  </div>
</div>

<?php if (empty($orders)): ?>
  <div class="card p-6 text-center">
    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
    </svg>
    <p class="text-gray-500">No orders yet from your distributors' accounts.</p>
    <p class="text-sm text-gray-400 mt-2">Orders from clinics assigned to your distributors will appear here.</p>
  </div>
<?php else: ?>
  <div class="card overflow-hidden">
    <table class="w-full">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left py-3 px-4">Order</th>
          <th class="text-left py-3 px-4">Distributor</th>
          <th class="text-left py-3 px-4">Clinic</th>
          <th class="text-left py-3 px-4">Patient</th>
          <th class="text-center py-3 px-4">Status</th>
          <th class="text-right py-3 px-4">Revenue</th>
          <th class="text-right py-3 px-4">Your Override</th>
          <th class="text-right py-3 px-4">Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <?php $override = (float)$order['expected_revenue'] * $overrideRate; ?>
          <tr class="border-t border-gray-100 hover:bg-gray-50">
            <td class="py-4 px-4">
              <span class="text-sm font-medium text-indigo-600">
                #<?= htmlspecialchars(substr($order['order_number'] ?: $order['id'], 0, 8)) ?>
              </span>
            </td>
            <td class="py-4 px-4">
              <div class="text-sm font-medium text-purple-600">
                <?= htmlspecialchars($order['dist_company'] ?: $order['dist_first'] . ' ' . $order['dist_last']) ?>
              </div>
            </td>
            <td class="py-4 px-4">
              <div class="text-sm text-gray-600">
                <?= htmlspecialchars($order['practice_name'] ?: $order['phys_first'] . ' ' . $order['phys_last']) ?>
              </div>
            </td>
            <td class="py-4 px-4">
              <div class="text-sm text-gray-600">
                <?= htmlspecialchars($order['patient_first'] . ' ' . $order['patient_last']) ?>
              </div>
            </td>
            <td class="py-4 px-4 text-center">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                <?php
                switch ($order['status']) {
                  case 'delivered': echo 'bg-green-100 text-green-800'; break;
                  case 'shipped': echo 'bg-blue-100 text-blue-800'; break;
                  case 'approved': echo 'bg-teal-100 text-teal-800'; break;
                  case 'submitted': echo 'bg-yellow-100 text-yellow-800'; break;
                  case 'rejected': echo 'bg-red-100 text-red-800'; break;
                  case 'cancelled': echo 'bg-gray-100 text-gray-500'; break;
                  default: echo 'bg-gray-100 text-gray-800';
                }
                ?>">
                <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
              </span>
            </td>
            <td class="py-4 px-4 text-right">
              <span class="font-medium text-green-600">$<?= number_format((float)$order['expected_revenue'], 0) ?></span>
            </td>
            <td class="py-4 px-4 text-right">
              <span class="font-medium text-purple-600">$<?= number_format($override, 0) ?></span>
            </td>
            <td class="py-4 px-4 text-right text-sm text-gray-500">
              <?= date('M j', strtotime($order['created_at'])) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between mt-4">
      <div class="text-sm text-gray-500">
        Showing <?= $offset + 1 ?> - <?= min($offset + $perPage, $totalOrders) ?> of <?= $totalOrders ?>
      </div>
      <div class="flex gap-2">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?>" class="btn">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page + 1 ?>" class="btn">Next</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
