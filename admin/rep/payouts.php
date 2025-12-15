<?php
/**
 * Sales Rep: Payout History
 *
 * View past payout records.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

$repId = $admin['rep_id'] ?? null;
if (!$repId) {
  echo '<div class="card p-6"><p class="text-red-600">Sales rep profile not found.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

// Get payout summary
$summaryStmt = $pdo->prepare("
  SELECT
    COALESCE(SUM(amount), 0) as total_paid,
    COUNT(*) as payout_count,
    MAX(paid_at) as last_payout
  FROM rep_commission_payouts
  WHERE rep_id = ? AND status = 'completed'
");
$summaryStmt->execute([$repId]);
$summary = $summaryStmt->fetch();

// Get payout history
$payoutsStmt = $pdo->prepare("
  SELECT id, amount, payment_method, reference_number, period_start, period_end,
         status, paid_at, notes, created_at
  FROM rep_commission_payouts
  WHERE rep_id = ?
  ORDER BY created_at DESC
  LIMIT 100
");
$payoutsStmt->execute([$repId]);
$payouts = $payoutsStmt->fetchAll();
?>

<!-- Page Header -->
<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Payout History</h2>
  <p class="text-gray-600 mt-1">View your commission payout records</p>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Total Paid Out</p>
        <p class="text-2xl font-bold text-green-600 mt-1">$<?= number_format((float)$summary['total_paid'], 2) ?></p>
      </div>
      <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Total Payouts</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $summary['payout_count'] ?></p>
      </div>
      <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
        </svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Last Payout</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">
          <?= $summary['last_payout'] ? date('M j, Y', strtotime($summary['last_payout'])) : '-' ?>
        </p>
      </div>
      <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
      </div>
    </div>
  </div>
</div>

<?php if (empty($payouts)): ?>
  <div class="card p-8 text-center">
    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
    </svg>
    <h3 class="text-lg font-medium text-gray-900 mb-2">No Payouts Yet</h3>
    <p class="text-gray-500">Your payout history will appear here once you receive commission payments.</p>
  </div>
<?php else: ?>
  <!-- Payouts Table -->
  <div class="card overflow-hidden">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Period</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Reference</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payouts as $payout): ?>
          <tr>
            <td>
              <div class="text-sm font-medium"><?= date('M j, Y', strtotime($payout['paid_at'] ?: $payout['created_at'])) ?></div>
            </td>
            <td>
              <?php if ($payout['period_start'] && $payout['period_end']): ?>
                <div class="text-sm">
                  <?= date('M j', strtotime($payout['period_start'])) ?> - <?= date('M j, Y', strtotime($payout['period_end'])) ?>
                </div>
              <?php else: ?>
                <span class="text-gray-400">-</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="font-bold text-green-600">$<?= number_format((float)$payout['amount'], 2) ?></span>
            </td>
            <td>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                <?= ucfirst($payout['payment_method'] ?: 'Other') ?>
              </span>
            </td>
            <td>
              <?php if ($payout['reference_number']): ?>
                <span class="font-mono text-sm"><?= htmlspecialchars($payout['reference_number']) ?></span>
              <?php else: ?>
                <span class="text-gray-400">-</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                <?php
                switch ($payout['status']) {
                  case 'completed': echo 'bg-green-100 text-green-800'; break;
                  case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                  case 'failed': echo 'bg-red-100 text-red-800'; break;
                  default: echo 'bg-gray-100 text-gray-800';
                }
                ?>
              ">
                <?= ucfirst($payout['status']) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
