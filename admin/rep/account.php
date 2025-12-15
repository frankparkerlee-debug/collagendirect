<?php
/**
 * Sales Rep: My Account
 *
 * View and update account information.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../../api/lib/commission.php';

$repId = $admin['rep_id'] ?? null;
if (!$repId) {
  echo '<div class="card p-6"><p class="text-red-600">Sales rep profile not found.</p></div>';
  require __DIR__ . '/_footer.php';
  exit;
}

// CSV Export for Payout History
if (isset($_GET['export']) && $_GET['export'] === 'payouts-csv') {
  $filename = 'payout_history_' . date('Y-m-d') . '.csv';

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=' . $filename);

  $output = fopen('php://output', 'w');

  // CSV Headers
  fputcsv($output, [
    'Date',
    'Amount',
    'Payment Method',
    'Reference #',
    'Period',
    'Status',
    'Notes'
  ]);

  // Fetch all payouts for this rep
  $exportQuery = "
    SELECT *
    FROM rep_commission_payouts
    WHERE rep_id = ?
    ORDER BY payout_date DESC
  ";
  $exportStmt = $pdo->prepare($exportQuery);
  $exportStmt->execute([$repId]);

  while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
    $period = '';
    if ($row['period_start'] && $row['period_end']) {
      $period = date('M j, Y', strtotime($row['period_start'])) . ' - ' . date('M j, Y', strtotime($row['period_end']));
    }
    fputcsv($output, [
      date('Y-m-d', strtotime($row['payout_date'])),
      number_format((float)$row['amount'], 2),
      ucfirst($row['payment_method'] ?? 'check'),
      $row['reference_number'] ?? '',
      $period,
      'Completed',
      $row['notes'] ?? ''
    ]);
  }

  fclose($output);
  exit;
}

$message = '';
$error = '';

// Get full rep profile
$repStmt = $pdo->prepare("
  SELECT sr.*, u.email, u.first_name, u.last_name, u.phone
  FROM sales_reps sr
  JOIN users u ON u.id = sr.user_id
  WHERE sr.id = ?
");
$repStmt->execute([$repId]);
$repProfile = $repStmt->fetch();

// Get current commission rate and effective date
$rateStmt = $pdo->prepare("
  SELECT rate, effective_date, created_at
  FROM rep_commission_rates
  WHERE rep_id = ?
  AND (effective_date IS NULL OR effective_date <= CURRENT_DATE)
  ORDER BY effective_date DESC NULLS LAST
  LIMIT 1
");
$rateStmt->execute([$repId]);
$currentRate = $rateStmt->fetch();

// Get payout history
$payoutsStmt = $pdo->prepare("
  SELECT *
  FROM rep_commission_payouts
  WHERE rep_id = ?
  ORDER BY payout_date DESC
  LIMIT 20
");
$payoutsStmt->execute([$repId]);
$payouts = $payoutsStmt->fetchAll();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
  verify_csrf();

  $currentPw = $_POST['current_password'] ?? '';
  $newPw = $_POST['new_password'] ?? '';
  $confirmPw = $_POST['confirm_password'] ?? '';

  if (!$currentPw || !$newPw || !$confirmPw) {
    $error = 'Please fill in all password fields.';
  } elseif ($newPw !== $confirmPw) {
    $error = 'New passwords do not match.';
  } elseif (strlen($newPw) < 8) {
    $error = 'New password must be at least 8 characters.';
  } else {
    // Verify current password
    $userStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $userStmt->execute([$admin['id']]);
    $user = $userStmt->fetch();

    if (!password_verify($currentPw, $user['password_hash'])) {
      $error = 'Current password is incorrect.';
    } else {
      $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")
          ->execute([password_hash($newPw, PASSWORD_DEFAULT), $admin['id']]);
      $message = 'Password updated successfully.';
    }
  }
}
?>

<!-- Page Header -->
<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">My Account</h2>
  <p class="text-gray-600 mt-1">View your profile and account settings</p>
</div>

<?php if ($message): ?>
  <div class="card p-4 mb-6 bg-green-50 border-green-200">
    <div class="flex items-center text-green-800">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
      <?= $message ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="card p-4 mb-6 bg-red-50 border-red-200">
    <div class="flex items-center text-red-800">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      <?= $error ?>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <!-- Profile Information -->
  <div class="card p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Profile Information</h3>

    <div class="space-y-4">
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Name</span>
        <span class="font-medium"><?= htmlspecialchars($repProfile['first_name'] . ' ' . $repProfile['last_name']) ?></span>
      </div>
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Email</span>
        <span class="font-medium"><?= htmlspecialchars($repProfile['email']) ?></span>
      </div>
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Phone</span>
        <span class="font-medium"><?= htmlspecialchars($repProfile['phone'] ?: '-') ?></span>
      </div>
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Company</span>
        <span class="font-medium"><?= htmlspecialchars($repProfile['company_name'] ?: '-') ?></span>
      </div>
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Status</span>
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
          <?php
          switch ($repProfile['status']) {
            case 'active': echo 'bg-green-100 text-green-800'; break;
            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
            case 'suspended': echo 'bg-red-100 text-red-800'; break;
            default: echo 'bg-gray-100 text-gray-800';
          }
          ?>
        ">
          <?= ucfirst($repProfile['status']) ?>
        </span>
      </div>
      <div class="flex justify-between py-3 border-b border-gray-100">
        <span class="text-gray-500">Member Since</span>
        <span class="font-medium"><?= date('F j, Y', strtotime($repProfile['created_at'])) ?></span>
      </div>
      <?php if ($currentRate): ?>
        <div class="flex justify-between py-3">
          <span class="text-gray-500">Commission Rate</span>
          <span class="font-medium text-green-600"><?= number_format((float)$currentRate['rate'] * 100, 1) ?>%</span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Change Password</h3>

    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
          <input type="password" name="current_password" required class="w-full">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
          <input type="password" name="new_password" required minlength="8" class="w-full">
          <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
          <input type="password" name="confirm_password" required class="w-full">
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </div>
    </form>
  </div>
</div>

<!-- Commission Terms -->
<div class="card p-6 mt-6">
  <h3 class="text-lg font-medium text-gray-900 mb-4">Commission Terms</h3>

  <div class="bg-teal-50 rounded-lg p-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div>
        <p class="text-sm text-gray-500 mb-1">Your Commission Rate</p>
        <p class="text-2xl font-bold text-teal-600"><?= $currentRate ? number_format((float)$currentRate['rate'] * 100, 0) : '25' ?>%</p>
      </div>
      <div>
        <p class="text-sm text-gray-500 mb-1">Effective Since</p>
        <p class="text-lg font-medium text-gray-900">
          <?php
          if ($currentRate) {
            echo $currentRate['effective_date']
              ? date('F j, Y', strtotime($currentRate['effective_date']))
              : date('F j, Y', strtotime($currentRate['created_at']));
          } else {
            echo 'N/A';
          }
          ?>
        </p>
      </div>
      <div>
        <p class="text-sm text-gray-500 mb-1">Calculation Basis</p>
        <p class="text-sm text-gray-700">Commission is calculated on collected payments, not order placement.</p>
      </div>
    </div>
  </div>

  <p class="text-sm text-gray-500 mt-4">
    Your commission is calculated when payment is collected from your assigned clinics.
    Commission entries appear in your ledger after payment is recorded.
    <a href="/admin/rep/commissions.php" class="text-teal-600 hover:underline">View Commission Ledger &rarr;</a>
  </p>
</div>

<!-- Signed Documents -->
<div class="card p-6 mt-6">
  <h3 class="text-lg font-medium text-gray-900 mb-4">Signed Documents</h3>

  <?php
  $docsStmt = $pdo->prepare("
    SELECT document_type, document_version, signed_at, signature_name
    FROM rep_signed_documents
    WHERE rep_id = ?
    ORDER BY signed_at DESC
  ");
  $docsStmt->execute([$repId]);
  $docs = $docsStmt->fetchAll();
  ?>

  <?php if (empty($docs)): ?>
    <p class="text-gray-500">No signed documents on file.</p>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr>
            <th class="text-left py-2">Document</th>
            <th class="text-left py-2">Version</th>
            <th class="text-left py-2">Signed By</th>
            <th class="text-left py-2">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $doc): ?>
            <tr class="border-t border-gray-100">
              <td class="py-2">
                <?php
                $docNames = [
                  'rep_agreement' => 'Sales Rep Agreement',
                  'baa' => 'Business Associate Agreement',
                  'nda' => 'Non-Disclosure Agreement',
                  'w9' => 'W-9 Form',
                ];
                echo htmlspecialchars($docNames[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type'])));
                ?>
              </td>
              <td class="py-2"><?= htmlspecialchars($doc['document_version'] ?: '-') ?></td>
              <td class="py-2"><?= htmlspecialchars($doc['signature_name']) ?></td>
              <td class="py-2"><?= date('M j, Y', strtotime($doc['signed_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Payout History -->
<div class="card p-6 mt-6">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-medium text-gray-900">Payout History</h3>
    <?php if (!empty($payouts)): ?>
      <a href="?export=payouts-csv" class="btn text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
        Export CSV
      </a>
    <?php endif; ?>
  </div>

  <?php if (empty($payouts)): ?>
    <p class="text-gray-500">No payouts yet.</p>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr>
            <th class="text-left py-2">Date</th>
            <th class="text-right py-2">Amount</th>
            <th class="text-left py-2">Method</th>
            <th class="text-left py-2">Reference #</th>
            <th class="text-left py-2">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payouts as $payout): ?>
            <tr class="border-t border-gray-100">
              <td class="py-2"><?= date('M j, Y', strtotime($payout['payout_date'])) ?></td>
              <td class="py-2 text-right font-medium text-green-600">$<?= number_format((float)$payout['amount'], 2) ?></td>
              <td class="py-2"><?= ucfirst($payout['payment_method'] ?? 'check') ?></td>
              <td class="py-2"><?= htmlspecialchars($payout['reference_number'] ?? '-') ?></td>
              <td class="py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                  Completed
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="text-sm text-gray-500 mt-4">
      Showing last 20 payouts.
      <a href="/admin/rep/payouts.php" class="text-teal-600 hover:underline">View all payouts &rarr;</a>
    </p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
