<?php
/**
 * Sales Rep: Commission Ledger
 *
 * View commission entries from orders placed by assigned clinics.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../lib/order_display.php';

$repId = $admin['rep_id'] ?? null;
if (!$repId) {
  echo '<div class="card p-6"><p class="text-red-600">Sales rep profile not found.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Get commission summary
$summaryStmt = $pdo->prepare("
  SELECT
    COALESCE(SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END), 0) as pending,
    COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END), 0) as paid,
    COALESCE(SUM(CASE WHEN status = 'voided' THEN commission_amount ELSE 0 END), 0) as voided,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
    COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count
  FROM rep_commission_ledger
  WHERE rep_id = ?
");
$summaryStmt->execute([$repId]);
$summary = $summaryStmt->fetch();

// Get ledger entries
$query = "
  SELECT cl.id, cl.order_id, cl.clinic_id, cl.collected_amount, cl.commission_rate, cl.commission_amount,
         cl.status, cl.payout_id, cl.created_at,
         u.practice_name, u.first_name, u.last_name,
         o.product
  FROM rep_commission_ledger cl
  LEFT JOIN users u ON u.id = cl.clinic_id
  LEFT JOIN orders o ON o.id = cl.order_id
  WHERE cl.rep_id = ?
";
$params = [$repId];

if ($statusFilter) {
  $query .= " AND cl.status = ?";
  $params[] = $statusFilter;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM rep_commission_ledger WHERE rep_id = ?" . ($statusFilter ? " AND status = ?" : ""));
$countParams = $statusFilter ? [$repId, $statusFilter] : [$repId];
$countStmt->execute($countParams);
$totalEntries = (int)$countStmt->fetch()['total'];

$query .= " ORDER BY cl.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$entriesStmt = $pdo->prepare($query);
$entriesStmt->execute($params);
$entries = $entriesStmt->fetchAll();

$totalPages = ceil($totalEntries / $perPage);
?>

<!-- Page Header -->
<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Commission Ledger</h2>
  <p class="text-gray-600 mt-1">Track your earned commissions from orders</p>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Pending Commissions</p>
        <p class="text-2xl font-bold text-yellow-600 mt-1">$<?= number_format((float)$summary['pending'], 2) ?></p>
        <p class="text-xs text-gray-400 mt-1"><?= $summary['pending_count'] ?> entries</p>
      </div>
      <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Paid Out</p>
        <p class="text-2xl font-bold text-green-600 mt-1">$<?= number_format((float)$summary['paid'], 2) ?></p>
        <p class="text-xs text-gray-400 mt-1"><?= $summary['paid_count'] ?> entries</p>
      </div>
      <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Total Earned</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">$<?= number_format((float)$summary['pending'] + (float)$summary['paid'], 2) ?></p>
      </div>
      <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card p-4 mb-6">
  <form method="GET" class="flex gap-4">
    <div>
      <select name="status" class="w-full">
        <option value="">All Statuses</option>
        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
        <option value="voided" <?= $statusFilter === 'voided' ? 'selected' : '' ?>>Voided</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($statusFilter): ?>
      <a href="/admin/rep/commissions.php" class="btn">Clear</a>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($entries)): ?>
  <div class="card p-8 text-center">
    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>
    <h3 class="text-lg font-medium text-gray-900 mb-2">No Commission Entries</h3>
    <p class="text-gray-500">Commission entries will appear here when payments are collected on orders from your clinics.</p>
  </div>
<?php else: ?>
  <!-- Ledger Table -->
  <div class="card overflow-hidden">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Order</th>
          <th>Clinic</th>
          <th>Collected</th>
          <th>Rate</th>
          <th>Commission</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $entry): ?>
          <tr>
            <td>
              <div class="text-sm"><?= date('M j, Y', strtotime($entry['created_at'])) ?></div>
            </td>
            <td>
              <span class="font-mono text-sm"><?= format_order_number_html(['id' => $entry['order_id']]) ?></span>
              <?php if ($entry['product']): ?>
                <div class="text-xs text-gray-500"><?= htmlspecialchars($entry['product']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="text-sm"><?= htmlspecialchars($entry['practice_name'] ?: $entry['first_name'] . ' ' . $entry['last_name']) ?></div>
            </td>
            <td>
              <span class="font-medium">$<?= number_format((float)$entry['collected_amount'], 2) ?></span>
            </td>
            <td>
              <span class="text-sm"><?= number_format((float)$entry['commission_rate'] * 100, 1) ?>%</span>
            </td>
            <td>
              <span class="font-medium text-green-600">$<?= number_format((float)$entry['commission_amount'], 2) ?></span>
            </td>
            <td>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                <?php
                switch ($entry['status']) {
                  case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                  case 'paid': echo 'bg-green-100 text-green-800'; break;
                  case 'voided': echo 'bg-red-100 text-red-800'; break;
                  default: echo 'bg-gray-100 text-gray-800';
                }
                ?>
              ">
                <?= ucfirst($entry['status']) ?>
              </span>
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
        <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($statusFilter) ?>" class="btn">&larr; Previous</a>
      <?php endif; ?>
      <span class="btn bg-gray-100">Page <?= $page ?> of <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($statusFilter) ?>" class="btn">Next &rarr;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
