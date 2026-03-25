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
// EXCLUDE wholesale orders (billed_by='practice_dme') - those are shown in the Wholesale Orders page
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
    AND (o.billed_by IS NULL OR o.billed_by NOT IN ('practice_dme', 'healkit'))
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
    // Fetch products for this group - include stored calculated values
    if (empty($grouped_orders[$display_id]['products'])) {
      $prod_stmt = $pdo->prepare("
        SELECT o.product, o.product_price, o.qty_per_change, o.id as order_id,
               o.frequency_per_week, o.duration_days, o.refills_allowed, o.billed_by,
               o.boxes_to_ship, o.total_pieces,
               pr.pieces_per_box
        FROM orders o
        LEFT JOIN products pr ON pr.id = o.product_id
        WHERE o.order_group_id = ?
        ORDER BY o.created_at ASC
      ");
      $prod_stmt->execute([$order['group_id']]);
      $grouped_orders[$display_id]['products'] = $prod_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
  } else {
    // Single product order - fetch additional fields including stored calculated values
    $single_stmt = $pdo->prepare("
      SELECT o.product, o.product_price, o.qty_per_change, o.id as order_id,
             o.frequency_per_week, o.duration_days, o.refills_allowed, o.billed_by,
             o.boxes_to_ship, o.total_pieces,
             pr.pieces_per_box
      FROM orders o
      LEFT JOIN products pr ON pr.id = o.product_id
      WHERE o.id = ?
    ");
    $single_stmt->execute([$order['order_id']]);
    $grouped_orders[$display_id]['products'] = [$single_stmt->fetch(PDO::FETCH_ASSOC)];
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
      <h1 style="font-size: 1.875rem; font-weight: 700; margin-bottom: 0.5rem;">Patient Referral</h1>
      <p style="color: #64748b; font-size: 0.875rem;">Manage your patient referral orders and shipments</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openOrderDialog()">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
      </svg>
      New Referral
    </button>
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
        <button type="button" class="btn btn-primary" onclick="openOrderDialog()">Create Referral</button>
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
                  <th style="text-align: left; padding: 0.5rem; font-weight: 500; color: #64748b;">Qty/Change</th>
                  <th style="text-align: left; padding: 0.5rem; font-weight: 500; color: #64748b;">Boxes to Ship</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($order['products'] as $prod):
                  // Use stored boxes_to_ship if available, otherwise calculate (fallback for legacy orders)
                  $isWholesale = ($prod['billed_by'] ?? '') === 'practice_dme';
                  $qty_per_change = (int)($prod['qty_per_change'] ?? 1);

                  if (!empty($prod['boxes_to_ship'])) {
                    // Use stored value (calculated at order creation)
                    $boxes_to_ship = (int)$prod['boxes_to_ship'];
                    $qty_display = $isWholesale ? '-' : $qty_per_change;
                  } elseif ($isWholesale) {
                    // Wholesale fallback: qty_per_change is boxes
                    $boxes_to_ship = $qty_per_change;
                    $qty_display = '-';
                  } else {
                    // Referral fallback: calculate boxes from frequency, duration, qty
                    $fpw = (int)($prod['frequency_per_week'] ?? 0);
                    $days = (int)($prod['duration_days'] ?? 30);
                    $refills = max(0, (int)($prod['refills_allowed'] ?? 0));
                    $pieces_per_box = max(1, (int)($prod['pieces_per_box'] ?? 10));

                    if ($fpw === 0) $fpw = 1;
                    if ($days === 0) $days = 30;

                    $weeks = $days / 7.0;
                    $total_pieces = $weeks * $fpw * $qty_per_change * (1 + $refills);
                    $boxes_to_ship = (int)ceil($total_pieces / $pieces_per_box);
                    $qty_display = $qty_per_change;
                  }
                ?>
                  <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.5rem; font-weight: 500;">
                      <?= htmlspecialchars($prod['product'] ?? '') ?>
                    </td>
                    <td style="padding: 0.5rem;">
                      <?= $qty_display ?>
                    </td>
                    <td style="padding: 0.5rem; font-weight: 600; color: #10b981;">
                      <?= $boxes_to_ship ?> box<?= $boxes_to_ship !== 1 ? 'es' : '' ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <!-- Action Links -->
            <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
              <!-- Order Details Link (for all orders) -->
              <a
                href="?page=order-detail&id=<?= htmlspecialchars($order['display_id']) ?>"
                class="btn btn-ghost"
                style="padding: 0.375rem 0.75rem; font-size: 0.75rem;"
              >
                📋 Order Details
              </a>

              <?php if ($is_group): ?>
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
              <?php endif; ?>
            </div>
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
  const dateFilter = document.getElementById('filter-date');

  function filterOrders() {
    const searchTerm = (searchInput?.value || '').toLowerCase();
    const selectedStatus = (statusFilter?.value || '').toLowerCase();
    const selectedDate = dateFilter?.value || '';

    document.querySelectorAll('.order-row').forEach(row => {
      const text = row.textContent.toLowerCase();
      const products = row.getAttribute('data-products') || '';
      const status = (row.getAttribute('data-status') || '').toLowerCase();
      const createdDate = row.getAttribute('data-created-date') || '';

      // Match search (patient name or products)
      const matchesSearch = !searchTerm || text.includes(searchTerm) || products.includes(searchTerm);

      // Match status
      const matchesStatus = !selectedStatus || status === selectedStatus;

      // Match date
      const matchesDate = !selectedDate || createdDate === selectedDate;

      row.style.display = (matchesSearch && matchesStatus && matchesDate) ? 'block' : 'none';
    });
  }

  if (searchInput) searchInput.addEventListener('input', filterOrders);
  if (statusFilter) statusFilter.addEventListener('change', filterOrders);
  if (dateFilter) dateFilter.addEventListener('change', filterOrders);
});
</script>
