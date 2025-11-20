<?php
/**
 * Wholesale Orders List Page - Invoice View
 * Displays grouped wholesale orders by order number (WS-YYYYMMDD-XXX)
 * Each order is presented as an invoice with proper formatting
 * Applies practice-specific pricing discounts from practice_pricing table
 */

// This file is included by portal/index.php
global $pdo, $user;

if (!isset($user) || !is_array($user)) {
  echo '<div style="padding: 2rem;">Unauthorized access</div>';
  return;
}

$userId = $user['id'];

// Check if order_number column exists
$hasOrderNumber = false;
try {
  $checkCol = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = 'order_number'
  ")->fetchColumn();
  $hasOrderNumber = !empty($checkCol);
} catch (Exception $e) {
  $hasOrderNumber = false;
}

// Fetch wholesale orders grouped by order_number (if column exists) or by order id
// Wholesale orders are identified by billed_by='practice_dme'
if ($hasOrderNumber) {
  // Group by order_number when available
  $sql = "
    SELECT
      COALESCE(o.order_number, o.id) as order_number,
      MIN(o.created_at) as order_date,
      COUNT(DISTINCT o.id) as product_count,
      COUNT(DISTINCT o.patient_id) as patient_count,
      MAX(o.status) as status,
      MAX(o.delivery_mode) as delivery_mode,
      MAX(o.shipping_address) as shipping_address,
      MAX(o.shipping_city) as shipping_city,
      MAX(o.shipping_state) as shipping_state,
      MAX(o.shipping_zip) as shipping_zip,
      MAX(o.shipping_name) as shipping_name
    FROM orders o
    WHERE o.user_id = ?
      AND o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
    GROUP BY COALESCE(o.order_number, o.id)
    ORDER BY MIN(o.created_at) DESC
  ";
} else {
  // Fallback: show individual orders when order_number doesn't exist
  $sql = "
    SELECT
      o.id as order_number,
      o.created_at as order_date,
      1 as product_count,
      1 as patient_count,
      o.status,
      o.delivery_mode,
      o.shipping_address,
      o.shipping_city,
      o.shipping_state,
      o.shipping_zip,
      o.shipping_name
    FROM orders o
    WHERE o.user_id = ?
      AND o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
    ORDER BY o.created_at DESC
  ";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$groupedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get practice information for invoice header
$practiceStmt = $pdo->prepare("
  SELECT practice_name, first_name, last_name, email, phone,
         address, city, state, zip, npi
  FROM users
  WHERE id = ?
");
$practiceStmt->execute([$userId]);
$practice = $practiceStmt->fetch(PDO::FETCH_ASSOC);

// Calculate summary stats with discounted pricing
$totalOrders = count($groupedOrders);
$totalSpent = 0;
$pendingCount = 0;
$completedCount = 0;

// We'll calculate totals when we fetch each order's details
foreach ($groupedOrders as &$order) {
  // Fetch detailed items for this order to calculate discounted total
  if ($hasOrderNumber) {
    // Query by order_number when available
    $detailStmt = $pdo->prepare("
      SELECT
        o.id,
        o.product_id,
        o.product,
        o.product_price,
        o.qty_per_change as boxes,
        prod.pieces_per_box,
        prod.price_wholesale,
        pp.discount_percentage,
        pp.custom_price
      FROM orders o
      LEFT JOIN products prod ON o.product_id = prod.id
      LEFT JOIN practice_pricing pp ON pp.product_id = o.product_id AND pp.user_id = ?
      WHERE (o.order_number = ? OR (o.order_number IS NULL AND o.id = ?)) AND o.user_id = ?
    ");
    $detailStmt->execute([$userId, $order['order_number'], $order['order_number'], $userId]);
  } else {
    // Query by order id when order_number column doesn't exist
    $detailStmt = $pdo->prepare("
      SELECT
        o.id,
        o.product_id,
        o.product,
        o.product_price,
        o.qty_per_change as boxes,
        prod.pieces_per_box,
        prod.price_wholesale,
        pp.discount_percentage,
        pp.custom_price
      FROM orders o
      LEFT JOIN products prod ON o.product_id = prod.id
      LEFT JOIN practice_pricing pp ON pp.product_id = o.product_id AND pp.user_id = ?
      WHERE o.id = ? AND o.user_id = ?
    ");
    $detailStmt->execute([$userId, $order['order_number'], $userId]);
  }
  $items = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

  $orderTotal = 0;
  foreach ($items as $item) {
    $boxes = (int)($item['boxes'] ?? 0);
    $piecesPerBox = (int)($item['pieces_per_box'] ?? 1);

    // Recalculate with discount applied
    if (!empty($item['custom_price']) && $item['custom_price'] > 0) {
      // Custom price per piece
      $pricePerPiece = (float)$item['custom_price'];
      $pricePerBox = $pricePerPiece * $piecesPerBox;
    } elseif (!empty($item['discount_percentage']) && $item['discount_percentage'] > 0) {
      // Apply discount to wholesale price
      $pricePerBox = (float)($item['price_wholesale'] ?? 0);
      $discountMultiplier = 1 - ((float)$item['discount_percentage'] / 100);
      $pricePerBox = $pricePerBox * $discountMultiplier;
    } else {
      // Use stored price (product_price is per piece in orders table)
      $pricePerPiece = (float)($item['product_price'] ?? 0);
      $pricePerBox = $pricePerPiece * $piecesPerBox;
    }

    $orderTotal += $boxes * $pricePerBox;
  }

  $order['total_cost'] = $orderTotal;
  $totalSpent += $orderTotal;

  $status = strtolower($order['status'] ?? '');
  if (in_array($status, ['submitted', 'pending', 'awaiting_approval', 'approved'])) {
    $pendingCount++;
  } elseif (in_array($status, ['delivered'])) {
    $completedCount++;
  }
}
unset($order); // Break reference

?>

<style>
.wholesale-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1.5rem;
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

.invoice-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  margin-bottom: 1rem;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.invoice-header {
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  padding: 1rem 1.25rem;
  cursor: pointer;
  display: grid;
  grid-template-columns: 150px 1fr 100px 120px 100px auto;
  gap: 1rem;
  align-items: center;
  transition: background 0.15s;
}

.invoice-header:hover {
  background: #f1f5f9;
}

.invoice-body {
  display: none;
  padding: 0;
}

.invoice-card.expanded .invoice-body {
  display: block;
}

.invoice-card .when-expanded {
  display: none;
}

.invoice-card.expanded .when-collapsed {
  display: none;
}

.invoice-card.expanded .when-expanded {
  display: inline;
}

.invoice-details {
  padding: 1.5rem;
  background: white;
  border-bottom: 1px solid #e2e8f0;
}

.invoice-meta-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
  margin-bottom: 2rem;
}

.invoice-meta-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.invoice-meta-label {
  font-size: 0.75rem;
  color: #64748b;
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: 0.025em;
}

.invoice-meta-value {
  font-size: 0.875rem;
  color: #1e293b;
  font-weight: 500;
}

.invoice-items-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.invoice-items-table thead {
  background: #1e293b;
  color: white;
}

.invoice-items-table th {
  padding: 0.875rem 1rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.invoice-items-table th.text-right {
  text-align: right;
}

.invoice-items-table th.text-center {
  text-align: center;
}

.invoice-items-table tbody tr {
  border-bottom: 1px solid #e2e8f0;
}

.invoice-items-table tbody tr:hover {
  background: #f8fafc;
}

.invoice-items-table td {
  padding: 1rem;
  font-size: 0.875rem;
}

.invoice-items-table tfoot {
  background: #f8fafc;
  border-top: 2px solid #1e293b;
}

.invoice-items-table tfoot td {
  padding: 1rem;
  font-weight: 600;
}

.discount-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.5rem;
  background: #fef3c7;
  color: #92400e;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  margin-left: 0.5rem;
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

.btn-secondary {
  padding: 0.625rem 1.25rem;
  background: white;
  color: #3b82f6;
  border: 1px solid #3b82f6;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
}

.btn-secondary:hover {
  background: #3b82f6;
  color: white;
}

.invoice-actions {
  padding: 1.5rem 2rem;
  background: white;
  border-top: 1px solid #e2e8f0;
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
}
</style>

<div class="wholesale-container">
  <!-- Header -->
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
    <div>
      <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; color: #1e293b;">
        Wholesale Orders & Invoices
      </h1>
      <p style="color: #64748b; font-size: 0.875rem;">
        View and manage your wholesale orders - each order is your invoice
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
      <div class="stat-label">Total Invoices</div>
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

  <!-- Orders/Invoices List -->
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
    <div id="invoices-list">
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
        <div class="invoice-card" onclick="toggleInvoice(this, event)" data-order-number="<?= $orderNumber ?>">
          <!-- Invoice Header (Collapsed View) -->
          <div class="invoice-header">
            <!-- Invoice Number -->
            <div>
              <div style="font-size: 0.7rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Invoice #</div>
              <div style="font-weight: 600; font-size: 0.875rem; color: #1e293b;">
                <?= $orderNumber ?>
              </div>
            </div>

            <!-- Date & Details -->
            <div>
              <div style="font-weight: 600; font-size: 0.875rem; color: #1e293b; margin-bottom: 0.25rem;">
                <?= $orderDate ?>
              </div>
              <div style="font-size: 0.75rem; color: #64748b;">
                <?= $productCount ?> item<?= $productCount !== 1 ? 's' : '' ?>
                <?php if ($patientCount > 0): ?>
                  · <?= $patientCount ?> patient<?= $patientCount !== 1 ? 's' : '' ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- Item Count -->
            <div style="text-align: center;">
              <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b;"><?= $productCount ?></div>
              <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase;">Items</div>
            </div>

            <!-- Total Amount -->
            <div style="text-align: right;">
              <div style="font-size: 0.7rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase;">Total</div>
              <div style="font-weight: 700; font-size: 1.125rem; color: #10b981;">
                $<?= number_format($totalCost, 2) ?>
              </div>
            </div>

            <!-- Status -->
            <div>
              <span class="status-badge <?= $statusClass ?>">
                <?= $statusLabel ?>
              </span>
            </div>

            <!-- Expand/Collapse Indicator -->
            <div style="text-align: right;">
              <span class="expand-indicator" style="font-size: 0.75rem; color: #3b82f6; font-weight: 500;">
                <span class="when-collapsed">View Details ▼</span>
                <span class="when-expanded" style="display: none;">Hide Details ▲</span>
              </span>
            </div>
          </div>

          <!-- Invoice Body (Expanded View) -->
          <div class="invoice-body">
            <!-- Invoice Details -->
            <div class="invoice-details">
              <!-- Practice & Order Info -->
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-bottom: 2rem;">
                <!-- Bill To -->
                <div>
                  <h4 style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 0.75rem; letter-spacing: 0.05em;">Bill To</h4>
                  <div style="color: #1e293b;">
                    <div style="font-weight: 700; font-size: 1rem; margin-bottom: 0.25rem;">
                      <?= htmlspecialchars($practice['practice_name'] ?? ($practice['first_name'] . ' ' . $practice['last_name'])) ?>
                    </div>
                    <?php if (!empty($practice['address'])): ?>
                      <div style="font-size: 0.875rem; color: #64748b; line-height: 1.5;">
                        <?= htmlspecialchars($practice['address']) ?><br>
                        <?= htmlspecialchars(($practice['city'] ?? '') . ', ' . ($practice['state'] ?? '') . ' ' . ($practice['zip'] ?? '')) ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($practice['npi'])): ?>
                      <div style="font-size: 0.875rem; color: #64748b; margin-top: 0.5rem;">
                        NPI: <?= htmlspecialchars($practice['npi']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Ship To -->
                <div>
                  <h4 style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 0.75rem; letter-spacing: 0.05em;">Ship To</h4>
                  <div style="color: #1e293b;">
                    <div style="font-weight: 600; font-size: 0.9375rem; margin-bottom: 0.25rem;">
                      <?= htmlspecialchars($order['shipping_name'] ?? 'N/A') ?>
                    </div>
                    <?php if ($shippingAddress): ?>
                      <div style="font-size: 0.875rem; color: #64748b; line-height: 1.5;">
                        <?= htmlspecialchars($shippingAddress) ?>
                      </div>
                    <?php endif; ?>
                    <div style="font-size: 0.875rem; color: #64748b; margin-top: 0.5rem;">
                      <span style="display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; background: #dbeafe; color: #1e40af; border-radius: 4px; font-size: 0.75rem; font-weight: 500;">
                        <?= $order['delivery_mode'] === 'office' ? 'Office Delivery' : 'Patient Delivery' ?>
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Order Metadata -->
              <div class="invoice-meta-grid">
                <div class="invoice-meta-item">
                  <span class="invoice-meta-label">Invoice Number</span>
                  <span class="invoice-meta-value"><?= $orderNumber ?></span>
                </div>
                <div class="invoice-meta-item">
                  <span class="invoice-meta-label">Invoice Date</span>
                  <span class="invoice-meta-value"><?= $orderDate ?> at <?= $orderTime ?></span>
                </div>
                <div class="invoice-meta-item">
                  <span class="invoice-meta-label">Status</span>
                  <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                </div>
              </div>
            </div>

            <!-- Items Table -->
            <?php
            // Fetch detailed order items with pricing
            if ($hasOrderNumber) {
              $detailStmt = $pdo->prepare("
                SELECT
                  o.id as order_id,
                  o.product,
                  o.product_id,
                  o.product_price,
                  o.qty_per_change as boxes,
                  p.first_name,
                  p.last_name,
                  p.mrn,
                  prod.pieces_per_box,
                  prod.price_wholesale,
                  prod.name as product_name,
                  pp.custom_price,
                  pp.discount_percentage
                FROM orders o
                LEFT JOIN patients p ON o.patient_id = p.id
                LEFT JOIN products prod ON o.product_id = prod.id
                LEFT JOIN practice_pricing pp ON pp.product_id = o.product_id AND pp.user_id = ?
                WHERE (o.order_number = ? OR (o.order_number IS NULL AND o.id = ?)) AND o.user_id = ?
                ORDER BY o.created_at ASC
              ");
              $detailStmt->execute([$userId, $order['order_number'], $order['order_number'], $userId]);
            } else {
              $detailStmt = $pdo->prepare("
                SELECT
                  o.id as order_id,
                  o.product,
                  o.product_id,
                  o.product_price,
                  o.qty_per_change as boxes,
                  p.first_name,
                  p.last_name,
                  p.mrn,
                  prod.pieces_per_box,
                  prod.price_wholesale,
                  prod.name as product_name,
                  pp.custom_price,
                  pp.discount_percentage
                FROM orders o
                LEFT JOIN patients p ON o.patient_id = p.id
                LEFT JOIN products prod ON o.product_id = prod.id
                LEFT JOIN practice_pricing pp ON pp.product_id = o.product_id AND pp.user_id = ?
                WHERE o.id = ? AND o.user_id = ?
                ORDER BY o.created_at ASC
              ");
              $detailStmt->execute([$userId, $order['order_number'], $userId]);
            }
            $orderItems = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

            $invoiceSubtotal = 0;
            $totalDiscount = 0;
            ?>

            <table class="invoice-items-table">
              <thead>
                <tr>
                  <th style="width: 35%;">Item Description</th>
                  <th style="width: 20%;">Patient</th>
                  <th class="text-center" style="width: 10%;">Boxes</th>
                  <th class="text-center" style="width: 10%;">Pcs/Box</th>
                  <th class="text-right" style="width: 12%;">Unit Price</th>
                  <th class="text-right" style="width: 13%;">Amount</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orderItems as $item):
                  $boxes = (int)($item['boxes'] ?? 0);
                  $piecesPerBox = (int)($item['pieces_per_box'] ?? 1);
                  $wholesalePrice = (float)($item['price_wholesale'] ?? 0);

                  // Calculate actual price with discounts
                  $hasDiscount = false;
                  $discountPercent = 0;

                  if (!empty($item['custom_price']) && $item['custom_price'] > 0) {
                    // Custom price per piece
                    $pricePerPiece = (float)$item['custom_price'];
                    $pricePerBox = $pricePerPiece * $piecesPerBox;
                    $hasDiscount = true; // Custom pricing is essentially a discount
                    $originalPrice = $wholesalePrice;
                    $discountPercent = $originalPrice > 0 ? (($originalPrice - $pricePerBox) / $originalPrice * 100) : 0;
                  } elseif (!empty($item['discount_percentage']) && $item['discount_percentage'] > 0) {
                    // Apply percentage discount
                    $discountPercent = (float)$item['discount_percentage'];
                    $pricePerBox = $wholesalePrice * (1 - ($discountPercent / 100));
                    $hasDiscount = true;
                  } else {
                    // Use stored price (product_price is per piece)
                    $pricePerPiece = (float)($item['product_price'] ?? 0);
                    $pricePerBox = $pricePerPiece * $piecesPerBox;
                  }

                  $lineTotal = $boxes * $pricePerBox;
                  $invoiceSubtotal += $lineTotal;

                  if ($hasDiscount && $discountPercent > 0) {
                    $originalLineTotal = $boxes * $wholesalePrice;
                    $totalDiscount += ($originalLineTotal - $lineTotal);
                  }
                ?>
                  <tr>
                    <td style="font-weight: 500; color: #1e293b;">
                      <?= htmlspecialchars($item['product_name'] ?? $item['product'] ?? 'N/A') ?>
                      <?php if ($hasDiscount && $discountPercent > 0): ?>
                        <span class="discount-badge"><?= number_format($discountPercent, 1) ?>% off</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div style="font-weight: 500; color: #1e293b; font-size: 0.875rem;">
                        <?= htmlspecialchars(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?>
                      </div>
                      <div style="font-size: 0.75rem; color: #94a3b8;">
                        <?= htmlspecialchars($item['mrn'] ?? 'N/A') ?>
                      </div>
                    </td>
                    <td class="text-center" style="font-weight: 600; color: #1e293b;">
                      <?= $boxes ?>
                    </td>
                    <td class="text-center" style="color: #64748b;">
                      <?= $piecesPerBox ?>
                    </td>
                    <td class="text-right" style="color: #64748b;">
                      $<?= number_format($pricePerBox, 2) ?>
                    </td>
                    <td class="text-right" style="font-weight: 600; color: #1e293b;">
                      $<?= number_format($lineTotal, 2) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <?php if ($totalDiscount > 0): ?>
                  <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td colspan="5" style="text-align: right; color: #64748b;">Subtotal (before discount):</td>
                    <td style="text-align: right; color: #64748b;">$<?= number_format($invoiceSubtotal + $totalDiscount, 2) ?></td>
                  </tr>
                  <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td colspan="5" style="text-align: right; color: #10b981; font-weight: 600;">
                      Practice Discount:
                    </td>
                    <td style="text-align: right; color: #10b981; font-weight: 600;">
                      -$<?= number_format($totalDiscount, 2) ?>
                    </td>
                  </tr>
                <?php endif; ?>
                <tr>
                  <td colspan="5" style="text-align: right; font-size: 1.125rem; color: #1e293b;">Invoice Total:</td>
                  <td style="text-align: right; font-size: 1.25rem; font-weight: 700; color: #10b981;">
                    $<?= number_format($invoiceSubtotal, 2) ?>
                  </td>
                </tr>
              </tfoot>
            </table>

            <!-- Actions -->
            <div class="invoice-actions">
              <a
                href="/portal/wholesale-order.pdf.php?order_group=<?= urlencode($orderNumber) ?>&csrf=<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>"
                target="_blank"
                class="btn-secondary"
              >
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download PDF Invoice
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
      About Wholesale Invoices
    </h3>
    <ul style="margin: 0; padding-left: 1.5rem; color: #475569; font-size: 0.875rem; line-height: 1.7;">
      <li style="margin-bottom: 0.5rem;">Each wholesale order is your invoice - click to expand and view full details</li>
      <li style="margin-bottom: 0.5rem;">Invoices show your practice-specific discounted pricing automatically</li>
      <li style="margin-bottom: 0.5rem;">Download PDF invoices for your accounting records</li>
      <li style="margin-bottom: 0.5rem;">All prices shown reflect any custom pricing or percentage discounts configured for your practice</li>
      <li>Contact support if you have questions about your pricing or need to request custom pricing</li>
    </ul>
  </div>
</div>

<script>
// Define toggleInvoice function globally
window.toggleInvoice = function(card, event) {
  // Prevent toggle if clicking on a link
  if (event && (event.target.tagName === 'A' || event.target.closest('a'))) {
    return;
  }

  card.classList.toggle('expanded');
};

// Also ensure it's available without window prefix
function toggleInvoice(card, event) {
  window.toggleInvoice(card, event);
}
</script>
