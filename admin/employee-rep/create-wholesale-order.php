<?php
/**
 * Employee Sales Rep: Create Wholesale Order
 *
 * Create wholesale orders for assigned clinics only.
 * Simplified version - redirects to main wholesale order flow with clinic pre-selected.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

// Ensure adminId is an integer (admin_users.id is INTEGER, not VARCHAR)
$adminId = (int)$admin['id'];

// Get assigned clinics with wholesale capability
$clinicsStmt = $pdo->prepare("
  SELECT u.id, u.practice_name, u.first_name, u.last_name, u.email, u.phone,
         u.address, u.city, u.state, u.zip,
         pb.current_balance
  FROM users u
  LEFT JOIN practice_balances pb ON pb.user_id = u.id
  WHERE u.employee_rep_id = ?
  AND (u.account_type = 'wholesale' OR u.account_type = 'both' OR u.has_dme_license = TRUE OR u.is_hybrid = TRUE)
  ORDER BY u.practice_name, u.last_name
");
$clinicsStmt->execute([$adminId]);
$clinics = $clinicsStmt->fetchAll();

$selectedClinic = $_GET['clinic_id'] ?? '';
?>

<!-- Page Header -->
<div class="mb-6">
  <a href="/admin/employee-rep/clinics.php" class="text-brand hover:underline text-sm">&larr; Back to My Clinics</a>
  <h2 class="text-2xl font-bold text-gray-900 mt-2">Create Wholesale Order</h2>
  <p class="text-gray-600 mt-1">Select one of your assigned clinics to create a wholesale order.</p>
</div>

<?php if (empty($clinics)): ?>
  <div class="card p-8 text-center">
    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
    </svg>
    <h3 class="text-lg font-medium text-gray-900 mb-2">No Wholesale-Enabled Clinics</h3>
    <p class="text-gray-500 mb-4">You need to have clinics with wholesale capabilities assigned to you.</p>
    <a href="/admin/employee-rep/onboard-clinic.php" class="btn btn-primary">Onboard a Wholesale Clinic</a>
  </div>
<?php else: ?>
  <div class="card p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Select Clinic</h3>
    <p class="text-gray-600 text-sm mb-6">Choose a clinic to create an order for. You'll be redirected to the order creation form.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($clinics as $clinic): ?>
        <div class="border rounded-lg p-4 hover:border-brand hover:bg-brand-light/20 transition cursor-pointer group">
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <h4 class="font-medium text-gray-900 group-hover:text-brand">
                <?= htmlspecialchars($clinic['practice_name'] ?: $clinic['first_name'] . ' ' . $clinic['last_name']) ?>
              </h4>
              <p class="text-sm text-gray-500"><?= htmlspecialchars($clinic['email']) ?></p>
              <?php if ($clinic['city'] || $clinic['state']): ?>
                <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars(trim($clinic['city'] . ', ' . $clinic['state'], ', ')) ?></p>
              <?php endif; ?>
            </div>
            <?php if ($clinic['current_balance'] && (float)$clinic['current_balance'] > 0): ?>
              <div class="text-right">
                <span class="text-xs text-gray-500">Balance</span>
                <p class="text-sm font-medium text-amber-600">$<?= number_format((float)$clinic['current_balance'], 2) ?></p>
              </div>
            <?php endif; ?>
          </div>

          <!-- This would link to the main wholesale order creation flow with practice pre-selected -->
          <a href="/portal/wholesale/order.php?practice_id=<?= urlencode($clinic['id']) ?>&rep_mode=1" class="btn btn-primary w-full mt-4 justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Create Order
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Info Box -->
  <div class="card p-4 mt-6 bg-blue-50 border-blue-200">
    <div class="flex items-start">
      <svg class="w-5 h-5 text-blue-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      <div class="text-sm text-blue-800">
        <p class="font-medium mb-1">About Wholesale Orders</p>
        <ul class="list-disc list-inside space-y-1 text-blue-700">
          <li>Wholesale orders are billed directly to the clinic at wholesale prices</li>
          <li>Orders are tracked in the clinic's account balance</li>
          <li>Commissions are calculated based on your commission rate when payment is received</li>
        </ul>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
