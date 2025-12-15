<?php
/**
 * Sales Rep: Orders View
 *
 * View orders from clinics assigned to this rep (scoped view).
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

$repId = $admin['rep_id'] ?? null;
if (!$repId) {
  echo '<div class="card p-6"><p class="text-red-600">Sales rep profile not found.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$clinicFilter = $_GET['clinic'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Get assigned clinics for filter dropdown
$clinicsStmt = $pdo->prepare("
  SELECT id, practice_name, first_name, last_name
  FROM users WHERE assigned_rep_id = ?
  ORDER BY practice_name, last_name
");
$clinicsStmt->execute([$repId]);
$clinics = $clinicsStmt->fetchAll();

// Build orders query - SCOPED to assigned clinics only
$query = "
  SELECT o.id, o.status, o.created_at, o.product, o.payment_type, o.amount_due,
         p.first_name as patient_first, p.last_name as patient_last,
         u.id as clinic_id, u.practice_name, u.first_name as phys_first, u.last_name as phys_last
  FROM orders o
  JOIN patients p ON p.id = o.patient_id
  JOIN users u ON u.id = o.user_id
  WHERE u.assigned_rep_id = ?
  AND o.status NOT IN ('draft')
  AND o.deleted_at IS NULL
";
$params = [$repId];

if ($statusFilter) {
  $query .= " AND o.status = ?";
  $params[] = $statusFilter;
}

if ($clinicFilter) {
  $query .= " AND u.id = ?";
  $params[] = $clinicFilter;
}

if ($search) {
  $query .= " AND (p.first_name ILIKE ? OR p.last_name ILIKE ? OR o.product ILIKE ?)";
  $searchPattern = "%{$search}%";
  $params[] = $searchPattern;
  $params[] = $searchPattern;
  $params[] = $searchPattern;
}

// Get total count
$countQuery = str_replace("SELECT o.id, o.status", "SELECT COUNT(*) as total", $query);
$countQuery = preg_replace('/SELECT .* FROM orders/', 'SELECT COUNT(*) as total FROM orders', $query);
// Simpler approach - count separately
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders o JOIN users u ON u.id = o.user_id WHERE u.assigned_rep_id = ? AND o.status NOT IN ('draft') AND o.deleted_at IS NULL");
$countStmt->execute([$repId]);
$totalOrders = (int)$countStmt->fetch()['total'];

$query .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$ordersStmt = $pdo->prepare($query);
$ordersStmt->execute($params);
$orders = $ordersStmt->fetchAll();

$totalPages = ceil($totalOrders / $perPage);

// Status options
$statuses = ['submitted', 'under_review', 'approved', 'in_production', 'shipped', 'delivered', 'cancelled', 'rejected'];
?>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Orders</h2>
    <p class="text-gray-600 mt-1"><?= $totalOrders ?> total order<?= $totalOrders !== 1 ? 's' : '' ?> from your clinics</p>
  </div>
</div>

<!-- Filters -->
<div class="card p-4 mb-6">
  <form method="GET" class="flex flex-wrap gap-4">
    <div class="flex-1 min-w-[200px]">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search patient name or product..." class="w-full">
    </div>
    <div>
      <select name="status" class="w-full">
        <option value="">All Statuses</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <select name="clinic" class="w-full">
        <option value="">All Clinics</option>
        <?php foreach ($clinics as $c): ?>
          <option value="<?= htmlspecialchars($c['id']) ?>" <?= $clinicFilter === $c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['practice_name'] ?: $c['first_name'] . ' ' . $c['last_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($statusFilter || $clinicFilter || $search): ?>
      <a href="/admin/rep/orders.php" class="btn">Clear</a>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($orders)): ?>
  <div class="card p-8 text-center">
    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>
    <h3 class="text-lg font-medium text-gray-900 mb-2">No Orders Found</h3>
    <p class="text-gray-500">
      <?php if ($statusFilter || $clinicFilter || $search): ?>
        Try adjusting your filters.
      <?php else: ?>
        Orders from your assigned clinics will appear here.
      <?php endif; ?>
    </p>
  </div>
<?php else: ?>
  <!-- Orders Table -->
  <div class="card overflow-hidden">
    <table>
      <thead>
        <tr>
          <th>Patient</th>
          <th>Clinic</th>
          <th>Product</th>
          <th>Type</th>
          <th>Status</th>
          <th>Amount</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td>
              <div class="font-medium"><?= htmlspecialchars($order['patient_first'] . ' ' . $order['patient_last']) ?></div>
            </td>
            <td>
              <div class="text-sm"><?= htmlspecialchars($order['practice_name'] ?: $order['phys_first'] . ' ' . $order['phys_last']) ?></div>
            </td>
            <td>
              <div class="text-sm"><?= htmlspecialchars($order['product'] ?: '-') ?></div>
            </td>
            <td>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                <?= $order['payment_type'] === 'wholesale' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>
              ">
                <?= ucfirst($order['payment_type'] ?: 'Insurance') ?>
              </span>
            </td>
            <td>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                <?php
                switch ($order['status']) {
                  case 'delivered': echo 'bg-green-100 text-green-800'; break;
                  case 'shipped': echo 'bg-blue-100 text-blue-800'; break;
                  case 'approved': case 'in_production': echo 'bg-teal-100 text-teal-800'; break;
                  case 'submitted': case 'under_review': echo 'bg-yellow-100 text-yellow-800'; break;
                  case 'cancelled': case 'rejected': echo 'bg-red-100 text-red-800'; break;
                  default: echo 'bg-gray-100 text-gray-800';
                }
                ?>
              ">
                <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
              </span>
            </td>
            <td>
              <?php if ($order['amount_due']): ?>
                $<?= number_format((float)$order['amount_due'], 2) ?>
              <?php else: ?>
                <span class="text-gray-400">-</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="text-sm"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
              <div class="text-xs text-gray-500"><?= date('g:i A', strtotime($order['created_at'])) ?></div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="flex justify-center gap-2 mt-6">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($statusFilter) ?>&clinic=<?= urlencode($clinicFilter) ?>&q=<?= urlencode($search) ?>" class="btn">&larr; Previous</a>
      <?php endif; ?>
      <span class="btn bg-gray-100">Page <?= $page ?> of <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($statusFilter) ?>&clinic=<?= urlencode($clinicFilter) ?>&q=<?= urlencode($search) ?>" class="btn">Next &rarr;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
