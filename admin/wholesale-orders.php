<?php
// /public/admin/wholesale-orders.php — Dedicated wholesale orders view
// Updated: 2025-11-20 - Fixed column references
declare(strict_types=1);
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
require_once __DIR__.'/db.php';
$auth = __DIR__.'/auth.php'; if (is_file($auth)) { require_once $auth; if (function_exists('require_admin')) require_admin(); }

// Get current admin user and role
$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';

/* ---------- Export functionality ---------- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  // CSV Export for wholesale orders
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=wholesale_orders_' . date('Y-m-d') . '.csv');

  $output = fopen('php://output', 'w');

  // CSV Headers
  fputcsv($output, [
    'Order #',
    'Order Date',
    'Practice',
    'Physician',
    'Patient Name',
    'Product',
    'Boxes Ordered',
    'Pieces/Box',
    'Unit Price (Wholesale)',
    'Total Value',
    'Status',
    'Payment Status',
    'Shipping Address'
  ]);

  // Build query
  $sql = "
    SELECT
      o.id,
      o.created_at,
      u.practice_name,
      CONCAT(u.first_name, ' ', u.last_name) as physician_name,
      CONCAT(p.first_name, ' ', p.last_name) as patient_name,
      o.product,
      o.shipments_remaining,
      o.product_price as unit_price,
      o.status,
      o.paid_at,
      CONCAT_WS(', ', o.shipping_address, o.shipping_city, o.shipping_state, o.shipping_zip) as shipping_address,
      pr.pieces_per_box,
      pr.price_wholesale
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN patients p ON o.patient_id = p.id
    LEFT JOIN products pr ON o.product_id = pr.id
    WHERE o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
    ORDER BY o.created_at DESC
  ";

  $stmt = $pdo->query($sql);

  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $boxes = (int)($row['shipments_remaining'] ?? 0);
    $pieces_per_box = (int)($row['pieces_per_box'] ?? 10);
    $unit_price = (float)($row['unit_price'] ?? $row['price_wholesale'] ?? 0);
    $total_value = $boxes * ($unit_price * $pieces_per_box);
    $paymentStatus = $row['paid_at'] ? 'Paid (' . date('m/d/Y', strtotime($row['paid_at'])) . ')' : 'Unpaid';

    fputcsv($output, [
      $row['id'],
      date('m/d/Y', strtotime($row['created_at'])),
      $row['practice_name'] ?? '',
      $row['physician_name'] ?? '',
      $row['patient_name'] ?? '',
      $row['product'] ?? '',
      $boxes,
      $pieces_per_box,
      '$' . number_format($unit_price, 2),
      '$' . number_format($total_value, 2),
      ucfirst($row['status'] ?? ''),
      $paymentStatus,
      $row['shipping_address'] ?? ''
    ]);
  }

  fclose($output);
  exit;
}

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();
  $action = $_POST['action'] ?? '';
  $id = $_POST['id'] ?? '';

  if ($id && $action==='approve') {
    $pdo->prepare("UPDATE orders SET status='approved', updated_at=NOW() WHERE id=?")->execute([$id]);
    $_SESSION['success_msg'] = 'Order approved successfully';
  } elseif ($id && $action==='reject') {
    $reason = $_POST['reject_reason'] ?? '';
    $pdo->prepare("UPDATE orders SET status='rejected', notes=?, updated_at=NOW() WHERE id=?")->execute([$reason, $id]);
    $_SESSION['success_msg'] = 'Order rejected';
  } elseif ($id && $action==='mark_shipped') {
    $trackingNumber = $_POST['tracking_number'] ?? '';
    $pdo->prepare("UPDATE orders SET status='in_transit', tracking_number=?, updated_at=NOW() WHERE id=?")->execute([$trackingNumber, $id]);
    $_SESSION['success_msg'] = 'Order marked as shipped';
  } elseif ($id && $action==='mark_delivered') {
    $pdo->prepare("UPDATE orders SET status='delivered', delivered_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$id]);
    $_SESSION['success_msg'] = 'Order marked as delivered';
  } elseif ($id && $action==='record_payment') {
    // Record a payment (partial or full)
    $paymentAmount = (float)($_POST['payment_amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? 'check';
    $paymentNotes = $_POST['payment_notes'] ?? '';

    if ($paymentAmount > 0) {
      // Get current order financials
      $stmt = $pdo->prepare("SELECT amount_due, amount_paid, balance_due, qty_per_change, product_price, pieces_per_box FROM orders o LEFT JOIN products pr ON o.product_id = pr.id WHERE o.id = ?");
      $stmt->execute([$id]);
      $orderData = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($orderData) {
        // Calculate order value if not set
        $currentAmountDue = (float)($orderData['amount_due'] ?? 0);
        $currentAmountPaid = (float)($orderData['amount_paid'] ?? 0);
        $currentBalance = (float)($orderData['balance_due'] ?? 0);

        if ($currentAmountDue == 0) {
          $boxes = (int)($orderData['qty_per_change'] ?? 0);
          $unitPrice = (float)($orderData['product_price'] ?? 0);
          $piecesPerBox = (int)($orderData['pieces_per_box'] ?? 10);
          $currentAmountDue = $boxes * $unitPrice * $piecesPerBox;
          $currentBalance = $currentAmountDue;
        }

        // Apply payment
        $newAmountPaid = $currentAmountPaid + $paymentAmount;
        $newBalance = $currentAmountDue - $newAmountPaid;

        // Update order
        $updateStmt = $pdo->prepare("
          UPDATE orders
          SET amount_due = ?,
              amount_paid = ?,
              balance_due = ?,
              paid_at = CASE WHEN ? <= 0 THEN NOW() ELSE paid_at END,
              notes = CONCAT(COALESCE(notes, ''), '\n', ?),
              updated_at = NOW()
          WHERE id = ?
        ");

        $paymentNote = date('Y-m-d H:i') . " - Payment recorded: $" . number_format($paymentAmount, 2) . " via " . $paymentMethod . ($paymentNotes ? " - " . $paymentNotes : "");
        $updateStmt->execute([$currentAmountDue, $newAmountPaid, $newBalance, $newBalance, $paymentNote, $id]);

        $_SESSION['success_msg'] = 'Payment of $' . number_format($paymentAmount, 2) . ' recorded successfully';
      }
    }
  } elseif ($id && $action==='mark_unpaid') {
    $pdo->prepare("UPDATE orders SET paid_at=NULL, amount_paid=0, balance_due=amount_due, updated_at=NOW() WHERE id=?")->execute([$id]);
    $_SESSION['success_msg'] = 'Payment status reset to unpaid';
  } elseif ($id && $action==='delete_order') {
    // Delete wholesale order
    try {
      $pdo->beginTransaction();

      // Get order info
      $orderInfo = $pdo->prepare("SELECT patient_id, additional_instructions FROM orders WHERE id = ?");
      $orderInfo->execute([$id]);
      $orderData = $orderInfo->fetch(PDO::FETCH_ASSOC);

      // Delete the order
      $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$id]);

      // If patient has no other orders, delete patient too
      if ($orderData && $orderData['patient_id']) {
        $patientCheck = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders WHERE patient_id = ?");
        $patientCheck->execute([$orderData['patient_id']]);
        $result = $patientCheck->fetch(PDO::FETCH_ASSOC);

        if ($result['cnt'] == 0) {
          $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$orderData['patient_id']]);
        }
      }

      $pdo->commit();
      $_SESSION['success_msg'] = 'Order deleted successfully';
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      error_log('[wholesale-orders] delete_order error: ' . $e->getMessage());
      $_SESSION['error_msg'] = 'Error deleting order';
    }
  } elseif ($id && $action==='send_invoice') {
    // Send invoice email
    try {
      require_once __DIR__ . '/../api/lib/sg_curl.php';

      // Get order details
      $orderStmt = $pdo->prepare("
        SELECT o.*, u.email as phys_email, u.first_name as phys_first, u.last_name as phys_last,
               u.practice_name, p.first_name as pat_first, p.last_name as pat_last,
               pr.pieces_per_box, pr.price_wholesale
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN patients p ON o.patient_id = p.id
        LEFT JOIN products pr ON o.product_id = pr.id
        WHERE o.id = ?
      ");
      $orderStmt->execute([$id]);
      $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

      if ($order && $order['phys_email']) {
        $boxes = (int)($order['shipments_remaining'] ?? 0);
        $pieces_per_box = (int)($order['pieces_per_box'] ?? 10);
        $unit_price = (float)($order['product_price'] ?? $order['price_wholesale'] ?? 0);
        $total_value = $boxes * ($unit_price * $pieces_per_box);

        $subject = "Invoice for Wholesale Order #{$order['id']}";
        $body = "
          <h2>CollagenDirect Wholesale Order Invoice</h2>
          <p>Dear " . htmlspecialchars($order['phys_first'] . ' ' . $order['phys_last']) . ",</p>
          <p>Please find below the invoice for your recent wholesale order:</p>

          <h3>Order Details</h3>
          <table style='border-collapse: collapse; width: 100%; max-width: 600px;'>
            <tr style='border-bottom: 1px solid #ddd;'>
              <td style='padding: 8px;'><strong>Order Number:</strong></td>
              <td style='padding: 8px;'>#{$order['id']}</td>
            </tr>
            <tr style='border-bottom: 1px solid #ddd;'>
              <td style='padding: 8px;'><strong>Order Date:</strong></td>
              <td style='padding: 8px;'>" . date('F j, Y', strtotime($order['created_at'])) . "</td>
            </tr>
            <tr style='border-bottom: 1px solid #ddd;'>
              <td style='padding: 8px;'><strong>Practice:</strong></td>
              <td style='padding: 8px;'>" . htmlspecialchars($order['practice_name']) . "</td>
            </tr>
            <tr style='border-bottom: 1px solid #ddd;'>
              <td style='padding: 8px;'><strong>Patient:</strong></td>
              <td style='padding: 8px;'>" . htmlspecialchars($order['pat_first'] . ' ' . $order['pat_last']) . "</td>
            </tr>
            <tr style='border-bottom: 1px solid #ddd;'>
              <td style='padding: 8px;'><strong>Product:</strong></td>
              <td style='padding: 8px;'>" . htmlspecialchars($order['product']) . "</td>
            </tr>
            <tr style='border-bottom: 1px solid #ddd;'>
              <td style='padding: 8px;'><strong>Quantity:</strong></td>
              <td style='padding: 8px;'>{$boxes} boxes ({$pieces_per_box} pieces/box)</td>
            </tr>
            <tr style='border-bottom: 1px solid #ddd;'>
              <td style='padding: 8px;'><strong>Unit Price:</strong></td>
              <td style='padding: 8px;'>$" . number_format($unit_price, 2) . " per piece</td>
            </tr>
            <tr style='background: #f9fafb;'>
              <td style='padding: 8px;'><strong>Total Amount Due:</strong></td>
              <td style='padding: 8px;'><strong>$" . number_format($total_value, 2) . "</strong></td>
            </tr>
          </table>

          <p style='margin-top: 20px;'>Payment instructions will be provided separately. Please contact us if you have any questions.</p>

          <p>Best regards,<br>CollagenDirect Team</p>
        ";

        $sent = sg_send_simple($order['phys_email'], $subject, $body);
        if ($sent) {
          // Record invoice sent
          $pdo->prepare("UPDATE orders SET invoice_sent_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$id]);
          $_SESSION['success_msg'] = 'Invoice sent successfully to ' . $order['phys_email'];
        } else {
          $_SESSION['error_msg'] = 'Failed to send invoice email';
        }
      } else {
        $_SESSION['error_msg'] = 'Order not found or physician has no email';
      }
    } catch (Throwable $e) {
      error_log('[wholesale-orders] send_invoice error: ' . $e->getMessage());
      $_SESSION['error_msg'] = 'Error sending invoice';
    }
  }

  header('Location: /admin/wholesale-orders.php');
  exit;
}

/* ---------- Fetch wholesale orders ---------- */
// Check if billed_by column exists
$hasBilledBy = false;
try {
  $checkCol = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='orders' AND column_name='billed_by'");
  $hasBilledBy = $checkCol->rowCount() > 0;
} catch (Throwable $e) {
  error_log('[wholesale-orders] Column check error: ' . $e->getMessage());
}

if (!$hasBilledBy) {
  // Column doesn't exist yet - show empty state with instructions
  error_log('[wholesale-orders] billed_by column does not exist - please run migration');
  $orders = [];
} else {
  try {
    $sql = "
      SELECT
        o.id,
        o.created_at,
        o.product,
        o.qty_per_change as shipments_remaining,
        o.product_price as unit_price,
        o.status,
        o.paid_at,
        o.notes,
        o.billed_by,
        o.order_number as invoice_number,
        o.created_at as invoice_date,
        o.amount_due,
        o.amount_paid,
        o.balance_due,
        u.practice_name,
        u.first_name as phys_first,
        u.last_name as phys_last,
        u.email as phys_email,
        p.first_name as pat_first,
        p.last_name as pat_last,
        CONCAT_WS(', ', o.shipping_address, o.shipping_city, o.shipping_state, o.shipping_zip) as shipping_address,
        pr.pieces_per_box,
        pr.price_wholesale
      FROM orders o
      LEFT JOIN users u ON o.user_id = u.id
      LEFT JOIN patients p ON o.patient_id = p.id
      LEFT JOIN products pr ON o.product_id = pr.id
      WHERE o.billed_by = 'practice_dme'
        AND (o.review_status IS NULL OR o.review_status != 'draft')
      ORDER BY
        CASE
          WHEN o.status IN ('submitted', 'pending', 'awaiting_approval') THEN 1
          WHEN o.status = 'approved' THEN 2
          ELSE 3
        END,
        o.created_at DESC
    ";

    $orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    error_log('[wholesale-orders] Found ' . count($orders) . ' wholesale orders');
  } catch (Throwable $e) {
    error_log('[wholesale-orders] Query error: ' . $e->getMessage());
    error_log('[wholesale-orders] SQL: ' . $sql);
    $orders = [];
  }
}

/* ---------- Calculate totals and aging ---------- */
$totalOrders = count($orders);
$totalRevenue = 0.0;
$pendingOrders = 0;
$unpaidOrders = 0;

// Aging buckets
$aging = [
  'current' => 0.0,      // Not yet due (0 days)
  '0-30' => 0.0,         // 1-30 days past due
  '31-60' => 0.0,        // 31-60 days past due
  '61-90' => 0.0,        // 61-90 days past due
  'over_90' => 0.0       // Over 90 days past due
];

foreach ($orders as $order) {
  // Calculate order value
  $balanceDue = (float)($order['balance_due'] ?? 0);
  if ($balanceDue > 0) {
    $totalRevenue += $balanceDue;
  } else {
    // Fallback to old calculation if invoice fields don't exist yet
    $boxes = (int)($order['shipments_remaining'] ?? 0);
    $pieces_per_box = (int)($order['pieces_per_box'] ?? 10);
    $unit_price = (float)($order['unit_price'] ?? $order['price_wholesale'] ?? 0);
    $orderValue = $boxes * ($unit_price * $pieces_per_box);
    $totalRevenue += $orderValue;
  }

  // Count pending orders
  if (in_array($order['status'], ['submitted', 'pending', 'awaiting_approval'])) {
    $pendingOrders++;
  }

  // Count unpaid orders and track aging
  if ($balanceDue > 0 && !in_array($order['status'], ['rejected', 'cancelled'])) {
    $unpaidOrders++;

    // Add to aging buckets
    $agingBucket = $order['aging_bucket'] ?? null;
    switch ($agingBucket) {
      case 0:
        $aging['current'] += $balanceDue;
        break;
      case 1:
        $aging['0-30'] += $balanceDue;
        break;
      case 2:
        $aging['31-60'] += $balanceDue;
        break;
      case 3:
        $aging['61-90'] += $balanceDue;
        break;
      case 4:
        $aging['over_90'] += $balanceDue;
        break;
    }
  }
}

require_once '_header.php';
?>

<style>
  /* Modern admin styling matching portal design system */
  .stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
  }
  .stat-label {
    font-size: 0.875rem;
    color: var(--muted);
    font-weight: 500;
    margin-bottom: 0.5rem;
  }
  .stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--ink);
  }
  .stat-value.success { color: var(--success); }
  .stat-value.warning { color: var(--warning); }
  .stat-value.error { color: var(--error); }

  .order-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  .order-table th {
    text-align: left;
    padding: 0.75rem;
    border-bottom: 2px solid var(--border);
    font-weight: 600;
    color: var(--ink);
    white-space: nowrap;
  }
  .order-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border);
  }
  .order-table tbody tr:hover {
    background: var(--bg-gray);
  }

  .badge {
    display: inline-block;
    padding: 0.25rem 0.625rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
  }
  .badge-pending { background: var(--warning-light); color: #92400e; }
  .badge-approved { background: #dbeafe; color: #1e40af; }
  .badge-shipped { background: #e0e7ff; color: #4338ca; }
  .badge-delivered { background: var(--success-light); color: #065f46; }
  .badge-rejected { background: var(--error-light); color: #991b1b; }
  .badge-paid { background: var(--success-light); color: #065f46; }
  .badge-unpaid { background: #fef3c7; color: #92400e; }

  .btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: white;
    color: var(--ink);
    cursor: pointer;
    transition: all 0.15s ease;
  }
  .btn-icon:hover {
    background: var(--bg-gray);
    border-color: var(--muted);
  }
  .btn-icon.primary { color: var(--info); border-color: var(--info); }
  .btn-icon.primary:hover { background: #dbeafe; }
  .btn-icon.success { color: var(--success); border-color: var(--success); }
  .btn-icon.success:hover { background: var(--success-light); }
  .btn-icon.warning { color: var(--warning); border-color: var(--warning); }
  .btn-icon.warning:hover { background: var(--warning-light); }

  /* Modal styles */
  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
  }
  .modal.active {
    display: flex;
  }
  .modal-content {
    background: white;
    border-radius: var(--radius-lg);
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  }
  .modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
  }
  .modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--ink);
  }
  .modal-body {
    padding: 1.5rem;
  }
  .modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
  }
</style>

<div style="max-width: 1400px; margin: 0 auto; padding: 2rem;">
  <!-- Header -->
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
    <div>
      <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">Wholesale Orders</h1>
      <p style="color: var(--muted); font-size: 0.875rem;">Manage orders where practices bill their own DME license</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
      <a href="/admin/wholesale-orders.php?export=csv" class="btn" style="background: var(--success); color: white; border-color: var(--success);">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        Export CSV
      </a>
      <a href="/admin/orders.php" class="btn">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        All Orders
      </a>
    </div>
  </div>

  <!-- Stats Cards -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
    <div class="stat-card">
      <div class="stat-label">Total Orders</div>
      <div class="stat-value"><?= number_format($totalOrders) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pending Approval</div>
      <div class="stat-value warning"><?= number_format($pendingOrders) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Unpaid Orders</div>
      <div class="stat-value error"><?= number_format($unpaidOrders) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total A/R Balance</div>
      <div class="stat-value success">$<?= number_format($totalRevenue, 2) ?></div>
    </div>
  </div>

  <!-- Aging Summary -->
  <div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; color: var(--ink);">Accounts Receivable Aging</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
      <div style="text-align: center; padding: 0.75rem; background: #f0fdf4; border-radius: var(--radius); border: 1px solid #bbf7d0;">
        <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem; font-weight: 500;">Current</div>
        <div style="font-size: 1.25rem; font-weight: 700; color: #15803d;">$<?= number_format($aging['current'], 2) ?></div>
        <div style="font-size: 0.7rem; color: var(--muted);">Not yet due</div>
      </div>
      <div style="text-align: center; padding: 0.75rem; background: #fefce8; border-radius: var(--radius); border: 1px solid #fde047;">
        <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem; font-weight: 500;">0-30 Days</div>
        <div style="font-size: 1.25rem; font-weight: 700; color: #ca8a04;">$<?= number_format($aging['0-30'], 2) ?></div>
        <div style="font-size: 0.7rem; color: var(--muted);">Past due</div>
      </div>
      <div style="text-align: center; padding: 0.75rem; background: #fff7ed; border-radius: var(--radius); border: 1px solid #fed7aa;">
        <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem; font-weight: 500;">31-60 Days</div>
        <div style="font-size: 1.25rem; font-weight: 700; color: #ea580c;">$<?= number_format($aging['31-60'], 2) ?></div>
        <div style="font-size: 0.7rem; color: var(--muted);">Past due</div>
      </div>
      <div style="text-align: center; padding: 0.75rem; background: #fef2f2; border-radius: var(--radius); border: 1px solid #fecaca;">
        <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem; font-weight: 500;">61-90 Days</div>
        <div style="font-size: 1.25rem; font-weight: 700; color: #dc2626;">$<?= number_format($aging['61-90'], 2) ?></div>
        <div style="font-size: 0.7rem; color: var(--muted);">Past due</div>
      </div>
      <div style="text-align: center; padding: 0.75rem; background: #fef2f2; border-radius: var(--radius); border: 2px solid #dc2626;">
        <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem; font-weight: 500;">Over 90 Days</div>
        <div style="font-size: 1.25rem; font-weight: 700; color: #991b1b;">$<?= number_format($aging['over_90'], 2) ?></div>
        <div style="font-size: 0.7rem; color: #991b1b; font-weight: 600;">⚠️ BLOCKS ORDERING</div>
      </div>
    </div>
  </div>

  <?php if (isset($_SESSION['success_msg'])): ?>
  <div class="card" style="padding: 1rem; margin-bottom: 1.5rem; background: var(--success-light); border-color: var(--success); color: #065f46;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
      <span><?= htmlspecialchars($_SESSION['success_msg']) ?></span>
    </div>
  </div>
  <?php unset($_SESSION['success_msg']); endif; ?>

  <?php if (isset($_SESSION['error_msg'])): ?>
  <div class="card" style="padding: 1rem; margin-bottom: 1.5rem; background: var(--error-light); border-color: var(--error); color: #991b1b;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
      <span><?= htmlspecialchars($_SESSION['error_msg']) ?></span>
    </div>
  </div>
  <?php unset($_SESSION['error_msg']); endif; ?>

  <!-- Orders Table -->
  <div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
      <table class="order-table">
        <thead>
          <tr>
            <th>Invoice #</th>
            <th>Practice</th>
            <th>Patient</th>
            <th>Product</th>
            <th style="text-align: right;">Qty</th>
            <th style="text-align: right;">Amount</th>
            <th>Due Date</th>
            <th>Aging</th>
            <th style="text-align: center;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
          <tr>
            <td colspan="9" style="text-align: center; padding: 3rem; color: var(--muted);">
              <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 1rem; opacity: 0.3;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
              <div style="font-size: 1.125rem; font-weight: 500; margin-bottom: 0.5rem;">No wholesale orders found</div>
              <div style="font-size: 0.875rem;">
                <?php if (!$hasBilledBy): ?>
                  The 'billed_by' column does not exist. Please run the migration to add it.
                <?php else: ?>
                  Wholesale orders have billed_by='practice_dme'. No orders currently match this criteria.
                  <br><a href="/admin/check-wholesale-data.php" style="color: var(--info); text-decoration: underline; margin-top: 0.5rem; display: inline-block;">Run diagnostic to check database</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($orders as $order):
            $boxes = (int)($order['shipments_remaining'] ?? 0);
            $pieces_per_box = (int)($order['pieces_per_box'] ?? 10);
            $unit_price = (float)($order['unit_price'] ?? $order['price_wholesale'] ?? 0);

            // Calculate total order value
            $orderValue = $boxes * ($unit_price * $pieces_per_box);

            // Use database payment tracking if available
            $amountDue = (float)($order['amount_due'] ?? 0);
            $amountPaid = (float)($order['amount_paid'] ?? 0);
            $balanceDue = (float)($order['balance_due'] ?? 0);

            // If no payment tracking set, initialize with order value
            if ($amountDue == 0 && $amountPaid == 0 && $balanceDue == 0) {
              $amountDue = $orderValue;
              $balanceDue = $orderValue;
            }

            $isPaid = !empty($order['paid_at']) && $balanceDue == 0;

            // Calculate aging in PHP instead of SQL
            $createdDate = new DateTime($order['created_at']);
            $dueDate = clone $createdDate;
            $dueDate->modify('+30 days');
            $today = new DateTime();
            $daysOld = $today->diff($createdDate)->days;
            $daysPastDue = max(0, $today->diff($dueDate)->days);
            if ($today < $dueDate) {
              $daysPastDue = 0;
            }

            // Determine aging bucket
            if ($isPaid) {
              $agingBucket = -1;
            } elseif ($daysOld <= 30) {
              $agingBucket = 0;
            } elseif ($daysOld <= 60) {
              $agingBucket = 1;
            } elseif ($daysOld <= 90) {
              $agingBucket = 2;
            } else {
              $agingBucket = 3;
            }

            // Determine aging badge
            $agingBadge = '';
            $agingColor = '';
            if ($isPaid) {
              $agingBadge = 'Paid';
              $agingColor = '#15803d';
            } elseif ($agingBucket == -1) {
              $agingBadge = 'Paid';
              $agingColor = '#15803d';
            } elseif ($agingBucket === 0) {
              $agingBadge = '0-30 days';
              $agingColor = '#15803d';
            } elseif ($agingBucket === 1) {
              $agingBadge = '31-60 days';
              $agingColor = '#ca8a04';
            } elseif ($agingBucket === 2) {
              $agingBadge = '61-90 days';
              $agingColor = '#ea580c';
            } elseif ($agingBucket === 3) {
              $agingBadge = '61-90 days';
              $agingColor = '#dc2626';
            } else {
              $agingBadge = 'Over 90 days';
              $agingColor = '#991b1b';
            }

            $needsApproval = in_array($order['status'], ['submitted', 'pending', 'awaiting_approval']);
            $canShip = $order['status'] === 'approved';
            $canDeliver = $order['status'] === 'in_transit';
          ?>
          <tr>
            <td>
              <div style="font-weight: 500;"><?= htmlspecialchars($order['invoice_number'] ?? 'N/A') ?></div>
              <div style="font-size: 0.75rem; color: var(--muted);"><?= !empty($order['invoice_date']) ? date('M j, Y', strtotime($order['invoice_date'])) : date('M j, Y', strtotime($order['created_at'])) ?></div>
            </td>
            <td>
              <div style="font-weight: 500;"><?= htmlspecialchars($order['practice_name'] ?? 'N/A') ?></div>
              <div style="font-size: 0.75rem; color: var(--muted);"><?= htmlspecialchars(trim(($order['phys_first'] ?? '') . ' ' . ($order['phys_last'] ?? ''))) ?></div>
            </td>
            <td><?= htmlspecialchars(trim(($order['pat_first'] ?? '') . ' ' . ($order['pat_last'] ?? ''))) ?></td>
            <td>
              <div style="font-weight: 500; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($order['product'] ?? '') ?>">
                <?= htmlspecialchars($order['product'] ?? '') ?>
              </div>
            </td>
            <td style="text-align: right;">
              <div style="font-weight: 600;"><?= $boxes ?> boxes</div>
              <div style="font-size: 0.75rem; color: var(--muted);"><?= $pieces_per_box ?> pcs/box</div>
            </td>
            <td style="text-align: right;">
              <div style="font-weight: 600; color: var(--success);">$<?= number_format($orderValue, 2) ?></div>
              <?php if ($amountPaid > 0 && !$isPaid): ?>
                <div style="font-size: 0.75rem; color: var(--muted);">Paid: $<?= number_format($amountPaid, 2) ?></div>
              <?php else: ?>
                <div style="font-size: 0.75rem; color: var(--muted);"><?= $boxes ?> boxes @ $<?= number_format($unit_price, 2) ?>/pc</div>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight: 500;"><?= $dueDate->format('M j, Y') ?></div>
              <?php if (!$isPaid && $daysPastDue > 0): ?>
                <div style="font-size: 0.75rem; color: #dc2626;"><?= $daysPastDue ?> days past due</div>
              <?php elseif (!$isPaid): ?>
                <div style="font-size: 0.75rem; color: var(--muted);">Net 30</div>
              <?php endif; ?>
            </td>
            <td>
              <span style="display: inline-block; padding: 0.25rem 0.625rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; background: <?= $isPaid ? '#f0fdf4' : ($agingBucket >= 3 ? '#fef2f2' : '#fefce8') ?>; color: <?= $agingColor ?>;">
                <?= $agingBadge ?>
              </span>
            </td>
            <td>
              <div style="display: flex; gap: 0.5rem; justify-content: center;">
                <!-- View Details -->
                <button class="btn-icon primary" onclick="viewOrderDetail('<?= $order['id'] ?>')" title="View Details">
                  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                </button>

                <!-- Approve (if pending) -->
                <?php if ($needsApproval): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Approve this order?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="id" value="<?= $order['id'] ?>">
                  <button type="submit" class="btn-icon success" title="Approve Order">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                  </button>
                </form>
                <?php endif; ?>

                <!-- Ship (if approved) -->
                <?php if ($canShip): ?>
                <button class="btn-icon" onclick="openShipModal('<?= $order['id'] ?>')" title="Mark as Shipped" style="color: #4338ca; border-color: #4338ca;">
                  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>
                </button>
                <?php endif; ?>

                <!-- Record Payment -->
                <?php if (!$isPaid): ?>
                <button class="btn-icon" onclick="openPaymentModal('<?= $order['id'] ?>', <?= $balanceDue ?>, '<?= htmlspecialchars($order['invoice_number'] ?? $order['id']) ?>')" title="Record Payment" style="color: #059669; border-color: #059669;">
                  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </button>
                <?php endif; ?>

                <!-- View Invoice -->
                <button class="btn-icon" onclick="viewInvoice('<?= $order['id'] ?>')" title="View Invoice" style="color: #0ea5e9; border-color: #0ea5e9;">
                  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </button>

                <!-- Send Invoice -->
                <form method="POST" style="display: inline;" onsubmit="return confirm('Send invoice to <?= htmlspecialchars($order['phys_email']) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="send_invoice">
                  <input type="hidden" name="id" value="<?= $order['id'] ?>">
                  <button type="submit" class="btn-icon warning" title="Send Invoice">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                  </button>
                </form>

                <!-- Delete Order -->
                <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Are you sure you want to DELETE this order? This action cannot be undone!');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_order">
                  <input type="hidden" name="id" value="<?= $order['id'] ?>">
                  <button type="submit" class="btn-icon" title="Delete Order" style="color: #dc2626; border-color: #dc2626;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Ship Order Modal -->
<div id="shipModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">Mark Order as Shipped</h3>
    </div>
    <form method="POST" id="shipForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mark_shipped">
      <input type="hidden" name="id" id="shipOrderId">
      <div class="modal-body">
        <div style="margin-bottom: 1rem;">
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Tracking Number (Optional)</label>
          <input type="text" name="tracking_number" class="w-full" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);" placeholder="Enter tracking number">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeShipModal()">Cancel</button>
        <button type="submit" class="btn" style="background: var(--info); color: white; border-color: var(--info);">Mark as Shipped</button>
      </div>
    </form>
  </div>
</div>

<!-- Payment Recording Modal -->
<div id="paymentModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">Record Payment</h3>
    </div>
    <form method="POST" id="paymentForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="record_payment">
      <input type="hidden" name="id" id="paymentOrderId">
      <div class="modal-body">
        <div style="background: #f8fafc; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem;">Invoice #</div>
              <div style="font-weight: 600;" id="paymentInvoiceNum">-</div>
            </div>
            <div style="text-align: right;">
              <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem;">Balance Due</div>
              <div style="font-weight: 700; font-size: 1.25rem; color: #dc2626;" id="paymentBalanceDue">$0.00</div>
            </div>
          </div>
        </div>

        <div style="margin-bottom: 1rem;">
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Payment Amount *</label>
          <input type="number" name="payment_amount" id="paymentAmount" step="0.01" min="0.01" required class="w-full" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius); font-size: 1rem;" placeholder="0.00">
          <div style="margin-top: 0.5rem;">
            <button type="button" class="btn btn-sm" onclick="setFullPayment()" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">Pay Full Balance</button>
          </div>
        </div>

        <div style="margin-bottom: 1rem;">
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Payment Method</label>
          <select name="payment_method" class="w-full" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
            <option value="check">Check</option>
            <option value="wire">Wire Transfer</option>
            <option value="ach">ACH</option>
            <option value="credit_card">Credit Card</option>
            <option value="cash">Cash</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div style="margin-bottom: 1rem;">
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Notes (Optional)</label>
          <textarea name="payment_notes" rows="3" class="w-full" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);" placeholder="Check number, transaction ID, etc."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closePaymentModal()">Cancel</button>
        <button type="submit" class="btn" style="background: #059669; color: white; border-color: #059669;">Record Payment</button>
      </div>
    </form>
  </div>
</div>

<!-- Order Detail Modal -->
<div id="detailModal" class="modal">
  <div class="modal-content" style="max-width: 800px;">
    <div class="modal-header">
      <h3 class="modal-title">Order Details</h3>
    </div>
    <div class="modal-body" id="orderDetailContent">
      <div style="text-align: center; padding: 2rem; color: var(--muted);">Loading...</div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn" onclick="closeDetailModal()">Close</button>
    </div>
  </div>
</div>

<script>
function openShipModal(orderId) {
  document.getElementById('shipOrderId').value = orderId;
  document.getElementById('shipModal').classList.add('active');
}

function closeShipModal() {
  document.getElementById('shipModal').classList.remove('active');
}

function closeDetailModal() {
  document.getElementById('detailModal').classList.remove('active');
}

async function viewOrderDetail(orderId) {
  document.getElementById('detailModal').classList.add('active');
  document.getElementById('orderDetailContent').innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--muted);">Loading...</div>';

  try {
    const response = await fetch(`/admin/api-order-detail.php?id=${orderId}`);
    const data = await response.json();

    if (data.ok) {
      const o = data.order;
      const boxes = parseInt(o.shipments_remaining || 0);
      const piecesPerBox = parseInt(o.pieces_per_box || 10);
      const unitPrice = parseFloat(o.unit_price || o.price_wholesale || 0);
      const total = boxes * (unitPrice * piecesPerBox);

      document.getElementById('orderDetailContent').innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
          <div>
            <h4 style="font-weight: 600; margin-bottom: 1rem; color: var(--ink);">Order Information</h4>
            <div style="display: grid; gap: 0.75rem;">
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Order Number</div>
                <div style="font-weight: 500;">#${o.id}</div>
              </div>
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Order Date</div>
                <div>${new Date(o.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
              </div>
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Status</div>
                <div><span class="badge badge-${o.status}">${o.status}</span></div>
              </div>
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Payment Status</div>
                <div><span class="badge badge-${o.paid_at ? 'paid' : 'unpaid'}">${o.paid_at ? 'Paid on ' + new Date(o.paid_at).toLocaleDateString() : 'Unpaid'}</span></div>
              </div>
            </div>
          </div>

          <div>
            <h4 style="font-weight: 600; margin-bottom: 1rem; color: var(--ink);">Practice & Patient</h4>
            <div style="display: grid; gap: 0.75rem;">
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Practice</div>
                <div style="font-weight: 500;">${o.practice_name || 'N/A'}</div>
              </div>
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Physician</div>
                <div>${o.phys_first} ${o.phys_last}</div>
                <div style="font-size: 0.75rem; color: var(--muted);">${o.phys_email}</div>
              </div>
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Patient</div>
                <div>${o.pat_first} ${o.pat_last}</div>
              </div>
            </div>
          </div>

          <div>
            <h4 style="font-weight: 600; margin-bottom: 1rem; color: var(--ink);">Product Details</h4>
            <div style="display: grid; gap: 0.75rem;">
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Product</div>
                <div>${o.product}</div>
              </div>
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Quantity</div>
                <div>${boxes} boxes (${piecesPerBox} pieces per box)</div>
              </div>
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Unit Price</div>
                <div>$${unitPrice.toFixed(2)} per piece</div>
              </div>
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Total Amount</div>
                <div style="font-size: 1.25rem; font-weight: 700; color: var(--success);">$${total.toFixed(2)}</div>
              </div>
            </div>
          </div>

          <div>
            <h4 style="font-weight: 600; margin-bottom: 1rem; color: var(--ink);">Shipping Information</h4>
            <div style="display: grid; gap: 0.75rem;">
              <div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Shipping Address</div>
                <div>${o.shipping_address || 'N/A'}</div>
              </div>
              ${o.tracking_number ? `
                <div>
                  <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.25rem;">Tracking Number</div>
                  <div style="font-family: monospace;">${o.tracking_number}</div>
                </div>
              ` : ''}
            </div>
          </div>
        </div>

        ${o.notes ? `
          <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
            <h4 style="font-weight: 600; margin-bottom: 0.75rem; color: var(--ink);">Order Notes</h4>
            <div style="padding: 1rem; background: var(--bg-gray); border-radius: var(--radius); font-size: 0.875rem;">
              ${o.notes}
            </div>
          </div>
        ` : ''}
      `;
    } else {
      document.getElementById('orderDetailContent').innerHTML = `
        <div style="text-align: center; padding: 2rem; color: var(--error);">
          <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 1rem; opacity: 0.5;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <div style="font-weight: 500;">Failed to load order details</div>
        </div>
      `;
    }
  } catch (err) {
    document.getElementById('orderDetailContent').innerHTML = `
      <div style="text-align: center; padding: 2rem; color: var(--error);">
        <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 1rem; opacity: 0.5;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <div style="font-weight: 500;">Error loading order details</div>
      </div>
    `;
  }
}

// Payment Modal Functions
let currentBalanceDue = 0;

function openPaymentModal(orderId, balanceDue, invoiceNum) {
  currentBalanceDue = balanceDue;
  document.getElementById('paymentOrderId').value = orderId;
  document.getElementById('paymentInvoiceNum').textContent = invoiceNum;
  document.getElementById('paymentBalanceDue').textContent = '$' + balanceDue.toFixed(2);
  document.getElementById('paymentAmount').value = '';
  document.getElementById('paymentModal').classList.add('active');
}

function closePaymentModal() {
  document.getElementById('paymentModal').classList.remove('active');
}

function setFullPayment() {
  document.getElementById('paymentAmount').value = currentBalanceDue.toFixed(2);
}

// Invoice Viewing Function
function viewInvoice(orderId) {
  // Fetch order to get order_number for wholesale PDF
  fetch(`/admin/api-order-detail.php?id=${orderId}`)
    .then(res => res.json())
    .then(data => {
      if (data.ok && data.order.order_number) {
        // Use existing wholesale order PDF with CSRF token
        const csrfToken = '<?= $_SESSION['csrf'] ?? '' ?>';
        window.open(`/portal/wholesale-order.pdf.php?order_group=${data.order.order_number}&csrf=${csrfToken}`, '_blank');
      } else {
        alert('Unable to generate invoice for this order');
      }
    })
    .catch(err => {
      console.error('Error fetching order:', err);
      alert('Error loading invoice');
    });
}

// Close modals on background click
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal')) {
    e.target.classList.remove('active');
  }
});
</script>

<?php require_once '_footer.php'; ?>
