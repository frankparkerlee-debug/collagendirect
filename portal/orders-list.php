<?php
/**
 * Orders List Component - Supports Grouped Orders
 * Displays both single-product orders and multi-product order groups
 */

// This file is included by portal/index.php
global $pdo, $user;

if (!isset($user) || !is_array($user)) {
  echo '<div style="padding: 2rem;">Unauthorized access</div>';
  return;
}

$userId = $user['id'];
$userRole = $user['role'] ?? '';

// Fetch orders with grouping support
$sql = "
  SELECT
    COALESCE(og.id, o.id) as display_id,
    og.id as group_id,
    o.id as order_id,
    o.patient_id,
    p.first_name,
    p.last_name,
    p.mrn,
    COALESCE(og.wound_location, o.wound_location) as wound_location,
    COALESCE(og.wound_type, o.wound_type) as wound_type,
    COALESCE(og.status, o.status) as status,
    COALESCE(og.created_at, o.created_at) as created_at,
    COALESCE(og.payment_type, o.payment_type) as payment_type,
    o.product,
    o.product_price,
    o.delivery_mode,
    o.additional_instructions,
    CASE
      WHEN og.id IS NOT NULL THEN (
        SELECT COUNT(*) FROM orders WHERE order_group_id = og.id
      )
      ELSE 1
    END as product_count,
    CASE
      WHEN og.id IS NOT NULL THEN (
        SELECT SUM(product_price) FROM orders WHERE order_group_id = og.id
      )
      ELSE o.product_price
    END as total_price,
    og.visit_note_path,
    og.baseline_wound_photo_path
  FROM orders o
  LEFT JOIN order_groups og ON og.id = o.order_group_id
  JOIN patients p ON p.id = o.patient_id
  WHERE o.user_id = ?
  ORDER BY created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group orders by display_id to avoid duplicates
$grouped_orders = [];
foreach ($orders as $order) {
  $display_id = $order['display_id'];
  if (!isset($grouped_orders[$display_id])) {
    $grouped_orders[$display_id] = $order;
    $grouped_orders[$display_id]['products'] = [];
  }

  // If it's a group, fetch all products
  if ($order['group_id']) {
    // Fetch products for this group
    if (empty($grouped_orders[$display_id]['products'])) {
      $prod_stmt = $pdo->prepare("
        SELECT o.product, o.product_price, o.qty_per_change, o.id as order_id
        FROM orders o
        WHERE o.order_group_id = ?
        ORDER BY o.created_at ASC
      ");
      $prod_stmt->execute([$order['group_id']]);
      $grouped_orders[$display_id]['products'] = $prod_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
  } else {
    // Single product order
    $grouped_orders[$display_id]['products'] = [[
      'product' => $order['product'],
      'product_price' => $order['product_price'],
      'qty_per_change' => null,
      'order_id' => $order['order_id']
    ]];
  }
}
?>

<style>
.order-row {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 0.75rem;
  transition: all 0.2s;
  cursor: pointer;
}

.order-row:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  transform: translateY(-2px);
}

.order-row.group {
  border-left: 4px solid #10b981;
}

.order-row.expanded {
  background: #f8fafc;
}

.order-header {
  display: grid;
  grid-template-columns: 150px 1fr 150px 120px 120px auto;
  gap: 1rem;
  align-items: center;
}

.order-products {
  display: none;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
}

.order-row.expanded .order-products {
  display: block;
}

.product-badge {
  background: #10b981;
  color: white;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;
}

.status-submitted { background: #dbeafe; color: #1e40af; }
.status-draft { background: #f3f4f6; color: #6b7280; }
.status-shipped { background: #d1fae5; color: #065f46; }
.status-approved { background: #fef3c7; color: #92400e; }

.expand-icon {
  transition: transform 0.2s;
}

.order-row.expanded .expand-icon {
  transform: rotate(180deg);
}
</style>

<div style="max-width: 1400px; margin: 0 auto; padding: 1.5rem;">
  <!-- Header -->
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
    <div>
      <h1 style="font-size: 1.875rem; font-weight: 700; margin-bottom: 0.5rem;">My Orders</h1>
      <p style="color: #64748b; font-size: 0.875rem;">Manage all your patient orders and shipments</p>
    </div>
    <a href="?page=new-order" class="btn btn-primary">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
      </svg>
      New Order
    </a>
  </div>

  <!-- Filters -->
  <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
    <input
      type="text"
      id="search-orders"
      placeholder="Search patient or product..."
      style="flex: 1; min-width: 250px; padding: 0.5rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px;"
    >
    <select
      id="filter-order-type"
      style="padding: 0.5rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px; min-width: 150px;"
    >
      <option value="">All Order Types</option>
      <option value="referral">Referral</option>
      <option value="wholesale">Wholesale</option>
    </select>
    <select
      id="filter-status"
      style="padding: 0.5rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px; min-width: 120px;"
    >
      <option value="">All Status</option>
      <option value="draft">Draft</option>
      <option value="submitted">Submitted</option>
      <option value="approved">Approved</option>
      <option value="shipped">Shipped</option>
    </select>
    <input
      type="date"
      id="filter-date"
      style="padding: 0.5rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px; min-width: 150px;"
      placeholder="Filter by date"
    >
  </div>

  <!-- Orders List -->
  <div id="orders-container">
    <?php if (empty($grouped_orders)): ?>
      <div style="text-align: center; padding: 4rem; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
        <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 1rem; opacity: 0.3;">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
        </svg>
        <h3 style="font-size: 1.125rem; margin-bottom: 0.5rem;">No orders yet</h3>
        <p style="color: #64748b; margin-bottom: 1.5rem;">Create your first order to get started</p>
        <a href="?page=new-order" class="btn btn-primary">Create Order</a>
      </div>
    <?php else: ?>
      <?php foreach ($grouped_orders as $order): ?>
        <?php
          $is_group = $order['product_count'] > 1;
          $status_class = 'status-' . strtolower($order['status']);
        ?>
        <div
          class="order-row <?= $is_group ? 'group' : '' ?>"
          data-order-id="<?= htmlspecialchars($order['display_id']) ?>"
          data-status="<?= htmlspecialchars($order['status']) ?>"
          data-type="<?= $is_group ? 'multi' : 'single' ?>"
          data-payment-type="<?= htmlspecialchars($order['payment_type'] ?? 'insurance') ?>"
          data-created-date="<?= date('Y-m-d', strtotime($order['created_at'])) ?>"
          data-products="<?= htmlspecialchars(strtolower(implode(' ', array_column($order['products'], 'product')))) ?>"
          onclick="toggleOrderDetails(this)"
        >
          <div class="order-header">
            <!-- Date -->
            <div>
              <div style="font-size: 0.75rem; color: #64748b;">Created</div>
              <div style="font-weight: 500;">
                <?= date('M j, Y', strtotime($order['created_at'])) ?>
              </div>
            </div>

            <!-- Patient & Wound -->
            <div>
              <div style="font-weight: 600; margin-bottom: 0.25rem;">
                <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
              </div>
              <div style="font-size: 0.875rem; color: #64748b;">
                <?php if ($order['wound_location']): ?>
                  <?= htmlspecialchars($order['wound_location']) ?>
                  <?php if ($order['wound_type']): ?>
                    · <?= htmlspecialchars($order['wound_type']) ?>
                  <?php endif; ?>
                <?php else: ?>
                  MRN: <?= htmlspecialchars($order['mrn']) ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- Products -->
            <div>
              <?php if ($is_group): ?>
                <span class="product-badge">
                  <?= $order['product_count'] ?> Products
                </span>
              <?php else: ?>
                <div style="font-size: 0.875rem; font-weight: 500;">
                  <?= htmlspecialchars($order['product']) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Order Type -->
            <div>
              <div style="font-size: 0.75rem; color: #64748b;">Type</div>
              <div style="font-weight: 500; font-size: 0.875rem;">
                <?= $order['payment_type'] === 'wholesale' ? 'Wholesale' : 'Referral' ?>
              </div>
            </div>

            <!-- Status -->
            <div>
              <span class="status-badge <?= $status_class ?>">
                <?= ucfirst($order['status']) ?>
              </span>
            </div>

            <!-- Actions -->
            <div style="display: flex; gap: 0.5rem; align-items: center;">
              <?php if ($order['payment_type'] === 'wholesale'): ?>
                <?php
                  // Extract wholesale order number from notes
                  $notes = $order['additional_instructions'] ?? '';
                  preg_match('/Wholesale Order #(WS-\d+-\d+)/', $notes, $matches);
                  $wholesale_order_num = $matches[1] ?? '';
                ?>
                <?php if ($wholesale_order_num): ?>
                  <a
                    href="/portal/wholesale-order.pdf.php?order_group=<?= urlencode($wholesale_order_num) ?>&csrf=<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>"
                    target="_blank"
                    class="btn btn-primary"
                    style="padding: 0.375rem 0.75rem; font-size: 0.75rem;"
                    onclick="event.stopPropagation()"
                  >
                    Order Form
                  </a>
                <?php endif; ?>
              <?php endif; ?>
              <a
                href="?page=order-detail&id=<?= htmlspecialchars($order['display_id']) ?>"
                class="btn btn-ghost"
                style="padding: 0.375rem 0.75rem; font-size: 0.75rem;"
                onclick="event.stopPropagation()"
              >
                View
              </a>
              <svg class="expand-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
              </svg>
            </div>
          </div>

          <!-- Expanded Details -->
          <div class="order-products">
            <h4 style="font-weight: 600; margin-bottom: 0.75rem;">
              Products in this order (<?= $order['product_count'] ?>)
            </h4>
            <table style="width: 100%; font-size: 0.875rem;">
              <thead>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                  <th style="text-align: left; padding: 0.5rem; font-weight: 500; color: #64748b;">Product</th>
                  <th style="text-align: left; padding: 0.5rem; font-weight: 500; color: #64748b;">Quantity</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($order['products'] as $prod): ?>
                  <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.5rem; font-weight: 500;">
                      <?= htmlspecialchars($prod['product']) ?>
                    </td>
                    <td style="padding: 0.5rem;">
                      <?= $prod['qty_per_change'] ?? 'N/A' ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <?php if ($is_group): ?>
              <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                <?php if ($order['visit_note_path']): ?>
                  <a
                    href="<?= htmlspecialchars($order['visit_note_path']) ?>"
                    target="_blank"
                    class="btn btn-ghost"
                    style="padding: 0.375rem 0.75rem; font-size: 0.75rem;"
                  >
                    Visit Note
                  </a>
                <?php endif; ?>
                <?php if ($order['baseline_wound_photo_path']): ?>
                  <a
                    href="<?= htmlspecialchars($order['baseline_wound_photo_path']) ?>"
                    target="_blank"
                    class="btn btn-ghost"
                    style="padding: 0.375rem 0.75rem; font-size: 0.75rem;"
                  >
                    Baseline Photo
                  </a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleOrderDetails(row) {
  row.classList.toggle('expanded');
}

// Search and filter functionality
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('search-orders');
  const statusFilter = document.getElementById('filter-status');
  const orderTypeFilter = document.getElementById('filter-order-type');
  const dateFilter = document.getElementById('filter-date');

  function filterOrders() {
    const searchTerm = (searchInput?.value || '').toLowerCase();
    const selectedStatus = (statusFilter?.value || '').toLowerCase();
    const selectedOrderType = (orderTypeFilter?.value || '').toLowerCase();
    const selectedDate = dateFilter?.value || '';

    document.querySelectorAll('.order-row').forEach(row => {
      const text = row.textContent.toLowerCase();
      const products = row.getAttribute('data-products') || '';
      const status = (row.getAttribute('data-status') || '').toLowerCase();
      const paymentType = (row.getAttribute('data-payment-type') || '').toLowerCase();
      const createdDate = row.getAttribute('data-created-date') || '';

      // Match search (patient name or products)
      const matchesSearch = !searchTerm || text.includes(searchTerm) || products.includes(searchTerm);

      // Match status
      const matchesStatus = !selectedStatus || status === selectedStatus;

      // Match order type (wholesale vs referral)
      const matchesOrderType = !selectedOrderType || paymentType === selectedOrderType;

      // Match date
      const matchesDate = !selectedDate || createdDate === selectedDate;

      row.style.display = (matchesSearch && matchesStatus && matchesOrderType && matchesDate) ? 'block' : 'none';
    });
  }

  if (searchInput) searchInput.addEventListener('input', filterOrders);
  if (statusFilter) statusFilter.addEventListener('change', filterOrders);
  if (orderTypeFilter) orderTypeFilter.addEventListener('change', filterOrders);
  if (dateFilter) dateFilter.addEventListener('change', filterOrders);
});
</script>
