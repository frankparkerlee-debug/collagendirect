<?php
/**
 * Unified Wholesale Orders Page
 * Combines order creation and order management in one tabbed interface
 */

// This file is included by portal/index.php, so $cu (current user) and $pdo are available
global $pdo, $cu;

// Check if user is logged in
if (!isset($cu) || !is_array($cu) || !isset($cu['id'])) {
  header('Location: /login.php');
  exit;
}

$userId = $cu['id'];
$activeTab = $_GET['tab'] ?? 'create'; // 'create' or 'manage'

// Handle cancel action (for manage tab)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
  $orderId = $_POST['order_id'] ?? '';

  if ($orderId) {
    // Verify order belongs to this user and is cancellable
    $stmt = $pdo->prepare("
      SELECT id, status, review_status
      FROM orders
      WHERE id = ? AND user_id = ? AND billed_by = 'practice_dme'
    ");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
      // Only allow cancellation if order hasn't been shipped yet
      if (!in_array($order['status'], ['in_transit', 'delivered'])) {
        $pdo->prepare("
          UPDATE orders
          SET status = 'cancelled',
              review_status = 'cancelled',
              updated_at = NOW()
          WHERE id = ?
        ")->execute([$orderId]);

        $successMsg = 'Order cancelled successfully.';
        $activeTab = 'manage'; // Stay on manage tab after cancellation
      } else {
        $errorMsg = 'Cannot cancel order that has already shipped.';
        $activeTab = 'manage';
      }
    } else {
      $errorMsg = 'Order not found or you do not have permission to cancel it.';
      $activeTab = 'manage';
    }
  }
}

// Fetch wholesale orders for manage tab
$stmt = $pdo->prepare("
  SELECT
    o.id,
    o.created_at,
    o.product,
    o.shipments_remaining,
    o.product_price as unit_price,
    o.status,
    o.review_status,
    o.delivery_mode,
    o.paid_at,
    p.first_name as pat_first,
    p.last_name as pat_last,
    p.mrn,
    pr.pieces_per_box,
    pr.price_wholesale,
    CONCAT_WS(', ', o.shipping_address, o.shipping_city, o.shipping_state, o.shipping_zip) as shipping_address
  FROM orders o
  JOIN patients p ON o.patient_id = p.id
  LEFT JOIN products pr ON o.product_id = pr.id
  WHERE o.user_id = ?
    AND o.billed_by = 'practice_dme'
    AND o.review_status != 'draft'
  ORDER BY o.created_at DESC
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalOrders = count($orders);
$totalSpent = 0;
$totalPaid = 0;
$totalOwed = 0;
$pendingOrders = 0;

foreach ($orders as $order) {
  $boxes = (int)($order['shipments_remaining'] ?? 0);
  $pieces_per_box = (int)($order['pieces_per_box'] ?? 10);
  $unit_price = (float)($order['unit_price'] ?? $order['price_wholesale'] ?? 0);
  $orderValue = $boxes * ($unit_price * $pieces_per_box);
  $totalSpent += $orderValue;

  if (!empty($order['paid_at'])) {
    $totalPaid += $orderValue;
  } else {
    if (in_array($order['status'], ['approved', 'in_transit', 'delivered'])) {
      $totalOwed += $orderValue;
    }
  }

  if (in_array($order['status'], ['submitted', 'pending', 'awaiting_approval', 'approved'])) {
    $pendingOrders++;
  }
}
?>

<style>
.wholesale-tabs {
  display: flex;
  border-bottom: 2px solid #e2e8f0;
  margin-bottom: 2rem;
  gap: 0;
}

.wholesale-tab {
  padding: 1rem 2rem;
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  font-size: 1rem;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
  margin-bottom: -2px;
}

.wholesale-tab:hover {
  color: #1e293b;
  background: #f8fafc;
}

.wholesale-tab.active {
  color: #10b981;
  border-bottom-color: #10b981;
  background: transparent;
}

.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
}

.cancel-btn:hover {
  background: #fee2e2 !important;
  border-color: #dc2626 !important;
  color: #dc2626 !important;
}
</style>

<div style="max-width: 1400px; margin: 0 auto; padding: 2rem;">
  <!-- Header -->
  <div style="margin-bottom: 1.5rem;">
    <h1 style="font-size: 1.875rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">
      Wholesale Orders
    </h1>
    <p style="color: #64748b;">
      Create new orders or manage existing wholesale/office stock orders
    </p>
  </div>

  <?php if (isset($successMsg)): ?>
  <div style="background: #dcfce7; border: 1px solid #86efac; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: #166534;">
    <?= htmlspecialchars($successMsg) ?>
  </div>
  <?php endif; ?>

  <?php if (isset($errorMsg)): ?>
  <div style="background: #fee2e2; border: 1px solid #fca5a5; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: #991b1b;">
    <?= htmlspecialchars($errorMsg) ?>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="wholesale-tabs">
    <button class="wholesale-tab <?= $activeTab === 'create' ? 'active' : '' ?>" onclick="switchTab('create')">
      <svg style="width: 20px; height: 20px; display: inline-block; margin-right: 0.5rem; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
      </svg>
      New Order
    </button>
    <button class="wholesale-tab <?= $activeTab === 'manage' ? 'active' : '' ?>" onclick="switchTab('manage')">
      <svg style="width: 20px; height: 20px; display: inline-block; margin-right: 0.5rem; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
      </svg>
      My Orders (<?= $totalOrders ?>)
    </button>
  </div>

  <!-- Create Order Tab -->
  <div id="tab-create" class="tab-content <?= $activeTab === 'create' ? 'active' : '' ?>">
    <?php include __DIR__ . '/wholesale-order-form.html'; ?>
  </div>

  <!-- Manage Orders Tab -->
  <div id="tab-manage" class="tab-content <?= $activeTab === 'manage' ? 'active' : '' ?>">
    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
      <div style="background: white; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem;">
        <div style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.5rem;">Total Orders</div>
        <div style="font-size: 2rem; font-weight: 700; color: #1e293b;"><?= $totalOrders ?></div>
      </div>

      <div style="background: white; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem;">
        <div style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.5rem;">Pending</div>
        <div style="font-size: 2rem; font-weight: 700; color: #f59e0b;"><?= $pendingOrders ?></div>
      </div>

      <div style="background: white; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem;">
        <div style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.5rem;">Total Spent</div>
        <div style="font-size: 2rem; font-weight: 700; color: #10b981;">$<?= number_format($totalSpent, 2) ?></div>
      </div>

      <div style="background: white; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem;">
        <div style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.5rem;">Balance Owed</div>
        <div style="font-size: 2rem; font-weight: 700; color: <?= $totalOwed > 0 ? '#ef4444' : '#10b981' ?>;">
          $<?= number_format($totalOwed, 2) ?>
        </div>
      </div>
    </div>

    <!-- Orders Table -->
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 0.5rem; overflow: hidden;">
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
          <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <tr>
              <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Date</th>
              <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Patient</th>
              <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Product</th>
              <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Delivery</th>
              <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Boxes</th>
              <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Total</th>
              <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Status</th>
              <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
            <tr>
              <td colspan="8" style="padding: 3rem; text-align: center; color: #94a3b8;">
                <div style="font-size: 1.125rem; margin-bottom: 0.5rem;">No wholesale orders yet</div>
                <div style="font-size: 0.875rem;">
                  <a href="#" onclick="switchTab('create'); return false;" style="color: #3b82f6; text-decoration: underline;">Create your first wholesale order</a>
                </div>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($orders as $order):
              $boxes = (int)($order['shipments_remaining'] ?? 0);
              $pieces_per_box = (int)($order['pieces_per_box'] ?? 10);
              $unit_price = (float)($order['unit_price'] ?? $order['price_wholesale'] ?? 0);
              $orderValue = $boxes * ($unit_price * $pieces_per_box);

              $statusConfig = match($order['status']) {
                'submitted', 'pending' => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => 'Pending'],
                'awaiting_approval' => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => 'Awaiting Approval'],
                'approved' => ['color' => '#3b82f6', 'bg' => '#dbeafe', 'label' => 'Approved'],
                'in_transit' => ['color' => '#8b5cf6', 'bg' => '#ede9fe', 'label' => 'Shipped'],
                'delivered' => ['color' => '#10b981', 'bg' => '#d1fae5', 'label' => 'Delivered'],
                'cancelled' => ['color' => '#ef4444', 'bg' => '#fee2e2', 'label' => 'Cancelled'],
                'rejected' => ['color' => '#ef4444', 'bg' => '#fee2e2', 'label' => 'Rejected'],
                default => ['color' => '#64748b', 'bg' => '#f1f5f9', 'label' => ucfirst($order['status'])]
              };

              $canCancel = !in_array($order['status'], ['in_transit', 'delivered', 'cancelled', 'rejected']);
            ?>
            <tr style="border-bottom: 1px solid #e2e8f0;">
              <td style="padding: 1rem; font-size: 0.875rem; color: #1e293b;">
                <?= date('m/d/Y', strtotime($order['created_at'])) ?>
                <div style="font-size: 0.75rem; color: #94a3b8;"><?= date('g:i A', strtotime($order['created_at'])) ?></div>
              </td>
              <td style="padding: 1rem; font-size: 0.875rem;">
                <div style="font-weight: 500; color: #1e293b;">
                  <?= htmlspecialchars(trim(($order['pat_first'] ?? '') . ' ' . ($order['pat_last'] ?? ''))) ?>
                </div>
                <div style="font-size: 0.75rem; color: #94a3b8;">MRN: <?= htmlspecialchars($order['mrn'] ?? 'N/A') ?></div>
              </td>
              <td style="padding: 1rem; font-size: 0.875rem; color: #64748b; max-width: 200px;">
                <?= htmlspecialchars($order['product'] ?? '') ?>
              </td>
              <td style="padding: 1rem; font-size: 0.875rem;">
                <?php if ($order['delivery_mode'] === 'ship_to_office'): ?>
                  <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; background: #dbeafe; color: #1e40af; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 500;">
                    Office
                  </span>
                <?php else: ?>
                  <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; background: #fef3c7; color: #92400e; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 500;">
                    Patient
                  </span>
                <?php endif; ?>
              </td>
              <td style="padding: 1rem; text-align: right; font-size: 0.875rem;">
                <div style="font-weight: 600; color: #1e293b;"><?= $boxes ?></div>
                <div style="font-size: 0.75rem; color: #94a3b8;"><?= $pieces_per_box ?> pcs/box</div>
              </td>
              <td style="padding: 1rem; text-align: right; font-size: 0.875rem;">
                <div style="font-weight: 600; color: #1e293b;">$<?= number_format($orderValue, 2) ?></div>
                <div style="font-size: 0.75rem; color: #94a3b8;">$<?= number_format($unit_price, 2) ?>/pc</div>
                <?php if (!empty($order['paid_at'])): ?>
                  <div style="font-size: 0.75rem; color: #10b981; font-weight: 500;">Paid</div>
                <?php endif; ?>
              </td>
              <td style="padding: 1rem; font-size: 0.875rem;">
                <span style="display: inline-flex; align-items: center; padding: 0.375rem 0.75rem; background: <?= $statusConfig['bg'] ?>; color: <?= $statusConfig['color'] ?>; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600;">
                  <?= $statusConfig['label'] ?>
                </span>
              </td>
              <td style="padding: 1rem; text-align: right;">
                <?php if ($canCancel): ?>
                  <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this order? This action cannot be undone.');">
                    <input type="hidden" name="action" value="cancel_order">
                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['id']) ?>">
                    <button type="submit" class="cancel-btn" style="padding: 0.5rem 1rem; background: white; color: #ef4444; border: 1px solid #ef4444; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                      Cancel
                    </button>
                  </form>
                <?php else: ?>
                  <span style="font-size: 0.75rem; color: #94a3b8;">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Help Text -->
    <div style="margin-top: 2rem; padding: 1.5rem; background: #f8fafc; border-radius: 0.5rem; border-left: 4px solid #3b82f6;">
      <h3 style="font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 0.75rem;">About Wholesale Orders</h3>
      <ul style="margin: 0; padding-left: 1.5rem; color: #64748b; font-size: 0.875rem; line-height: 1.6;">
        <li style="margin-bottom: 0.5rem;">Wholesale orders are for office stock or bulk ordering at discounted pricing</li>
        <li style="margin-bottom: 0.5rem;">You can cancel orders before they are shipped (status: Pending, Approved)</li>
        <li style="margin-bottom: 0.5rem;">Once an order is shipped or delivered, it cannot be cancelled</li>
        <li style="margin-bottom: 0.5rem;">Delivery mode "Office" ships to your practice, "Patient" ships directly to the patient</li>
        <li>Orders marked "Paid" have been settled; unpaid approved/shipped orders show in "Balance Owed"</li>
      </ul>
    </div>
  </div>
</div>

<script>
function switchTab(tabName) {
  // Update URL without reloading
  const url = new URL(window.location);
  url.searchParams.set('tab', tabName);
  window.history.pushState({}, '', url);

  // Hide all tabs
  document.querySelectorAll('.tab-content').forEach(tab => {
    tab.classList.remove('active');
  });

  // Remove active class from all tab buttons
  document.querySelectorAll('.wholesale-tab').forEach(btn => {
    btn.classList.remove('active');
  });

  // Show selected tab
  const selectedTab = document.getElementById('tab-' + tabName);
  if (selectedTab) {
    selectedTab.classList.add('active');
  }

  // Activate button
  event.target.classList.add('active');
}
</script>

<!-- Include wholesale order form JavaScript -->
<script src="/portal/wholesale-order-form.js"></script>
