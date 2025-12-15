<?php
/**
 * Sales Rep Detail View
 *
 * Comprehensive view of a single sales rep with all related data.
 * Accessible to: superadmin, manufacturer
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';

// Check permissions
$allowedRoles = ['superadmin', 'manufacturer'];
if (!in_array($admin['role'] ?? '', $allowedRoles)) {
  header('Location: /admin/');
  exit;
}

$repId = $_GET['id'] ?? '';
if (!$repId) {
  header('Location: /admin/sales-reps.php');
  exit;
}

// Handle form actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $action = $_POST['action'] ?? '';

  try {
    switch ($action) {
      case 'update_commission_rate':
        $newRate = floatval($_POST['new_rate'] ?? 0);
        $effectiveDate = $_POST['effective_date'] ?? date('Y-m-d');
        $notes = $_POST['notes'] ?? '';

        if ($newRate > 0 && $newRate <= 1) {
          $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, set_by, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
              ->execute([$repId, $newRate, $effectiveDate, $admin['id'], $notes ?: null]);
          $message = 'Commission rate updated successfully.';
        } else {
          $error = 'Invalid commission rate. Must be between 0 and 1 (e.g., 0.25 for 25%).';
        }
        break;

      case 'suspend_rep':
        $pdo->prepare("UPDATE sales_reps SET status = 'suspended', updated_at = NOW() WHERE id = ?")->execute([$repId]);
        $message = 'Sales rep suspended.';
        break;

      case 'reactivate_rep':
        $pdo->prepare("UPDATE sales_reps SET status = 'active', updated_at = NOW() WHERE id = ?")->execute([$repId]);
        $message = 'Sales rep reactivated.';
        break;

      case 'terminate_rep':
        $pdo->prepare("UPDATE sales_reps SET status = 'terminated', updated_at = NOW() WHERE id = ?")->execute([$repId]);
        $message = 'Sales rep terminated.';
        break;

      case 'record_payout':
        $amount = floatval($_POST['amount'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'check';
        $referenceNumber = $_POST['reference_number'] ?? '';
        $periodStart = $_POST['period_start'] ?? null;
        $periodEnd = $_POST['period_end'] ?? null;
        $notes = $_POST['notes'] ?? '';

        if ($amount > 0) {
          $pdo->prepare("INSERT INTO rep_commission_payouts (rep_id, amount, payment_method, reference_number, period_start, period_end, status, paid_at, processed_by, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW(), ?, ?, NOW())")
              ->execute([$repId, $amount, $paymentMethod, $referenceNumber ?: null, $periodStart ?: null, $periodEnd ?: null, $admin['id'], $notes ?: null]);
          $message = 'Payout recorded successfully.';
        } else {
          $error = 'Invalid payout amount.';
        }
        break;

      case 'unassign_clinic':
        $clinicUserId = $_POST['clinic_user_id'] ?? '';
        if ($clinicUserId) {
          $pdo->prepare("UPDATE users SET assigned_rep_id = NULL, rep_assignment_date = NULL WHERE id = ?")->execute([$clinicUserId]);
          $message = 'Clinic unassigned from this rep.';
        }
        break;
    }
  } catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
  }
}

// Fetch rep details
$repQuery = "
  SELECT sr.*,
    u.email, u.first_name, u.last_name, u.phone, u.created_at as user_created_at
  FROM sales_reps sr
  JOIN users u ON u.id = sr.user_id
  WHERE sr.id = ?
";
$repStmt = $pdo->prepare($repQuery);
$repStmt->execute([$repId]);
$rep = $repStmt->fetch();

if (!$rep) {
  header('Location: /admin/sales-reps.php');
  exit;
}

// Fetch current commission rate
$rateQuery = "
  SELECT rate, effective_date, set_by, notes, created_at
  FROM rep_commission_rates
  WHERE rep_id = ? AND (effective_date IS NULL OR effective_date <= CURRENT_DATE)
  ORDER BY effective_date DESC NULLS LAST
  LIMIT 1
";
$rateStmt = $pdo->prepare($rateQuery);
$rateStmt->execute([$repId]);
$currentRate = $rateStmt->fetch();

// Fetch commission rate history
$rateHistoryQuery = "
  SELECT rcr.*, au.name as set_by_name
  FROM rep_commission_rates rcr
  LEFT JOIN admin_users au ON au.id = rcr.set_by::uuid
  WHERE rcr.rep_id = ?
  ORDER BY rcr.created_at DESC
  LIMIT 20
";
$rateHistoryStmt = $pdo->prepare($rateHistoryQuery);
$rateHistoryStmt->execute([$repId]);
$rateHistory = $rateHistoryStmt->fetchAll();

// Fetch signed documents
$docsQuery = "
  SELECT * FROM rep_signed_documents
  WHERE rep_id = ?
  ORDER BY signed_at DESC
";
$docsStmt = $pdo->prepare($docsQuery);
$docsStmt->execute([$repId]);
$signedDocs = $docsStmt->fetchAll();

// Fetch assigned clinics
$clinicsQuery = "
  SELECT u.id, u.first_name, u.last_name, u.practice_name, u.email, u.npi,
    u.rep_assignment_date, u.rep_assigned_by,
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
    (SELECT SUM(total) FROM orders WHERE user_id = u.id AND status NOT IN ('cancelled', 'voided')) as total_revenue
  FROM users u
  WHERE u.assigned_rep_id = ?
  ORDER BY u.rep_assignment_date DESC
";
$clinicsStmt = $pdo->prepare($clinicsQuery);
$clinicsStmt->execute([$repId]);
$assignedClinics = $clinicsStmt->fetchAll();

// Performance metrics
$metricsQuery = "
  SELECT
    (SELECT COUNT(*) FROM users WHERE assigned_rep_id = :rep_id) as total_clinics,
    (SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id = u.id WHERE u.assigned_rep_id = :rep_id) as total_orders,
    (SELECT COALESCE(SUM(o.total), 0) FROM orders o JOIN users u ON o.user_id = u.id WHERE u.assigned_rep_id = :rep_id AND o.status NOT IN ('cancelled', 'voided')) as total_revenue,
    (SELECT COALESCE(SUM(commission_amount), 0) FROM rep_commission_ledger WHERE rep_id = :rep_id) as total_commission
";
$metricsStmt = $pdo->prepare($metricsQuery);
$metricsStmt->execute(['rep_id' => $repId]);
$metrics = $metricsStmt->fetch();

// Fetch commission ledger
$ledgerQuery = "
  SELECT rcl.*,
    o.id as order_number,
    u.practice_name as clinic_name, u.first_name as clinic_first, u.last_name as clinic_last
  FROM rep_commission_ledger rcl
  LEFT JOIN orders o ON o.id = rcl.order_id
  LEFT JOIN users u ON u.id = rcl.clinic_id
  WHERE rcl.rep_id = ?
  ORDER BY rcl.created_at DESC
  LIMIT 100
";
$ledgerStmt = $pdo->prepare($ledgerQuery);
$ledgerStmt->execute([$repId]);
$ledgerEntries = $ledgerStmt->fetchAll();

// Fetch payout history
$payoutsQuery = "
  SELECT rcp.*, au.name as processed_by_name
  FROM rep_commission_payouts rcp
  LEFT JOIN admin_users au ON au.id = rcp.processed_by::uuid
  WHERE rcp.rep_id = ?
  ORDER BY rcp.paid_at DESC
  LIMIT 50
";
$payoutsStmt = $pdo->prepare($payoutsQuery);
$payoutsStmt->execute([$repId]);
$payoutHistory = $payoutsStmt->fetchAll();

// Calculate current balance
$totalCommission = (float)($metrics['total_commission'] ?? 0);
$totalPaid = 0;
foreach ($payoutHistory as $p) {
  if ($p['status'] === 'completed') {
    $totalPaid += (float)$p['amount'];
  }
}
$currentBalance = $totalCommission - $totalPaid;

$statusColors = [
  'active' => 'bg-green-100 text-green-800',
  'pending' => 'bg-amber-100 text-amber-800',
  'suspended' => 'bg-red-100 text-red-800',
  'terminated' => 'bg-gray-100 text-gray-800',
];
?>

<!-- Page Header with Back Link -->
<div class="mb-6">
  <a href="/admin/sales-reps.php" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4">
    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
    Back to Rep Management
  </a>

  <div class="flex items-start justify-between">
    <div>
      <h2 class="text-2xl font-bold text-gray-900">
        <?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name']) ?>
      </h2>
      <p class="text-gray-600 mt-1">
        <?= htmlspecialchars($rep['email']) ?>
        <?php if ($rep['phone']): ?>
          · <?= htmlspecialchars($rep['phone']) ?>
        <?php endif; ?>
      </p>
    </div>
    <div class="flex items-center gap-3">
      <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $statusColors[$rep['status']] ?? 'bg-gray-100' ?>">
        <?= ucfirst($rep['status']) ?>
      </span>
      <?php if ($rep['status'] === 'active'): ?>
        <form method="POST" class="inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="suspend_rep">
          <button type="submit" class="btn text-amber-600" onclick="return confirm('Suspend this rep?')">Suspend</button>
        </form>
      <?php elseif ($rep['status'] === 'suspended'): ?>
        <form method="POST" class="inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reactivate_rep">
          <button type="submit" class="btn text-green-600">Reactivate</button>
        </form>
      <?php endif; ?>
      <?php if ($rep['status'] !== 'terminated'): ?>
        <form method="POST" class="inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="terminate_rep">
          <button type="submit" class="btn text-red-600" onclick="return confirm('Terminate this rep? This cannot be undone.')">Terminate</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($message): ?>
  <div class="card p-4 mb-6 bg-green-50 border-green-200">
    <div class="flex items-center text-green-800">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
      <?= htmlspecialchars($message) ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="card p-4 mb-6 bg-red-50 border-red-200">
    <div class="flex items-center text-red-800">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      <?= htmlspecialchars($error) ?>
    </div>
  </div>
<?php endif; ?>

<!-- Section 1: Profile Overview -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
  <div class="card p-6">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Profile Information</h3>
    <dl class="space-y-3">
      <div>
        <dt class="text-xs text-gray-500">Full Name</dt>
        <dd class="font-medium"><?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name']) ?></dd>
      </div>
      <?php if ($rep['company_name']): ?>
      <div>
        <dt class="text-xs text-gray-500">Company</dt>
        <dd><?= htmlspecialchars($rep['company_name']) ?></dd>
      </div>
      <?php endif; ?>
      <div>
        <dt class="text-xs text-gray-500">Email</dt>
        <dd><?= htmlspecialchars($rep['email']) ?></dd>
      </div>
      <div>
        <dt class="text-xs text-gray-500">Phone</dt>
        <dd><?= htmlspecialchars($rep['phone'] ?? '-') ?></dd>
      </div>
      <div>
        <dt class="text-xs text-gray-500">Application Date</dt>
        <dd><?= $rep['application_date'] ? date('M j, Y', strtotime($rep['application_date'])) : '-' ?></dd>
      </div>
      <?php if ($rep['approved_date']): ?>
      <div>
        <dt class="text-xs text-gray-500">Approved Date</dt>
        <dd><?= date('M j, Y', strtotime($rep['approved_date'])) ?></dd>
      </div>
      <?php endif; ?>
    </dl>
  </div>

  <!-- Section 2: Commission Agreement -->
  <div class="card p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Commission Agreement</h3>
      <button onclick="document.getElementById('rateModal').showModal()" class="text-sm text-teal-600 hover:underline">Edit Rate</button>
    </div>
    <div class="mb-4">
      <div class="text-3xl font-bold text-teal-600">
        <?= $currentRate ? number_format((float)$currentRate['rate'] * 100, 1) . '%' : 'Not Set' ?>
      </div>
      <div class="text-sm text-gray-500">Current Commission Rate</div>
      <?php if ($currentRate && $currentRate['effective_date']): ?>
        <div class="text-xs text-gray-400 mt-1">Effective: <?= date('M j, Y', strtotime($currentRate['effective_date'])) ?></div>
      <?php endif; ?>
    </div>

    <?php if (!empty($rateHistory)): ?>
    <div class="border-t pt-4">
      <h4 class="text-xs font-medium text-gray-500 mb-2">Rate History</h4>
      <div class="space-y-2 max-h-40 overflow-y-auto">
        <?php foreach (array_slice($rateHistory, 0, 5) as $rate): ?>
          <div class="flex justify-between text-sm">
            <span><?= number_format((float)$rate['rate'] * 100, 1) ?>%</span>
            <span class="text-gray-500"><?= $rate['effective_date'] ? date('M j, Y', strtotime($rate['effective_date'])) : 'Initial' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Quick Stats -->
  <div class="card p-6">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Quick Stats</h3>
    <div class="space-y-4">
      <div>
        <div class="text-2xl font-bold"><?= $metrics['total_clinics'] ?? 0 ?></div>
        <div class="text-sm text-gray-500">Assigned Clinics</div>
      </div>
      <div>
        <div class="text-2xl font-bold"><?= $metrics['total_orders'] ?? 0 ?></div>
        <div class="text-sm text-gray-500">Total Orders</div>
      </div>
      <div>
        <div class="text-2xl font-bold text-green-600">$<?= number_format((float)($metrics['total_revenue'] ?? 0), 2) ?></div>
        <div class="text-sm text-gray-500">Generated Revenue</div>
      </div>
    </div>
  </div>
</div>

<!-- Section 3: Signed Documents -->
<div class="card mb-6">
  <div class="p-6 border-b">
    <h3 class="text-lg font-semibold">Signed Documents</h3>
  </div>
  <?php if (empty($signedDocs)): ?>
    <div class="p-6 text-center text-gray-500">No signed documents on file.</div>
  <?php else: ?>
    <div class="divide-y">
      <?php foreach ($signedDocs as $doc): ?>
        <div class="p-4 flex items-center justify-between">
          <div>
            <div class="font-medium">
              <?php
              $docTypes = [
                'rep_agreement' => 'Sales Rep Agreement',
                'baa' => 'Business Associate Agreement (BAA)',
              ];
              echo $docTypes[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type']));
              ?>
            </div>
            <div class="text-sm text-gray-500">
              Signed <?= date('M j, Y \a\t g:i A', strtotime($doc['signed_at'])) ?>
            </div>
            <div class="text-xs text-gray-400 mt-1">
              IP: <?= htmlspecialchars($doc['ip_address'] ?? '-') ?>
            </div>
          </div>
          <div class="flex items-center gap-4">
            <div class="text-right">
              <div class="text-sm font-medium"><?= htmlspecialchars($doc['signer_name']) ?></div>
              <div class="text-xs text-gray-500">e-Signature</div>
            </div>
            <span class="text-green-600">
              <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Section 4: Assigned Clinics -->
<div class="card mb-6">
  <div class="p-6 border-b flex items-center justify-between">
    <h3 class="text-lg font-semibold">Assigned Clinics</h3>
    <span class="text-sm text-gray-500"><?= count($assignedClinics) ?> clinic<?= count($assignedClinics) !== 1 ? 's' : '' ?></span>
  </div>
  <?php if (empty($assignedClinics)): ?>
    <div class="p-6 text-center text-gray-500">No clinics assigned to this rep.</div>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table>
        <thead>
          <tr>
            <th>Clinic</th>
            <th>NPI</th>
            <th>Email</th>
            <th>Orders</th>
            <th>Revenue</th>
            <th>Assigned</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($assignedClinics as $clinic): ?>
            <tr>
              <td>
                <div class="font-medium">
                  <?= htmlspecialchars($clinic['practice_name'] ?: ($clinic['first_name'] . ' ' . $clinic['last_name'])) ?>
                </div>
                <?php if ($clinic['practice_name']): ?>
                  <div class="text-xs text-gray-500"><?= htmlspecialchars($clinic['first_name'] . ' ' . $clinic['last_name']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-sm"><?= htmlspecialchars($clinic['npi'] ?? '-') ?></td>
              <td class="text-sm"><?= htmlspecialchars($clinic['email']) ?></td>
              <td><?= $clinic['order_count'] ?? 0 ?></td>
              <td>$<?= number_format((float)($clinic['total_revenue'] ?? 0), 2) ?></td>
              <td class="text-sm text-gray-500">
                <?= $clinic['rep_assignment_date'] ? date('M j, Y', strtotime($clinic['rep_assignment_date'])) : '-' ?>
              </td>
              <td>
                <form method="POST" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="unassign_clinic">
                  <input type="hidden" name="clinic_user_id" value="<?= $clinic['id'] ?>">
                  <button type="submit" class="text-red-600 text-sm hover:underline" onclick="return confirm('Unassign this clinic from the rep?')">Unassign</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Section 5: Performance Metrics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" id="metrics">
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Total Commission Earned</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">$<?= number_format($totalCommission, 2) ?></p>
      </div>
      <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Total Paid Out</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">$<?= number_format($totalPaid, 2) ?></p>
      </div>
      <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Current Balance</p>
        <p class="text-2xl font-bold <?= $currentBalance > 0 ? 'text-amber-600' : 'text-gray-900' ?> mt-1">$<?= number_format($currentBalance, 2) ?></p>
      </div>
      <div class="w-10 h-10 rounded-full <?= $currentBalance > 0 ? 'bg-amber-100' : 'bg-gray-100' ?> flex items-center justify-center">
        <svg class="w-5 h-5 <?= $currentBalance > 0 ? 'text-amber-600' : 'text-gray-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
      </div>
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">Actions</p>
        <?php if ($currentBalance > 0): ?>
          <button onclick="openPayoutModal()" class="btn btn-primary mt-2 text-sm">Record Payout</button>
        <?php else: ?>
          <p class="text-sm text-gray-400 mt-2">No balance to pay</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Section 6: Commission Ledger -->
<div class="card mb-6" id="ledger">
  <div class="p-6 border-b flex items-center justify-between">
    <h3 class="text-lg font-semibold">Commission Ledger</h3>
    <button onclick="exportLedger()" class="btn text-sm">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
      Export CSV
    </button>
  </div>
  <?php if (empty($ledgerEntries)): ?>
    <div class="p-6 text-center text-gray-500">No commission entries yet.</div>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table id="ledgerTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>Order Type</th>
            <th>Order</th>
            <th>Clinic</th>
            <th>Collected</th>
            <th>Rate</th>
            <th>Commission</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ledgerEntries as $entry):
            $statusColors = [
              'pending' => 'bg-amber-100 text-amber-800',
              'paid' => 'bg-green-100 text-green-800',
              'voided' => 'bg-gray-100 text-gray-800',
            ];
          ?>
            <tr>
              <td class="text-sm"><?= date('M j, Y', strtotime($entry['payment_date'])) ?></td>
              <td class="text-sm"><?= ucfirst($entry['order_type']) ?></td>
              <td class="text-sm">
                <?php if ($entry['order_number']): ?>
                  <a href="/admin/orders.php?id=<?= $entry['order_id'] ?>" class="text-teal-600 hover:underline">#<?= $entry['order_number'] ?></a>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <td class="text-sm"><?= htmlspecialchars($entry['clinic_name'] ?? ($entry['clinic_first'] . ' ' . $entry['clinic_last'])) ?></td>
              <td class="text-sm">$<?= number_format((float)$entry['collected_amount'], 2) ?></td>
              <td class="text-sm"><?= number_format((float)$entry['commission_rate'] * 100, 1) ?>%</td>
              <td class="font-medium text-green-600">+$<?= number_format((float)$entry['commission_amount'], 2) ?></td>
              <td>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $statusColors[$entry['status']] ?? 'bg-gray-100' ?>">
                  <?= ucfirst($entry['status']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Section 7: Payout History -->
<div class="card mb-6" id="payouts">
  <div class="p-6 border-b">
    <h3 class="text-lg font-semibold">Payout History</h3>
  </div>
  <?php if (empty($payoutHistory)): ?>
    <div class="p-6 text-center text-gray-500">No payouts recorded yet.</div>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Reference</th>
            <th>Period</th>
            <th>Processed By</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payoutHistory as $payout): ?>
            <tr>
              <td class="text-sm"><?= date('M j, Y', strtotime($payout['paid_at'])) ?></td>
              <td class="font-medium">$<?= number_format((float)$payout['amount'], 2) ?></td>
              <td class="text-sm"><?= ucfirst($payout['payment_method']) ?></td>
              <td class="text-sm"><?= htmlspecialchars($payout['reference_number'] ?? '-') ?></td>
              <td class="text-sm text-gray-500">
                <?php if ($payout['period_start'] && $payout['period_end']): ?>
                  <?= date('M j', strtotime($payout['period_start'])) ?> - <?= date('M j, Y', strtotime($payout['period_end'])) ?>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <td class="text-sm"><?= htmlspecialchars($payout['processed_by_name'] ?? 'System') ?></td>
              <td>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $payout['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
                  <?= ucfirst($payout['status']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Edit Commission Rate Modal -->
<dialog id="rateModal" class="rounded-2xl w-full max-w-md p-0 backdrop:bg-black/50">
  <form method="POST" class="p-0">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_commission_rate">

    <div class="p-6 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold text-gray-900">Update Commission Rate</h3>
        <button type="button" onclick="document.getElementById('rateModal').close()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
    </div>

    <div class="p-6 space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">New Commission Rate</label>
        <div class="flex items-center gap-2">
          <input type="number" name="new_rate" id="newRate" value="<?= $currentRate['rate'] ?? 0.25 ?>" step="0.01" min="0.01" max="1" required class="w-24">
          <span class="text-gray-600">= <span id="newRatePercent"><?= $currentRate ? number_format((float)$currentRate['rate'] * 100, 0) : 25 ?></span>%</span>
        </div>
        <p class="text-xs text-gray-500 mt-1">Enter as decimal (e.g., 0.25 for 25%)</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Effective Date</label>
        <input type="date" name="effective_date" value="<?= date('Y-m-d') ?>" class="w-full">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
        <textarea name="notes" rows="2" class="w-full" placeholder="Reason for rate change..."></textarea>
      </div>
    </div>

    <div class="p-6 border-t border-gray-200 flex gap-3">
      <button type="button" onclick="document.getElementById('rateModal').close()" class="flex-1 btn">Cancel</button>
      <button type="submit" class="flex-1 btn btn-primary">Update Rate</button>
    </div>
  </form>
</dialog>

<!-- Record Payout Modal -->
<dialog id="payoutModal" class="rounded-2xl w-full max-w-lg p-0 backdrop:bg-black/50">
  <form method="POST" class="p-0">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="record_payout">

    <div class="p-6 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold text-gray-900">Record Payout</h3>
        <button type="button" onclick="document.getElementById('payoutModal').close()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
    </div>

    <div class="p-6 space-y-4">
      <div>
        <p class="text-sm text-gray-500">Current Balance</p>
        <p class="text-xl font-bold text-amber-600">$<?= number_format($currentBalance, 2) ?></p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payout Amount</label>
          <input type="number" name="amount" value="<?= number_format($currentBalance, 2, '.', '') ?>" step="0.01" min="0" required class="w-full">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
          <select name="payment_method" class="w-full">
            <option value="check">Check</option>
            <option value="ach">ACH</option>
            <option value="wire">Wire</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Period Start</label>
          <input type="date" name="period_start" class="w-full">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Period End</label>
          <input type="date" name="period_end" class="w-full">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
        <input type="text" name="reference_number" class="w-full" placeholder="Check # or Transaction ID">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
        <textarea name="notes" rows="2" class="w-full" placeholder="Optional notes..."></textarea>
      </div>
    </div>

    <div class="p-6 border-t border-gray-200 flex gap-3">
      <button type="button" onclick="document.getElementById('payoutModal').close()" class="flex-1 btn">Cancel</button>
      <button type="submit" class="flex-1 btn btn-primary">Record Payout</button>
    </div>
  </form>
</dialog>

<script>
document.getElementById('newRate')?.addEventListener('input', function() {
  document.getElementById('newRatePercent').textContent = Math.round(this.value * 100);
});

function openPayoutModal() {
  document.getElementById('payoutModal').showModal();
}

function exportLedger() {
  const table = document.getElementById('ledgerTable');
  if (!table) return;

  let csv = [];
  const rows = table.querySelectorAll('tr');

  rows.forEach(row => {
    const cols = row.querySelectorAll('th, td');
    const rowData = [];
    cols.forEach(col => {
      let text = col.innerText.replace(/"/g, '""');
      rowData.push('"' + text + '"');
    });
    csv.push(rowData.join(','));
  });

  const csvContent = csv.join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', 'commission_ledger_<?= $repId ?>.csv');
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>
