<?php
/**
 * Billing > Wholesale - AR Management for Wholesale Orders
 *
 * Invoice-centric view focused on:
 * - Aging buckets (Current, 1-30, 31-60, 61-90, 90+)
 * - Payment tracking and recording
 * - Practice ledger drill-down
 * - Statement generation
 */
declare(strict_types=1);

$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
require_once __DIR__.'/db.php';
require_once __DIR__.'/../api/lib/commission.php';
$auth = __DIR__.'/auth.php'; if (is_file($auth)) { require_once $auth; if (function_exists('require_admin')) require_admin(); }

// Sales reps cannot access wholesale billing
if (function_exists('deny_sales_rep')) deny_sales_rep();

// Get current admin user and role
$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminIdRaw = $admin['id'] ?? '';
// recorded_by column expects INTEGER (admin_users.id)
// If admin ID is a UUID (superadmin from users table), don't store it
$adminId = is_numeric($adminIdRaw) ? (int)$adminIdRaw : null;

/* ================= Polyfills / safety ================= */
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* ================= CSV Export ================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=wholesale_billing_' . date('Y-m-d') . '.csv');

  $output = fopen('php://output', 'w');

  // CSV Headers
  fputcsv($output, [
    'Invoice #',
    'Practice',
    'Invoice Date',
    'Due Date',
    'Terms',
    'Amount Due',
    'Amount Paid',
    'Balance Due',
    'Status',
    'Days Past Due',
    'Aging Bucket',
    'Items'
  ]);

  // Build same query as main view but for export
  // Match wholesale-orders.php logic: billed_by = 'practice_dme'
  $whereConditions = ["o.billed_by = 'practice_dme'"];
  $whereConditions[] = "(o.review_status IS NULL OR o.review_status != 'draft')";
  $params = [];

  $filterPractice = $_GET['practice'] ?? '';
  $filterStatus = $_GET['status'] ?? '';
  $filterFrom = $_GET['from'] ?? '';
  $filterTo = $_GET['to'] ?? '';
  $filterSearch = $_GET['search'] ?? '';

  if ($filterPractice) {
    $whereConditions[] = "o.user_id = ?";
    $params[] = $filterPractice;
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
    $whereConditions[] = "(o.order_number ILIKE ? OR u.practice_name ILIKE ?)";
    $params[] = '%' . $filterSearch . '%';
    $params[] = '%' . $filterSearch . '%';
  }

  $whereClause = implode(' AND ', $whereConditions);

  $sql = "
    SELECT
      o.id, o.order_number, o.created_at, o.due_date, o.invoice_status,
      o.amount_due, o.amount_paid, o.balance_due, o.paid_at,
      o.qty_per_change as boxes, o.product_price as unit_price, o.product,
      u.practice_name, u.default_payment_terms,
      pr.pieces_per_box, pr.name as product_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN products pr ON o.product_id = pr.id
    WHERE $whereClause
    ORDER BY o.order_number DESC, o.created_at DESC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Group by order_number for export
  $grouped = [];
  foreach ($rows as $row) {
    $orderNum = $row['order_number'] ?? $row['id'];
    if (!isset($grouped[$orderNum])) {
      $terms = $row['default_payment_terms'] ?? 'net30';
      $netDays = (int)str_replace('net', '', $terms);
      if ($netDays <= 0) $netDays = 30;
      $invoiceDate = new DateTime($row['created_at']);
      $dueDate = clone $invoiceDate;
      $dueDate->add(new DateInterval('P' . $netDays . 'D'));

      $grouped[$orderNum] = [
        'order_number' => $orderNum,
        'practice_name' => $row['practice_name'],
        'invoice_date' => $row['created_at'],
        'due_date' => $dueDate->format('Y-m-d'),
        'payment_terms' => $terms,
        'invoice_status' => $row['invoice_status'] ?? 'pending',
        'paid_at' => $row['paid_at'],
        'items' => [],
        'amount_due' => 0.0,
        'amount_paid' => 0.0,
        'balance_due' => 0.0,
        'total_value' => 0.0
      ];
    }

    $boxes = (int)($row['boxes'] ?? 0);
    $piecesPerBox = (int)($row['pieces_per_box'] ?? 10);
    $unitPrice = (float)($row['unit_price'] ?? 0);
    $itemValue = $boxes * $unitPrice * $piecesPerBox;

    $grouped[$orderNum]['items'][] = $row['product_name'] ?? $row['product'];
    $grouped[$orderNum]['total_value'] += $itemValue;
    $grouped[$orderNum]['amount_due'] += (float)($row['amount_due'] ?? 0);
    $grouped[$orderNum]['amount_paid'] += (float)($row['amount_paid'] ?? 0);
    $grouped[$orderNum]['balance_due'] += (float)($row['balance_due'] ?? 0);
  }

  $now = new DateTime();
  foreach ($grouped as $inv) {
    // Calculate effective balance
    if ($inv['amount_due'] == 0 && $inv['balance_due'] == 0 && empty($inv['paid_at'])) {
      $inv['amount_due'] = $inv['total_value'];
      $inv['balance_due'] = $inv['total_value'];
    }

    // Calculate aging
    $dueDate = new DateTime($inv['due_date']);
    $daysPastDue = 0;
    $agingBucket = 'Current';
    if ($now > $dueDate) {
      $daysPastDue = $now->diff($dueDate)->days;
      if ($daysPastDue <= 30) $agingBucket = '1-30 Days';
      elseif ($daysPastDue <= 60) $agingBucket = '31-60 Days';
      elseif ($daysPastDue <= 90) $agingBucket = '61-90 Days';
      else $agingBucket = '90+ Days';
    }

    fputcsv($output, [
      $inv['order_number'],
      $inv['practice_name'] ?? '',
      date('Y-m-d', strtotime($inv['invoice_date'])),
      $inv['due_date'],
      strtoupper($inv['payment_terms']),
      '$' . number_format($inv['amount_due'] ?: $inv['total_value'], 2),
      '$' . number_format($inv['amount_paid'], 2),
      '$' . number_format($inv['balance_due'], 2),
      ucfirst($inv['invoice_status']),
      $daysPastDue,
      $agingBucket,
      implode('; ', $inv['items'])
    ]);
  }

  fclose($output);
  exit;
}

/* ================= Handle Actions ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $action = $_POST['action'] ?? '';

  if ($action === 'record_payment') {
    $orderNumber = $_POST['order_number'] ?? '';
    $paymentAmount = (float)($_POST['payment_amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? 'check';
    $referenceNumber = $_POST['reference_number'] ?? '';
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $paymentNotes = $_POST['payment_notes'] ?? '';

    if ($orderNumber && $paymentAmount > 0) {
      try {
        $pdo->beginTransaction();

        // Get all orders with this order_number to distribute payment
        $orderStmt = $pdo->prepare("
          SELECT o.id, o.user_id, o.amount_due, o.amount_paid, o.balance_due, o.qty_per_change, o.product_price, o.product_id
          FROM orders o
          WHERE o.order_number = ? AND o.billed_by = 'practice_dme'
          ORDER BY o.created_at
        ");
        $orderStmt->execute([$orderNumber]);
        $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($orders) {
          $userId = $orders[0]['user_id'];
          $remainingPayment = $paymentAmount;

          // Record payment in wholesale_payments table
          $paymentStmt = $pdo->prepare("
            INSERT INTO wholesale_payments (order_id, order_number, user_id, amount, payment_method, reference_number, payment_date, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
          ");
          $paymentStmt->execute([
            $orders[0]['id'], // First order in group
            $orderNumber,
            $userId,
            $paymentAmount,
            $paymentMethod,
            $referenceNumber,
            $paymentDate,
            $paymentNotes,
            $adminId
          ]);

          // Distribute payment across orders in this group
          foreach ($orders as $order) {
            if ($remainingPayment <= 0) break;

            // Get current values
            $currentAmountDue = (float)($order['amount_due'] ?? 0);
            $currentAmountPaid = (float)($order['amount_paid'] ?? 0);
            $currentBalance = (float)($order['balance_due'] ?? 0);

            // Calculate order value if amount_due not set
            if ($currentAmountDue == 0) {
              $boxes = (int)($order['qty_per_change'] ?? 0);
              $unitPrice = (float)($order['product_price'] ?? 0);
              // Get pieces per box
              $piecesStmt = $pdo->prepare("SELECT pieces_per_box FROM products WHERE id = ?");
              $piecesStmt->execute([$order['product_id']]);
              $piecesPerBox = (int)($piecesStmt->fetchColumn() ?: 10);
              $currentAmountDue = round($boxes * $unitPrice * $piecesPerBox, 2);
            }

            // Calculate actual balance (amount_due - amount_paid)
            // Don't trust balance_due if it's inconsistent
            $actualBalance = round($currentAmountDue - $currentAmountPaid, 2);
            if ($actualBalance <= 0) continue; // Already fully paid

            // Apply payment to this order (use actual balance, not stored balance)
            $paymentForOrder = min($remainingPayment, $actualBalance);
            $newAmountPaid = round($currentAmountPaid + $paymentForOrder, 2);
            $newBalance = round($currentAmountDue - $newAmountPaid, 2);
            $remainingPayment = round($remainingPayment - $paymentForOrder, 2);

            // Update order (also set billed_by to normalize the data)
            if ($newBalance <= 0) {
              $updateStmt = $pdo->prepare("
                UPDATE orders SET amount_due = ?, amount_paid = ?, balance_due = ?, paid_at = NOW(),
                invoice_status = 'paid', billed_by = 'practice_dme', updated_at = NOW() WHERE id = ?
              ");
            } else {
              $updateStmt = $pdo->prepare("
                UPDATE orders SET amount_due = ?, amount_paid = ?, balance_due = ?,
                invoice_status = 'partial', billed_by = 'practice_dme', updated_at = NOW() WHERE id = ?
              ");
            }
            $updateStmt->execute([$currentAmountDue, $newAmountPaid, max(0, $newBalance), $order['id']]);
          }

          $pdo->commit();

          // Calculate commission for the sales rep (if clinic has an assigned rep)
          // Use the first order's UUID as the order_id reference
          $commissionResult = calculate_commission(
            $pdo,
            $orders[0]['id'],  // Use order UUID, not display order number
            'wholesale',
            $userId,
            $paymentAmount,
            $paymentDate
          );

          if ($commissionResult) {
            $_SESSION['success_msg'] = 'Payment of $' . number_format($paymentAmount, 2) . ' recorded successfully for ' . $orderNumber .
              ' (Commission: $' . number_format($commissionResult['commission_amount'], 2) . ')';
          } else {
            $_SESSION['success_msg'] = 'Payment of $' . number_format($paymentAmount, 2) . ' recorded successfully for ' . $orderNumber;
          }
        }
      } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Payment recording error: " . $e->getMessage());
        $_SESSION['error_msg'] = 'Error recording payment: ' . $e->getMessage();
      }
    }
    header('Location: /admin/billing-wholesale.php?' . http_build_query($_GET));
    exit;
  }

  if ($action === 'void_invoice') {
    $orderNumber = $_POST['order_number'] ?? '';
    $voidReason = $_POST['void_reason'] ?? '';

    if ($orderNumber) {
      $stmt = $pdo->prepare("
        UPDATE orders
        SET invoice_status = 'void', voided_at = NOW(), voided_by = ?, void_reason = ?, updated_at = NOW()
        WHERE order_number = ? AND billed_by = 'practice_dme'
      ");
      $stmt->execute([$adminId, $voidReason, $orderNumber]);
      $_SESSION['success_msg'] = 'Invoice voided: ' . $orderNumber;
    }
    header('Location: /admin/billing-wholesale.php?' . http_build_query($_GET));
    exit;
  }

  if ($action === 'flag_collection') {
    $orderNumber = $_POST['order_number'] ?? '';
    $flag = (int)($_POST['flag'] ?? 1);

    if ($orderNumber) {
      $status = $flag ? 'collections' : 'overdue';
      $stmt = $pdo->prepare("
        UPDATE orders
        SET collection_flag = ?, invoice_status = ?, updated_at = NOW()
        WHERE order_number = ? AND billed_by = 'practice_dme'
      ");
      $stmt->execute([$flag ? true : false, $status, $orderNumber]);
      $_SESSION['success_msg'] = $flag ? 'Flagged for collections: ' . $orderNumber : 'Removed collection flag: ' . $orderNumber;
    }
    header('Location: /admin/billing-wholesale.php?' . http_build_query($_GET));
    exit;
  }

  if ($action === 'update_practice_terms') {
    $userId = $_POST['user_id'] ?? '';
    $paymentTerms = $_POST['payment_terms'] ?? 'net30';
    $creditLimit = $_POST['credit_limit'] !== '' ? (float)$_POST['credit_limit'] : null;
    $billingNotes = $_POST['billing_notes'] ?? '';
    $billingContactName = $_POST['billing_contact_name'] ?? '';
    $billingContactEmail = $_POST['billing_contact_email'] ?? '';
    $billingContactPhone = $_POST['billing_contact_phone'] ?? '';

    if ($userId) {
      $stmt = $pdo->prepare("
        UPDATE users SET
          default_payment_terms = ?,
          credit_limit = ?,
          billing_notes = ?,
          billing_contact_name = ?,
          billing_contact_email = ?,
          billing_contact_phone = ?,
          updated_at = NOW()
        WHERE id = ?
      ");
      $stmt->execute([$paymentTerms, $creditLimit, $billingNotes, $billingContactName, $billingContactEmail, $billingContactPhone, $userId]);
      $_SESSION['success_msg'] = 'Practice billing settings updated';
    }
    header('Location: /admin/billing-wholesale.php?' . http_build_query($_GET));
    exit;
  }
}

/* ================= Filters ================= */
$filterPractice = $_GET['practice'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterAging = $_GET['aging'] ?? '';
// Default to showing all orders (no date filter) to ensure orders display
$filterFrom = $_GET['from'] ?? '';
$filterTo = $_GET['to'] ?? '';
$filterSearch = $_GET['search'] ?? '';

/* ================= Fetch Practices for Filter ================= */
$practices = [];
try {
  $stmt = $pdo->query("
    SELECT DISTINCT u.id, u.practice_name, u.first_name, u.last_name, u.default_payment_terms
    FROM users u
    INNER JOIN orders o ON o.user_id = u.id
    WHERE o.billed_by = 'practice_dme'
    ORDER BY u.practice_name
  ");
  $practices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log("Practice fetch error: " . $e->getMessage());
}

/* ================= Fetch Wholesale Invoices ================= */
$invoices = [];
$groupedInvoices = [];
$aging = ['current' => 0.0, '1-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
$totalAR = 0.0;
$totalPaid = 0.0;

try {
  // Match wholesale-orders.php logic: billed_by = 'practice_dme'
  // This is the authoritative marker for wholesale orders
  $whereConditions = ["o.billed_by = 'practice_dme'"];
  $whereConditions[] = "(o.review_status IS NULL OR o.review_status != 'draft')";
  $params = [];

  if ($filterPractice) {
    $whereConditions[] = "o.user_id = ?";
    $params[] = $filterPractice;
  }

  if ($filterStatus) {
    if ($filterStatus === 'unpaid') {
      // Handle NULL invoice_status as unpaid
      $whereConditions[] = "(o.paid_at IS NULL AND (o.invoice_status IS NULL OR o.invoice_status NOT IN ('paid', 'void')))";
    } elseif ($filterStatus === 'paid') {
      $whereConditions[] = "(o.paid_at IS NOT NULL OR o.invoice_status = 'paid')";
    } elseif ($filterStatus === 'partial') {
      $whereConditions[] = "o.invoice_status = 'partial'";
    } elseif ($filterStatus === 'overdue') {
      // Handle NULL invoice_status
      $whereConditions[] = "(o.invoice_status = 'overdue' OR (o.due_date < CURRENT_DATE AND o.paid_at IS NULL AND (o.invoice_status IS NULL OR o.invoice_status NOT IN ('paid', 'void'))))";
    } elseif ($filterStatus === 'collections') {
      $whereConditions[] = "o.collection_flag = TRUE";
    } elseif ($filterStatus === 'void') {
      $whereConditions[] = "o.invoice_status = 'void'";
    }
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
    $whereConditions[] = "(o.order_number ILIKE ? OR u.practice_name ILIKE ?)";
    $params[] = '%' . $filterSearch . '%';
    $params[] = '%' . $filterSearch . '%';
  }

  $whereClause = implode(' AND ', $whereConditions);

  $sql = "
    SELECT
      o.id,
      o.order_number,
      o.created_at,
      o.due_date,
      o.invoice_status,
      o.amount_due,
      o.amount_paid,
      o.balance_due,
      o.paid_at,
      o.collection_flag,
      o.qty_per_change as boxes,
      o.product_price as unit_price,
      o.product,
      o.product_id,
      o.user_id,
      o.status,
      u.practice_name,
      u.first_name as phys_first,
      u.last_name as phys_last,
      u.default_payment_terms,
      pr.pieces_per_box,
      pr.price_wholesale,
      pr.name as product_name,
      pr.size as product_size
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN products pr ON o.product_id = pr.id
    WHERE $whereClause
    ORDER BY o.order_number DESC, o.created_at DESC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
  error_log("[billing-wholesale] Found " . count($invoices) . " wholesale invoice records");

  // Group invoices by order_number
  foreach ($invoices as $inv) {
    $orderNum = $inv['order_number'] ?? $inv['id'];
    if (!isset($groupedInvoices[$orderNum])) {
      // Get payment terms (practice default or net30)
      $terms = $inv['default_payment_terms'] ?? 'net30';
      $netDays = (int)str_replace('net', '', $terms);
      if ($netDays <= 0) $netDays = 30;

      // Calculate due date
      $invoiceDate = new DateTime($inv['created_at']);
      $dueDate = clone $invoiceDate;
      $dueDate->add(new DateInterval('P' . $netDays . 'D'));

      $groupedInvoices[$orderNum] = [
        'order_number' => $orderNum,
        'invoice_date' => $inv['created_at'],
        'due_date' => $dueDate->format('Y-m-d'),
        'payment_terms' => $terms,
        'practice_name' => $inv['practice_name'],
        'user_id' => $inv['user_id'],
        'status' => $inv['status'],
        'invoice_status' => $inv['invoice_status'] ?? 'pending',
        'paid_at' => $inv['paid_at'],
        'collection_flag' => $inv['collection_flag'],
        'items' => [],
        'amount_due' => 0.0,
        'amount_paid' => 0.0,
        'balance_due' => 0.0,
        'total_value' => 0.0
      ];
    }

    // Calculate item value
    $boxes = (int)($inv['boxes'] ?? 0);
    $piecesPerBox = (int)($inv['pieces_per_box'] ?? 10);
    $unitPrice = (float)($inv['unit_price'] ?? $inv['price_wholesale'] ?? 0);
    $itemValue = $boxes * $unitPrice * $piecesPerBox;

    // Build product label with size for fulfillment clarity
    $productLabel = $inv['product_name'] ?? $inv['product'] ?? 'Unknown Product';
    if (!empty($inv['product_size'])) {
      $productLabel .= ' (' . $inv['product_size'] . ')';
    }

    $groupedInvoices[$orderNum]['items'][] = [
      'id' => $inv['id'],
      'product' => $productLabel,
      'boxes' => $boxes,
      'unit_price' => $unitPrice,
      'pieces_per_box' => $piecesPerBox,
      'value' => $itemValue
    ];

    $groupedInvoices[$orderNum]['total_value'] += $itemValue;
    $groupedInvoices[$orderNum]['amount_due'] += (float)($inv['amount_due'] ?? 0);
    $groupedInvoices[$orderNum]['amount_paid'] += (float)($inv['amount_paid'] ?? 0);
    $groupedInvoices[$orderNum]['balance_due'] += (float)($inv['balance_due'] ?? 0);

    // Use worst invoice_status
    if ($inv['invoice_status'] === 'collections') {
      $groupedInvoices[$orderNum]['invoice_status'] = 'collections';
    } elseif ($inv['invoice_status'] === 'overdue' && $groupedInvoices[$orderNum]['invoice_status'] !== 'collections') {
      $groupedInvoices[$orderNum]['invoice_status'] = 'overdue';
    }
  }

  // Calculate aging and effective balances
  $now = new DateTime();
  foreach ($groupedInvoices as $orderNum => &$group) {
    // If no tracking set, use total_value as balance
    if ($group['amount_due'] == 0 && $group['balance_due'] == 0 && empty($group['paid_at'])) {
      $group['amount_due'] = $group['total_value'];
      $group['balance_due'] = $group['total_value'];
    }

    $effectiveBalance = $group['balance_due'];
    $isPaid = !empty($group['paid_at']) || $group['invoice_status'] === 'paid';

    if ($isPaid) {
      $totalPaid += $group['amount_paid'];
      continue;
    }

    if ($effectiveBalance <= 0) continue;

    $totalAR += $effectiveBalance;

    // Calculate aging based on due date
    $dueDate = new DateTime($group['due_date']);
    $daysPastDue = 0;
    if ($now > $dueDate) {
      $daysPastDue = $now->diff($dueDate)->days;
    }

    // Filter by aging bucket if specified
    $agingBucket = 'current';
    if ($daysPastDue === 0) {
      $agingBucket = 'current';
      $aging['current'] += $effectiveBalance;
    } elseif ($daysPastDue <= 30) {
      $agingBucket = '1-30';
      $aging['1-30'] += $effectiveBalance;
    } elseif ($daysPastDue <= 60) {
      $agingBucket = '31-60';
      $aging['31-60'] += $effectiveBalance;
    } elseif ($daysPastDue <= 90) {
      $agingBucket = '61-90';
      $aging['61-90'] += $effectiveBalance;
    } else {
      $agingBucket = '90+';
      $aging['90+'] += $effectiveBalance;
    }

    $group['aging_bucket'] = $agingBucket;
    $group['days_past_due'] = $daysPastDue;

    // Update invoice_status based on aging
    if ($daysPastDue > 0 && $group['invoice_status'] === 'pending') {
      $group['invoice_status'] = 'overdue';
    }
  }
  unset($group);

  // Filter by aging bucket after calculation
  if ($filterAging) {
    $groupedInvoices = array_filter($groupedInvoices, function($g) use ($filterAging) {
      return ($g['aging_bucket'] ?? 'current') === $filterAging;
    });
  }

} catch (Throwable $e) {
  error_log("Invoice fetch error: " . $e->getMessage());
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
.status-invoiced { background: #e0e7ff; color: #3730a3; }
.status-partial { background: #fef3c7; color: #92400e; }
.status-paid { background: #d1fae5; color: #065f46; }
.status-overdue { background: #fee2e2; color: #991b1b; }
.status-collections { background: #991b1b; color: white; }
.status-void { background: #e5e7eb; color: #6b7280; text-decoration: line-through; }

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
  max-width: 500px;
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
      <h1 class="text-2xl font-bold text-slate-800">Wholesale Billing</h1>
      <p class="text-slate-500">Accounts Receivable Management</p>
    </div>
    <div class="flex gap-2">
      <a href="/admin/billing-wholesale.php?export=csv&<?=http_build_query($_GET)?>" class="btn btn-secondary text-sm">
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
      <div class="label">Current</div>
    </a>
    <a href="?<?=http_build_query(array_merge($_GET, ['aging' => '1-30']))?>" class="aging-card aging-30 <?=$filterAging === '1-30' ? 'active' : ''?>">
      <div class="amount">$<?=number_format($aging['1-30'], 2)?></div>
      <div class="label">1-30 Days</div>
    </a>
    <a href="?<?=http_build_query(array_merge($_GET, ['aging' => '31-60']))?>" class="aging-card aging-60 <?=$filterAging === '31-60' ? 'active' : ''?>">
      <div class="amount">$<?=number_format($aging['31-60'], 2)?></div>
      <div class="label">31-60 Days</div>
    </a>
    <a href="?<?=http_build_query(array_merge($_GET, ['aging' => '61-90']))?>" class="aging-card aging-90 <?=$filterAging === '61-90' ? 'active' : ''?>">
      <div class="amount">$<?=number_format($aging['61-90'], 2)?></div>
      <div class="label">61-90 Days</div>
    </a>
    <a href="?<?=http_build_query(array_merge($_GET, ['aging' => '90+']))?>" class="aging-card aging-over90 <?=$filterAging === '90+' ? 'active' : ''?>">
      <div class="amount">$<?=number_format($aging['90+'], 2)?></div>
      <div class="label">90+ Days</div>
    </a>
  </div>

  <!-- Summary Stats -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="card p-4">
      <div class="text-2xl font-bold text-slate-800">$<?=number_format($totalAR, 2)?></div>
      <div class="text-sm text-slate-500">Total A/R Outstanding</div>
    </div>
    <div class="card p-4">
      <div class="text-2xl font-bold text-green-600">$<?=number_format($totalPaid, 2)?></div>
      <div class="text-sm text-slate-500">Total Collected (Period)</div>
    </div>
    <div class="card p-4">
      <div class="text-2xl font-bold text-slate-800"><?=count($groupedInvoices)?></div>
      <div class="text-sm text-slate-500">Open Invoices</div>
    </div>
    <div class="card p-4 cursor-pointer hover:bg-slate-50" onclick="document.getElementById('practiceSettingsSection').scrollIntoView({behavior: 'smooth'})">
      <div class="text-2xl font-bold text-slate-800"><?=count($practices)?></div>
      <div class="text-sm text-slate-500">Active Practices</div>
    </div>
  </div>

  <!-- Practice Billing Settings -->
  <div id="practiceSettingsSection" class="card p-4 mb-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-slate-800">Practice Billing Settings</h3>
      <span class="text-xs text-slate-500">Configure payment terms, credit limits, and billing contacts</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b bg-slate-50">
          <tr class="text-left text-xs text-slate-600">
            <th class="py-2 px-3">Practice</th>
            <th class="py-2 px-3">Payment Terms</th>
            <th class="py-2 px-3">Credit Limit</th>
            <th class="py-2 px-3">Billing Contact</th>
            <th class="py-2 px-3">Notes</th>
            <th class="py-2 px-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          // Fetch full practice details for settings display
          $practiceDetails = [];
          try {
            $pStmt = $pdo->query("
              SELECT u.id, u.practice_name, u.first_name, u.last_name, u.email,
                     u.default_payment_terms, u.credit_limit, u.collection_flag,
                     u.billing_notes, u.billing_contact_name, u.billing_contact_email, u.billing_contact_phone
              FROM users u
              WHERE u.id IN (SELECT DISTINCT user_id FROM orders WHERE billed_by = 'practice_dme')
              ORDER BY u.practice_name
            ");
            $practiceDetails = $pStmt->fetchAll(PDO::FETCH_ASSOC);
          } catch (Throwable $e) { /* columns may not exist yet */ }
          ?>
          <?php if (empty($practiceDetails)): ?>
            <tr><td colspan="6" class="py-4 text-center text-slate-500">No practices with wholesale orders found.</td></tr>
          <?php else: ?>
            <?php foreach ($practiceDetails as $pd): ?>
              <tr class="border-t hover:bg-slate-50">
                <td class="py-2 px-3">
                  <div class="font-medium"><?=e($pd['practice_name'] ?: ($pd['first_name'] . ' ' . $pd['last_name']))?></div>
                  <div class="text-xs text-slate-500"><?=e($pd['email'] ?? '')?></div>
                </td>
                <td class="py-2 px-3">
                  <span class="px-2 py-1 bg-slate-100 rounded text-xs uppercase font-medium">
                    <?=e($pd['default_payment_terms'] ?: 'net30')?>
                  </span>
                </td>
                <td class="py-2 px-3">
                  <?php if (!empty($pd['credit_limit'])): ?>
                    $<?=number_format((float)$pd['credit_limit'], 2)?>
                  <?php else: ?>
                    <span class="text-slate-400">No limit</span>
                  <?php endif; ?>
                </td>
                <td class="py-2 px-3">
                  <?php if (!empty($pd['billing_contact_name'])): ?>
                    <div class="text-sm"><?=e($pd['billing_contact_name'])?></div>
                    <div class="text-xs text-slate-500"><?=e($pd['billing_contact_email'] ?? '')?></div>
                  <?php else: ?>
                    <span class="text-slate-400">Not set</span>
                  <?php endif; ?>
                </td>
                <td class="py-2 px-3">
                  <?php if (!empty($pd['billing_notes'])): ?>
                    <span class="text-xs text-slate-600" title="<?=e($pd['billing_notes'])?>">
                      <?=e(substr($pd['billing_notes'], 0, 30))?><?=strlen($pd['billing_notes']) > 30 ? '...' : ''?>
                    </span>
                  <?php else: ?>
                    <span class="text-slate-400">-</span>
                  <?php endif; ?>
                </td>
                <td class="py-2 px-3">
                  <button onclick='openPracticeSettingsModal(<?=json_encode($pd)?>)'
                          class="text-xs px-2 py-1 bg-brand text-white rounded hover:bg-brand-dark">
                    Edit
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Filters -->
  <div class="card p-4 mb-6">
    <form method="get" class="grid grid-cols-1 md:grid-cols-6 gap-3">
      <div>
        <label class="block text-xs text-slate-600 mb-1">Practice</label>
        <select name="practice" class="w-full rounded border-slate-300 text-sm">
          <option value="">All Practices</option>
          <?php foreach ($practices as $p): ?>
            <option value="<?=e($p['id'])?>" <?=$filterPractice === $p['id'] ? 'selected' : ''?>>
              <?=e($p['practice_name'] ?: ($p['first_name'] . ' ' . $p['last_name']))?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">Status</label>
        <select name="status" class="w-full rounded border-slate-300 text-sm">
          <option value="">All Statuses</option>
          <option value="unpaid" <?=$filterStatus === 'unpaid' ? 'selected' : ''?>>Unpaid</option>
          <option value="partial" <?=$filterStatus === 'partial' ? 'selected' : ''?>>Partial</option>
          <option value="paid" <?=$filterStatus === 'paid' ? 'selected' : ''?>>Paid</option>
          <option value="overdue" <?=$filterStatus === 'overdue' ? 'selected' : ''?>>Overdue</option>
          <option value="collections" <?=$filterStatus === 'collections' ? 'selected' : ''?>>Collections</option>
          <option value="void" <?=$filterStatus === 'void' ? 'selected' : ''?>>Void</option>
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
      <div>
        <label class="block text-xs text-slate-600 mb-1">Search</label>
        <input type="text" name="search" value="<?=e($filterSearch)?>" placeholder="Invoice # or Practice" class="w-full rounded border-slate-300 text-sm">
      </div>
      <div class="flex items-end gap-2">
        <button type="submit" class="btn btn-primary text-sm">Filter</button>
        <a href="/admin/billing-wholesale.php" class="btn btn-secondary text-sm">Clear</a>
      </div>
    </form>
  </div>

  <!-- Invoice Table -->
  <div class="card">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b bg-slate-50">
          <tr class="text-left text-xs text-slate-600">
            <th class="py-3 px-4">Invoice #</th>
            <th class="py-3 px-4">Practice</th>
            <th class="py-3 px-4">Invoice Date</th>
            <th class="py-3 px-4">Due Date</th>
            <th class="py-3 px-4">Terms</th>
            <th class="py-3 px-4 text-right">Amount Due</th>
            <th class="py-3 px-4 text-right">Paid</th>
            <th class="py-3 px-4 text-right">Balance</th>
            <th class="py-3 px-4">Status</th>
            <th class="py-3 px-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($groupedInvoices)): ?>
            <tr><td colspan="10" class="py-8 text-center text-slate-500">No invoices found matching your filters.</td></tr>
          <?php else: ?>
            <?php foreach ($groupedInvoices as $inv): ?>
              <?php
                $statusClass = 'status-' . ($inv['invoice_status'] ?? 'pending');
                $isPaid = $inv['invoice_status'] === 'paid' || !empty($inv['paid_at']);
                $isVoid = $inv['invoice_status'] === 'void';
                $effectiveBalance = $inv['balance_due'] > 0 ? $inv['balance_due'] : ($isPaid ? 0 : $inv['total_value']);
              ?>
              <tr class="border-t hover:bg-slate-50">
                <td class="py-3 px-4">
                  <span class="font-medium"><?=e($inv['order_number'])?></span>
                  <?php if (count($inv['items']) > 1): ?>
                    <span class="text-xs text-slate-400">(<?=count($inv['items'])?> items)</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-4">
                  <a href="?practice=<?=e($inv['user_id'])?>" class="text-brand hover:underline">
                    <?=e($inv['practice_name'] ?: 'Unknown')?>
                  </a>
                </td>
                <td class="py-3 px-4"><?=date('M j, Y', strtotime($inv['invoice_date']))?></td>
                <td class="py-3 px-4 <?=($inv['days_past_due'] ?? 0) > 0 ? 'text-red-600 font-medium' : ''?>">
                  <?=date('M j, Y', strtotime($inv['due_date']))?>
                  <?php if (($inv['days_past_due'] ?? 0) > 0): ?>
                    <span class="text-xs">(<?=$inv['days_past_due']?> days)</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-4 text-xs uppercase"><?=e($inv['payment_terms'])?></td>
                <td class="py-3 px-4 text-right font-medium">$<?=number_format($inv['amount_due'] ?: $inv['total_value'], 2)?></td>
                <td class="py-3 px-4 text-right text-green-600">$<?=number_format($inv['amount_paid'], 2)?></td>
                <td class="py-3 px-4 text-right font-semibold <?=$effectiveBalance > 0 && !$isVoid ? 'text-red-600' : ''?>">
                  $<?=number_format($effectiveBalance, 2)?>
                </td>
                <td class="py-3 px-4">
                  <span class="<?=$statusClass?> status-badge"><?=ucfirst($inv['invoice_status'] ?? 'pending')?></span>
                </td>
                <td class="py-3 px-4">
                  <div class="flex gap-1">
                    <?php if (!$isPaid && !$isVoid): ?>
                      <button onclick="openPaymentModal('<?=e($inv['order_number'])?>', <?=$effectiveBalance?>)"
                              class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200">
                        Pay
                      </button>
                    <?php endif; ?>
                    <button onclick="openDetailModal('<?=e($inv['order_number'])?>')"
                            class="text-xs px-2 py-1 bg-slate-100 text-slate-700 rounded hover:bg-slate-200">
                      View
                    </button>
                    <?php if (!$isVoid && $inv['status'] !== 'delivered'): ?>
                      <button onclick="openVoidModal('<?=e($inv['order_number'])?>')"
                              class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">
                        Void
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <?php if (!empty($groupedInvoices)): ?>
        <tfoot class="bg-slate-50 font-semibold">
          <tr>
            <td colspan="5" class="py-3 px-4">Total (<?=count($groupedInvoices)?> invoices)</td>
            <td class="py-3 px-4 text-right">$<?=number_format(array_sum(array_column($groupedInvoices, 'amount_due')) ?: array_sum(array_column($groupedInvoices, 'total_value')), 2)?></td>
            <td class="py-3 px-4 text-right text-green-600">$<?=number_format(array_sum(array_column($groupedInvoices, 'amount_paid')), 2)?></td>
            <td class="py-3 px-4 text-right text-red-600">$<?=number_format($totalAR, 2)?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="font-semibold text-lg">Record Payment</h3>
      <button onclick="closeModal('paymentModal')" class="text-slate-400 hover:text-slate-600">&times;</button>
    </div>
    <form method="post">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="record_payment">
      <input type="hidden" name="order_number" id="payment_order_number">
      <div class="modal-body space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Invoice</label>
          <div id="payment_invoice_display" class="font-mono text-lg"></div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Payment Amount *</label>
          <input type="number" name="payment_amount" id="payment_amount" step="0.01" min="0.01" required
                 class="w-full rounded border-slate-300">
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method</label>
            <select name="payment_method" class="w-full rounded border-slate-300">
              <option value="check">Check</option>
              <option value="ach">ACH Transfer</option>
              <option value="wire">Wire Transfer</option>
              <option value="credit_card">Credit Card</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Reference #</label>
            <input type="text" name="reference_number" placeholder="Check # / Transaction ID"
                   class="w-full rounded border-slate-300">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Payment Date</label>
          <input type="date" name="payment_date" value="<?=date('Y-m-d')?>" class="w-full rounded border-slate-300">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
          <textarea name="payment_notes" rows="2" class="w-full rounded border-slate-300"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('paymentModal')" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn btn-primary">Record Payment</button>
      </div>
    </form>
  </div>
</div>

<!-- Void Modal -->
<div id="voidModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="font-semibold text-lg">Void Invoice</h3>
      <button onclick="closeModal('voidModal')" class="text-slate-400 hover:text-slate-600">&times;</button>
    </div>
    <form method="post">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="void_invoice">
      <input type="hidden" name="order_number" id="void_order_number">
      <div class="modal-body space-y-4">
        <p class="text-slate-600">Are you sure you want to void invoice <strong id="void_invoice_display"></strong>?</p>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Reason for Voiding *</label>
          <textarea name="void_reason" rows="3" required class="w-full rounded border-slate-300"
                    placeholder="e.g., Order cancelled before shipment, duplicate invoice..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('voidModal')" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn bg-red-600 text-white hover:bg-red-700">Void Invoice</button>
      </div>
    </form>
  </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="modal">
  <div class="modal-content" style="max-width: 700px;">
    <div class="modal-header">
      <h3 class="font-semibold text-lg">Invoice Details</h3>
      <button onclick="closeModal('detailModal')" class="text-slate-400 hover:text-slate-600">&times;</button>
    </div>
    <div class="modal-body" id="detail_content">
      Loading...
    </div>
    <div class="modal-footer">
      <button type="button" onclick="closeModal('detailModal')" class="btn btn-secondary">Close</button>
    </div>
  </div>
</div>

<!-- Practice Settings Modal -->
<div id="practiceSettingsModal" class="modal">
  <div class="modal-content" style="max-width: 600px;">
    <div class="modal-header">
      <h3 class="font-semibold text-lg">Practice Billing Settings</h3>
      <button onclick="closeModal('practiceSettingsModal')" class="text-slate-400 hover:text-slate-600">&times;</button>
    </div>
    <form method="post">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="update_practice_terms">
      <input type="hidden" name="user_id" id="practice_user_id">
      <div class="modal-body space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Practice</label>
          <div id="practice_name_display" class="font-semibold text-lg"></div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Payment Terms *</label>
            <select name="payment_terms" id="practice_payment_terms" class="w-full rounded border-slate-300" required>
              <option value="net15">Net 15</option>
              <option value="net30">Net 30</option>
              <option value="net45">Net 45</option>
              <option value="net60">Net 60</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Credit Limit</label>
            <input type="number" name="credit_limit" id="practice_credit_limit" step="0.01" min="0"
                   placeholder="No limit" class="w-full rounded border-slate-300">
          </div>
        </div>

        <div class="border-t pt-4">
          <div class="text-sm font-medium text-slate-700 mb-2">Billing Contact</div>
          <div class="grid grid-cols-1 gap-3">
            <div>
              <label class="block text-xs text-slate-600 mb-1">Contact Name</label>
              <input type="text" name="billing_contact_name" id="practice_billing_contact_name"
                     class="w-full rounded border-slate-300 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-slate-600 mb-1">Email</label>
                <input type="email" name="billing_contact_email" id="practice_billing_contact_email"
                       class="w-full rounded border-slate-300 text-sm">
              </div>
              <div>
                <label class="block text-xs text-slate-600 mb-1">Phone</label>
                <input type="tel" name="billing_contact_phone" id="practice_billing_contact_phone"
                       class="w-full rounded border-slate-300 text-sm">
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Internal Notes</label>
          <textarea name="billing_notes" id="practice_billing_notes" rows="3" class="w-full rounded border-slate-300 text-sm"
                    placeholder="Internal notes for collections, payment history, etc."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('practiceSettingsModal')" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Settings</button>
      </div>
    </form>
  </div>
</div>

<script>
const invoiceData = <?=json_encode($groupedInvoices)?>;

function openPaymentModal(orderNumber, balance) {
  document.getElementById('payment_order_number').value = orderNumber;
  document.getElementById('payment_invoice_display').textContent = orderNumber;
  document.getElementById('payment_amount').value = balance.toFixed(2);
  document.getElementById('payment_amount').max = balance;
  document.getElementById('paymentModal').classList.add('active');
}

function openVoidModal(orderNumber) {
  document.getElementById('void_order_number').value = orderNumber;
  document.getElementById('void_invoice_display').textContent = orderNumber;
  document.getElementById('voidModal').classList.add('active');
}

function openDetailModal(orderNumber) {
  const inv = invoiceData[orderNumber];
  if (!inv) return;

  let html = `
    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <div class="text-xs text-slate-500 uppercase">Invoice #</div>
          <div class="font-medium">${orderNumber}</div>
        </div>
        <div>
          <div class="text-xs text-slate-500 uppercase">Practice</div>
          <div class="font-medium">${inv.practice_name || 'Unknown'}</div>
        </div>
        <div>
          <div class="text-xs text-slate-500 uppercase">Invoice Date</div>
          <div>${new Date(inv.invoice_date).toLocaleDateString()}</div>
        </div>
        <div>
          <div class="text-xs text-slate-500 uppercase">Due Date</div>
          <div>${new Date(inv.due_date).toLocaleDateString()}</div>
        </div>
      </div>

      <div class="border-t pt-4">
        <div class="text-xs text-slate-500 uppercase mb-2">Line Items</div>
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="text-left py-2 px-2">Product</th>
              <th class="text-right py-2 px-2">Qty</th>
              <th class="text-right py-2 px-2">Price/Box</th>
              <th class="text-right py-2 px-2">Total</th>
            </tr>
          </thead>
          <tbody>
            ${inv.items.map(item => `
              <tr class="border-t">
                <td class="py-2 px-2">${item.product}</td>
                <td class="text-right py-2 px-2">${item.boxes} boxes</td>
                <td class="text-right py-2 px-2">$${(item.unit_price * item.pieces_per_box).toFixed(2)}</td>
                <td class="text-right py-2 px-2 font-medium">$${item.value.toFixed(2)}</td>
              </tr>
            `).join('')}
          </tbody>
          <tfoot class="bg-slate-50 font-semibold">
            <tr>
              <td colspan="3" class="py-2 px-2">Total</td>
              <td class="text-right py-2 px-2">$${inv.total_value.toFixed(2)}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="border-t pt-4 grid grid-cols-3 gap-4 text-center">
        <div>
          <div class="text-xs text-slate-500 uppercase">Amount Due</div>
          <div class="text-lg font-semibold">$${(inv.amount_due || inv.total_value).toFixed(2)}</div>
        </div>
        <div>
          <div class="text-xs text-slate-500 uppercase">Amount Paid</div>
          <div class="text-lg font-semibold text-green-600">$${inv.amount_paid.toFixed(2)}</div>
        </div>
        <div>
          <div class="text-xs text-slate-500 uppercase">Balance Due</div>
          <div class="text-lg font-semibold text-red-600">$${inv.balance_due.toFixed(2)}</div>
        </div>
      </div>
    </div>
  `;

  document.getElementById('detail_content').innerHTML = html;
  document.getElementById('detailModal').classList.add('active');
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove('active');
}

function openPracticeSettingsModal(practice) {
  document.getElementById('practice_user_id').value = practice.id;
  document.getElementById('practice_name_display').textContent = practice.practice_name || (practice.first_name + ' ' + practice.last_name);
  document.getElementById('practice_payment_terms').value = practice.default_payment_terms || 'net30';
  document.getElementById('practice_credit_limit').value = practice.credit_limit || '';
  document.getElementById('practice_billing_contact_name').value = practice.billing_contact_name || '';
  document.getElementById('practice_billing_contact_email').value = practice.billing_contact_email || '';
  document.getElementById('practice_billing_contact_phone').value = practice.billing_contact_phone || '';
  document.getElementById('practice_billing_notes').value = practice.billing_notes || '';
  document.getElementById('practiceSettingsModal').classList.add('active');
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
