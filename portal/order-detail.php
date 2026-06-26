<?php
/**
 * Order Detail Page - Supports Both Single and Grouped Orders
 */

// This file is included by portal/index.php
global $pdo, $user;

if (!isset($user) || !is_array($user)) {
  echo '<div style="padding: 2rem;">Unauthorized access</div>';
  return;
}

$userId = $user['id'];
$orderId = $_GET['id'] ?? '';

if (!$orderId) {
  echo '<div style="padding: 2rem;">Order ID required</div>';
  return;
}

// First, check if this is an order_group_id or individual order_id
$group_check = $pdo->prepare("SELECT id FROM order_groups WHERE id = ? AND user_id = ?");
$group_check->execute([$orderId, $userId]);
$is_group = $group_check->fetch();

if ($is_group) {
  // Fetch order group details
  $sql = "
    SELECT
      og.*,
      p.first_name, p.last_name, p.dob, p.mrn, p.phone, p.email,
      p.address as patient_address, p.city as patient_city,
      p.state as patient_state, p.zip as patient_zip
    FROM order_groups og
    JOIN patients p ON p.id = og.patient_id
    WHERE og.id = ? AND og.user_id = ?
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$orderId, $userId]);
  $orderGroup = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$orderGroup) {
    echo '<div style="padding: 2rem;">Order group not found</div>';
    return;
  }

  // Fetch all orders in this group - include stored calculated values
  $orders_stmt = $pdo->prepare("
    SELECT o.*, pr.name as product_name, pr.size, pr.pieces_per_box,
           o.boxes_to_ship, o.total_pieces, o.expected_revenue
    FROM orders o
    LEFT JOIN products pr ON pr.id = o.product_id
    WHERE o.order_group_id = ?
    ORDER BY o.created_at ASC
  ");
  $orders_stmt->execute([$orderId]);
  $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

  $order = $orderGroup; // Use group data
  $order['products'] = $orders;
  $order['is_group'] = true;
} else {
  // Fetch individual order - include stored calculated values
  $sql = "
    SELECT
      o.*,
      p.first_name, p.last_name, p.dob, p.mrn, p.phone, p.email,
      p.address as patient_address, p.city as patient_city,
      p.state as patient_state, p.zip as patient_zip,
      pr.name as product_name, pr.size, pr.pieces_per_box,
      o.boxes_to_ship, o.total_pieces, o.expected_revenue
    FROM orders o
    JOIN patients p ON p.id = o.patient_id
    LEFT JOIN products pr ON pr.id = o.product_id
    WHERE o.id = ? AND o.user_id = ?
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$orderId, $userId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    echo '<div style="padding: 2rem;">Order not found</div>';
    return;
  }

  $order['products'] = [$order];
  $order['is_group'] = false;
}

// Calculate totals
$total_price = 0;
foreach ($order['products'] as $prod) {
  $total_price += $prod['product_price'] ?? 0;
}
?>

<style>
.detail-section {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.detail-row {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 1rem;
  padding: 0.75rem 0;
  border-bottom: 1px solid #f1f5f9;
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-label {
  font-weight: 500;
  color: #64748b;
  font-size: 0.875rem;
}

.detail-value {
  font-size: 0.875rem;
  color: #0f172a;
}

.photo-preview {
  max-width: 300px;
  max-height: 300px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  transition: transform 0.2s;
}

.photo-preview:hover {
  transform: scale(1.05);
}

.product-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.product-table th {
  text-align: left;
  padding: 0.75rem;
  background: #f8fafc;
  font-weight: 600;
  color: #475569;
  border-bottom: 2px solid #e2e8f0;
}

.product-table td {
  padding: 0.75rem;
  border-bottom: 1px solid #f1f5f9;
}

.product-table tr:last-child td {
  border-bottom: none;
}

.status-badge {
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  display: inline-block;
}

.status-submitted { background: #dbeafe; color: #1e40af; }
.status-draft { background: #f3f4f6; color: #6b7280; }
.status-shipped { background: #d1fae5; color: #065f46; }
.status-approved { background: #fef3c7; color: #92400e; }
</style>

<div style="max-width: 1200px; margin: 0 auto; padding: 1.5rem;">
  <!-- Header -->
  <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
    <a href="?page=orders" class="btn btn-ghost">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
      </svg>
      Back to Orders
    </a>
    <div style="flex: 1;">
      <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700;">
          <?php if ($order['is_group']): ?>
            Order Group #<?= substr($order['id'], 0, 12) ?>
          <?php else: ?>
            Order #<?= substr($order['id'], 0, 12) ?>
          <?php endif; ?>
        </h1>
        <?php if ($order['is_group']): ?>
          <span class="product-badge" style="background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
            🔗 <?= count($order['products']) ?> Products
          </span>
        <?php endif; ?>
        <span class="status-badge status-<?= strtolower($order['status']) ?>">
          <?= ucfirst($order['status']) ?>
        </span>
        <?php
          $trk = $order['carrier_tracking'] ?? '';
          if (!$trk && !empty($order['products'])) { $trk = $order['products'][0]['carrier_tracking'] ?? ''; }
          if ($trk):
        ?>
        <a href="https://www.ups.com/track?loc=en_US&tracknum=<?= urlencode($trk) ?>" target="_blank" rel="noopener"
           style="display:inline-block; margin-left:0.5rem; padding:0.25rem 0.75rem; background:#0075bc; color:#fff; border-radius:4px; font-size:0.75rem; font-weight:600; text-decoration:none;">
          Track Package (UPS) &#8599;
        </a>
        <?php endif; ?>
      </div>
      <p style="color: #64748b; font-size: 0.875rem;">
        Created <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
      </p>
    </div>
  </div>

  <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Main Content -->
    <div>
      <!-- Products Section -->
      <div class="detail-section">
        <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">
          Products (<?= count($order['products']) ?>)
        </h3>
        <table class="product-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Size</th>
              <th>Qty/Change</th>
              <th>Quantity to Ship</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($order['products'] as $prod):
              // Wholesale ships whole BOXES; patient-referral and HealKit are tracked in PIECES.
              $isWholesale = ($prod['billed_by'] ?? '') === 'practice_dme';
              $qty_per_change = (int)($prod['qty_per_change'] ?? 1);
              $stored_boxes  = (int)($prod['boxes_to_ship'] ?? 0);
              $stored_pieces = (int)($prod['total_pieces'] ?? 0);

              if ($isWholesale) {
                $ship_count  = $stored_boxes > 0 ? $stored_boxes : $qty_per_change;
                $qty_display = '-';
              } elseif ($stored_pieces > 0) {
                // Referral / HealKit: use the stored piece count
                $ship_count  = $stored_pieces;
                $qty_display = $qty_per_change;
              } else {
                // Fallback for legacy orders without stored pieces
                $fpw  = (int)($prod['frequency'] ?? $prod['frequency_per_week'] ?? 1); if ($fpw === 0) $fpw = 1;
                $days = (int)($prod['duration_days'] ?? 30); if ($days === 0) $days = 30;
                $refills = max(0, (int)($prod['refills_allowed'] ?? 0));
                $ship_count  = (int)ceil(($days / 7.0) * $fpw * $qty_per_change * (1 + $refills));
                $qty_display = $qty_per_change;
              }
              $ship_unit = $isWholesale
                ? ($ship_count == 1 ? 'box' : 'boxes')
                : ($ship_count == 1 ? 'piece' : 'pieces');
            ?>
              <tr>
                <td style="font-weight: 500;">
                  <?= htmlspecialchars($prod['product'] ?? $prod['product_name'] ?? 'Unknown') ?>
                </td>
                <td><?= htmlspecialchars($prod['size'] ?? 'N/A') ?></td>
                <td><?= $qty_display ?></td>
                <td style="font-weight: 600; color: #10b981;"><?= $ship_count ?> <?= $ship_unit ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Wound Information -->
      <?php if ($order['wound_location']): ?>
        <div class="detail-section">
          <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Wound Information</h3>
          <div class="detail-row">
            <div class="detail-label">Location</div>
            <div class="detail-value">
              <?= htmlspecialchars($order['wound_location']) ?>
              <?php if ($order['wound_laterality']): ?>
                · <?= htmlspecialchars($order['wound_laterality']) ?>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($order['wound_type']): ?>
            <div class="detail-row">
              <div class="detail-label">Type</div>
              <div class="detail-value"><?= htmlspecialchars($order['wound_type']) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($order['wound_stage']): ?>
            <div class="detail-row">
              <div class="detail-label">Stage</div>
              <div class="detail-value"><?= htmlspecialchars($order['wound_stage']) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($order['wound_length_cm'] || $order['wound_width_cm'] || $order['wound_depth_cm']): ?>
            <div class="detail-row">
              <div class="detail-label">Dimensions</div>
              <div class="detail-value">
                <?= $order['wound_length_cm'] ?? '?' ?> ×
                <?= $order['wound_width_cm'] ?? '?' ?> ×
                <?= $order['wound_depth_cm'] ?? '?' ?> cm
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Visit Documentation -->
      <?php
        $visit_note = $order['is_group'] ? ($order['visit_note_path'] ?? null) : ($order['rx_note_path'] ?? null);
        $baseline_photo = $order['is_group'] ? ($order['baseline_wound_photo_path'] ?? null) : ($order['baseline_wound_photo_path'] ?? null);
      ?>
      <?php if ($visit_note || $baseline_photo): ?>
        <div class="detail-section">
          <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Visit Documentation</h3>

          <?php if ($visit_note): ?>
            <div style="margin-bottom: 1rem;">
              <div class="detail-label" style="margin-bottom: 0.5rem;">Visit Note</div>
              <a href="<?= htmlspecialchars($visit_note) ?>" target="_blank" class="btn btn-ghost">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                View Visit Note
              </a>
            </div>
          <?php endif; ?>

          <?php if ($baseline_photo): ?>
            <div>
              <div class="detail-label" style="margin-bottom: 0.5rem;">Baseline Photo</div>
              <a href="<?= htmlspecialchars($baseline_photo) ?>" target="_blank">
                <img src="<?= htmlspecialchars($baseline_photo) ?>" alt="Baseline wound photo" class="photo-preview">
              </a>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div>
      <!-- Patient Information -->
      <div class="detail-section">
        <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Patient</h3>
        <div class="detail-row">
          <div class="detail-label">Name</div>
          <div class="detail-value" style="font-weight: 600;">
            <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
          </div>
        </div>
        <?php if ($order['dob']): ?>
          <div class="detail-row">
            <div class="detail-label">DOB</div>
            <div class="detail-value"><?= date('m/d/Y', strtotime($order['dob'])) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($order['mrn']): ?>
          <div class="detail-row">
            <div class="detail-label">MRN</div>
            <div class="detail-value"><?= htmlspecialchars($order['mrn']) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($order['phone']): ?>
          <div class="detail-row">
            <div class="detail-label">Phone</div>
            <div class="detail-value"><?= htmlspecialchars($order['phone']) ?></div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Shipping Information -->
      <div class="detail-section">
        <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Shipping</h3>
        <div class="detail-row">
          <div class="detail-label">Ship to</div>
          <div class="detail-value">
            <?= htmlspecialchars($order['shipping_name'] ?? ($order['first_name'] . ' ' . $order['last_name'])) ?>
          </div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Address</div>
          <div class="detail-value">
            <?= htmlspecialchars($order['shipping_address'] ?? $order['patient_address'] ?? '') ?><br>
            <?= htmlspecialchars($order['shipping_city'] ?? $order['patient_city'] ?? '') ?>,
            <?= htmlspecialchars($order['shipping_state'] ?? $order['patient_state'] ?? '') ?>
            <?= htmlspecialchars($order['shipping_zip'] ?? $order['patient_zip'] ?? '') ?>
          </div>
        </div>
        <?php if ($order['shipping_phone']): ?>
          <div class="detail-row">
            <div class="detail-label">Phone</div>
            <div class="detail-value"><?= htmlspecialchars($order['shipping_phone']) ?></div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Insurance Information -->
      <?php if ($order['payment_type'] === 'insurance'): ?>
        <div class="detail-section">
          <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Insurance</h3>
          <?php if ($order['insurer_name']): ?>
            <div class="detail-row">
              <div class="detail-label">Provider</div>
              <div class="detail-value"><?= htmlspecialchars($order['insurer_name']) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($order['member_id']): ?>
            <div class="detail-row">
              <div class="detail-label">Member ID</div>
              <div class="detail-value"><?= htmlspecialchars($order['member_id']) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($order['group_id']): ?>
            <div class="detail-row">
              <div class="detail-label">Group ID</div>
              <div class="detail-value"><?= htmlspecialchars($order['group_id']) ?></div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- E-Signature -->
      <?php if ($order['sign_name'] || ($order['e_sign_name'] ?? null)): ?>
        <div class="detail-section">
          <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">E-Signature</h3>
          <div class="detail-row">
            <div class="detail-label">Signed by</div>
            <div class="detail-value">
              <?= htmlspecialchars($order['sign_name'] ?? $order['e_sign_name']) ?>
            </div>
          </div>
          <?php if ($order['sign_title'] || ($order['e_sign_title'] ?? null)): ?>
            <div class="detail-row">
              <div class="detail-label">Title</div>
              <div class="detail-value"><?= htmlspecialchars($order['sign_title'] ?? $order['e_sign_title']) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($order['signed_at'] || ($order['e_sign_at'] ?? null)): ?>
            <div class="detail-row">
              <div class="detail-label">Date/Time</div>
              <div class="detail-value">
                <?= date('m/d/Y g:i A', strtotime($order['signed_at'] ?? $order['e_sign_at'])) ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
