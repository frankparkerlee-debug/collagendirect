<?php
/**
 * Employee Sales Rep - Direct Clinics
 *
 * Lists all clinics directly assigned to this employee sales rep.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

$adminId = $admin['id'];

// Get all direct clinics
$clinicsStmt = $pdo->prepare("
  SELECT u.id, u.email, u.first_name, u.last_name, u.practice_name, u.role,
         u.phone, u.city, u.state, u.rep_assignment_date, u.created_at,
         (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status NOT IN ('draft', 'cancelled', 'rejected')) as order_count,
         (SELECT COALESCE(SUM(o.expected_revenue), 0) FROM orders o WHERE o.user_id = u.id AND o.status NOT IN ('draft', 'cancelled', 'rejected')) as total_revenue
  FROM users u
  WHERE u.employee_rep_id = ?
  AND u.role IN ('physician', 'practice_admin')
  ORDER BY u.last_name, u.first_name
");
$clinicsStmt->execute([$adminId]);
$clinics = $clinicsStmt->fetchAll();
?>

<div class="mb-6 flex items-center justify-between">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">My Direct Clinics</h2>
    <p class="text-gray-600 mt-1">Physicians and practices directly assigned to you</p>
  </div>
  <div class="text-sm text-gray-500">
    <?= count($clinics) ?> total
  </div>
</div>

<?php if (empty($clinics)): ?>
  <div class="card p-6 text-center">
    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
    </svg>
    <p class="text-gray-500">No direct clinics assigned yet.</p>
    <p class="text-sm text-gray-400 mt-2">Contact your manager to get clinics assigned to you.</p>
  </div>
<?php else: ?>
  <div class="card overflow-hidden">
    <table class="w-full">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left py-3 px-4">Clinic</th>
          <th class="text-left py-3 px-4">Contact</th>
          <th class="text-left py-3 px-4">Type</th>
          <th class="text-right py-3 px-4">Orders</th>
          <th class="text-right py-3 px-4">Revenue</th>
          <th class="text-right py-3 px-4">Assigned</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clinics as $clinic): ?>
          <tr class="border-t border-gray-100 hover:bg-gray-50">
            <td class="py-4 px-4">
              <div class="font-medium text-gray-900">
                <?= htmlspecialchars($clinic['practice_name'] ?: $clinic['first_name'] . ' ' . $clinic['last_name']) ?>
              </div>
              <?php if ($clinic['city'] && $clinic['state']): ?>
                <div class="text-xs text-gray-400"><?= htmlspecialchars($clinic['city'] . ', ' . $clinic['state']) ?></div>
              <?php endif; ?>
            </td>
            <td class="py-4 px-4">
              <div class="text-sm text-gray-600"><?= htmlspecialchars($clinic['email']) ?></div>
              <?php if ($clinic['phone']): ?>
                <div class="text-xs text-gray-400"><?= htmlspecialchars($clinic['phone']) ?></div>
              <?php endif; ?>
            </td>
            <td class="py-4 px-4">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                <?= $clinic['role'] === 'practice_admin' ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-800' ?>">
                <?= $clinic['role'] === 'practice_admin' ? 'Practice' : 'Physician' ?>
              </span>
            </td>
            <td class="py-4 px-4 text-right">
              <span class="font-medium text-gray-900"><?= (int)$clinic['order_count'] ?></span>
            </td>
            <td class="py-4 px-4 text-right">
              <span class="font-medium text-green-600">$<?= number_format((float)$clinic['total_revenue'], 0) ?></span>
            </td>
            <td class="py-4 px-4 text-right text-sm text-gray-500">
              <?= $clinic['rep_assignment_date'] ? date('M j, Y', strtotime($clinic['rep_assignment_date'])) : '-' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
