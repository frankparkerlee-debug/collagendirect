<?php
/**
 * Sales Rep Detail View
 *
 * Comprehensive view of a single sales rep with all related data.
 * Accessible to: superadmin, manufacturer
 */
declare(strict_types=1);
require __DIR__ . '/_header.php';
require_once __DIR__ . '/lib/order_display.php';

// Check permissions
$allowedRoles = ['superadmin', 'manufacturer'];
if (!in_array($admin['role'] ?? '', $allowedRoles)) {
  header('Location: /admin/');
  exit;
}

$repId = $_GET['id'] ?? '';
if (!$repId) {
  header('Location: /admin/platform/distributors.php');
  exit;
}

// CSV Export for Commission Ledger
if (isset($_GET['export']) && $_GET['export'] === 'ledger-csv') {
  // Fetch rep info for filename
  $repInfoStmt = $pdo->prepare("SELECT u.first_name, u.last_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
  $repInfoStmt->execute([$repId]);
  $repInfo = $repInfoStmt->fetch();

  $filename = 'commission_ledger_' . strtolower(str_replace(' ', '_', $repInfo['first_name'] . '_' . $repInfo['last_name'])) . '_' . date('Y-m-d') . '.csv';

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=' . $filename);

  $output = fopen('php://output', 'w');

  // CSV Headers
  fputcsv($output, [
    'Date',
    'Order ID',
    'Order Type',
    'Clinic',
    'Collected Amount',
    'Commission Rate',
    'Commission Amount',
    'Status',
    'Notes'
  ]);

  // Fetch all ledger entries for this rep
  $exportQuery = "
    SELECT rcl.*,
      u.practice_name as clinic_name, u.first_name as clinic_first, u.last_name as clinic_last
    FROM rep_commission_ledger rcl
    LEFT JOIN users u ON u.id = rcl.clinic_id
    WHERE rcl.rep_id = ?
    ORDER BY rcl.payment_date DESC, rcl.created_at DESC
  ";
  $exportStmt = $pdo->prepare($exportQuery);
  $exportStmt->execute([$repId]);

  while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
    $clinicName = $row['clinic_name'] ?: trim($row['clinic_first'] . ' ' . $row['clinic_last']);
    fputcsv($output, [
      date('Y-m-d', strtotime($row['payment_date'] ?: $row['created_at'])),
      $row['order_id'],
      ucfirst($row['order_type'] ?? ''),
      $clinicName,
      number_format((float)$row['collected_amount'], 2),
      number_format((float)$row['commission_rate'] * 100, 1) . '%',
      number_format((float)$row['commission_amount'], 2),
      ucfirst($row['status'] ?? 'pending'),
      $row['notes'] ?? ''
    ]);
  }

  fclose($output);
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
          // Validate payout amount doesn't exceed current balance
          require_once __DIR__ . '/../api/lib/commission.php';
          $balanceInfo = get_commission_balance($pdo, $repId);
          $currentBalanceCheck = $balanceInfo['balance'];

          if ($amount > $currentBalanceCheck + 0.01) { // Allow small rounding tolerance
            $error = 'Payout amount ($' . number_format($amount, 2) . ') exceeds available balance ($' . number_format($currentBalanceCheck, 2) . ')';
          } else {
            $pdo->prepare("INSERT INTO rep_commission_payouts (rep_id, amount, payment_method, reference_number, period_start, period_end, payout_date, processed_by, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE, ?, ?, NOW())")
                ->execute([$repId, $amount, $paymentMethod, $referenceNumber ?: null, $periodStart ?: null, $periodEnd ?: null, $admin['id'], $notes ?: null]);
            $message = 'Payout of $' . number_format($amount, 2) . ' recorded successfully.';
          }
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

      case 'assign_clinic':
        $clinicUserId = $_POST['clinic_user_id'] ?? '';
        if ($clinicUserId) {
          // Verify clinic exists and is a physician or practice_admin
          $checkStmt = $pdo->prepare("SELECT id, first_name, last_name, practice_name FROM users WHERE id = ? AND role IN ('physician', 'practice_admin')");
          $checkStmt->execute([$clinicUserId]);
          $clinic = $checkStmt->fetch();

          if ($clinic) {
            $pdo->prepare("
              UPDATE users
              SET assigned_rep_id = ?, rep_assignment_date = NOW(), rep_assigned_by = 'admin_assign', rep_assigned_by_user_id = ?
              WHERE id = ?
            ")->execute([$repId, $admin['id'], $clinicUserId]);
            $clinicName = $clinic['practice_name'] ?: ($clinic['first_name'] . ' ' . $clinic['last_name']);
            $message = 'Clinic "' . $clinicName . '" assigned to this rep.';
          } else {
            $error = 'Invalid clinic selected.';
          }
        }
        break;

      case 'request_w9':
        // Send W9 request email to distributor
        require_once __DIR__ . '/../api/lib/email_notifications.php';
        $repStmt = $pdo->prepare("SELECT u.email, u.first_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
        $repStmt->execute([$repId]);
        $repInfo = $repStmt->fetch();
        if ($repInfo && function_exists('send_generic_email')) {
          send_generic_email(
            $repInfo['email'],
            "W9 Form Required - CollagenDirect",
            "Hi {$repInfo['first_name']},\n\nWe need you to submit your W9 form to process your commission payouts.\n\nPlease log in to your distributor portal, go to My Account > Documents, and upload your completed W9 form.\n\nIf you need a blank W9 form, you can download it from the IRS website: https://www.irs.gov/pub/irs-pdf/fw9.pdf\n\nThank you,\nCollagenDirect Team"
          );
          $message = 'W9 request email sent to ' . $repInfo['first_name'] . '.';
        } else {
          $error = 'Unable to send W9 request email.';
        }
        break;
    }
  } catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
  }
}

// Fetch rep details (including Phase 11 business profile fields)
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
  header('Location: /admin/platform/distributors.php');
  exit;
}

// Fetch W9 submissions (Phase 11)
$w9Query = "
  SELECT *
  FROM rep_w9_submissions
  WHERE rep_id = ?
  ORDER BY submitted_at DESC
";
$w9Stmt = $pdo->prepare($w9Query);
$w9Stmt->execute([$repId]);
$w9Submissions = $w9Stmt->fetchAll();
$currentW9 = !empty($w9Submissions) ? $w9Submissions[0] : null;

// Fetch current commission rate (defaults to 25% if not set)
// Order by effective_date DESC (most recent effective date first), then by created_at DESC (most recently created)
// This ensures if two rates have the same effective_date, the newer one wins
$rateQuery = "
  SELECT rate, effective_date, set_by, notes, created_at
  FROM rep_commission_rates
  WHERE rep_id = ? AND (effective_date IS NULL OR effective_date <= CURRENT_DATE)
  ORDER BY effective_date DESC NULLS LAST, created_at DESC
  LIMIT 1
";
$rateStmt = $pdo->prepare($rateQuery);
$rateStmt->execute([$repId]);
$currentRate = $rateStmt->fetch();

// Default to 25% if no rate is set
if (!$currentRate) {
  $currentRate = ['rate' => 0.25, 'effective_date' => null, 'set_by' => null, 'notes' => 'Default rate', 'created_at' => null];
}

// Fetch commission rate history
// set_by can be either admin_users.id (integer) or users.id (UUID string)
// Use subquery approach to completely avoid integer cast for UUID values
$rateHistoryQuery = "
  SELECT rcr.*,
    CASE
      WHEN rcr.set_by ~ '^[0-9]+$' THEN (SELECT name FROM admin_users WHERE id = rcr.set_by::integer)
      WHEN rcr.set_by IS NOT NULL THEN (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = rcr.set_by)
      ELSE NULL
    END as set_by_name
  FROM rep_commission_rates rcr
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

// Fetch assigned clinics (physicians only, exclude sales reps)
$clinicsQuery = "
  SELECT u.id, u.first_name, u.last_name, u.practice_name, u.email, u.npi,
    u.rep_assignment_date, u.rep_assigned_by,
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND status NOT IN ('cancelled', 'voided', 'rejected')) as order_count
  FROM users u
  WHERE u.assigned_rep_id = ?
    AND u.role IN ('physician', 'practice_admin')
    AND u.id NOT IN (SELECT user_id FROM sales_reps WHERE user_id IS NOT NULL)
  ORDER BY u.rep_assignment_date DESC
";
$clinicsStmt = $pdo->prepare($clinicsQuery);
$clinicsStmt->execute([$repId]);
$assignedClinics = $clinicsStmt->fetchAll();

// Fetch available clinics (physicians not assigned to any rep, excluding sales reps)
$availableClinicsQuery = "
  SELECT id, first_name, last_name, practice_name, email, npi
  FROM users
  WHERE role IN ('physician', 'practice_admin')
    AND (assigned_rep_id IS NULL OR assigned_rep_id = '')
    AND id NOT IN (SELECT user_id FROM sales_reps WHERE user_id IS NOT NULL)
  ORDER BY practice_name, last_name, first_name
  LIMIT 500
";
$availableClinicsStmt = $pdo->query($availableClinicsQuery);
$availableClinics = $availableClinicsStmt->fetchAll();

// Performance metrics (count only physician clinics, exclude sales reps)
// total_revenue calculated from order pricing (consistent with revenue report)
// collected_revenue and total_commission from commission ledger (actual payments)
$metricsQuery = "
  SELECT
    (SELECT COUNT(*) FROM users WHERE assigned_rep_id = :rep_id AND role IN ('physician', 'practice_admin') AND id NOT IN (SELECT user_id FROM sales_reps WHERE user_id IS NOT NULL)) as total_clinics,
    (SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id = u.id WHERE u.assigned_rep_id = :rep_id AND u.role IN ('physician', 'practice_admin') AND o.status NOT IN ('cancelled', 'voided', 'rejected')) as total_orders,
    (SELECT COALESCE(SUM(
      CASE
        WHEN o.billed_by = 'practice_dme' THEN
          -- Wholesale: boxes × price_per_piece × pieces_per_box
          COALESCE(o.qty_per_change, 1) * COALESCE(o.product_price, 0) * COALESCE(pr.pieces_per_box, 10)
        ELSE
          -- Referral: billable pieces × rate
          COALESCE(o.product_price, 0) * COALESCE(o.qty_per_change, 1)
      END
    ), 0) FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN products pr ON o.product_id = pr.id
    WHERE u.assigned_rep_id = :rep_id
      AND u.role IN ('physician', 'practice_admin')
      AND o.status NOT IN ('cancelled', 'voided', 'rejected')
    ) as total_revenue,
    (SELECT COALESCE(SUM(collected_amount), 0) FROM rep_commission_ledger WHERE rep_id = :rep_id) as collected_revenue,
    (SELECT COALESCE(SUM(commission_amount), 0) FROM rep_commission_ledger WHERE rep_id = :rep_id) as total_commission
";
$metricsStmt = $pdo->prepare($metricsQuery);
$metricsStmt->execute(['rep_id' => $repId]);
$metrics = $metricsStmt->fetch();

// Fetch commission ledger
$ledgerQuery = "
  SELECT rcl.*,
    o.id as order_uuid, o.order_number,
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

// Fetch payout history with optional date filtering
// processed_by can be either admin_users.id (integer) or users.id (UUID string)
$payoutMonth = $_GET['payout_month'] ?? '';
$payoutYear = $_GET['payout_year'] ?? '';

$payoutsQuery = "
  SELECT rcp.*,
    COALESCE(au.name, CONCAT(u.first_name, ' ', u.last_name)) as processed_by_name
  FROM rep_commission_payouts rcp
  LEFT JOIN admin_users au ON rcp.processed_by ~ '^[0-9]+$' AND au.id = rcp.processed_by::integer
  LEFT JOIN users u ON rcp.processed_by !~ '^[0-9]+$' AND u.id = rcp.processed_by
  WHERE rcp.rep_id = ?
";
$payoutParams = [$repId];

if ($payoutMonth && $payoutYear) {
  $payoutsQuery .= " AND EXTRACT(MONTH FROM rcp.payout_date) = ? AND EXTRACT(YEAR FROM rcp.payout_date) = ?";
  $payoutParams[] = (int)$payoutMonth;
  $payoutParams[] = (int)$payoutYear;
} elseif ($payoutYear) {
  $payoutsQuery .= " AND EXTRACT(YEAR FROM rcp.payout_date) = ?";
  $payoutParams[] = (int)$payoutYear;
}

$payoutsQuery .= " ORDER BY rcp.payout_date DESC LIMIT 100";

$payoutsStmt = $pdo->prepare($payoutsQuery);
$payoutsStmt->execute($payoutParams);
$payoutHistory = $payoutsStmt->fetchAll();

// Get available years for filter dropdown
$availableYearsStmt = $pdo->prepare("
  SELECT DISTINCT EXTRACT(YEAR FROM payout_date)::INTEGER as year
  FROM rep_commission_payouts
  WHERE rep_id = ?
  ORDER BY year DESC
");
$availableYearsStmt->execute([$repId]);
$availablePayoutYears = $availableYearsStmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate current balance
$totalCommission = (float)($metrics['total_commission'] ?? 0);
$totalPaid = 0;
foreach ($payoutHistory as $p) {
  $totalPaid += (float)$p['amount'];
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
  <a href="/admin/platform/distributors.php" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4">
    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
    Back to Distributors
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
        <?= number_format((float)$currentRate['rate'] * 100, 1) ?>%
        <?php if (!$currentRate['created_at']): ?>
          <span class="text-sm font-normal text-gray-400">(Default)</span>
        <?php endif; ?>
      </div>
      <div class="text-sm text-gray-500">Current Commission Rate</div>
      <?php if ($currentRate['effective_date']): ?>
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
      <div>
        <div class="text-2xl font-bold text-blue-600">$<?= number_format((float)($metrics['collected_revenue'] ?? 0), 2) ?></div>
        <div class="text-sm text-gray-500">Collected Revenue</div>
      </div>
      <div>
        <div class="text-2xl font-bold text-purple-600">$<?= number_format((float)($metrics['total_commission'] ?? 0), 2) ?></div>
        <div class="text-sm text-gray-500">Commission Earned</div>
      </div>
    </div>
  </div>
</div>

<!-- Section 2b: Business Information (Phase 11) -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
  <div class="card p-6">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Business Information</h3>
    <dl class="space-y-3">
      <?php if ($rep['dba']): ?>
      <div>
        <dt class="text-xs text-gray-500">DBA (Doing Business As)</dt>
        <dd class="font-medium"><?= htmlspecialchars($rep['dba']) ?></dd>
      </div>
      <?php endif; ?>
      <?php if ($rep['tax_classification']): ?>
      <div>
        <dt class="text-xs text-gray-500">Tax Classification</dt>
        <dd>
          <?php
          $taxLabels = [
            'sole_proprietor' => 'Individual/Sole Proprietor',
            'llc_single' => 'Single-member LLC',
            'llc_c' => 'LLC (C-Corp)',
            'llc_s' => 'LLC (S-Corp)',
            'llc_p' => 'LLC (Partnership)',
            'c_corp' => 'C Corporation',
            's_corp' => 'S Corporation',
            'partnership' => 'Partnership',
            'trust' => 'Trust/Estate',
            'other' => 'Other'
          ];
          echo htmlspecialchars($taxLabels[$rep['tax_classification']] ?? $rep['tax_classification']);
          ?>
        </dd>
      </div>
      <?php endif; ?>
      <?php if ($rep['ein_last4']): ?>
      <div>
        <dt class="text-xs text-gray-500">EIN (Last 4)</dt>
        <dd>***-**-<?= htmlspecialchars($rep['ein_last4']) ?></dd>
      </div>
      <?php endif; ?>
      <?php if ($rep['website']): ?>
      <div>
        <dt class="text-xs text-gray-500">Website</dt>
        <dd><a href="<?= htmlspecialchars($rep['website']) ?>" target="_blank" class="text-teal-600 hover:underline"><?= htmlspecialchars($rep['website']) ?></a></dd>
      </div>
      <?php endif; ?>
      <?php if ($rep['business_address_line1']): ?>
      <div>
        <dt class="text-xs text-gray-500">Business Address</dt>
        <dd>
          <?= htmlspecialchars($rep['business_address_line1']) ?><br>
          <?php if ($rep['business_address_line2']): ?><?= htmlspecialchars($rep['business_address_line2']) ?><br><?php endif; ?>
          <?= htmlspecialchars($rep['business_city'] ?? '') ?><?= $rep['business_city'] && $rep['business_state'] ? ', ' : '' ?><?= htmlspecialchars($rep['business_state'] ?? '') ?> <?= htmlspecialchars($rep['business_zip'] ?? '') ?>
        </dd>
      </div>
      <?php endif; ?>
      <?php if ($rep['business_phone']): ?>
      <div>
        <dt class="text-xs text-gray-500">Business Phone</dt>
        <dd><?= htmlspecialchars($rep['business_phone']) ?></dd>
      </div>
      <?php endif; ?>
      <?php if ($rep['business_email']): ?>
      <div>
        <dt class="text-xs text-gray-500">Business Email</dt>
        <dd><a href="mailto:<?= htmlspecialchars($rep['business_email']) ?>" class="text-teal-600 hover:underline"><?= htmlspecialchars($rep['business_email']) ?></a></dd>
      </div>
      <?php endif; ?>
      <?php if (!$rep['dba'] && !$rep['tax_classification'] && !$rep['business_address_line1'] && !$rep['business_phone'] && !$rep['business_email']): ?>
      <div class="text-gray-400 text-sm">No business information on file.</div>
      <?php endif; ?>
    </dl>
  </div>

  <!-- W9 Status (Phase 11) -->
  <div class="card p-6">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">W9 Status</h3>
    <?php
    $w9Status = $rep['w9_status'] ?? 'none';
    $w9StatusLabels = [
      'none' => ['label' => 'Not Submitted', 'class' => 'bg-gray-100 text-gray-800', 'desc' => 'No W9 form on file'],
      'pending' => ['label' => 'Pending Review', 'class' => 'bg-yellow-100 text-yellow-800', 'desc' => 'Awaiting admin review'],
      'approved' => ['label' => 'Approved', 'class' => 'bg-green-100 text-green-800', 'desc' => 'Valid W9 on file'],
      'rejected' => ['label' => 'Rejected', 'class' => 'bg-red-100 text-red-800', 'desc' => 'Needs resubmission'],
      'expired' => ['label' => 'Expired', 'class' => 'bg-orange-100 text-orange-800', 'desc' => 'W9 has expired'],
    ];
    $w9Info = $w9StatusLabels[$w9Status] ?? $w9StatusLabels['none'];
    ?>

    <div class="flex items-start gap-4 mb-4">
      <div class="flex-shrink-0">
        <span class="inline-flex items-center justify-center w-12 h-12 rounded-full <?= str_replace('text-', 'bg-', explode(' ', $w9Info['class'])[0]) ?>-200">
          <?php if ($w9Status === 'approved'): ?>
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <?php elseif ($w9Status === 'pending'): ?>
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <?php elseif ($w9Status === 'rejected'): ?>
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <?php else: ?>
            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <?php endif; ?>
        </span>
      </div>
      <div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium <?= $w9Info['class'] ?>">
          <?= $w9Info['label'] ?>
        </span>
        <p class="text-sm text-gray-500 mt-1"><?= $w9Info['desc'] ?></p>
        <?php if ($rep['w9_approved_at']): ?>
          <p class="text-xs text-gray-400 mt-1">Approved: <?= date('M j, Y', strtotime($rep['w9_approved_at'])) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($currentW9): ?>
    <div class="border-t pt-4">
      <h4 class="text-xs font-medium text-gray-500 mb-2">Latest Submission</h4>
      <div class="bg-gray-50 rounded-lg p-3">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium"><?= htmlspecialchars($currentW9['file_name']) ?></p>
            <p class="text-xs text-gray-500">Tax Year <?= $currentW9['tax_year'] ?> · Submitted <?= date('M j, Y', strtotime($currentW9['submitted_at'])) ?></p>
          </div>
          <a href="/<?= htmlspecialchars($currentW9['file_path']) ?>" target="_blank" class="btn text-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            View
          </a>
        </div>
        <?php if ($currentW9['rejection_reason']): ?>
          <p class="text-xs text-red-600 mt-2">Rejection reason: <?= htmlspecialchars($currentW9['rejection_reason']) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (count($w9Submissions) > 1): ?>
    <div class="border-t pt-4 mt-4">
      <h4 class="text-xs font-medium text-gray-500 mb-2">Submission History</h4>
      <div class="space-y-2 max-h-32 overflow-y-auto">
        <?php foreach (array_slice($w9Submissions, 1, 5) as $w9): ?>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600"><?= date('M j, Y', strtotime($w9['submitted_at'])) ?></span>
            <span class="<?= $w9['status'] === 'approved' ? 'text-green-600' : ($w9['status'] === 'rejected' ? 'text-red-600' : 'text-gray-500') ?>">
              <?= ucfirst($w9['status']) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($w9Status !== 'approved' && $w9Status !== 'pending'): ?>
    <div class="border-t pt-4 mt-4">
      <form method="POST" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="request_w9">
        <button type="submit" class="btn btn-primary w-full text-sm" onclick="return confirm('Send W9 request email to this distributor?')">
          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
          Request W9 Form
        </button>
      </form>
    </div>
    <?php endif; ?>
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
              <div class="text-sm font-medium"><?= htmlspecialchars($doc['signature_text'] ?? '-') ?></div>
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
    <div class="flex items-center gap-4">
      <span class="text-sm text-gray-500"><?= count($assignedClinics) ?> clinic<?= count($assignedClinics) !== 1 ? 's' : '' ?></span>
      <?php if (!empty($availableClinics)): ?>
        <button onclick="document.getElementById('assignClinicModal').showModal()" class="btn btn-primary text-sm">
          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          Assign Clinic
        </button>
      <?php endif; ?>
    </div>
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
    <a href="?id=<?= htmlspecialchars($repId) ?>&export=ledger-csv" class="btn text-sm">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
      Export CSV
    </a>
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
                <?php if ($entry['order_uuid']): ?>
                  <a href="/admin/orders.php?id=<?= htmlspecialchars($entry['order_id']) ?>" class="text-teal-600 hover:underline"><?= format_order_number_html(['id' => $entry['order_uuid'], 'order_number' => $entry['order_number']]) ?></a>
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
  <div class="p-6 border-b flex flex-wrap items-center justify-between gap-4">
    <h3 class="text-lg font-semibold">Payout History</h3>
    <!-- Date Filter -->
    <form method="GET" class="flex items-center gap-2">
      <input type="hidden" name="id" value="<?= htmlspecialchars($repId) ?>">
      <select name="payout_month" class="text-sm py-1.5">
        <option value="">All Months</option>
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= $payoutMonth == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
        <?php endfor; ?>
      </select>
      <select name="payout_year" class="text-sm py-1.5">
        <option value="">All Years</option>
        <?php
        $years = $availablePayoutYears ?: [date('Y')];
        if (!in_array(date('Y'), $years)) array_unshift($years, (int)date('Y'));
        foreach ($years as $y):
        ?>
          <option value="<?= $y ?>" <?= $payoutYear == $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-sm">Filter</button>
      <?php if ($payoutMonth || $payoutYear): ?>
        <a href="?id=<?= urlencode($repId) ?>#payouts" class="text-xs text-gray-500 hover:underline">Clear</a>
      <?php endif; ?>
    </form>
  </div>
  <?php if (empty($payoutHistory) && ($payoutMonth || $payoutYear)): ?>
    <div class="p-6 text-center text-gray-500">No payouts found for the selected period.</div>
  <?php elseif (empty($payoutHistory)): ?>
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
              <td class="text-sm"><?= date('M j, Y', strtotime($payout['payout_date'])) ?></td>
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
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                  Completed
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

<!-- Assign Clinic Modal -->
<dialog id="assignClinicModal" class="rounded-2xl w-full max-w-lg p-0 backdrop:bg-black/50">
  <form method="POST" class="p-0">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="assign_clinic">

    <div class="p-6 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold text-gray-900">Assign Clinic to Rep</h3>
        <button type="button" onclick="document.getElementById('assignClinicModal').close()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
    </div>

    <div class="p-6 space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Select Clinic</label>
        <select name="clinic_user_id" required class="w-full" id="clinicSelect">
          <option value="">-- Select a clinic --</option>
          <?php foreach ($availableClinics as $clinic): ?>
            <option value="<?= htmlspecialchars($clinic['id']) ?>">
              <?= htmlspecialchars($clinic['practice_name'] ?: ($clinic['first_name'] . ' ' . $clinic['last_name'])) ?>
              <?php if ($clinic['npi']): ?> (NPI: <?= htmlspecialchars($clinic['npi']) ?>)<?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="text-xs text-gray-500 mt-1"><?= count($availableClinics) ?> unassigned clinic<?= count($availableClinics) !== 1 ? 's' : '' ?> available</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Search Filter</label>
        <input type="text" id="clinicSearchFilter" placeholder="Type to filter clinics..." class="w-full" oninput="filterClinics(this.value)">
      </div>
    </div>

    <div class="p-6 border-t border-gray-200 flex gap-3">
      <button type="button" onclick="document.getElementById('assignClinicModal').close()" class="flex-1 btn">Cancel</button>
      <button type="submit" class="flex-1 btn btn-primary">Assign Clinic</button>
    </div>
  </form>
</dialog>

<script>
// Store original options for filtering
let originalClinicOptions = [];
document.addEventListener('DOMContentLoaded', function() {
  const select = document.getElementById('clinicSelect');
  if (select) {
    originalClinicOptions = Array.from(select.options).slice(1); // Skip the first "Select a clinic" option
  }
});

function filterClinics(searchTerm) {
  const select = document.getElementById('clinicSelect');
  if (!select) return;

  const term = searchTerm.toLowerCase();

  // Clear all options except the first one
  while (select.options.length > 1) {
    select.remove(1);
  }

  // Add back matching options
  originalClinicOptions.forEach(option => {
    if (option.text.toLowerCase().includes(term)) {
      select.add(option.cloneNode(true));
    }
  });
}
</script>

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
