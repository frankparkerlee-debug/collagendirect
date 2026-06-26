<?php
// /public/admin/healkit-orders.php — HealKit Orders Management
declare(strict_types=1);
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
require_once __DIR__.'/db.php';
$auth = __DIR__.'/auth.php'; if (is_file($auth)) { require_once $auth; if (function_exists('require_admin')) require_admin(); }

// Sales reps have scoped orders view
if (function_exists('deny_sales_rep')) deny_sales_rep();

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';

/* Actions */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();
  $action = $_POST['action'] ?? ''; $id = $_POST['id'] ?? '';
  if ($id && $action==='approve') {
    $pdo->prepare("UPDATE orders SET status='approved', updated_at=NOW() WHERE id=? AND billed_by='healkit'")->execute([$id]);
    $_SESSION['success_msg'] = 'HealKit order approved.';
  } elseif ($id && $action==='reject') {
    $pdo->prepare("UPDATE orders SET status='rejected', updated_at=NOW() WHERE id=? AND billed_by='healkit'")->execute([$id]);
    $_SESSION['success_msg'] = 'HealKit order rejected.';
  } elseif ($id && $action==='mark_shipped') {
    $tracking = trim($_POST['tracking'] ?? '');
    $trkVal = $tracking !== '' ? $tracking : null;
    $pdo->prepare("UPDATE orders SET status='shipped', shipped_at=NOW(), updated_at=NOW(), carrier_tracking=? WHERE id=? AND billed_by='healkit'")
        ->execute([$trkVal, $id]);
    // Multi-product HealKit orders ship together: propagate to the whole group under one tracking number
    $grpStmt = $pdo->prepare("SELECT order_group_id FROM orders WHERE id=?");
    $grpStmt->execute([$id]);
    $grp = $grpStmt->fetchColumn();
    if ($grp) {
      $pdo->prepare("UPDATE orders SET status='shipped', shipped_at=NOW(), updated_at=NOW(), carrier_tracking=? WHERE order_group_id=?")->execute([$trkVal, $grp]);
      try { $pdo->prepare("UPDATE order_groups SET status='shipped' WHERE id=?")->execute([$grp]); } catch (Throwable $e) {}
    }
    // Notify the practice the order has shipped, with a clickable UPS tracking link
    try {
      require_once __DIR__.'/../api/lib/email_notifications.php';
      $info = $pdo->prepare("SELECT o.order_number, o.product, u.email AS phys_email, u.first_name AS pf, u.last_name AS pl, u.practice_name
                             FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?");
      $info->execute([$id]);
      $row = $info->fetch(PDO::FETCH_ASSOC);
      if ($row && !empty($row['phys_email']) && function_exists('send_order_shipped_email')) {
        send_order_shipped_email([
          'patient_email'   => $row['phys_email'],
          'patient_name'    => trim(($row['pf'] ?? '').' '.($row['pl'] ?? '')) ?: ($row['practice_name'] ?? 'Provider'),
          'order_id'        => $row['order_number'] ?: substr((string)$id, 0, 8),
          'tracking_number' => $tracking,
          'carrier'         => 'UPS',
          'product_name'    => $row['product'] ?? 'HealKit Supplies',
          'shipped_date'    => date('m/d/Y'),
        ]);
      }
    } catch (Throwable $e) { error_log('[healkit ship email] '.$e->getMessage()); }
    $_SESSION['success_msg'] = 'HealKit order marked as shipped'.($tracking !== '' ? " (UPS {$tracking})" : '').'.';
  }
  header('Location: /admin/healkit-orders.php'); exit;
}

/* Fetch HealKit orders */
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['q'] ?? '';

$sql = "
  SELECT o.id, o.product, o.status, o.created_at, o.updated_at, o.shipped_at,
         o.product_price, o.delivery_mode, o.order_number,
         o.ivr_path, o.ivr_name, o.rx_note_path,
         o.wound_location, o.wound_laterality,
         o.qty_per_change, o.duration_days, o.frequency AS frequency_per_week,
         o.boxes_to_ship, o.total_pieces, o.carrier_tracking,
         p.first_name as patient_first, p.last_name as patient_last, p.mrn, p.phone as patient_phone,
         u.first_name as phys_first, u.last_name as phys_last, u.practice_name, u.email as phys_email
  FROM orders o
  JOIN patients p ON p.id = o.patient_id
  JOIN users u ON u.id = o.user_id
  WHERE o.billed_by = 'healkit'
";

$params = [];
if ($statusFilter) {
  $sql .= " AND o.status = ?";
  $params[] = $statusFilter;
}
if ($searchQuery) {
  $sql .= " AND (p.first_name ILIKE ? OR p.last_name ILIKE ? OR o.product ILIKE ? OR o.id LIKE ?)";
  $params[] = "%$searchQuery%";
  $params[] = "%$searchQuery%";
  $params[] = "%$searchQuery%";
  $params[] = "%$searchQuery%";
}
$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalOrders = count($orders);
$pendingCount = count(array_filter($orders, fn($o) => in_array($o['status'], ['pending', 'submitted'])));
$approvedCount = count(array_filter($orders, fn($o) => $o['status'] === 'approved'));
$shippedCount = count(array_filter($orders, fn($o) => $o['status'] === 'shipped'));

require_once __DIR__.'/_header.php';
?>

<!-- Flash Messages -->
<?php if (!empty($_SESSION['success_msg'])): ?>
<div style="background: #d1fae5; color: #065f46; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem;">
  <?= htmlspecialchars($_SESSION['success_msg']) ?>
  <?php unset($_SESSION['success_msg']); ?>
</div>
<?php endif; ?>
<?php if (!empty($_SESSION['error_msg'])): ?>
<div style="background: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem;">
  <?= htmlspecialchars($_SESSION['error_msg']) ?>
  <?php unset($_SESSION['error_msg']); ?>
</div>
<?php endif; ?>

<div style="margin-bottom: 1.5rem;">
  <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">
    <svg style="width: 24px; height: 24px; display: inline-block; margin-right: 0.5rem; vertical-align: middle; color: #0075bc;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
    </svg>
    HealKit Orders
  </h2>
  <p style="color: #64748b; font-size: 0.875rem;">Manage HealKit supply orders</p>
</div>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
  <div class="card" style="padding: 1rem; text-align: center;">
    <div style="font-size: 2rem; font-weight: 700; color: #0075bc;"><?= $totalOrders ?></div>
    <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Total Orders</div>
  </div>
  <div class="card" style="padding: 1rem; text-align: center;">
    <div style="font-size: 2rem; font-weight: 700; color: #f59e0b;"><?= $pendingCount ?></div>
    <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Pending Review</div>
  </div>
  <div class="card" style="padding: 1rem; text-align: center;">
    <div style="font-size: 2rem; font-weight: 700; color: #10b981;"><?= $approvedCount ?></div>
    <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Approved</div>
  </div>
  <div class="card" style="padding: 1rem; text-align: center;">
    <div style="font-size: 2rem; font-weight: 700; color: #3b82f6;"><?= $shippedCount ?></div>
    <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Shipped</div>
  </div>
</div>

<!-- Filters -->
<div class="card" style="padding: 1rem; margin-bottom: 1rem;">
  <form method="get" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
    <input name="q" type="text" placeholder="Search patient, product, order ID..."
      value="<?= htmlspecialchars($searchQuery) ?>"
      style="flex: 1; min-width: 200px;">
    <select name="status">
      <option value="">All Statuses</option>
      <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
      <option value="approved" <?= $statusFilter==='approved'?'selected':'' ?>>Approved</option>
      <option value="shipped" <?= $statusFilter==='shipped'?'selected':'' ?>>Shipped</option>
      <option value="delivered" <?= $statusFilter==='delivered'?'selected':'' ?>>Delivered</option>
      <option value="rejected" <?= $statusFilter==='rejected'?'selected':'' ?>>Rejected</option>
      <option value="draft" <?= $statusFilter==='draft'?'selected':'' ?>>Draft</option>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($statusFilter || $searchQuery): ?>
    <a href="/admin/healkit-orders.php" class="btn">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Orders Table -->
<div class="card" style="padding: 0; overflow: hidden;">
  <table style="width: 100%; font-size: 0.875rem;">
    <thead>
      <tr style="background: #f9fafb;">
        <th style="padding: 0.75rem 1rem; text-align: left;">Order</th>
        <th style="padding: 0.75rem 1rem; text-align: left;">Patient</th>
        <th style="padding: 0.75rem 1rem; text-align: left;">Practice</th>
        <th style="padding: 0.75rem 1rem; text-align: left;">Product</th>
        <th style="padding: 0.75rem 1rem; text-align: left;">Wound</th>
        <th style="padding: 0.75rem 1rem; text-align: center;">Quantity</th>
        <th style="padding: 0.75rem 1rem; text-align: center;">Status</th>
        <th style="padding: 0.75rem 1rem; text-align: center;">Docs</th>
        <th style="padding: 0.75rem 1rem; text-align: center;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($orders)): ?>
      <tr>
        <td colspan="9" style="padding: 3rem; text-align: center; color: #64748b;">
          No HealKit orders found<?= ($statusFilter || $searchQuery) ? ' matching your filters' : '' ?>.
        </td>
      </tr>
      <?php endif; ?>
      <?php foreach ($orders as $o):
        $statusColors = [
          'pending' => 'background:#fef3c7;color:#92400e;',
          'submitted' => 'background:#dbeafe;color:#1e40af;',
          'approved' => 'background:#d1fae5;color:#065f46;',
          'shipped' => 'background:#e6f2fb;color:#20419b;',
          'delivered' => 'background:#d1fae5;color:#065f46;',
          'rejected' => 'background:#fee2e2;color:#991b1b;',
          'draft' => 'background:#f3f4f6;color:#6b7280;',
        ];
        $statusStyle = $statusColors[$o['status']] ?? 'background:#f3f4f6;color:#6b7280;';
      ?>
      <tr style="border-bottom: 1px solid #e5e7eb;">
        <td style="padding: 0.75rem 1rem;">
          <div style="font-weight: 600; font-size: 0.8125rem;"><?= htmlspecialchars(substr($o['id'], 0, 8)) ?>...</div>
          <div style="font-size: 0.75rem; color: #64748b;"><?= date('M j, Y', strtotime($o['created_at'])) ?></div>
          <?php if ($o['order_number']): ?>
          <div style="font-size: 0.7rem; color: #0075bc;"><?= htmlspecialchars($o['order_number']) ?></div>
          <?php endif; ?>
        </td>
        <td style="padding: 0.75rem 1rem;">
          <div style="font-weight: 500;"><?= htmlspecialchars($o['patient_first'] . ' ' . $o['patient_last']) ?></div>
          <div style="font-size: 0.75rem; color: #64748b;">MRN: <?= htmlspecialchars($o['mrn'] ?? 'N/A') ?></div>
        </td>
        <td style="padding: 0.75rem 1rem;">
          <div style="font-size: 0.8125rem;"><?= htmlspecialchars($o['practice_name'] ?? '') ?></div>
          <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($o['phys_first'] . ' ' . $o['phys_last']) ?></div>
        </td>
        <td style="padding: 0.75rem 1rem; font-size: 0.8125rem;">
          <?= htmlspecialchars($o['product'] ?? '') ?>
        </td>
        <td style="padding: 0.75rem 1rem; font-size: 0.8125rem;">
          <?= htmlspecialchars($o['wound_location'] ?? '') ?>
          <?php if ($o['wound_laterality']): ?>
            <span style="color: #64748b;">(<?= htmlspecialchars($o['wound_laterality']) ?>)</span>
          <?php endif; ?>
        </td>
        <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 600;">
          <?php require_once __DIR__ . '/../api/lib/order_quantity.php'; $q = order_ship_quantity($o); ?><?= htmlspecialchars($q['label']) ?>
        </td>
        <td style="padding: 0.75rem 1rem; text-align: center;">
          <span style="padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 500; <?= $statusStyle ?>">
            <?= ucfirst($o['status']) ?>
          </span>
        </td>
        <td style="padding: 0.75rem 1rem; text-align: center;">
          <?php if ($o['rx_note_path']): ?>
            <a href="<?= htmlspecialchars($o['rx_note_path']) ?>" target="_blank" title="Visit Notes" style="color: #3b82f6; text-decoration: none; margin-right: 0.25rem;">Notes</a>
          <?php endif; ?>
          <?php if ($o['ivr_path']): ?>
            <a href="<?= htmlspecialchars($o['ivr_path']) ?>" target="_blank" title="IVR Document" style="color: #0075bc; text-decoration: none;">IVR</a>
          <?php endif; ?>
        </td>
        <td style="padding: 0.75rem 1rem; text-align: center;">
          <?php if (in_array($o['status'], ['pending', 'submitted'])): ?>
          <form method="post" style="display: inline;">
            <?=csrf_field()?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($o['id']) ?>">
            <button name="action" value="approve" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Approve</button>
            <button name="action" value="reject" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #ef4444;" onclick="return confirm('Reject this order?')">Reject</button>
          </form>
          <?php elseif ($o['status'] === 'approved'): ?>
          <form method="post" style="display:flex; gap:0.25rem; align-items:center; justify-content:center; flex-wrap:nowrap;"
                onsubmit="return this.tracking.value.trim() ? confirm('Mark shipped with UPS tracking '+this.tracking.value.trim()+'?') : confirm('Mark shipped with no tracking number?');">
            <?=csrf_field()?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($o['id']) ?>">
            <input type="hidden" name="action" value="mark_shipped">
            <input type="text" name="tracking" placeholder="UPS tracking #" autocomplete="off"
                   style="width:120px; padding:0.25rem 0.4rem; font-size:0.7rem; border:1px solid #d1d5db; border-radius:4px;">
            <button type="submit" class="btn" style="padding:0.25rem 0.6rem; font-size:0.75rem; background:#0075bc; color:white; border-color:#0075bc;">Ship</button>
          </form>
          <?php elseif ($o['status'] === 'shipped' && !empty($o['carrier_tracking'])): ?>
            <a href="https://www.ups.com/track?loc=en_US&tracknum=<?= urlencode($o['carrier_tracking']) ?>" target="_blank" rel="noopener"
               title="Track on UPS" style="color:#0075bc; font-size:0.75rem; font-weight:600; text-decoration:none;">UPS: <?= htmlspecialchars($o['carrier_tracking']) ?> &#8599;</a>
          <?php else: ?>
            <span style="color: #64748b; font-size: 0.75rem;">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__.'/_footer.php'; ?>
