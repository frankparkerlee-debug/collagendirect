<?php
/**
 * Sales Rep: Assignment Requests
 *
 * Request to be assigned to existing clinics and view request status.
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

// Handle new request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {
  verify_csrf();

  $clinicId = $_POST['clinic_id'] ?? '';
  $reason = trim($_POST['reason'] ?? '');

  if (!$clinicId) {
    $error = 'Please select a clinic.';
  } else {
    // Check if clinic exists and is not already assigned
    $checkStmt = $pdo->prepare("SELECT id, assigned_rep_id, practice_name, first_name, last_name FROM users WHERE id = ?");
    $checkStmt->execute([$clinicId]);
    $clinic = $checkStmt->fetch();

    if (!$clinic) {
      $error = 'Clinic not found.';
    } elseif ($clinic['assigned_rep_id'] === $repId) {
      $error = 'This clinic is already assigned to you.';
    } else {
      // Check for existing pending request
      $existingStmt = $pdo->prepare("SELECT id FROM rep_assignment_requests WHERE rep_id = ? AND clinic_id = ? AND status = 'pending'");
      $existingStmt->execute([$repId, $clinicId]);
      if ($existingStmt->fetch()) {
        $error = 'You already have a pending request for this clinic.';
      } else {
        // Create request
        $pdo->prepare("
          INSERT INTO rep_assignment_requests (rep_id, clinic_id, reason, status, created_at, updated_at)
          VALUES (?, ?, ?, 'pending', NOW(), NOW())
        ")->execute([$repId, $clinicId, $reason ?: null]);

        $clinicName = $clinic['practice_name'] ?: $clinic['first_name'] . ' ' . $clinic['last_name'];
        $message = 'Assignment request submitted for "' . htmlspecialchars($clinicName) . '". An admin will review your request.';
      }
    }
  }
}

// Get pending requests
$pendingStmt = $pdo->prepare("
  SELECT r.id, r.clinic_id, r.reason, r.status, r.created_at, r.reviewed_at, r.denial_reason,
         u.practice_name, u.first_name, u.last_name, u.email,
         reviewer.first_name as reviewer_first, reviewer.last_name as reviewer_last
  FROM rep_assignment_requests r
  JOIN users u ON u.id = r.clinic_id
  LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
  WHERE r.rep_id = ?
  ORDER BY r.created_at DESC
");
$pendingStmt->execute([$repId]);
$requests = $pendingStmt->fetchAll();

// Get clinics available for request (not assigned to this rep, no pending request)
$availableClinicsStmt = $pdo->prepare("
  SELECT u.id, u.practice_name, u.first_name, u.last_name, u.email, u.city, u.state, u.role
  FROM users u
  WHERE (u.role IN ('physician', 'practice_admin') OR u.role IS NULL)
  AND (u.assigned_rep_id IS NULL OR u.assigned_rep_id != ?)
  AND u.id NOT IN (
    SELECT clinic_id FROM rep_assignment_requests WHERE rep_id = ? AND status = 'pending'
  )
  ORDER BY u.practice_name, u.last_name, u.first_name
  LIMIT 100
");
$availableClinicsStmt->execute([$repId, $repId]);
$availableClinics = $availableClinicsStmt->fetchAll();
?>

<!-- Page Header -->
<div class="mb-6">
  <a href="/admin/rep/clinics.php" class="text-brand hover:underline text-sm">&larr; Back to My Clinics</a>
  <h2 class="text-2xl font-bold text-gray-900 mt-2">Assignment Requests</h2>
  <p class="text-gray-600 mt-1">Request to be assigned to existing clinics in the system.</p>
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

<!-- New Request Form -->
<div class="card p-6 mb-6">
  <h3 class="text-lg font-medium text-gray-900 mb-4">Request New Assignment</h3>
  <?php if (empty($availableClinics)): ?>
    <p class="text-gray-500">No available clinics to request at this time. All clinics are either already assigned to you or have pending requests.</p>
  <?php else: ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="request">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Select Clinic <span class="text-red-500">*</span></label>
          <select name="clinic_id" required class="w-full">
            <option value="">Choose a clinic...</option>
            <?php foreach ($availableClinics as $clinic): ?>
              <option value="<?= htmlspecialchars($clinic['id']) ?>">
                <?= htmlspecialchars($clinic['practice_name'] ?: $clinic['first_name'] . ' ' . $clinic['last_name']) ?>
                <?php if ($clinic['city'] || $clinic['state']): ?>
                  - <?= htmlspecialchars(trim($clinic['city'] . ', ' . $clinic['state'], ', ')) ?>
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Reason (Optional)</label>
          <input type="text" name="reason" placeholder="Why should you be assigned to this clinic?" class="w-full">
        </div>
      </div>

      <button type="submit" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Submit Request
      </button>
    </form>
  <?php endif; ?>
</div>

<!-- Request History -->
<div class="card">
  <div class="p-4 border-b border-gray-100">
    <h3 class="text-lg font-medium text-gray-900">Request History</h3>
  </div>

  <?php if (empty($requests)): ?>
    <div class="p-6 text-center text-gray-500">
      No assignment requests yet. Submit your first request above.
    </div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Clinic</th>
          <th>Reason</th>
          <th>Status</th>
          <th>Submitted</th>
          <th>Reviewed</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $req): ?>
          <tr>
            <td>
              <div class="font-medium"><?= htmlspecialchars($req['practice_name'] ?: $req['first_name'] . ' ' . $req['last_name']) ?></div>
              <div class="text-xs text-gray-500"><?= htmlspecialchars($req['email']) ?></div>
            </td>
            <td>
              <?php if ($req['reason']): ?>
                <span class="text-sm text-gray-600"><?= htmlspecialchars($req['reason']) ?></span>
              <?php else: ?>
                <span class="text-gray-400">-</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                <?php
                switch ($req['status']) {
                  case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                  case 'approved': echo 'bg-green-100 text-green-800'; break;
                  case 'denied': echo 'bg-red-100 text-red-800'; break;
                  default: echo 'bg-gray-100 text-gray-800';
                }
                ?>
              ">
                <?= ucfirst($req['status']) ?>
              </span>
              <?php if ($req['status'] === 'denied' && $req['denial_reason']): ?>
                <div class="text-xs text-red-600 mt-1"><?= htmlspecialchars($req['denial_reason']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="text-sm"><?= date('M j, Y', strtotime($req['created_at'])) ?></div>
              <div class="text-xs text-gray-500"><?= date('g:i A', strtotime($req['created_at'])) ?></div>
            </td>
            <td>
              <?php if ($req['reviewed_at']): ?>
                <div class="text-sm"><?= date('M j, Y', strtotime($req['reviewed_at'])) ?></div>
                <?php if ($req['reviewer_first']): ?>
                  <div class="text-xs text-gray-500">by <?= htmlspecialchars($req['reviewer_first'] . ' ' . $req['reviewer_last']) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-gray-400">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
