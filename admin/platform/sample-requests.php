<?php
/**
 * Sample Package Requests Management
 *
 * Admin interface to review, approve, and track sample package requests
 * from physicians.
 */
declare(strict_types=1);
require __DIR__ . '/../auth.php';
require_admin();

// Load permission helper
require_once __DIR__ . '/../lib/permissions.php';
require_once __DIR__ . '/../../api/lib/email_sender.php';

$admin = current_admin();
$adminId = $admin['id'] ?? null;

// Status filter
$statusFilter = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'approved', 'shipped', 'rejected', 'all'];
if (!in_array($statusFilter, $validStatuses)) {
  $statusFilter = 'pending';
}

$message = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $action = $_POST['action'] ?? '';
  $requestId = $_POST['request_id'] ?? '';

  if ($requestId) {
    try {
      switch ($action) {
        case 'approve':
          $pdo->prepare("
            UPDATE sample_package_requests
            SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
            WHERE id = ?
          ")->execute([$adminId, $requestId]);
          $message = 'Request approved. Ready to ship.';

          // Send approval email
          $req = $pdo->prepare("SELECT * FROM sample_package_requests WHERE id = ?")->execute([$requestId]);
          $req = $pdo->query("SELECT * FROM sample_package_requests WHERE id = " . $pdo->quote($requestId))->fetch();
          if ($req) {
            $subject = 'Your Sample Request Has Been Approved - CollagenDirect';
            $bodyContent = <<<HTML
<h2 style="color: #14b8a6; margin-bottom: 16px;">Great News!</h2>
<p style="margin-bottom: 16px;">Dear Dr. {$req['first_name']} {$req['last_name']},</p>
<p style="margin-bottom: 16px;">Your CollagenDirect sample request has been approved! We're preparing your sample kit and will ship it to:</p>
<div style="background: #f0fdfa; border-radius: 8px; padding: 16px; margin: 20px 0;">
  <p style="margin: 0; color: #374151;">
    {$req['ship_address']}<br>
    {$req['ship_city']}, {$req['ship_state']} {$req['ship_zip']}
  </p>
</div>
<p style="margin-bottom: 16px;">You'll receive tracking information once your package ships.</p>
<p style="margin-bottom: 8px;">Best regards,</p>
<p style="margin: 0; font-weight: bold;">The CollagenDirect Team</p>
HTML;
            $html = email_template($subject, $bodyContent);
            try {
              send_email($req['email'], $req['first_name'] . ' ' . $req['last_name'], $subject, $html, strip_tags($bodyContent));
            } catch (Exception $e) {
              error_log("Failed to send approval email: " . $e->getMessage());
            }
          }
          break;

        case 'mark_shipped':
          $trackingNumber = trim($_POST['tracking_number'] ?? '');
          $pdo->prepare("
            UPDATE sample_package_requests
            SET status = 'shipped', shipped_at = NOW(), tracking_number = ?, updated_at = NOW()
            WHERE id = ?
          ")->execute([$trackingNumber ?: null, $requestId]);
          $message = 'Marked as shipped.';

          // Send shipped notification
          $req = $pdo->query("SELECT * FROM sample_package_requests WHERE id = " . $pdo->quote($requestId))->fetch();
          if ($req) {
            $subject = 'Your Samples Have Shipped - CollagenDirect';
            $trackingInfo = $trackingNumber ? "<p style='margin-bottom: 16px;'><strong>Tracking Number:</strong> {$trackingNumber}</p>" : '';
            $bodyContent = <<<HTML
<h2 style="color: #14b8a6; margin-bottom: 16px;">Your Samples Are On The Way!</h2>
<p style="margin-bottom: 16px;">Dear Dr. {$req['first_name']} {$req['last_name']},</p>
<p style="margin-bottom: 16px;">Your CollagenDirect sample kit has shipped!</p>
{$trackingInfo}
<div style="background: #f0fdfa; border-radius: 8px; padding: 16px; margin: 20px 0;">
  <p style="margin: 0; font-weight: bold; color: #134e4a;">Shipping To:</p>
  <p style="margin: 8px 0 0 0; color: #374151;">
    {$req['ship_address']}<br>
    {$req['ship_city']}, {$req['ship_state']} {$req['ship_zip']}
  </p>
</div>
<p style="margin-bottom: 16px;">If you have any questions about our products, please don't hesitate to reach out.</p>
<p style="margin-bottom: 8px;">Best regards,</p>
<p style="margin: 0; font-weight: bold;">The CollagenDirect Team</p>
HTML;
            $html = email_template($subject, $bodyContent);
            try {
              send_email($req['email'], $req['first_name'] . ' ' . $req['last_name'], $subject, $html, strip_tags($bodyContent));
            } catch (Exception $e) {
              error_log("Failed to send shipped email: " . $e->getMessage());
            }
          }
          break;

        case 'reject':
          $reason = trim($_POST['rejection_reason'] ?? '');
          $pdo->prepare("
            UPDATE sample_package_requests
            SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_notes = ?, updated_at = NOW()
            WHERE id = ?
          ")->execute([$adminId, $reason ?: null, $requestId]);
          $message = 'Request rejected.';
          break;

        case 'delete':
          $pdo->prepare("DELETE FROM sample_package_requests WHERE id = ?")->execute([$requestId]);
          $message = 'Request deleted.';
          break;
      }
    } catch (PDOException $e) {
      error_log("Sample request action error: " . $e->getMessage());
      $error = 'An error occurred. Please try again.';
    }
  }
}

// Fetch requests
$whereClause = $statusFilter === 'all' ? '1=1' : 'status = ?';
$params = $statusFilter === 'all' ? [] : [$statusFilter];

$requests = $pdo->prepare("
  SELECT spr.*, au.display_name as reviewer_name
  FROM sample_package_requests spr
  LEFT JOIN admin_users au ON au.id = spr.reviewed_by
  WHERE {$whereClause}
  ORDER BY
    CASE status
      WHEN 'pending' THEN 1
      WHEN 'approved' THEN 2
      WHEN 'shipped' THEN 3
      WHEN 'rejected' THEN 4
    END,
    created_at DESC
");
$requests->execute($params);
$requests = $requests->fetchAll();

// Count by status
$statusCounts = $pdo->query("
  SELECT status, COUNT(*) as cnt
  FROM sample_package_requests
  GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

require __DIR__ . '/../_header.php';
?>

<div class="p-6 max-w-7xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Sample Package Requests</h1>
      <p class="text-gray-600 mt-1">Review and manage physician sample requests</p>
    </div>
    <a href="/samples/" target="_blank" class="btn">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
      </svg>
      View Request Page
    </a>
  </div>

  <?php if ($message): ?>
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <!-- Status Tabs -->
  <div class="flex gap-2 mb-6">
    <a href="?status=pending" class="px-4 py-2 rounded-lg text-sm font-medium transition
      <?= $statusFilter === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
      Pending <?php if (($statusCounts['pending'] ?? 0) > 0): ?><span class="ml-1 bg-yellow-200 text-yellow-900 px-2 py-0.5 rounded-full text-xs"><?= $statusCounts['pending'] ?></span><?php endif; ?>
    </a>
    <a href="?status=approved" class="px-4 py-2 rounded-lg text-sm font-medium transition
      <?= $statusFilter === 'approved' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
      Approved <?php if (($statusCounts['approved'] ?? 0) > 0): ?><span class="ml-1 bg-blue-200 text-blue-900 px-2 py-0.5 rounded-full text-xs"><?= $statusCounts['approved'] ?></span><?php endif; ?>
    </a>
    <a href="?status=shipped" class="px-4 py-2 rounded-lg text-sm font-medium transition
      <?= $statusFilter === 'shipped' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
      Shipped <?php if (($statusCounts['shipped'] ?? 0) > 0): ?><span class="ml-1 bg-green-200 text-green-900 px-2 py-0.5 rounded-full text-xs"><?= $statusCounts['shipped'] ?></span><?php endif; ?>
    </a>
    <a href="?status=rejected" class="px-4 py-2 rounded-lg text-sm font-medium transition
      <?= $statusFilter === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
      Rejected
    </a>
    <a href="?status=all" class="px-4 py-2 rounded-lg text-sm font-medium transition
      <?= $statusFilter === 'all' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
      All
    </a>
  </div>

  <?php if (empty($requests)): ?>
    <div class="card p-12 text-center">
      <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
      </svg>
      <p class="text-gray-500">No <?= $statusFilter !== 'all' ? htmlspecialchars($statusFilter) : '' ?> requests found.</p>
    </div>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($requests as $req): ?>
        <div class="card p-6">
          <div class="flex items-start justify-between gap-6">
            <!-- Left: Contact Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 mb-2">
                <h3 class="text-lg font-semibold text-gray-900">
                  Dr. <?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?>
                </h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                  <?php
                    switch ($req['status']) {
                      case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                      case 'approved': echo 'bg-blue-100 text-blue-800'; break;
                      case 'shipped': echo 'bg-green-100 text-green-800'; break;
                      case 'rejected': echo 'bg-red-100 text-red-800'; break;
                    }
                  ?>">
                  <?= ucfirst($req['status']) ?>
                </span>
              </div>

              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <span class="text-gray-500">Email:</span>
                  <a href="mailto:<?= htmlspecialchars($req['email']) ?>" class="text-brand hover:underline block">
                    <?= htmlspecialchars($req['email']) ?>
                  </a>
                </div>
                <div>
                  <span class="text-gray-500">Phone:</span>
                  <span class="block text-gray-900"><?= htmlspecialchars($req['phone']) ?></span>
                </div>
                <div>
                  <span class="text-gray-500">Practice:</span>
                  <span class="block text-gray-900"><?= htmlspecialchars($req['practice_name'] ?: '-') ?></span>
                </div>
                <div>
                  <span class="text-gray-500">Specialty:</span>
                  <span class="block text-gray-900"><?= htmlspecialchars($req['specialty'] ?: '-') ?></span>
                </div>
              </div>

              <div class="mt-4 pt-4 border-t border-gray-100">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                  <div>
                    <span class="text-gray-500">NPI:</span>
                    <span class="block text-gray-900"><?= htmlspecialchars($req['npi'] ?: '-') ?></span>
                  </div>
                  <div class="md:col-span-2">
                    <span class="text-gray-500">Ship To:</span>
                    <span class="block text-gray-900">
                      <?= htmlspecialchars($req['ship_address']) ?>,
                      <?= htmlspecialchars($req['ship_city']) ?>, <?= htmlspecialchars($req['ship_state']) ?> <?= htmlspecialchars($req['ship_zip']) ?>
                    </span>
                  </div>
                  <div>
                    <span class="text-gray-500">Submitted:</span>
                    <span class="block text-gray-900"><?= date('M j, Y g:ia', strtotime($req['created_at'])) ?></span>
                  </div>
                </div>

                <?php if ($req['how_heard']): ?>
                  <div class="mt-2 text-sm">
                    <span class="text-gray-500">How heard:</span>
                    <span class="text-gray-700"><?= htmlspecialchars($req['how_heard']) ?></span>
                  </div>
                <?php endif; ?>

                <?php if ($req['notes']): ?>
                  <div class="mt-2 text-sm">
                    <span class="text-gray-500">Notes:</span>
                    <span class="text-gray-700"><?= htmlspecialchars($req['notes']) ?></span>
                  </div>
                <?php endif; ?>

                <?php if ($req['tracking_number']): ?>
                  <div class="mt-2 text-sm">
                    <span class="text-gray-500">Tracking:</span>
                    <span class="text-gray-900 font-medium"><?= htmlspecialchars($req['tracking_number']) ?></span>
                  </div>
                <?php endif; ?>

                <?php if ($req['reviewer_name'] && $req['reviewed_at']): ?>
                  <div class="mt-2 text-xs text-gray-400">
                    Reviewed by <?= htmlspecialchars($req['reviewer_name']) ?> on <?= date('M j, Y', strtotime($req['reviewed_at'])) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Right: Actions -->
            <div class="flex flex-col gap-2">
              <?php if ($req['status'] === 'pending'): ?>
                <form method="POST" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="request_id" value="<?= htmlspecialchars($req['id']) ?>">
                  <button type="submit" class="btn btn-primary w-full">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Approve
                  </button>
                </form>
                <button type="button" onclick="showRejectModal('<?= htmlspecialchars($req['id']) ?>')" class="btn text-red-600 hover:bg-red-50">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                  Reject
                </button>
              <?php elseif ($req['status'] === 'approved'): ?>
                <button type="button" onclick="showShipModal('<?= htmlspecialchars($req['id']) ?>')" class="btn btn-primary w-full">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                  </svg>
                  Mark Shipped
                </button>
              <?php endif; ?>

              <?php if ($req['status'] === 'rejected'): ?>
                <form method="POST" onsubmit="return confirm('Delete this request permanently?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="request_id" value="<?= htmlspecialchars($req['id']) ?>">
                  <button type="submit" class="btn text-red-600 hover:bg-red-50 w-full">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50" style="display: none;">
  <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-4">Reject Request</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="request_id" id="rejectRequestId">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
        <textarea name="rejection_reason" rows="3" class="w-full" placeholder="Enter rejection reason..."></textarea>
      </div>
      <div class="flex gap-3">
        <button type="button" onclick="hideRejectModal()" class="btn flex-1">Cancel</button>
        <button type="submit" class="btn flex-1 bg-red-600 text-white border-red-600 hover:bg-red-700">Reject</button>
      </div>
    </form>
  </div>
</div>

<!-- Ship Modal -->
<div id="shipModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50" style="display: none;">
  <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-4">Mark as Shipped</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mark_shipped">
      <input type="hidden" name="request_id" id="shipRequestId">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Tracking Number (optional)</label>
        <input type="text" name="tracking_number" class="w-full" placeholder="Enter tracking number...">
      </div>
      <div class="flex gap-3">
        <button type="button" onclick="hideShipModal()" class="btn flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">Mark Shipped</button>
      </div>
    </form>
  </div>
</div>

<script>
function showRejectModal(requestId) {
  document.getElementById('rejectRequestId').value = requestId;
  document.getElementById('rejectModal').style.display = 'flex';
}

function hideRejectModal() {
  document.getElementById('rejectModal').style.display = 'none';
}

function showShipModal(requestId) {
  document.getElementById('shipRequestId').value = requestId;
  document.getElementById('shipModal').style.display = 'flex';
}

function hideShipModal() {
  document.getElementById('shipModal').style.display = 'none';
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    hideRejectModal();
    hideShipModal();
  }
});

// Close modals when clicking outside
document.getElementById('rejectModal').addEventListener('click', function(e) {
  if (e.target === this) hideRejectModal();
});
document.getElementById('shipModal').addEventListener('click', function(e) {
  if (e.target === this) hideShipModal();
});
</script>

<?php require __DIR__ . '/../_footer.php'; ?>
