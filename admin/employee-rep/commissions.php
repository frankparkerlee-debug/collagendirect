<?php
/**
 * Employee Sales Rep - Commissions
 *
 * Shows commission earnings from both direct accounts and distributor overrides.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

$adminId = $admin['id'];

// Get commission rates
$directRate = get_employee_rep_rate($pdo, $adminId, 'direct');
$overrideRate = get_employee_rep_rate($pdo, $adminId, 'distributor_override');

// Get commission balance
$balance = get_employee_rep_balance($pdo, $adminId);

// Get commission ledger entries
$ledgerStmt = $pdo->prepare("
  SELECT erl.*,
         o.order_number,
         u.practice_name, u.first_name as clinic_first, u.last_name as clinic_last,
         sr.company_name as dist_company,
         du.first_name as dist_first, du.last_name as dist_last
  FROM employee_rep_ledger erl
  LEFT JOIN orders o ON o.id = erl.order_id
  LEFT JOIN users u ON u.id = erl.clinic_id
  LEFT JOIN sales_reps sr ON sr.id = erl.distributor_id
  LEFT JOIN users du ON du.id = sr.user_id
  WHERE erl.admin_user_id = ?
  ORDER BY erl.created_at DESC
  LIMIT 50
");
$ledgerStmt->execute([$adminId]);
$ledgerEntries = $ledgerStmt->fetchAll();

// Monthly summary
$monthlySummaryStmt = $pdo->prepare("
  SELECT
    DATE_TRUNC('month', payment_date) as month,
    source_type,
    SUM(collected_amount) as collected,
    SUM(commission_amount) as commission
  FROM employee_rep_ledger
  WHERE admin_user_id = ?
  AND payment_date IS NOT NULL
  GROUP BY DATE_TRUNC('month', payment_date), source_type
  ORDER BY month DESC
  LIMIT 12
");
$monthlySummaryStmt->execute([$adminId]);
$monthlySummary = $monthlySummaryStmt->fetchAll();

// Group by month
$monthlyData = [];
foreach ($monthlySummary as $row) {
  $month = $row['month'];
  if (!isset($monthlyData[$month])) {
    $monthlyData[$month] = ['direct' => 0, 'override' => 0, 'direct_collected' => 0, 'override_collected' => 0];
  }
  if ($row['source_type'] === 'direct') {
    $monthlyData[$month]['direct'] = (float)$row['commission'];
    $monthlyData[$month]['direct_collected'] = (float)$row['collected'];
  } else {
    $monthlyData[$month]['override'] = (float)$row['commission'];
    $monthlyData[$month]['override_collected'] = (float)$row['collected'];
  }
}
?>

<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Commissions</h2>
  <p class="text-gray-600 mt-1">Track your earnings from direct accounts and distributor overrides</p>
</div>

<!-- Commission Rates -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
  <div class="card p-5 bg-blue-50 border-blue-200">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-blue-600">Direct Commission Rate</p>
        <p class="text-3xl font-bold text-blue-700 mt-1"><?= number_format($directRate * 100, 0) ?>%</p>
        <p class="text-xs text-blue-500 mt-1">On revenue from your direct accounts</p>
      </div>
      <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
        </svg>
      </div>
    </div>
  </div>

  <div class="card p-5 bg-purple-50 border-purple-200">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-purple-600">Override Commission Rate</p>
        <p class="text-3xl font-bold text-purple-700 mt-1"><?= number_format($overrideRate * 100, 0) ?>%</p>
        <p class="text-xs text-purple-500 mt-1">On revenue from your managed distributors</p>
      </div>
      <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
      </div>
    </div>
  </div>
</div>

<!-- Lifetime Summary -->
<div class="card p-6 mb-6 bg-gradient-to-r from-indigo-50 to-purple-50 border-indigo-200">
  <h3 class="text-lg font-semibold text-gray-900 mb-4">Lifetime Earnings</h3>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="text-center p-4 bg-white rounded-lg shadow-sm">
      <p class="text-sm text-gray-500 mb-1">Direct Commission</p>
      <p class="text-xl font-bold text-blue-600">$<?= number_format((float)$balance['direct_earned'], 2) ?></p>
    </div>
    <div class="text-center p-4 bg-white rounded-lg shadow-sm">
      <p class="text-sm text-gray-500 mb-1">Override Commission</p>
      <p class="text-xl font-bold text-purple-600">$<?= number_format((float)$balance['override_earned'], 2) ?></p>
    </div>
    <div class="text-center p-4 bg-indigo-600 text-white rounded-lg shadow-sm">
      <p class="text-sm text-indigo-100 mb-1">Total Earned</p>
      <p class="text-2xl font-bold">$<?= number_format((float)$balance['total_earned'], 2) ?></p>
    </div>
  </div>
</div>

<!-- Monthly Summary -->
<?php if (!empty($monthlyData)): ?>
<div class="card mb-6">
  <div class="p-4 border-b border-gray-100">
    <h3 class="text-lg font-semibold text-gray-900">Monthly Breakdown</h3>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left py-3 px-4">Month</th>
          <th class="text-right py-3 px-4">Direct Collected</th>
          <th class="text-right py-3 px-4">Direct Commission</th>
          <th class="text-right py-3 px-4">Override Collected</th>
          <th class="text-right py-3 px-4">Override Commission</th>
          <th class="text-right py-3 px-4">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($monthlyData as $month => $data): ?>
          <tr class="border-t border-gray-100">
            <td class="py-3 px-4 font-medium"><?= date('M Y', strtotime($month)) ?></td>
            <td class="py-3 px-4 text-right text-gray-600">$<?= number_format($data['direct_collected'], 0) ?></td>
            <td class="py-3 px-4 text-right text-blue-600 font-medium">$<?= number_format($data['direct'], 2) ?></td>
            <td class="py-3 px-4 text-right text-gray-600">$<?= number_format($data['override_collected'], 0) ?></td>
            <td class="py-3 px-4 text-right text-purple-600 font-medium">$<?= number_format($data['override'], 2) ?></td>
            <td class="py-3 px-4 text-right text-indigo-600 font-bold">$<?= number_format($data['direct'] + $data['override'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Commission Ledger -->
<div class="card">
  <div class="p-4 border-b border-gray-100">
    <h3 class="text-lg font-semibold text-gray-900">Recent Transactions</h3>
  </div>
  <?php if (empty($ledgerEntries)): ?>
    <div class="p-6 text-center">
      <p class="text-gray-500">No commission transactions yet.</p>
      <p class="text-sm text-gray-400 mt-2">Commission entries will appear here when payments are collected.</p>
    </div>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left py-3 px-4">Date</th>
            <th class="text-left py-3 px-4">Source</th>
            <th class="text-left py-3 px-4">Order</th>
            <th class="text-left py-3 px-4">Clinic/Distributor</th>
            <th class="text-right py-3 px-4">Collected</th>
            <th class="text-right py-3 px-4">Rate</th>
            <th class="text-right py-3 px-4">Commission</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ledgerEntries as $entry): ?>
            <tr class="border-t border-gray-100">
              <td class="py-3 px-4 text-sm text-gray-600">
                <?= $entry['payment_date'] ? date('M j, Y', strtotime($entry['payment_date'])) : '-' ?>
              </td>
              <td class="py-3 px-4">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                  <?= $entry['source_type'] === 'direct' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' ?>">
                  <?= $entry['source_type'] === 'direct' ? 'Direct' : 'Override' ?>
                </span>
              </td>
              <td class="py-3 px-4 text-sm">
                <?php if ($entry['order_number']): ?>
                  #<?= htmlspecialchars(substr($entry['order_number'], 0, 8)) ?>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <td class="py-3 px-4 text-sm text-gray-600">
                <?php if ($entry['source_type'] === 'direct'): ?>
                  <?= htmlspecialchars($entry['practice_name'] ?: $entry['clinic_first'] . ' ' . $entry['clinic_last']) ?>
                <?php else: ?>
                  <?= htmlspecialchars($entry['dist_company'] ?: $entry['dist_first'] . ' ' . $entry['dist_last']) ?>
                <?php endif; ?>
              </td>
              <td class="py-3 px-4 text-right text-gray-600">
                $<?= number_format((float)$entry['collected_amount'], 2) ?>
              </td>
              <td class="py-3 px-4 text-right text-gray-500">
                <?= number_format((float)$entry['commission_rate'] * 100, 0) ?>%
              </td>
              <td class="py-3 px-4 text-right font-medium
                <?= $entry['source_type'] === 'direct' ? 'text-blue-600' : 'text-purple-600' ?>">
                $<?= number_format((float)$entry['commission_amount'], 2) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
