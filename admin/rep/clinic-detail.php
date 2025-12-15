<?php
/**
 * Sales Rep: Clinic Detail View
 *
 * Detailed view of a single clinic assigned to this sales rep.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

$repId = $admin['rep_id'] ?? null;
if (!$repId) {
  echo '<div class="card p-6"><p class="text-red-600">Sales rep profile not found.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

$clinicId = $_GET['id'] ?? '';
if (!$clinicId) {
  header('Location: /admin/rep/clinics.php');
  exit;
}

// Get clinic details
$query = "
  SELECT u.*,
         (SELECT COUNT(*) FROM patients p WHERE p.user_id = u.id) as patient_count,
         (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status NOT IN ('cancelled', 'rejected', 'draft')) as order_count,
         (SELECT COALESCE(SUM(o.product_price), 0) FROM orders o WHERE o.user_id = u.id AND o.status NOT IN ('cancelled', 'rejected', 'voided')) as total_revenue
  FROM users u
  WHERE u.id = ?
  AND u.assigned_rep_id = ?
";
$stmt = $pdo->prepare($query);
$stmt->execute([$clinicId, $repId]);
$clinic = $stmt->fetch();

if (!$clinic) {
  echo '<div class="card p-6"><p class="text-red-600">Clinic not found or not assigned to you.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

// Get recent orders
$ordersStmt = $pdo->prepare("
  SELECT o.id, o.created_at, o.status, o.amount_due, p.first_name as patient_first, p.last_name as patient_last
  FROM orders o
  LEFT JOIN patients p ON p.id = o.patient_id
  WHERE o.user_id = ?
  ORDER BY o.created_at DESC
  LIMIT 10
");
$ordersStmt->execute([$clinicId]);
$recentOrders = $ordersStmt->fetchAll();

// Get physicians linked to this practice (if it's a practice_admin)
$linkedPhysicians = [];
if ($clinic['role'] === 'practice_admin') {
  $physStmt = $pdo->prepare("
    SELECT pp.*, u.email as user_email, u.status as user_status
    FROM practice_physicians pp
    LEFT JOIN users u ON u.id = pp.physician_id
    WHERE pp.practice_admin_id = ?
    ORDER BY pp.last_name, pp.first_name
  ");
  $physStmt->execute([$clinicId]);
  $linkedPhysicians = $physStmt->fetchAll();
}

$statusColors = [
  'active' => 'bg-green-100 text-green-800',
  'inactive' => 'bg-gray-100 text-gray-800',
  'suspended' => 'bg-red-100 text-red-800',
];
?>

<!-- Page Header with Back Link -->
<div class="mb-6">
  <a href="/admin/rep/clinics.php" class="text-brand hover:underline text-sm">&larr; Back to My Clinics</a>

  <div class="flex items-start justify-between mt-2">
    <div>
      <h2 class="text-2xl font-bold text-gray-900">
        <?= htmlspecialchars($clinic['practice_name'] ?: $clinic['first_name'] . ' ' . $clinic['last_name']) ?>
      </h2>
      <?php if ($clinic['practice_name'] && ($clinic['first_name'] || $clinic['last_name'])): ?>
        <p class="text-gray-600">Owner: <?= htmlspecialchars($clinic['first_name'] . ' ' . $clinic['last_name']) ?></p>
      <?php endif; ?>
    </div>
    <div class="flex items-center gap-3">
      <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $statusColors[$clinic['status']] ?? 'bg-gray-100' ?>">
        <?= ucfirst($clinic['status'] ?? 'unknown') ?>
      </span>
      <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
        <?= $clinic['role'] === 'practice_admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>
      ">
        <?= $clinic['role'] === 'practice_admin' ? 'Practice' : 'Physician' ?>
      </span>
      <?php if ($clinic['account_type'] === 'wholesale' || !empty($clinic['is_hybrid'])): ?>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
          Wholesale
        </span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Total Orders</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $clinic['order_count'] ?></p>
      </div>
      <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Total Revenue</p>
        <p class="text-2xl font-bold text-green-600 mt-1">$<?= number_format((float)$clinic['total_revenue'], 2) ?></p>
      </div>
      <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Patients</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $clinic['patient_count'] ?></p>
      </div>
      <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Assigned</p>
        <p class="text-lg font-bold text-gray-900 mt-1">
          <?= $clinic['rep_assignment_date'] ? date('M j, Y', strtotime($clinic['rep_assignment_date'])) : 'N/A' ?>
        </p>
        <p class="text-xs text-gray-500">
          <?php
          switch ($clinic['rep_assigned_by']) {
            case 'self_onboard': echo 'You onboarded'; break;
            case 'admin_assign': echo 'Admin assigned'; break;
            case 'approved_request': echo 'Request approved'; break;
            default: echo '';
          }
          ?>
        </p>
      </div>
      <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
      </div>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <!-- Clinic Information -->
  <div class="lg:col-span-2">
    <div class="card mb-6">
      <div class="p-6 border-b">
        <h3 class="text-lg font-semibold">Clinic Information</h3>
      </div>
      <div class="p-6">
        <dl class="grid grid-cols-2 gap-x-8 gap-y-4">
          <div>
            <dt class="text-xs text-gray-500 uppercase tracking-wider">Email</dt>
            <dd class="mt-1"><?= htmlspecialchars($clinic['email']) ?></dd>
          </div>
          <div>
            <dt class="text-xs text-gray-500 uppercase tracking-wider">Phone</dt>
            <dd class="mt-1"><?= htmlspecialchars($clinic['phone'] ?? '-') ?></dd>
          </div>
          <div class="col-span-2">
            <dt class="text-xs text-gray-500 uppercase tracking-wider">Address</dt>
            <dd class="mt-1">
              <?php if ($clinic['address']): ?>
                <?= htmlspecialchars($clinic['address']) ?><br>
                <?= htmlspecialchars(trim($clinic['city'] . ', ' . $clinic['state'] . ' ' . $clinic['zip'])) ?>
              <?php else: ?>
                -
              <?php endif; ?>
            </dd>
          </div>
          <div>
            <dt class="text-xs text-gray-500 uppercase tracking-wider">NPI</dt>
            <dd class="mt-1"><?= htmlspecialchars($clinic['npi'] ?? '-') ?></dd>
          </div>
          <div>
            <dt class="text-xs text-gray-500 uppercase tracking-wider">Account Type</dt>
            <dd class="mt-1">
              <?php
              if (!empty($clinic['is_hybrid'])) {
                echo 'Referral & Wholesale';
              } elseif ($clinic['account_type'] === 'wholesale') {
                echo 'Wholesale Only';
              } else {
                echo 'Referral Only';
              }
              ?>
            </dd>
          </div>
          <div>
            <dt class="text-xs text-gray-500 uppercase tracking-wider">Member Since</dt>
            <dd class="mt-1"><?= date('M j, Y', strtotime($clinic['created_at'])) ?></dd>
          </div>
          <div>
            <dt class="text-xs text-gray-500 uppercase tracking-wider">Medical License</dt>
            <dd class="mt-1">
              <?php if ($clinic['license']): ?>
                <?= htmlspecialchars($clinic['license']) ?>
                <?php if ($clinic['license_state']): ?>(<?= $clinic['license_state'] ?>)<?php endif; ?>
              <?php else: ?>
                -
              <?php endif; ?>
            </dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- Recent Orders -->
    <div class="card">
      <div class="p-6 border-b flex items-center justify-between">
        <h3 class="text-lg font-semibold">Recent Orders</h3>
        <a href="/admin/rep/orders.php?clinic=<?= urlencode($clinicId) ?>" class="text-sm text-brand hover:underline">View All</a>
      </div>
      <?php if (empty($recentOrders)): ?>
        <div class="p-6 text-center text-gray-500">No orders yet.</div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table>
            <thead>
              <tr>
                <th>Order #</th>
                <th>Patient</th>
                <th>Date</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentOrders as $order): ?>
                <tr>
                  <td class="font-medium">#<?= $order['id'] ?></td>
                  <td><?= htmlspecialchars(($order['patient_first'] ?? '') . ' ' . ($order['patient_last'] ?? '')) ?: '-' ?></td>
                  <td class="text-sm text-gray-500"><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                  <td>$<?= number_format((float)$order['amount_due'], 2) ?></td>
                  <td>
                    <?php
                    $statusColors = [
                      'pending' => 'bg-yellow-100 text-yellow-800',
                      'processing' => 'bg-blue-100 text-blue-800',
                      'shipped' => 'bg-purple-100 text-purple-800',
                      'delivered' => 'bg-green-100 text-green-800',
                      'completed' => 'bg-green-100 text-green-800',
                      'cancelled' => 'bg-gray-100 text-gray-800',
                      'rejected' => 'bg-red-100 text-red-800',
                    ];
                    ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $statusColors[$order['status']] ?? 'bg-gray-100' ?>">
                      <?= ucfirst($order['status']) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Sidebar -->
  <div>
    <!-- Quick Actions -->
    <div class="card p-6 mb-6">
      <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
      <div class="space-y-3">
        <a href="/admin/rep/add-physician.php?clinic=<?= urlencode($clinicId) ?>" class="btn btn-primary w-full justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
          Add Physician
        </a>
        <?php if ($clinic['account_type'] === 'wholesale' || !empty($clinic['is_hybrid']) || !empty($clinic['has_dme_license'])): ?>
        <a href="/admin/rep/create-wholesale-order.php?clinic=<?= urlencode($clinicId) ?>" class="btn w-full justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
          Create Wholesale Order
        </a>
        <?php endif; ?>
        <a href="/admin/rep/orders.php?clinic=<?= urlencode($clinicId) ?>" class="btn w-full justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
          View All Orders
        </a>
      </div>
    </div>

    <!-- Linked Physicians -->
    <?php if ($clinic['role'] === 'practice_admin'): ?>
    <div class="card">
      <div class="p-4 border-b flex items-center justify-between">
        <h3 class="font-semibold">Physicians</h3>
        <a href="/admin/rep/add-physician.php?clinic=<?= urlencode($clinicId) ?>" class="text-sm text-brand hover:underline">+ Add</a>
      </div>
      <?php if (empty($linkedPhysicians)): ?>
        <div class="p-4 text-center text-gray-500 text-sm">No physicians linked to this practice.</div>
      <?php else: ?>
        <ul class="divide-y">
          <?php foreach ($linkedPhysicians as $phys): ?>
            <li class="p-4">
              <div class="font-medium text-sm"><?= htmlspecialchars($phys['first_name'] . ' ' . $phys['last_name']) ?></div>
              <?php if ($phys['physician_npi']): ?>
                <div class="text-xs text-gray-500">NPI: <?= htmlspecialchars($phys['physician_npi']) ?></div>
              <?php endif; ?>
              <?php if ($phys['user_status']): ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mt-1 <?= $phys['user_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                  <?= ucfirst($phys['user_status']) ?>
                </span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
