<?php
/**
 * HealKit Orders List
 * Displays HealKit orders (billed_by='healkit')
 */

// This file is included by portal/index.php
global $pdo, $user;

if (!isset($user) || !is_array($user)) {
  echo '<div style="padding: 2rem;">Unauthorized access</div>';
  return;
}

$userId = $user['id'];

// Fetch HealKit orders
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
    o.product,
    o.product_price,
    o.delivery_mode,
    o.carrier_tracking,
    o.shipped_at,
    CASE
      WHEN og.id IS NOT NULL THEN (
        SELECT COUNT(*) FROM orders WHERE order_group_id = og.id
      )
      ELSE 1
    END as product_count,
    og.visit_note_path,
    o.ivr_path,
    o.ivr_name
  FROM orders o
  LEFT JOIN order_groups og ON og.id = o.order_group_id
  JOIN patients p ON p.id = o.patient_id
  WHERE o.user_id = ?
    AND o.billed_by = 'healkit'
  ORDER BY created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Deduplicate grouped orders
$grouped_orders = [];
foreach ($orders as $order) {
  $display_id = $order['display_id'];
  if (!isset($grouped_orders[$display_id])) {
    $grouped_orders[$display_id] = $order;
    $grouped_orders[$display_id]['products'] = [];
  }

  if ($order['group_id']) {
    if (empty($grouped_orders[$display_id]['products'])) {
      $prod_stmt = $pdo->prepare("
        SELECT o.product, o.product_price, o.qty_per_change, o.id as order_id,
               o.frequency_per_week, o.duration_days, o.boxes_to_ship, o.total_pieces,
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
    $single_stmt = $pdo->prepare("
      SELECT o.product, o.product_price, o.qty_per_change, o.id as order_id,
             o.frequency_per_week, o.duration_days, o.boxes_to_ship, o.total_pieces,
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
.hk-order-row {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 0.75rem;
  transition: all 0.2s;
  cursor: pointer;
  border-left: 4px solid #6366f1;
}
.hk-order-row:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  transform: translateY(-2px);
}
.hk-order-row.expanded { background: #f8fafc; }
.hk-order-header {
  display: grid;
  grid-template-columns: 150px 1fr 150px 120px auto;
  gap: 1rem;
  align-items: center;
}
.hk-order-products {
  display: none;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
}
.hk-order-row.expanded .hk-order-products { display: block; }
.hk-status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;
}
.hk-status-submitted { background: #dbeafe; color: #1e40af; }
.hk-status-draft { background: #f3f4f6; color: #6b7280; }
.hk-status-shipped { background: #d1fae5; color: #065f46; }
.hk-status-approved { background: #fef3c7; color: #92400e; }
.hk-status-pending { background: #e0e7ff; color: #4338ca; }
.hk-expand-icon { transition: transform 0.2s; }
.hk-order-row.expanded .hk-expand-icon { transform: rotate(180deg); }
</style>

<div style="max-width: 1400px; margin: 0 auto; padding: 1.5rem;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
    <div>
      <h1 style="font-size: 1.875rem; font-weight: 700; margin-bottom: 0.5rem;">
        <svg style="width: 28px; height: 28px; display: inline-block; margin-right: 0.5rem; vertical-align: middle; color: #6366f1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
        </svg>
        HealKit Orders
      </h1>
      <p style="color: #64748b; font-size: 0.875rem;">Manage your HealKit supply orders</p>
    </div>
    <a href="?page=healkit&tab=create" class="btn btn-primary" style="background: #6366f1; border-color: #6366f1;">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
      </svg>
      New HealKit Order
    </a>
  </div>

  <!-- Filters -->
  <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
    <input type="text" id="hk-search" placeholder="Search patient or product..."
      style="flex: 1; min-width: 250px; padding: 0.5rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px;">
    <select id="hk-filter-status"
      style="padding: 0.5rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px; min-width: 120px;">
      <option value="">All Status</option>
      <option value="draft">Draft</option>
      <option value="pending">Pending</option>
      <option value="submitted">Submitted</option>
      <option value="approved">Approved</option>
      <option value="shipped">Shipped</option>
    </select>
  </div>

  <div id="hk-orders-container">
    <?php if (empty($grouped_orders)): ?>
      <div style="text-align: center; padding: 4rem; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
        <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 1rem; opacity: 0.3;">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
        </svg>
        <h3 style="font-size: 1.125rem; margin-bottom: 0.5rem;">No HealKit orders yet</h3>
        <p style="color: #64748b; margin-bottom: 1.5rem;">Create your first HealKit order to get started</p>
        <a href="?page=healkit&tab=create" class="btn btn-primary" style="background: #6366f1; border-color: #6366f1;">Create HealKit Order</a>
      </div>
    <?php else: ?>
      <?php foreach ($grouped_orders as $order): ?>
        <?php $status_class = 'hk-status-' . strtolower($order['status']); ?>
        <div class="hk-order-row"
          data-status="<?= htmlspecialchars($order['status']) ?>"
          onclick="this.classList.toggle('expanded')">
          <div class="hk-order-header">
            <div>
              <div style="font-size: 0.75rem; color: #64748b;">Created</div>
              <div style="font-weight: 500;"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
            </div>
            <div>
              <div style="font-weight: 600; margin-bottom: 0.25rem;">
                <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
              </div>
              <div style="font-size: 0.875rem; color: #64748b;">
                <?php if ($order['wound_location']): ?>
                  <?= htmlspecialchars($order['wound_location']) ?>
                <?php else: ?>
                  MRN: <?= htmlspecialchars($order['mrn']) ?>
                <?php endif; ?>
              </div>
            </div>
            <div>
              <?php if ($order['product_count'] > 1): ?>
                <span style="background: #6366f1; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                  <?= $order['product_count'] ?> Products
                </span>
              <?php else: ?>
                <div style="font-size: 0.875rem; font-weight: 500;">
                  <?= htmlspecialchars($order['product'] ?? '') ?>
                </div>
              <?php endif; ?>
            </div>
            <div>
              <span class="hk-status-badge <?= $status_class ?>">
                <?= ucfirst($order['status']) ?>
              </span>
              <?php if (!empty($order['carrier_tracking'])): ?>
                <a href="https://www.ups.com/track?loc=en_US&tracknum=<?= urlencode($order['carrier_tracking']) ?>" target="_blank" rel="noopener" onclick="event.stopPropagation()"
                   style="display:block; margin-top:0.35rem; font-size:0.7rem; color:#0075bc; font-weight:700; text-decoration:none;">Track UPS &#8599;</a>
              <?php endif; ?>
            </div>
            <div>
              <svg class="hk-expand-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
              </svg>
            </div>
          </div>
          <div class="hk-order-products">
            <h4 style="font-weight: 600; margin-bottom: 0.75rem;">Products (<?= $order['product_count'] ?>)</h4>
            <table style="width: 100%; font-size: 0.875rem;">
              <thead>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                  <th style="text-align: left; padding: 0.5rem; font-weight: 500; color: #64748b;">Product</th>
                  <th style="text-align: left; padding: 0.5rem; font-weight: 500; color: #64748b;">Qty/Change</th>
                  <th style="text-align: left; padding: 0.5rem; font-weight: 500; color: #64748b;">Quantity</th>
                </tr>
              </thead>
              <tbody>
                <?php require_once __DIR__ . '/../api/lib/order_quantity.php'; foreach ($order['products'] as $prod): $q = order_ship_quantity($prod); ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                  <td style="padding: 0.5rem; font-weight: 500;"><?= htmlspecialchars($prod['product'] ?? '') ?></td>
                  <td style="padding: 0.5rem;"><?= (int)($prod['qty_per_change'] ?? 1) ?></td>
                  <td style="padding: 0.5rem; font-weight: 600; color: #6366f1;">
                    <?= htmlspecialchars($q['label']) ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
              <a href="?page=order-detail&id=<?= htmlspecialchars($order['display_id']) ?>"
                class="btn btn-ghost" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                Order Details
              </a>
              <?php if (!empty($order['carrier_tracking'])): ?>
              <a href="https://www.ups.com/track?loc=en_US&tracknum=<?= urlencode($order['carrier_tracking']) ?>" target="_blank" rel="noopener" onclick="event.stopPropagation()"
                class="btn btn-primary" style="padding: 0.375rem 0.75rem; font-size: 0.75rem; background:#0075bc; border-color:#0075bc;">
                Track Package (UPS) &#8599;
              </a>
              <?php endif; ?>
              <?php if (!empty($order['ivr_path'])): ?>
              <a href="<?= htmlspecialchars($order['ivr_path']) ?>" target="_blank"
                class="btn btn-ghost" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                IVR Document
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const search = document.getElementById('hk-search');
  const statusFilter = document.getElementById('hk-filter-status');

  function filterOrders() {
    const term = (search?.value || '').toLowerCase();
    const status = (statusFilter?.value || '').toLowerCase();

    document.querySelectorAll('.hk-order-row').forEach(row => {
      const text = row.textContent.toLowerCase();
      const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
      const matchSearch = !term || text.includes(term);
      const matchStatus = !status || rowStatus === status;
      row.style.display = (matchSearch && matchStatus) ? 'block' : 'none';
    });
  }

  if (search) search.addEventListener('input', filterOrders);
  if (statusFilter) statusFilter.addEventListener('change', filterOrders);
});
</script>
