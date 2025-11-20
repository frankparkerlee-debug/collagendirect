<?php
/**
 * Wholesale Orders List Page
 * Displays grouped wholesale orders by order number (WS-YYYYMMDD-XXX)
 */

// This file is included by portal/index.php
global $pdo, $user;

if (!isset($user) || !is_array($user)) {
  echo '<div style="padding: 2rem;">Unauthorized access</div>';
  return;
}

$userId = $user['id'];

// Fetch wholesale orders grouped by order number
// Wholesale orders are identified by billed_by='practice_dme'
$sql = "
  SELECT
    o.order_number,
    MIN(o.created_at) as order_date,
    COUNT(DISTINCT o.id) as product_count,
    COUNT(DISTINCT o.patient_id) as patient_count,
    SUM(o.product_price) as total_cost,
    MAX(o.status) as status,
    GROUP_CONCAT(DISTINCT p.product_name ORDER BY p.product_name SEPARATOR ', ') as products,
    o.delivery_mode,
    o.shipping_address,
    o.shipping_city,
    o.shipping_state,
    o.shipping_zip
  FROM orders o
  LEFT JOIN products p ON o.product_id = p.id
  WHERE o.user_id = ?
    AND o.billed_by = 'practice_dme'
    AND o.review_status != 'draft'
  GROUP BY o.order_number
  ORDER BY MIN(o.created_at) DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$groupedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary stats
$totalOrders = count($groupedOrders);
$totalSpent = 0;
$pendingCount = 0;
$completedCount = 0;

foreach ($groupedOrders as $order) {
  $totalSpent += (float)($order['total_cost'] ?? 0);

  $status = strtolower($order['status'] ?? '');
  if (in_array($status, ['submitted', 'pending', 'awaiting_approval', 'approved'])) {
    $pendingCount++;
  } elseif (in_array($status, ['delivered'])) {
    $completedCount++;
  }
}

?>

<style>
.wholesale-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.stat-label {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 0.5rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
}

.stat-card.green .stat-value {
  color: #10b981;
}

.stat-card.blue .stat-value {
  color: #3b82f6;
}

.stat-card.amber .stat-value {
  color: #f59e0b;
}

.order-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1rem;
  transition: all 0.2s;
  cursor: pointer;
  border-left: 4px solid #10b981;
}

.order-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  transform: translateY(-2px);
}

.order-card.expanded {
  background: #f8fafc;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.order-header {
  display: grid;
  grid-template-columns: 180px 1fr 120px 120px 150px auto;
  gap: 1.5rem;
  align-items: center;
}

.order-details {
  display: none;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 2px solid #e2e8f0;
}

.order-card.expanded .order-details {
  display: block;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.status-submitted,
.status-pending,
.status-awaiting_approval {
  background: #fef3c7;
  color: #92400e;
}

.status-approved {
  background: #dbeafe;
  color: #1e40af;
}

.status-in_transit {
  background: #e0e7ff;
  color: #3730a3;
}

.status-delivered {
  background: #d1fae5;
  color: #065f46;
}

.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.expand-icon {
  transition: transform 0.2s;
  color: #64748b;
}

.order-card.expanded .expand-icon {
  transform: rotate(180deg);
  color: #10b981;
}

.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  background: white;
  border-radius: 12px;
  border: 2px dashed #e2e8f0;
}

.empty-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 1.5rem;
  opacity: 0.3;
  color: #64748b;
}

.btn-primary {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.detail-section {
  background: white;
  padding: 1rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.detail-label {
  font-size: 0.75rem;
  color: #64748b;
  text-transform: uppercase;
  font-weight: 600;
  margin-bottom: 0.5rem;
  letter-spacing: 0.025em;
}

.detail-value {
  font-size: 0.875rem;
  color: #1e293b;
  font-weight: 500;
}
</style>

<div class="wholesale-container">
  <!-- Header -->
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
    <div>
      <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; color: #1e293b;">
        Wholesale Orders
      </h1>
      <p style="color: #64748b; font-size: 0.875rem;">
        Manage your wholesale and office stock orders
      </p>
    </div>
    <a href="?page=wholesale&tab=create" class="btn-primary">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
      </svg>
      New Wholesale Order
    </a>
  </div>

  <!-- Summary Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Total Orders</div>
      <div class="stat-value"><?= $totalOrders ?></div>
    </div>
    <div class="stat-card amber">
      <div class="stat-label">Pending</div>
      <div class="stat-value"><?= $pendingCount ?></div>
    </div>
    <div class="stat-card blue">
      <div class="stat-label">Completed</div>
      <div class="stat-value"><?= $completedCount ?></div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Total Spent</div>
      <div class="stat-value">$<?= number_format($totalSpent, 2) ?></div>
    </div>
  </div>

  <!-- Orders List -->
  <?php if (empty($groupedOrders)): ?>
    <div class="empty-state">
      <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
      </svg>
      <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; color: #1e293b;">
        No wholesale orders yet
      </h3>
      <p style="color: #64748b; margin-bottom: 1.5rem; font-size: 0.875rem;">
        Create your first wholesale order to get started with bulk ordering at discounted prices
      </p>
      <a href="?page=wholesale&tab=create" class="btn-primary">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Create First Wholesale Order
      </a>
    </div>
  <?php else: ?>
    <div id="orders-list">
      <?php foreach ($groupedOrders as $order):
        $orderNumber = htmlspecialchars($order['order_number'] ?? 'N/A');
        $orderDate = date('M j, Y', strtotime($order['order_date']));
        $orderTime = date('g:i A', strtotime($order['order_date']));
        $productCount = (int)($order['product_count'] ?? 0);
        $patientCount = (int)($order['patient_count'] ?? 0);
        $totalCost = (float)($order['total_cost'] ?? 0);
        $status = strtolower($order['status'] ?? 'pending');
        $statusClass = 'status-' . str_replace(' ', '_', $status);
        $statusLabel = ucwords(str_replace('_', ' ', $status));

        $shippingAddress = trim(
          implode(', ', array_filter([
            $order['shipping_address'] ?? '',
            $order['shipping_city'] ?? '',
            $order['shipping_state'] ?? '',
            $order['shipping_zip'] ?? ''
          ]))
        );
      ?>
        <div class="order-card" onclick="toggleOrderCard(this)" data-order-number="<?= $orderNumber ?>">
          <div class="order-header">
            <!-- Order Number -->
            <div>
              <div class="stat-label" style="margin-bottom: 0.25rem;">Order #</div>
              <div style="font-weight: 700; font-size: 1rem; color: #10b981;">
                <?= $orderNumber ?>
              </div>
              <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.125rem;">
                <?= $orderTime ?>
              </div>
            </div>

            <!-- Date & Details -->
            <div>
              <div style="font-weight: 600; font-size: 0.875rem; color: #1e293b; margin-bottom: 0.25rem;">
                <?= $orderDate ?>
              </div>
              <div style="font-size: 0.75rem; color: #64748b;">
                <?= $productCount ?> product<?= $productCount !== 1 ? 's' : '' ?>
                <?php if ($patientCount > 0): ?>
                  · <?= $patientCount ?> patient<?= $patientCount !== 1 ? 's' : '' ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- Product Count Badge -->
            <div style="text-align: center;">
              <div style="background: #10b981; color: white; border-radius: 8px; padding: 0.5rem 1rem;">
                <div style="font-size: 1.5rem; font-weight: 700;"><?= $productCount ?></div>
                <div style="font-size: 0.625rem; text-transform: uppercase; opacity: 0.9;">Products</div>
              </div>
            </div>

            <!-- Total Cost -->
            <div style="text-align: right;">
              <div class="stat-label" style="margin-bottom: 0.25rem;">Total</div>
              <div style="font-weight: 700; font-size: 1.25rem; color: #1e293b;">
                $<?= number_format($totalCost, 2) ?>
              </div>
            </div>

            <!-- Status -->
            <div>
              <span class="status-badge <?= $statusClass ?>">
                <?= $statusLabel ?>
              </span>
            </div>

            <!-- Expand Icon -->
            <div style="text-align: right;">
              <svg class="expand-icon" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
              </svg>
            </div>
          </div>

          <!-- Expanded Details -->
          <div class="order-details">
            <?php
            // Fetch detailed order items for this order number
            $detailStmt = $pdo->prepare("
              SELECT
                o.id as order_id,
                o.product,
                o.product_price,
                o.qty_per_change,
                o.shipments_remaining,
                p.first_name,
                p.last_name,
                p.mrn,
                prod.pieces_per_box,
                prod.product_name
              FROM orders o
              LEFT JOIN patients p ON o.patient_id = p.id
              LEFT JOIN products prod ON o.product_id = prod.id
              WHERE o.order_number = ? AND o.user_id = ?
              ORDER BY o.created_at ASC
            ");
            $detailStmt->execute([$order['order_number'], $userId]);
            $orderItems = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <!-- Shipping Information -->
            <?php if ($shippingAddress): ?>
              <div class="detail-grid">
                <div class="detail-section">
                  <div class="detail-label">Shipping Address</div>
                  <div class="detail-value"><?= htmlspecialchars($shippingAddress) ?></div>
                </div>
                <div class="detail-section">
                  <div class="detail-label">Delivery Mode</div>
                  <div class="detail-value">
                    <?= $order['delivery_mode'] === 'ship_to_office' ? 'Ship to Office' : 'Ship to Patient' ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <!-- Products Table -->
            <h4 style="font-weight: 600; margin-bottom: 1rem; color: #1e293b;">
              Order Items (<?= count($orderItems) ?>)
            </h4>
            <div style="background: white; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
              <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                  <tr>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Product</th>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Patient</th>
                    <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Boxes</th>
                    <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Pcs/Box</th>
                    <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Price</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orderItems as $item):
                    $boxes = (int)($item['shipments_remaining'] ?? 0);
                    $piecesPerBox = (int)($item['pieces_per_box'] ?? 10);
                    $itemPrice = (float)($item['product_price'] ?? 0);
                  ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                      <td style="padding: 1rem; font-size: 0.875rem; font-weight: 500; color: #1e293b;">
                        <?= htmlspecialchars($item['product_name'] ?? $item['product'] ?? 'N/A') ?>
                      </td>
                      <td style="padding: 1rem; font-size: 0.875rem;">
                        <div style="font-weight: 500; color: #1e293b;">
                          <?= htmlspecialchars(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #94a3b8;">
                          MRN: <?= htmlspecialchars($item['mrn'] ?? 'N/A') ?>
                        </div>
                      </td>
                      <td style="padding: 1rem; text-align: center; font-size: 0.875rem; font-weight: 600; color: #1e293b;">
                        <?= $boxes ?>
                      </td>
                      <td style="padding: 1rem; text-align: center; font-size: 0.875rem; color: #64748b;">
                        <?= $piecesPerBox ?>
                      </td>
                      <td style="padding: 1rem; text-align: right; font-size: 0.875rem; font-weight: 600; color: #1e293b;">
                        $<?= number_format($itemPrice, 2) ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot style="background: #f8fafc; border-top: 2px solid #e2e8f0;">
                  <tr>
                    <td colspan="4" style="padding: 1rem; text-align: right; font-weight: 600; font-size: 0.875rem; color: #64748b;">
                      Order Total:
                    </td>
                    <td style="padding: 1rem; text-align: right; font-weight: 700; font-size: 1.125rem; color: #10b981;">
                      $<?= number_format($totalCost, 2) ?>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Action Buttons -->
            <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem;">
              <a
                href="/portal/wholesale-order.pdf.php?order_group=<?= urlencode($orderNumber) ?>&csrf=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
                target="_blank"
                style="padding: 0.625rem 1.25rem; background: white; color: #3b82f6; border: 1px solid #3b82f6; border-radius: 8px; font-size: 0.875rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s;"
                onmouseover="this.style.background='#3b82f6'; this.style.color='white';"
                onmouseout="this.style.background='white'; this.style.color='#3b82f6';"
              >
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download Invoice
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Help Section -->
  <div style="margin-top: 2rem; padding: 1.5rem; background: #f0f9ff; border-radius: 12px; border-left: 4px solid #3b82f6;">
    <h3 style="font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      About Wholesale Orders
    </h3>
    <ul style="margin: 0; padding-left: 1.5rem; color: #475569; font-size: 0.875rem; line-height: 1.7;">
      <li style="margin-bottom: 0.5rem;">Wholesale orders are grouped by order number (WS-YYYYMMDD-XXX) for easy tracking</li>
      <li style="margin-bottom: 0.5rem;">Each order can contain multiple products for one or more patients</li>
      <li style="margin-bottom: 0.5rem;">Click on any order to view detailed product breakdown and shipping information</li>
      <li style="margin-bottom: 0.5rem;">Download invoices directly from the order details for your records</li>
      <li>Wholesale pricing is automatically applied based on your practice's wholesale agreement</li>
    </ul>
  </div>
</div>

<script>
function toggleOrderCard(card) {
  // Prevent toggle if clicking on a link
  if (event.target.tagName === 'A' || event.target.closest('a')) {
    return;
  }

  card.classList.toggle('expanded');
}
</script>
