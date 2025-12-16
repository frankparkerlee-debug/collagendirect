<?php
/**
 * Billing > Referral Collections - Insurance Reimbursement Tracking
 *
 * Tracks insurance billing and collections for referral orders:
 * - Insurance claim status
 * - Payment tracking (insurance paid, patient responsibility, adjustments)
 * - Aging buckets for unpaid claims
 * - Collection status management
 * - Commission calculation on collected payments
 */
declare(strict_types=1);

$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
require_once __DIR__.'/db.php';
require_once __DIR__.'/../api/lib/commission.php';
require_once __DIR__.'/lib/revenue_calculator.php';
require_once __DIR__.'/lib/order_display.php';
$auth = __DIR__.'/auth.php'; if (is_file($auth)) { require_once $auth; if (function_exists('require_admin')) require_admin(); }

// Sales reps cannot access billing
if (function_exists('deny_sales_rep')) deny_sales_rep();

// Get current admin user and role
$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';

/* ================= Polyfills / safety ================= */
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* ================= CSV Export ================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=referral_collections_' . date('Y-m-d') . '.csv');

  $output = fopen('php://output', 'w');

  // CSV Headers
  fputcsv($output, [
    'Order ID',
    'Patient',
    'Physician',
    'Insurance',
    'Service Date',
    'Product',
    'Billed Amount',
    'Allowed Amount',
    'Insurance Paid',
    'Patient Resp',
    'Patient Paid',
    'Adjustment',
    'Write-off',
    'Balance',
    'Collection Status',
    'Days Since Service'
  ]);

  // Build query for export
  $whereConditions = ["o.payment_type = 'insurance' OR (o.billed_by IS NULL AND u.account_type = 'referral')"];
  $whereConditions[] = "o.status NOT IN ('rejected', 'cancelled', 'voided')";
  $params = [];

  $filterPhysician = $_GET['physician'] ?? '';
  $filterStatus = $_GET['collection_status'] ?? '';
  $filterFrom = $_GET['from'] ?? date('Y-01-01');
  $filterTo = $_GET['to'] ?? date('Y-m-d');

  if ($filterPhysician) {
    $whereConditions[] = "o.user_id = ?";
    $params[] = $filterPhysician;
  }

  if ($filterStatus) {
    $whereConditions[] = "o.collection_status = ?";
    $params[] = $filterStatus;
  }

  if ($filterFrom) {
    $whereConditions[] = "DATE(o.created_at) >= ?";
    $params[] = $filterFrom;
  }

  if ($filterTo) {
    $whereConditions[] = "DATE(o.created_at) <= ?";
    $params[] = $filterTo;
  }

  $whereClause = implode(' AND ', $whereConditions);

  $sql = "
    SELECT
      o.id, o.order_number, o.created_at, o.status, o.billed_by,
      o.product, o.product_price, o.qty_per_change, o.duration_days, o.refills_allowed,
      o.frequency_per_week, o.wounds_data,
      o.insurer_name, o.member_id,
      o.insurance_billed, o.insurance_allowed, o.insurance_paid,
      o.patient_responsibility, o.patient_paid, o.adjustment, o.write_off,
      o.collection_status, o.collection_notes,
      p.first_name as patient_first, p.last_name as patient_last,
      u.first_name as phys_first, u.last_name as phys_last, u.practice_name, u.account_type,
      pr.name as product_name, pr.pieces_per_box, pr.hcpcs_code as cpt_code, pr.price_wholesale
    FROM orders o
    LEFT JOIN patients p ON o.patient_id = p.id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN products pr ON o.product_id = pr.id
    WHERE $whereClause
    ORDER BY o.created_at DESC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  $now = new DateTime();
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $serviceDate = new DateTime($row['created_at']);
    $daysSince = $now->diff($serviceDate)->days;

    // Calculate billed amount using revenue calculator for consistency
    $insuranceBilledValue = (float)($row['insurance_billed'] ?? 0);
    if ($insuranceBilledValue > 0) {
      $billedAmount = $insuranceBilledValue;
    } else {
      $revenueCalc = calculate_order_revenue($row);
      $billedAmount = $revenueCalc['revenue'];
    }

    $allowedAmount = (float)($row['insurance_allowed'] ?? 0);
    $insurancePaid = (float)($row['insurance_paid'] ?? 0);
    $patientResp = (float)($row['patient_responsibility'] ?? 0);
    $patientPaid = (float)($row['patient_paid'] ?? 0);
    $adjustment = (float)($row['adjustment'] ?? 0);
    $writeOff = (float)($row['write_off'] ?? 0);
    $balance = $billedAmount - $insurancePaid - $patientPaid - $adjustment - $writeOff;

    fputcsv($output, [
      get_order_identifier($row),
      trim($row['patient_first'] . ' ' . $row['patient_last']),
      trim($row['phys_first'] . ' ' . $row['phys_last']),
      $row['insurer_name'] ?? '',
      date('Y-m-d', strtotime($row['created_at'])),
      $row['product_name'] ?? $row['product'],
      '$' . number_format($billedAmount, 2),
      '$' . number_format($allowedAmount, 2),
      '$' . number_format($insurancePaid, 2),
      '$' . number_format($patientResp, 2),
      '$' . number_format($patientPaid, 2),
      '$' . number_format($adjustment, 2),
      '$' . number_format($writeOff, 2),
      '$' . number_format(max(0, $balance), 2),
      ucfirst($row['collection_status'] ?? 'pending'),
      $daysSince
    ]);
  }

  fclose($output);
  exit;
}

/* ================= Handle Actions ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $action = $_POST['action'] ?? '';

  if ($action === 'update_collection') {
    $orderId = $_POST['order_id'] ?? '';
    $insuranceBilled = $_POST['insurance_billed'] !== '' ? (float)$_POST['insurance_billed'] : null;
    $insuranceAllowed = $_POST['insurance_allowed'] !== '' ? (float)$_POST['insurance_allowed'] : null;
    $insurancePaid = $_POST['insurance_paid'] !== '' ? (float)$_POST['insurance_paid'] : null;
    $patientResponsibility = $_POST['patient_responsibility'] !== '' ? (float)$_POST['patient_responsibility'] : null;
    $patientPaid = $_POST['patient_paid'] !== '' ? (float)$_POST['patient_paid'] : null;
    $adjustment = $_POST['adjustment'] !== '' ? (float)$_POST['adjustment'] : null;
    $writeOff = $_POST['write_off'] !== '' ? (float)$_POST['write_off'] : null;
    $collectionStatus = $_POST['collection_status'] ?? 'pending';
    $collectionNotes = $_POST['collection_notes'] ?? '';
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');

    if ($orderId) {
      try {
        $pdo->beginTransaction();

        // Get order details for commission calculation (include product data for revenue calc)
        $orderStmt = $pdo->prepare("
          SELECT o.user_id, o.insurance_paid as prev_insurance_paid, o.patient_paid as prev_patient_paid,
                 o.collection_status as prev_status, o.insurance_billed, o.billed_by,
                 o.product_price, o.qty_per_change, o.duration_days, o.frequency_per_week, o.refills_allowed,
                 pr.pieces_per_box, u.account_type
          FROM orders o
          LEFT JOIN products pr ON pr.id = o.product_id
          LEFT JOIN users u ON u.id = o.user_id
          WHERE o.id = ?
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();

        if ($order) {
          // Calculate new payment amount (difference from previous)
          $prevTotal = (float)($order['prev_insurance_paid'] ?? 0) + (float)($order['prev_patient_paid'] ?? 0);
          $newTotal = ($insurancePaid ?? 0) + ($patientPaid ?? 0);
          $paymentDiff = $newTotal - $prevTotal;

          // If status is changing to 'collected' but no payment amounts entered, use billed amount
          $isNewlyCollected = ($collectionStatus === 'collected' && ($order['prev_status'] ?? '') !== 'collected');
          if ($isNewlyCollected && $paymentDiff <= 0 && $newTotal <= 0) {
            // Calculate the expected revenue for this order
            $revenueCalc = calculate_order_revenue($order);
            $estimatedPayment = (float)($order['insurance_billed'] ?? 0);
            if ($estimatedPayment <= 0) {
              $estimatedPayment = $revenueCalc['revenue'];
            }
            if ($estimatedPayment > 0) {
              $insurancePaid = $estimatedPayment;
              $newTotal = $estimatedPayment;
              $paymentDiff = $newTotal - $prevTotal;
            }
          }

          // Update order with collection data
          $updateStmt = $pdo->prepare("
            UPDATE orders SET
              insurance_billed = ?,
              insurance_allowed = ?,
              insurance_paid = ?,
              patient_responsibility = ?,
              patient_paid = ?,
              adjustment = ?,
              write_off = ?,
              collection_status = ?,
              collection_notes = ?,
              updated_at = NOW()
            WHERE id = ?
          ");
          $updateStmt->execute([
            $insuranceBilled,
            $insuranceAllowed,
            $insurancePaid,
            $patientResponsibility,
            $patientPaid,
            $adjustment,
            $writeOff,
            $collectionStatus,
            $collectionNotes ?: null,
            $orderId
          ]);

          $pdo->commit();

          // Calculate commission if there's a new payment
          if ($paymentDiff > 0) {
            try {
              $commissionResult = calculate_commission(
                $pdo,
                $orderId,
                'referral',
                $order['user_id'],
                $paymentDiff,
                $paymentDate
              );

              if ($commissionResult) {
                $_SESSION['success_msg'] = 'Collection updated. Commission of $' . number_format($commissionResult['commission_amount'], 2) . ' recorded for rep.';
                error_log("[billing-referral] Commission recorded: order={$orderId}, rep={$commissionResult['rep_id']}, amount={$commissionResult['commission_amount']}");
              } else {
                // Check if physician has assigned rep
                $repCheck = $pdo->prepare("SELECT assigned_rep_id FROM users WHERE id = ?");
                $repCheck->execute([$order['user_id']]);
                $assignedRep = $repCheck->fetchColumn();
                if ($assignedRep) {
                  $_SESSION['success_msg'] = 'Collection updated. Commission calculation failed - please check rate configuration.';
                  error_log("[billing-referral] Commission failed: order={$orderId}, user={$order['user_id']}, rep={$assignedRep} - no rate found");
                } else {
                  $_SESSION['success_msg'] = 'Collection updated. No sales rep assigned to this physician.';
                }
              }
            } catch (Exception $commErr) {
              error_log("[billing-referral] Commission error: " . $commErr->getMessage());
              $_SESSION['success_msg'] = 'Collection updated, but commission calculation failed: ' . $commErr->getMessage();
            }
          } else {
            $_SESSION['success_msg'] = 'Collection updated successfully.';
          }
        }
      } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Collection update error: " . $e->getMessage());
        $_SESSION['error_msg'] = 'Error updating collection: ' . $e->getMessage();
      }
    }
    header('Location: /admin/billing-referral.php?' . http_build_query($_GET));
    exit;
  }

  if ($action === 'bulk_update_status') {
    $orderIds = $_POST['order_ids'] ?? [];
    $newStatus = $_POST['new_status'] ?? '';

    if (!empty($orderIds) && $newStatus) {
      $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
      $pdo->prepare("UPDATE orders SET collection_status = ?, updated_at = NOW() WHERE id IN ($placeholders)")
          ->execute(array_merge([$newStatus], $orderIds));
      $_SESSION['success_msg'] = count($orderIds) . ' order(s) updated to ' . ucfirst($newStatus) . '.';
    }
    header('Location: /admin/billing-referral.php?' . http_build_query($_GET));
    exit;
  }
}

/* ================= Filters ================= */
$filterPhysician = $_GET['physician'] ?? '';
$filterStatus = $_GET['collection_status'] ?? '';
$filterInsurer = $_GET['insurer'] ?? '';
$filterAging = $_GET['aging'] ?? '';
$filterFrom = $_GET['from'] ?? date('Y-01-01');
$filterTo = $_GET['to'] ?? date('Y-m-d');
$filterSearch = $_GET['search'] ?? '';

/* ================= Fetch Physicians for Filter ================= */
$physicians = [];
try {
  $stmt = $pdo->query("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.practice_name
    FROM users u
    INNER JOIN orders o ON o.user_id = u.id
    WHERE (o.payment_type = 'insurance' OR (o.billed_by IS NULL AND u.account_type = 'referral'))
    ORDER BY u.last_name, u.first_name
  ");
  $physicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log("Physician fetch error: " . $e->getMessage());
}

/* ================= Fetch Insurers for Filter ================= */
$insurers = [];
try {
  $stmt = $pdo->query("
    SELECT DISTINCT o.insurer_name
    FROM orders o
    WHERE o.insurer_name IS NOT NULL AND o.insurer_name != ''
    ORDER BY o.insurer_name
  ");
  $insurers = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
  error_log("Insurer fetch error: " . $e->getMessage());
}

/* ================= Fetch Referral Orders ================= */
$orders = [];
$aging = ['current' => 0.0, '1-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
$totalBilled = 0.0;
$totalCollected = 0.0;
$totalPending = 0.0;

try {
  // Referral orders = insurance-billed orders (not wholesale)
  $whereConditions = ["(o.payment_type = 'insurance' OR (o.billed_by IS NULL AND u.account_type = 'referral'))"];
  $whereConditions[] = "o.status NOT IN ('rejected', 'cancelled', 'voided')";
  $params = [];

  if ($filterPhysician) {
    $whereConditions[] = "o.user_id = ?";
    $params[] = $filterPhysician;
  }

  if ($filterStatus) {
    $whereConditions[] = "o.collection_status = ?";
    $params[] = $filterStatus;
  }

  if ($filterInsurer) {
    $whereConditions[] = "o.insurer_name = ?";
    $params[] = $filterInsurer;
  }

  if ($filterFrom) {
    $whereConditions[] = "DATE(o.created_at) >= ?";
    $params[] = $filterFrom;
  }

  if ($filterTo) {
    $whereConditions[] = "DATE(o.created_at) <= ?";
    $params[] = $filterTo;
  }

  if ($filterSearch) {
    $whereConditions[] = "(o.order_number ILIKE ? OR p.first_name ILIKE ? OR p.last_name ILIKE ? OR o.insurer_name ILIKE ?)";
    $searchTerm = '%' . $filterSearch . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
  }

  $whereClause = implode(' AND ', $whereConditions);

  $sql = "
    SELECT
      o.id, o.order_number, o.created_at, o.status, o.billed_by,
      o.product, o.product_id, o.product_price, o.qty_per_change,
      o.duration_days, o.refills_allowed, o.frequency_per_week, o.wounds_data,
      o.insurer_name, o.member_id, o.prior_auth,
      o.insurance_billed, o.insurance_allowed, o.insurance_paid,
      o.patient_responsibility, o.patient_paid, o.adjustment, o.write_off,
      o.collection_status, o.collection_notes,
      o.user_id,
      p.first_name as patient_first, p.last_name as patient_last,
      u.first_name as phys_first, u.last_name as phys_last, u.practice_name,
      u.assigned_rep_id, u.account_type,
      pr.name as product_name, pr.pieces_per_box, pr.hcpcs_code, pr.hcpcs_code as cpt_code, pr.price_wholesale
    FROM orders o
    LEFT JOIN patients p ON o.patient_id = p.id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN products pr ON o.product_id = pr.id
    WHERE $whereClause
    ORDER BY o.created_at DESC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Calculate aging and totals
  $now = new DateTime();
  foreach ($orders as &$order) {
    $serviceDate = new DateTime($order['created_at']);
    $daysSince = $now->diff($serviceDate)->days;
    $order['days_since'] = $daysSince;

    // Calculate billed amount - use insurance_billed if set, otherwise use revenue calculator
    $insuranceBilledValue = (float)($order['insurance_billed'] ?? 0);

    // Use revenue calculator for consistency across all reports
    $revenueCalc = calculate_order_revenue($order);

    if ($insuranceBilledValue > 0) {
      // Use explicitly set insurance_billed amount
      $billed = $insuranceBilledValue;
    } else {
      $billed = $revenueCalc['revenue'];
    }

    // Store calculated quantities for display
    $order['calculated_boxes'] = $revenueCalc['boxes'] ?? 1;
    $order['calculated_pieces'] = $revenueCalc['pieces'] ?? $order['calculated_boxes'] * max(1, (int)($order['pieces_per_box'] ?? 10));

    $insurancePaid = (float)($order['insurance_paid'] ?? 0);
    $patientPaid = (float)($order['patient_paid'] ?? 0);
    $adjustment = (float)($order['adjustment'] ?? 0);
    $writeOff = (float)($order['write_off'] ?? 0);
    $collected = $insurancePaid + $patientPaid;
    $balance = $billed - $collected - $adjustment - $writeOff;

    $order['calculated_billed'] = $billed;
    $order['calculated_collected'] = $collected;
    $order['calculated_balance'] = max(0, $balance);

    $totalBilled += $billed;
    $totalCollected += $collected;

    // Only count unpaid in aging
    if ($balance > 0 && $order['collection_status'] !== 'collected' && $order['collection_status'] !== 'written_off') {
      $totalPending += $balance;

      // Aging buckets
      if ($daysSince <= 30) {
        $order['aging_bucket'] = 'current';
        $aging['current'] += $balance;
      } elseif ($daysSince <= 60) {
        $order['aging_bucket'] = '1-30';
        $aging['1-30'] += $balance;
      } elseif ($daysSince <= 90) {
        $order['aging_bucket'] = '31-60';
        $aging['31-60'] += $balance;
      } elseif ($daysSince <= 120) {
        $order['aging_bucket'] = '61-90';
        $aging['61-90'] += $balance;
      } else {
        $order['aging_bucket'] = '90+';
        $aging['90+'] += $balance;
      }
    } else {
      $order['aging_bucket'] = 'collected';
    }
  }
  unset($order);

  // Filter by aging bucket if specified
  if ($filterAging) {
    $orders = array_filter($orders, fn($o) => ($o['aging_bucket'] ?? '') === $filterAging);
    $orders = array_values($orders);
  }

} catch (Throwable $e) {
  error_log("Order fetch error: " . $e->getMessage());
}

/* ================= View ================= */
include __DIR__.'/_header.php';
?>

<style>
.aging-card {
  padding: 1rem 1.25rem;
  border-radius: 8px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  border: 2px solid transparent;
}
.aging-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}
.aging-card.active {
  border-color: var(--brand);
}
.aging-card .amount {
  font-size: 1.25rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}
.aging-card .label {
  font-size: 0.75rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.aging-current { background: #f0fdf4; }
.aging-current .amount { color: #16a34a; }
.aging-30 { background: #fefce8; }
.aging-30 .amount { color: #ca8a04; }
.aging-60 { background: #fff7ed; }
.aging-60 .amount { color: #ea580c; }
.aging-90 { background: #fef2f2; }
.aging-90 .amount { color: #dc2626; }
.aging-over90 { background: #fef2f2; border: 1px solid #fecaca; }
.aging-over90 .amount { color: #991b1b; }

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;
}
.status-pending { background: #dbeafe; color: #1e40af; }
.status-submitted { background: #e0e7ff; color: #3730a3; }
.status-partial { background: #fef3c7; color: #92400e; }
.status-collected { background: #d1fae5; color: #065f46; }
.status-denied { background: #fee2e2; color: #991b1b; }
.status-written_off { background: #e5e7eb; color: #6b7280; }
.status-appealing { background: #fce7f3; color: #9d174d; }

.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0; top: 0;
  width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.5);
  align-items: center;
  justify-content: center;
}
.modal.active { display: flex; }
.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}
.modal-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-body { padding: 1.5rem; }
.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>

<div class="p-6 max-w-[1400px] mx-auto">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Referral Collections</h1>
      <p class="text-slate-500">Insurance Reimbursement Tracking</p>
    </div>
    <div class="flex gap-2">
      <a href="/admin/billing-referral.php?export=csv&<?=http_build_query($_GET)?>" class="btn btn-secondary text-sm">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
        Export CSV
      </a>
    </div>
  </div>

  <?php if (!empty($_SESSION['success_msg'])): ?>
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded mb-4">
      <?=e($_SESSION['success_msg'])?>
    </div>
    <?php unset($_SESSION['success_msg']); ?>
  <?php endif; ?>

  <?php if (!empty($_SESSION['error_msg'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-4">
      <?=e($_SESSION['error_msg'])?>
    </div>
    <?php unset($_SESSION['error_msg']); ?>
  <?php endif; ?>

  <!-- Aging Summary Cards -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <a href="?<?=http_build_query(array_merge($_GET, ['aging' => '']))?>" class="aging-card aging-current <?=$filterAging === '' ? 'active' : ''?>">
      <div class="amount">$<?=number_format($aging['current'], 2)?></div>
      <div class="label">0-30 Days</div>
    </a>
    <a href="?<?=http_build_query(array_merge($_GET, ['aging' => '1-30']))?>" class="aging-card aging-30 <?=$filterAging === '1-30' ? 'active' : ''?>">
      <div class="amount">$<?=number_format($aging['1-30'], 2)?></div>
      <div class="label">31-60 Days</div>
    </a>
    <a href="?<?=http_build_query(array_merge($_GET, ['aging' => '31-60']))?>" class="aging-card aging-60 <?=$filterAging === '31-60' ? 'active' : ''?>">
      <div class="amount">$<?=number_format($aging['31-60'], 2)?></div>
      <div class="label">61-90 Days</div>
    </a>
    <a href="?<?=http_build_query(array_merge($_GET, ['aging' => '61-90']))?>" class="aging-card aging-90 <?=$filterAging === '61-90' ? 'active' : ''?>">
      <div class="amount">$<?=number_format($aging['61-90'], 2)?></div>
      <div class="label">91-120 Days</div>
    </a>
    <a href="?<?=http_build_query(array_merge($_GET, ['aging' => '90+']))?>" class="aging-card aging-over90 <?=$filterAging === '90+' ? 'active' : ''?>">
      <div class="amount">$<?=number_format($aging['90+'], 2)?></div>
      <div class="label">120+ Days</div>
    </a>
  </div>

  <!-- Summary Stats -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="card p-4">
      <div class="text-2xl font-bold text-slate-800">$<?=number_format($totalBilled, 2)?></div>
      <div class="text-sm text-slate-500">Total Billed</div>
    </div>
    <div class="card p-4">
      <div class="text-2xl font-bold text-green-600">$<?=number_format($totalCollected, 2)?></div>
      <div class="text-sm text-slate-500">Total Collected</div>
    </div>
    <div class="card p-4">
      <div class="text-2xl font-bold text-amber-600">$<?=number_format($totalPending, 2)?></div>
      <div class="text-sm text-slate-500">Pending Collection</div>
    </div>
    <div class="card p-4">
      <div class="text-2xl font-bold text-slate-800"><?=count($orders)?></div>
      <div class="text-sm text-slate-500">Claims (Filtered)</div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card p-4 mb-6">
    <form method="get" class="grid grid-cols-1 md:grid-cols-6 gap-3">
      <div>
        <label class="block text-xs text-slate-600 mb-1">Physician</label>
        <select name="physician" class="w-full rounded border-slate-300 text-sm">
          <option value="">All Physicians</option>
          <?php foreach ($physicians as $p): ?>
            <option value="<?=e($p['id'])?>" <?=$filterPhysician === $p['id'] ? 'selected' : ''?>>
              <?=e($p['last_name'] . ', ' . $p['first_name'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">Insurance</label>
        <select name="insurer" class="w-full rounded border-slate-300 text-sm">
          <option value="">All Insurers</option>
          <?php foreach ($insurers as $ins): ?>
            <option value="<?=e($ins)?>" <?=$filterInsurer === $ins ? 'selected' : ''?>>
              <?=e($ins)?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">Collection Status</label>
        <select name="collection_status" class="w-full rounded border-slate-300 text-sm">
          <option value="">All Statuses</option>
          <option value="pending" <?=$filterStatus === 'pending' ? 'selected' : ''?>>Pending</option>
          <option value="submitted" <?=$filterStatus === 'submitted' ? 'selected' : ''?>>Submitted</option>
          <option value="partial" <?=$filterStatus === 'partial' ? 'selected' : ''?>>Partial</option>
          <option value="collected" <?=$filterStatus === 'collected' ? 'selected' : ''?>>Collected</option>
          <option value="denied" <?=$filterStatus === 'denied' ? 'selected' : ''?>>Denied</option>
          <option value="appealing" <?=$filterStatus === 'appealing' ? 'selected' : ''?>>Appealing</option>
          <option value="written_off" <?=$filterStatus === 'written_off' ? 'selected' : ''?>>Written Off</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">From</label>
        <input type="date" name="from" value="<?=e($filterFrom)?>" class="w-full rounded border-slate-300 text-sm">
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">To</label>
        <input type="date" name="to" value="<?=e($filterTo)?>" class="w-full rounded border-slate-300 text-sm">
      </div>
      <div class="flex items-end gap-2">
        <button type="submit" class="btn btn-primary text-sm">Filter</button>
        <a href="/admin/billing-referral.php" class="btn btn-secondary text-sm">Clear</a>
      </div>
    </form>
  </div>

  <!-- Orders Table -->
  <div class="card">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b bg-slate-50">
          <tr class="text-left text-xs text-slate-600">
            <th class="py-3 px-4">Order #</th>
            <th class="py-3 px-4">Patient</th>
            <th class="py-3 px-4">Physician</th>
            <th class="py-3 px-4">Product</th>
            <th class="py-3 px-4">Insurance</th>
            <th class="py-3 px-4">Service Date</th>
            <th class="py-3 px-4 text-right">Billed</th>
            <th class="py-3 px-4 text-right">Collected</th>
            <th class="py-3 px-4 text-right">Balance</th>
            <th class="py-3 px-4">Status</th>
            <th class="py-3 px-4">Age</th>
            <th class="py-3 px-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="12" class="py-8 text-center text-slate-500">No orders found matching your filters.</td></tr>
          <?php else: ?>
            <?php foreach ($orders as $order): ?>
              <?php
                $statusClass = 'status-' . ($order['collection_status'] ?? 'pending');
                $hasRep = !empty($order['assigned_rep_id']);
              ?>
              <tr class="border-t hover:bg-slate-50">
                <td class="py-3 px-4">
                  <a href="/admin/orders.php?id=<?=e($order['id'])?>" class="font-medium text-brand hover:underline">
                    <?=format_order_number_html($order)?>
                  </a>
                  <?php if ($hasRep): ?>
                    <span class="ml-1 text-xs text-purple-600" title="Has assigned rep">&#x2605;</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-4">
                  <?=e(trim($order['patient_first'] . ' ' . $order['patient_last']) ?: 'Unknown')?>
                </td>
                <td class="py-3 px-4">
                  <div class="text-sm"><?=e($order['phys_last'] . ', ' . $order['phys_first'])?></div>
                  <?php if ($order['practice_name']): ?>
                    <div class="text-xs text-slate-500"><?=e($order['practice_name'])?></div>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-4">
                  <?php
                    $productName = $order['product_name'] ?: $order['product'] ?: 'Unknown';
                    $calcBoxes = (int)($order['calculated_boxes'] ?? 1);
                    $calcPieces = (int)($order['calculated_pieces'] ?? $calcBoxes * 10);
                  ?>
                  <div class="text-sm font-medium"><?=e($productName)?></div>
                  <div class="text-xs text-slate-500"><?=$calcBoxes?> box<?=$calcBoxes > 1 ? 'es' : ''?> (<?=$calcPieces?> pieces)</div>
                  <?php if (!empty($order['hcpcs_code'])): ?>
                    <div class="text-xs text-slate-400"><?=e($order['hcpcs_code'])?></div>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-4">
                  <div class="text-sm"><?=e($order['insurer_name'] ?: 'Not specified')?></div>
                  <?php if ($order['member_id']): ?>
                    <div class="text-xs text-slate-500">ID: <?=e($order['member_id'])?></div>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-4 text-sm"><?=date('M j, Y', strtotime($order['created_at']))?></td>
                <td class="py-3 px-4 text-right font-medium">$<?=number_format($order['calculated_billed'], 2)?></td>
                <td class="py-3 px-4 text-right text-green-600">$<?=number_format($order['calculated_collected'], 2)?></td>
                <td class="py-3 px-4 text-right font-semibold <?=$order['calculated_balance'] > 0 ? 'text-red-600' : 'text-slate-800'?>">
                  $<?=number_format($order['calculated_balance'], 2)?>
                </td>
                <td class="py-3 px-4">
                  <span class="<?=$statusClass?> status-badge"><?=ucfirst($order['collection_status'] ?? 'pending')?></span>
                </td>
                <td class="py-3 px-4 text-sm <?=$order['days_since'] > 90 ? 'text-red-600 font-medium' : 'text-slate-600'?>">
                  <?=$order['days_since']?> days
                </td>
                <td class="py-3 px-4">
                  <button onclick="openEditModal(<?=htmlspecialchars(json_encode($order), ENT_QUOTES)?>)"
                          class="text-xs px-2 py-1 bg-brand text-white rounded hover:bg-brand/90">
                    Edit
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <?php if (!empty($orders)): ?>
        <tfoot class="bg-slate-50 font-semibold">
          <tr>
            <td colspan="6" class="py-3 px-4">Total (<?=count($orders)?> claims)</td>
            <td class="py-3 px-4 text-right">$<?=number_format($totalBilled, 2)?></td>
            <td class="py-3 px-4 text-right text-green-600">$<?=number_format($totalCollected, 2)?></td>
            <td class="py-3 px-4 text-right text-red-600">$<?=number_format($totalPending, 2)?></td>
            <td colspan="3"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<!-- Edit Collection Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="font-semibold text-lg">Update Collection</h3>
      <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600">&times;</button>
    </div>
    <form method="post">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="update_collection">
      <input type="hidden" name="order_id" id="edit_order_id">
      <div class="modal-body space-y-4">
        <div class="bg-slate-50 rounded-lg p-3 mb-4">
          <div class="text-xs text-slate-500 uppercase mb-1">Order</div>
          <div class="font-medium" id="edit_order_display"></div>
          <div class="text-sm text-slate-600" id="edit_patient_display"></div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Insurance Billed</label>
            <input type="number" name="insurance_billed" id="edit_insurance_billed" step="0.01" min="0"
                   class="w-full rounded border-slate-300">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Allowed Amount</label>
            <input type="number" name="insurance_allowed" id="edit_insurance_allowed" step="0.01" min="0"
                   class="w-full rounded border-slate-300">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Insurance Paid</label>
            <input type="number" name="insurance_paid" id="edit_insurance_paid" step="0.01" min="0"
                   class="w-full rounded border-slate-300">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Patient Responsibility</label>
            <input type="number" name="patient_responsibility" id="edit_patient_responsibility" step="0.01" min="0"
                   class="w-full rounded border-slate-300">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Patient Paid</label>
            <input type="number" name="patient_paid" id="edit_patient_paid" step="0.01" min="0"
                   class="w-full rounded border-slate-300">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Payment Date</label>
            <input type="date" name="payment_date" id="edit_payment_date" value="<?=date('Y-m-d')?>"
                   class="w-full rounded border-slate-300">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Adjustment</label>
            <input type="number" name="adjustment" id="edit_adjustment" step="0.01" min="0"
                   class="w-full rounded border-slate-300">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Write-off</label>
            <input type="number" name="write_off" id="edit_write_off" step="0.01" min="0"
                   class="w-full rounded border-slate-300">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Collection Status</label>
          <select name="collection_status" id="edit_collection_status" class="w-full rounded border-slate-300">
            <option value="pending">Pending</option>
            <option value="submitted">Submitted to Insurance</option>
            <option value="partial">Partial Payment</option>
            <option value="collected">Fully Collected</option>
            <option value="denied">Denied</option>
            <option value="appealing">Appealing</option>
            <option value="written_off">Written Off</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
          <textarea name="collection_notes" id="edit_collection_notes" rows="2" class="w-full rounded border-slate-300"
                    placeholder="Denial reason, appeal notes, etc."></textarea>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
          <strong>Note:</strong> When you record new payments (insurance or patient), commission will be automatically calculated for the physician's assigned sales rep.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('editModal')" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(order) {
  document.getElementById('edit_order_id').value = order.id;
  document.getElementById('edit_order_display').textContent = order.order_number || order.id.substring(0, 8);
  document.getElementById('edit_patient_display').textContent =
    (order.patient_first || '') + ' ' + (order.patient_last || '') + ' - ' + (order.insurer_name || 'No insurance');

  document.getElementById('edit_insurance_billed').value = order.insurance_billed || order.product_price || '';
  document.getElementById('edit_insurance_allowed').value = order.insurance_allowed || '';
  document.getElementById('edit_insurance_paid').value = order.insurance_paid || '';
  document.getElementById('edit_patient_responsibility').value = order.patient_responsibility || '';
  document.getElementById('edit_patient_paid').value = order.patient_paid || '';
  document.getElementById('edit_adjustment').value = order.adjustment || '';
  document.getElementById('edit_write_off').value = order.write_off || '';
  document.getElementById('edit_collection_status').value = order.collection_status || 'pending';
  document.getElementById('edit_collection_notes').value = order.collection_notes || '';

  document.getElementById('editModal').classList.add('active');
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove('active');
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      modal.classList.remove('active');
    }
  });
});
</script>

<?php include __DIR__.'/_footer.php'; ?>
