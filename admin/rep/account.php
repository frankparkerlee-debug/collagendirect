<?php
/**
 * Sales Rep: My Account
 *
 * View and update account information.
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

$repId = $admin['rep_id'] ?? null;
if (!$repId) {
  echo '<div class="card p-6"><p class="text-red-600">Sales rep profile not found.</p></div>';
  require __DIR__ . '/_footer.php';
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

// Get current commission rate
$rateStmt = $pdo->prepare("
  SELECT rate, effective_date
  FROM rep_commission_rates
  WHERE rep_id = ?
  AND (effective_date IS NULL OR effective_date <= CURRENT_DATE)
  ORDER BY effective_date DESC NULLS LAST
  LIMIT 1
");
$rateStmt->execute([$repId]);
$currentRate = $rateStmt->fetch();

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

<?php require __DIR__ . '/_footer.php'; ?>
