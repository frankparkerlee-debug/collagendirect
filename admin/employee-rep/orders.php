<?php
/**
 * Employee Sales Rep - Orders
 *
 * Shows orders from direct accounts only.
 * For distributor orders, see distributor-activity.php
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

$adminId = $admin['id'];

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Get total count
$countStmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM orders o
  JOIN users u ON u.id = o.user_id
  WHERE u.employee_rep_id = ?
  AND o.status NOT IN ('draft')
");
$countStmt->execute([$adminId]);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);

// Get orders
$ordersStmt = $pdo->prepare("
  SELECT o.id, o.order_number, o.status, o.created_at, o.expected_revenue, o.product,
         p.first_name as patient_first, p.last_name as patient_last,
         u.practice_name, u.first_name as phys_first, u.last_name as phys_last
  FROM orders o
  JOIN patients p ON p.id = o.patient_id
  JOIN users u ON u.id = o.user_id
  WHERE u.employee_rep_id = ?
  AND o.status NOT IN ('draft')
  ORDER BY o.created_at DESC
  LIMIT ? OFFSET ?
");
$ordersStmt->execute([$adminId, $perPage, $offset]);
$orders = $ordersStmt->fetchAll();
?>

<div class="mb-6 flex items-center justify-between">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Orders</h2>
    <p class="text-gray-600 mt-1">Orders from your direct accounts</p>
  </div>
  <div class="text-sm text-gray-500">
    <?= $totalOrders ?> total orders
  </div>
</div>

<?php if (empty($orders)): ?>
  <div class="card p-6 text-center">
    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>
    <p class="text-gray-500">No orders yet from your direct accounts.</p>
  </div>
<?php else: ?>
  <div class="card overflow-hidden">
    <table class="w-full">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left py-3 px-4">Order</th>
          <th class="text-left py-3 px-4">Patient</th>
          <th class="text-left py-3 px-4">Clinic</th>
          <th class="text-left py-3 px-4">Product</th>
          <th class="text-center py-3 px-4">Status</th>
          <th class="text-right py-3 px-4">Revenue</th>
          <th class="text-right py-3 px-4">Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr class="border-t border-gray-100 hover:bg-gray-50">
            <td class="py-4 px-4">
              <span class="text-sm font-medium text-indigo-600">
                #<?= htmlspecialchars(substr($order['order_number'] ?: $order['id'], 0, 8)) ?>
              </span>
            </td>
            <td class="py-4 px-4">
              <div class="font-medium text-gray-900">
                <?= htmlspecialchars($order['patient_first'] . ' ' . $order['patient_last']) ?>
              </div>
            </td>
            <td class="py-4 px-4">
              <div class="text-sm text-gray-600">
                <?= htmlspecialchars($order['practice_name'] ?: $order['phys_first'] . ' ' . $order['phys_last']) ?>
              </div>
            </td>
            <td class="py-4 px-4">
              <div class="text-sm text-gray-600"><?= htmlspecialchars($order['product'] ?? '-') ?></div>
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
            <td class="py-4 px-4 text-right text-sm text-gray-500">
              <?= date('M j, Y', strtotime($order['created_at'])) ?>
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
