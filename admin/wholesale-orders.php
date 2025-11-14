<?php
// /public/admin/wholesale-orders.php — Dedicated wholesale orders view
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

  if ($id && $action==='mark_shipped') {
    $pdo->prepare("UPDATE orders SET status='in_transit', updated_at=NOW() WHERE id=?")->execute([$id]);
    $_SESSION['success_msg'] = 'Order marked as shipped';
  } elseif ($id && $action==='mark_paid') {
    // Add paid_at timestamp if column exists
    try {
      $pdo->prepare("UPDATE orders SET paid_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$id]);
      $_SESSION['success_msg'] = 'Order marked as paid';
    } catch (Throwable $e) {
      error_log('[wholesale-orders] mark_paid error: ' . $e->getMessage());
      $_SESSION['error_msg'] = 'Error marking as paid';
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
  $orders = [];
} else {
  try {
    $sql = "
      SELECT
        o.id,
        o.created_at,
        o.product,
        o.shipments_remaining,
        o.product_price as unit_price,
        o.status,
        o.paid_at,
        u.practice_name,
        u.first_name as phys_first,
        u.last_name as phys_last,
        p.first_name as pat_first,
        p.last_name as pat_last,
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

    $orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    error_log('[wholesale-orders] Query error: ' . $e->getMessage());
    $orders = [];
  }
}

/* ---------- Calculate totals ---------- */
$totalOrders = count($orders);
$totalRevenue = 0.0;
$pendingOrders = 0;

foreach ($orders as $order) {
  $boxes = (int)($order['shipments_remaining'] ?? 0);
  $pieces_per_box = (int)($order['pieces_per_box'] ?? 10);
  $unit_price = (float)($order['unit_price'] ?? $order['price_wholesale'] ?? 0);
  $orderValue = $boxes * ($unit_price * $pieces_per_box);
  $totalRevenue += $orderValue;

  if (in_array($order['status'], ['submitted', 'pending', 'awaiting_approval', 'approved'])) {
    $pendingOrders++;
  }
}

require_once '_header.php';
?>

<div class="container-fluid py-4">
  <div class="row mb-4">
    <div class="col-md-8">
      <h2>Wholesale Orders</h2>
      <p class="text-muted">Orders where practices bill their own DME license (billed_by = practice_dme)</p>
    </div>
    <div class="col-md-4 text-end">
      <a href="/admin/wholesale-orders.php?export=csv" class="btn btn-success">
        <i class="bi bi-download"></i> Export CSV
      </a>
      <a href="/admin/orders.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> All Orders
      </a>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title text-muted">Total Wholesale Orders</h5>
          <h2 class="mb-0"><?= number_format($totalOrders) ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title text-muted">Pending/In Progress</h5>
          <h2 class="mb-0 text-warning"><?= number_format($pendingOrders) ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title text-muted">Total Revenue</h5>
          <h2 class="mb-0 text-success">$<?= number_format($totalRevenue, 2) ?></h2>
        </div>
      </div>
    </div>
  </div>

  <?php if (isset($_SESSION['success_msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['success_msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['success_msg']); endif; ?>

  <?php if (isset($_SESSION['error_msg'])): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['error_msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['error_msg']); endif; ?>

  <!-- Orders Table -->
  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Order Date</th>
              <th>Practice</th>
              <th>Physician</th>
              <th>Patient</th>
              <th>Product</th>
              <th class="text-end">Boxes</th>
              <th class="text-end">Unit Price</th>
              <th class="text-end">Total Value</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
            <tr>
              <td colspan="10" class="text-center text-muted py-4">
                No wholesale orders found. Wholesale orders use billing route "practice_dme".
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($orders as $order):
              $boxes = (int)($order['shipments_remaining'] ?? 0);
              $pieces_per_box = (int)($order['pieces_per_box'] ?? 10);
              $unit_price = (float)($order['unit_price'] ?? $order['price_wholesale'] ?? 0);
              $orderValue = $boxes * ($unit_price * $pieces_per_box);

              $statusClass = match($order['status']) {
                'submitted', 'pending', 'awaiting_approval' => 'warning',
                'approved' => 'info',
                'in_transit' => 'primary',
                'delivered' => 'success',
                'rejected', 'cancelled' => 'danger',
                default => 'secondary'
              };
            ?>
            <tr>
              <td><?= date('m/d/Y', strtotime($order['created_at'])) ?></td>
              <td>
                <strong><?= htmlspecialchars($order['practice_name'] ?? 'N/A') ?></strong>
              </td>
              <td>
                <?= htmlspecialchars(trim(($order['phys_first'] ?? '') . ' ' . ($order['phys_last'] ?? ''))) ?>
              </td>
              <td>
                <?= htmlspecialchars(trim(($order['pat_first'] ?? '') . ' ' . ($order['pat_last'] ?? ''))) ?>
              </td>
              <td>
                <small class="text-muted"><?= htmlspecialchars($order['product'] ?? '') ?></small>
              </td>
              <td class="text-end">
                <strong><?= $boxes ?></strong>
                <small class="text-muted">boxes</small><br>
                <small class="text-muted">(<?= $pieces_per_box ?>/box)</small>
              </td>
              <td class="text-end">
                $<?= number_format($unit_price, 2) ?>
                <small class="text-muted">/pc</small>
              </td>
              <td class="text-end">
                <strong>$<?= number_format($orderValue, 2) ?></strong>
              </td>
              <td>
                <span class="badge bg-<?= $statusClass ?>">
                  <?= ucfirst($order['status']) ?>
                </span>
                <?php if ($order['paid_at']): ?>
                <br><small class="text-success">Paid <?= date('m/d', strtotime($order['paid_at'])) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <div class="btn-group btn-group-sm" role="group">
                  <a href="/admin/order-detail.php?id=<?= urlencode($order['id']) ?>"
                     class="btn btn-outline-primary" title="View Details">
                    <i class="bi bi-eye"></i>
                  </a>

                  <?php if ($order['status'] === 'approved'): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this order as shipped?');">
                    <?= csrf_token() ?>
                    <input type="hidden" name="action" value="mark_shipped">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($order['id']) ?>">
                    <button type="submit" class="btn btn-outline-success" title="Mark Shipped">
                      <i class="bi bi-truck"></i>
                    </button>
                  </form>
                  <?php endif; ?>

                  <?php if (!$order['paid_at']): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this order as paid?');">
                    <?= csrf_token() ?>
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($order['id']) ?>">
                    <button type="submit" class="btn btn-outline-info" title="Mark Paid">
                      <i class="bi bi-cash"></i>
                    </button>
                  </form>
                  <?php endif; ?>
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

  <!-- Info Box -->
  <div class="alert alert-info mt-4">
    <h5><i class="bi bi-info-circle"></i> About Wholesale Orders</h5>
    <ul class="mb-0">
      <li><strong>Simplified Requirements:</strong> No insurance cards, AOB, or detailed wound documentation required</li>
      <li><strong>Wholesale Pricing:</strong> Uses price_wholesale from products table (lower than Medicare rates)</li>
      <li><strong>Box-Based:</strong> Orders calculate boxes needed based on frequency × duration ÷ pieces_per_box</li>
      <li><strong>Practice Bills:</strong> Practice uses their own DME license to bill patient/insurance at Medicare rates</li>
      <li><strong>Profit Margin:</strong> Practice profit = (Medicare rate - Wholesale cost) × pieces</li>
    </ul>
  </div>
</div>

<style>
  .table th { white-space: nowrap; }
  .table td small { display: block; line-height: 1.2; }
  .btn-group-sm .btn { padding: 0.25rem 0.4rem; }
</style>

<?php require_once '_footer.php'; ?>
