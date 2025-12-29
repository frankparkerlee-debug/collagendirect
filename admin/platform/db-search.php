<?php
/**
 * Database Search Tool
 *
 * Quick lookup for orders, users, patients, and other records by ID.
 * Superadmin only.
 */
declare(strict_types=1);
require __DIR__ . '/../auth.php';
require_admin();

$admin = current_admin();
$isSuperAdmin = in_array(($admin['role'] ?? ''), ['owner', 'superadmin']);

if (!$isSuperAdmin) {
  header('Location: /admin/index.php');
  exit;
}

require __DIR__ . '/../db.php';

$searchId = trim($_GET['id'] ?? $_POST['id'] ?? '');
$searchType = $_GET['type'] ?? $_POST['type'] ?? 'auto';
$results = [];
$error = '';

if ($searchId) {
    try {
        // Auto-detect search type based on ID format or search all
        if ($searchType === 'auto' || $searchType === 'order') {
            // Search orders
            $stmt = $pdo->prepare("
                SELECT o.*,
                       u.email as user_email, u.first_name as user_first_name, u.last_name as user_last_name, u.practice_name,
                       p.first_name as patient_first_name, p.last_name as patient_last_name, p.email as patient_email
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN patients p ON o.patient_id = p.id
                WHERE o.id = ?
            ");
            $stmt->execute([$searchId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($order) {
                // Get order items
                $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $itemsStmt->execute([$searchId]);
                $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                // Get shipments
                $shipmentsStmt = $pdo->prepare("SELECT * FROM shipments WHERE order_id = ?");
                $shipmentsStmt->execute([$searchId]);
                $order['shipments'] = $shipmentsStmt->fetchAll(PDO::FETCH_ASSOC);

                // Get status history
                $historyStmt = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC");
                $historyStmt->execute([$searchId]);
                $order['status_history'] = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

                $results['order'] = $order;
            }
        }

        if ($searchType === 'auto' || $searchType === 'user') {
            // Search users table
            $stmt = $pdo->prepare("
                SELECT u.*,
                       sr.id as sales_rep_id, sr.status as rep_status,
                       au.name as employee_rep_name
                FROM users u
                LEFT JOIN sales_reps sr ON sr.user_id = u.id
                LEFT JOIN admin_users au ON au.id = u.employee_rep_id
                WHERE u.id = ? OR u.email = ?
            ");
            $stmt->execute([$searchId, $searchId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $results['user'] = $user;
            }
        }

        if ($searchType === 'auto' || $searchType === 'patient') {
            // Search patients
            $stmt = $pdo->prepare("
                SELECT p.*, u.email as provider_email, u.first_name as provider_first_name, u.last_name as provider_last_name, u.practice_name
                FROM patients p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.id = ? OR p.email = ?
            ");
            $stmt->execute([$searchId, $searchId]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient) {
                // Get patient orders
                $ordersStmt = $pdo->prepare("SELECT id, status, created_at, total_amount FROM orders WHERE patient_id = ? ORDER BY created_at DESC LIMIT 10");
                $ordersStmt->execute([$patient['id']]);
                $patient['recent_orders'] = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

                $results['patient'] = $patient;
            }
        }

        if ($searchType === 'auto' || $searchType === 'admin_user') {
            // Search admin_users
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ? OR email = ?");
            $stmt->execute([$searchId, $searchId]);
            $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($adminUser) {
                // Remove password hash from results
                unset($adminUser['password_hash']);
                $results['admin_user'] = $adminUser;
            }
        }

        if ($searchType === 'auto' || $searchType === 'sales_rep') {
            // Search sales_reps
            $stmt = $pdo->prepare("
                SELECT sr.*, u.email, u.first_name, u.last_name
                FROM sales_reps sr
                JOIN users u ON u.id = sr.user_id
                WHERE sr.id = ? OR sr.user_id = ?
            ");
            $stmt->execute([$searchId, $searchId]);
            $salesRep = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($salesRep) {
                $results['sales_rep'] = $salesRep;
            }
        }

        if ($searchType === 'auto' || $searchType === 'shipment') {
            // Search shipments
            $stmt = $pdo->prepare("
                SELECT s.*, o.status as order_status
                FROM shipments s
                LEFT JOIN orders o ON o.id = s.order_id
                WHERE s.id = ? OR s.tracking_number = ?
            ");
            $stmt->execute([$searchId, $searchId]);
            $shipment = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($shipment) {
                $results['shipment'] = $shipment;
            }
        }

        if ($searchType === 'auto' || $searchType === 'wholesale_order') {
            // Search wholesale orders
            $stmt = $pdo->prepare("
                SELECT wo.*, u.email as user_email, u.first_name, u.last_name, u.practice_name
                FROM wholesale_orders wo
                LEFT JOIN users u ON wo.user_id = u.id
                WHERE wo.id = ?
            ");
            $stmt->execute([$searchId]);
            $wholesaleOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($wholesaleOrder) {
                // Get wholesale order items
                $itemsStmt = $pdo->prepare("SELECT * FROM wholesale_order_items WHERE wholesale_order_id = ?");
                $itemsStmt->execute([$searchId]);
                $wholesaleOrder['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                $results['wholesale_order'] = $wholesaleOrder;
            }
        }

    } catch (Exception $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

include __DIR__ . '/../_header.php';
?>

<div class="mb-6">
  <h1 class="text-2xl font-bold mb-2">Database Search</h1>
  <p class="text-slate-600">Look up orders, users, patients, and other records by ID or email</p>
</div>

<!-- Search Form -->
<div class="bg-white border rounded-2xl p-6 shadow-soft mb-6">
  <form method="GET" class="flex flex-wrap gap-4 items-end">
    <div class="flex-1 min-w-[300px]">
      <label class="block text-sm font-medium text-gray-700 mb-1">Search ID or Email</label>
      <input type="text" name="id" value="<?= htmlspecialchars($searchId) ?>"
             placeholder="Enter UUID, ID, email, or tracking number..."
             class="w-full" autofocus>
    </div>
    <div class="w-48">
      <label class="block text-sm font-medium text-gray-700 mb-1">Search Type</label>
      <select name="type" class="w-full">
        <option value="auto" <?= $searchType === 'auto' ? 'selected' : '' ?>>Auto-detect (All)</option>
        <option value="order" <?= $searchType === 'order' ? 'selected' : '' ?>>Order</option>
        <option value="wholesale_order" <?= $searchType === 'wholesale_order' ? 'selected' : '' ?>>Wholesale Order</option>
        <option value="user" <?= $searchType === 'user' ? 'selected' : '' ?>>User (Provider)</option>
        <option value="patient" <?= $searchType === 'patient' ? 'selected' : '' ?>>Patient</option>
        <option value="admin_user" <?= $searchType === 'admin_user' ? 'selected' : '' ?>>Admin User</option>
        <option value="sales_rep" <?= $searchType === 'sales_rep' ? 'selected' : '' ?>>Sales Rep</option>
        <option value="shipment" <?= $searchType === 'shipment' ? 'selected' : '' ?>>Shipment</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
      Search
    </button>
  </form>
</div>

<?php if ($error): ?>
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 text-red-800">
  <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if ($searchId && empty($results) && !$error): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-amber-800">
  No records found for: <strong><?= htmlspecialchars($searchId) ?></strong>
</div>
<?php endif; ?>

<?php if (!empty($results)): ?>

<!-- Results -->
<?php if (isset($results['order'])): ?>
<div class="bg-white border rounded-2xl p-6 shadow-soft mb-6">
  <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
    Order Found
  </h3>
  <?php $o = $results['order']; ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
    <div><span class="text-gray-500">ID:</span> <span class="font-mono text-xs"><?= htmlspecialchars($o['id']) ?></span></div>
    <div><span class="text-gray-500">Status:</span> <span class="font-semibold <?= $o['status'] === 'delivered' ? 'text-green-600' : ($o['status'] === 'cancelled' ? 'text-red-600' : 'text-blue-600') ?>"><?= htmlspecialchars($o['status'] ?? 'N/A') ?></span></div>
    <div><span class="text-gray-500">Created:</span> <?= htmlspecialchars($o['created_at'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Provider:</span> <?= htmlspecialchars(($o['user_first_name'] ?? '') . ' ' . ($o['user_last_name'] ?? '')) ?></div>
    <div><span class="text-gray-500">Provider Email:</span> <?= htmlspecialchars($o['user_email'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Practice:</span> <?= htmlspecialchars($o['practice_name'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Patient:</span> <?= htmlspecialchars(($o['patient_first_name'] ?? '') . ' ' . ($o['patient_last_name'] ?? '')) ?></div>
    <div><span class="text-gray-500">Patient Email:</span> <?= htmlspecialchars($o['patient_email'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Total:</span> $<?= number_format((float)($o['total_amount'] ?? 0), 2) ?></div>
    <?php if (!empty($o['shipping_address'])): ?>
    <div class="md:col-span-2"><span class="text-gray-500">Shipping:</span> <?= htmlspecialchars($o['shipping_address']) ?></div>
    <?php endif; ?>
  </div>

  <?php if (!empty($o['items'])): ?>
  <div class="mt-4 pt-4 border-t">
    <h4 class="font-medium mb-2">Order Items (<?= count($o['items']) ?>)</h4>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50">
            <th class="text-left p-2">Product</th>
            <th class="text-left p-2">SKU</th>
            <th class="text-right p-2">Qty</th>
            <th class="text-right p-2">Price</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($o['items'] as $item): ?>
          <tr class="border-t">
            <td class="p-2"><?= htmlspecialchars($item['product_name'] ?? 'N/A') ?></td>
            <td class="p-2 font-mono text-xs"><?= htmlspecialchars($item['sku'] ?? 'N/A') ?></td>
            <td class="p-2 text-right"><?= $item['quantity'] ?? 1 ?></td>
            <td class="p-2 text-right">$<?= number_format((float)($item['price'] ?? 0), 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($o['shipments'])): ?>
  <div class="mt-4 pt-4 border-t">
    <h4 class="font-medium mb-2">Shipments (<?= count($o['shipments']) ?>)</h4>
    <div class="space-y-2">
      <?php foreach ($o['shipments'] as $shipment): ?>
      <div class="bg-gray-50 rounded p-3 text-sm">
        <div class="flex flex-wrap gap-4">
          <div><span class="text-gray-500">Carrier:</span> <?= htmlspecialchars($shipment['carrier'] ?? 'N/A') ?></div>
          <div><span class="text-gray-500">Tracking:</span> <span class="font-mono"><?= htmlspecialchars($shipment['tracking_number'] ?? 'N/A') ?></span></div>
          <div><span class="text-gray-500">Status:</span> <?= htmlspecialchars($shipment['status'] ?? 'N/A') ?></div>
          <div><span class="text-gray-500">Shipped:</span> <?= htmlspecialchars($shipment['shipped_at'] ?? $shipment['created_at'] ?? 'N/A') ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($o['status_history'])): ?>
  <div class="mt-4 pt-4 border-t">
    <h4 class="font-medium mb-2">Status History</h4>
    <div class="space-y-1 text-sm">
      <?php foreach ($o['status_history'] as $history): ?>
      <div class="flex gap-4">
        <span class="text-gray-500 w-40"><?= htmlspecialchars($history['created_at'] ?? '') ?></span>
        <span class="font-medium"><?= htmlspecialchars($history['status'] ?? '') ?></span>
        <?php if (!empty($history['notes'])): ?>
        <span class="text-gray-500">- <?= htmlspecialchars($history['notes']) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="mt-4 pt-4 border-t">
    <a href="/admin/orders.php?search=<?= urlencode($o['id']) ?>" class="btn btn-primary text-sm">View in Orders</a>
  </div>
</div>
<?php endif; ?>

<?php if (isset($results['wholesale_order'])): ?>
<div class="bg-white border rounded-2xl p-6 shadow-soft mb-6">
  <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
    Wholesale Order Found
  </h3>
  <?php $wo = $results['wholesale_order']; ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
    <div><span class="text-gray-500">ID:</span> <span class="font-mono text-xs"><?= htmlspecialchars($wo['id']) ?></span></div>
    <div><span class="text-gray-500">Status:</span> <span class="font-semibold"><?= htmlspecialchars($wo['status'] ?? 'N/A') ?></span></div>
    <div><span class="text-gray-500">Created:</span> <?= htmlspecialchars($wo['created_at'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Provider:</span> <?= htmlspecialchars(($wo['first_name'] ?? '') . ' ' . ($wo['last_name'] ?? '')) ?></div>
    <div><span class="text-gray-500">Email:</span> <?= htmlspecialchars($wo['user_email'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Practice:</span> <?= htmlspecialchars($wo['practice_name'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Total:</span> $<?= number_format((float)($wo['total_amount'] ?? 0), 2) ?></div>
  </div>

  <?php if (!empty($wo['items'])): ?>
  <div class="mt-4 pt-4 border-t">
    <h4 class="font-medium mb-2">Order Items</h4>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50">
            <th class="text-left p-2">Product</th>
            <th class="text-right p-2">Qty</th>
            <th class="text-right p-2">Price</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($wo['items'] as $item): ?>
          <tr class="border-t">
            <td class="p-2"><?= htmlspecialchars($item['product_name'] ?? 'N/A') ?></td>
            <td class="p-2 text-right"><?= $item['quantity'] ?? 1 ?></td>
            <td class="p-2 text-right">$<?= number_format((float)($item['unit_price'] ?? $item['price'] ?? 0), 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div class="mt-4 pt-4 border-t">
    <a href="/admin/wholesale-orders.php?search=<?= urlencode($wo['id']) ?>" class="btn btn-primary text-sm">View in Wholesale Orders</a>
  </div>
</div>
<?php endif; ?>

<?php if (isset($results['user'])): ?>
<div class="bg-white border rounded-2xl p-6 shadow-soft mb-6">
  <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
    User (Provider) Found
  </h3>
  <?php $u = $results['user']; ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
    <div><span class="text-gray-500">ID:</span> <span class="font-mono text-xs"><?= htmlspecialchars($u['id']) ?></span></div>
    <div><span class="text-gray-500">Email:</span> <?= htmlspecialchars($u['email'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Name:</span> <?= htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?></div>
    <div><span class="text-gray-500">Practice:</span> <?= htmlspecialchars($u['practice_name'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Role:</span> <?= htmlspecialchars($u['role'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Status:</span> <span class="<?= ($u['status'] ?? '') === 'active' ? 'text-green-600' : 'text-red-600' ?>"><?= htmlspecialchars($u['status'] ?? 'N/A') ?></span></div>
    <div><span class="text-gray-500">Account Type:</span> <?= htmlspecialchars($u['account_type'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">NPI:</span> <?= htmlspecialchars($u['npi'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Phone:</span> <?= htmlspecialchars($u['phone'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Created:</span> <?= htmlspecialchars($u['created_at'] ?? 'N/A') ?></div>
    <?php if (!empty($u['assigned_rep_id'])): ?>
    <div><span class="text-gray-500">Assigned Rep ID:</span> <span class="font-mono text-xs"><?= htmlspecialchars($u['assigned_rep_id']) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($u['employee_rep_id'])): ?>
    <div><span class="text-gray-500">Employee Rep:</span> <?= htmlspecialchars($u['employee_rep_name'] ?? $u['employee_rep_id']) ?></div>
    <?php endif; ?>
    <?php if (!empty($u['sales_rep_id'])): ?>
    <div><span class="text-gray-500">Is Sales Rep:</span> Yes (ID: <?= htmlspecialchars($u['sales_rep_id']) ?>, Status: <?= htmlspecialchars($u['rep_status'] ?? 'N/A') ?>)</div>
    <?php endif; ?>
  </div>

  <?php if (!empty($u['address'])): ?>
  <div class="mt-4 pt-4 border-t">
    <h4 class="font-medium mb-2">Address</h4>
    <p class="text-sm text-gray-600">
      <?= htmlspecialchars($u['address'] ?? '') ?><br>
      <?= htmlspecialchars(($u['city'] ?? '') . ', ' . ($u['state'] ?? '') . ' ' . ($u['zip'] ?? '')) ?>
    </p>
  </div>
  <?php endif; ?>

  <div class="mt-4 pt-4 border-t">
    <a href="/admin/platform/practices.php?search=<?= urlencode($u['email']) ?>" class="btn btn-primary text-sm">View in Practices</a>
  </div>
</div>
<?php endif; ?>

<?php if (isset($results['patient'])): ?>
<div class="bg-white border rounded-2xl p-6 shadow-soft mb-6">
  <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
    Patient Found
  </h3>
  <?php $p = $results['patient']; ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
    <div><span class="text-gray-500">ID:</span> <span class="font-mono text-xs"><?= htmlspecialchars($p['id']) ?></span></div>
    <div><span class="text-gray-500">Name:</span> <?= htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?></div>
    <div><span class="text-gray-500">Email:</span> <?= htmlspecialchars($p['email'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Phone:</span> <?= htmlspecialchars($p['phone'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">DOB:</span> <?= htmlspecialchars($p['dob'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Provider:</span> <?= htmlspecialchars(($p['provider_first_name'] ?? '') . ' ' . ($p['provider_last_name'] ?? '')) ?></div>
    <div><span class="text-gray-500">Provider Email:</span> <?= htmlspecialchars($p['provider_email'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Practice:</span> <?= htmlspecialchars($p['practice_name'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Created:</span> <?= htmlspecialchars($p['created_at'] ?? 'N/A') ?></div>
  </div>

  <?php if (!empty($p['recent_orders'])): ?>
  <div class="mt-4 pt-4 border-t">
    <h4 class="font-medium mb-2">Recent Orders (<?= count($p['recent_orders']) ?>)</h4>
    <div class="space-y-1 text-sm">
      <?php foreach ($p['recent_orders'] as $order): ?>
      <div class="flex gap-4 items-center">
        <a href="?id=<?= urlencode($order['id']) ?>" class="font-mono text-xs text-brand hover:underline"><?= htmlspecialchars(substr($order['id'], 0, 8)) ?>...</a>
        <span class="<?= $order['status'] === 'delivered' ? 'text-green-600' : 'text-blue-600' ?>"><?= htmlspecialchars($order['status']) ?></span>
        <span>$<?= number_format((float)($order['total_amount'] ?? 0), 2) ?></span>
        <span class="text-gray-500"><?= htmlspecialchars($order['created_at']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="mt-4 pt-4 border-t">
    <a href="/admin/patients.php?search=<?= urlencode($p['email'] ?? $p['id']) ?>" class="btn btn-primary text-sm">View in Patients</a>
  </div>
</div>
<?php endif; ?>

<?php if (isset($results['admin_user'])): ?>
<div class="bg-white border rounded-2xl p-6 shadow-soft mb-6">
  <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
    Admin User Found
  </h3>
  <?php $au = $results['admin_user']; ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
    <div><span class="text-gray-500">ID:</span> <?= htmlspecialchars($au['id']) ?></div>
    <div><span class="text-gray-500">Name:</span> <?= htmlspecialchars($au['name'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Email:</span> <?= htmlspecialchars($au['email'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Role:</span> <?= htmlspecialchars($au['role'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Status:</span> <span class="<?= ($au['status'] ?? '') === 'active' ? 'text-green-600' : 'text-red-600' ?>"><?= htmlspecialchars($au['status'] ?? 'N/A') ?></span></div>
    <div><span class="text-gray-500">Has Rep View:</span> <?= !empty($au['has_rep_view']) ? 'Yes' : 'No' ?></div>
    <div><span class="text-gray-500">Created:</span> <?= htmlspecialchars($au['created_at'] ?? 'N/A') ?></div>
  </div>

  <div class="mt-4 pt-4 border-t">
    <a href="/admin/platform/internal-users.php" class="btn btn-primary text-sm">View Internal Users</a>
  </div>
</div>
<?php endif; ?>

<?php if (isset($results['sales_rep'])): ?>
<div class="bg-white border rounded-2xl p-6 shadow-soft mb-6">
  <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
    Sales Rep Found
  </h3>
  <?php $sr = $results['sales_rep']; ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
    <div><span class="text-gray-500">Rep ID:</span> <span class="font-mono text-xs"><?= htmlspecialchars($sr['id']) ?></span></div>
    <div><span class="text-gray-500">User ID:</span> <span class="font-mono text-xs"><?= htmlspecialchars($sr['user_id'] ?? 'N/A') ?></span></div>
    <div><span class="text-gray-500">Name:</span> <?= htmlspecialchars(($sr['first_name'] ?? '') . ' ' . ($sr['last_name'] ?? '')) ?></div>
    <div><span class="text-gray-500">Email:</span> <?= htmlspecialchars($sr['email'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Status:</span> <span class="<?= ($sr['status'] ?? '') === 'active' ? 'text-green-600' : 'text-red-600' ?>"><?= htmlspecialchars($sr['status'] ?? 'N/A') ?></span></div>
    <div><span class="text-gray-500">Commission Rate:</span> <?= isset($sr['commission_rate']) ? number_format((float)$sr['commission_rate'] * 100, 1) . '%' : 'N/A' ?></div>
    <?php if (!empty($sr['managed_by_admin_id'])): ?>
    <div><span class="text-gray-500">Managed By Admin ID:</span> <?= htmlspecialchars($sr['managed_by_admin_id']) ?></div>
    <?php endif; ?>
    <div><span class="text-gray-500">Created:</span> <?= htmlspecialchars($sr['created_at'] ?? 'N/A') ?></div>
  </div>

  <div class="mt-4 pt-4 border-t">
    <a href="/admin/platform/distributors.php" class="btn btn-primary text-sm">View Distributors</a>
  </div>
</div>
<?php endif; ?>

<?php if (isset($results['shipment'])): ?>
<div class="bg-white border rounded-2xl p-6 shadow-soft mb-6">
  <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
    <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
    Shipment Found
  </h3>
  <?php $s = $results['shipment']; ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
    <div><span class="text-gray-500">Shipment ID:</span> <span class="font-mono text-xs"><?= htmlspecialchars($s['id']) ?></span></div>
    <div><span class="text-gray-500">Order ID:</span> <a href="?id=<?= urlencode($s['order_id'] ?? '') ?>" class="font-mono text-xs text-brand hover:underline"><?= htmlspecialchars($s['order_id'] ?? 'N/A') ?></a></div>
    <div><span class="text-gray-500">Carrier:</span> <?= htmlspecialchars($s['carrier'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Tracking:</span> <span class="font-mono"><?= htmlspecialchars($s['tracking_number'] ?? 'N/A') ?></span></div>
    <div><span class="text-gray-500">Status:</span> <?= htmlspecialchars($s['status'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Order Status:</span> <?= htmlspecialchars($s['order_status'] ?? 'N/A') ?></div>
    <div><span class="text-gray-500">Shipped:</span> <?= htmlspecialchars($s['shipped_at'] ?? $s['created_at'] ?? 'N/A') ?></div>
    <?php if (!empty($s['delivered_at'])): ?>
    <div><span class="text-gray-500">Delivered:</span> <?= htmlspecialchars($s['delivered_at']) ?></div>
    <?php endif; ?>
  </div>

  <div class="mt-4 pt-4 border-t">
    <a href="/admin/shipments.php?search=<?= urlencode($s['tracking_number'] ?? $s['id']) ?>" class="btn btn-primary text-sm">View in Shipments</a>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Quick Reference -->
<div class="bg-slate-50 border rounded-2xl p-6 mt-6">
  <h3 class="font-semibold text-lg mb-4">Quick Reference</h3>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
    <div>
      <h4 class="font-medium text-gray-700 mb-2">Search by:</h4>
      <ul class="list-disc list-inside text-gray-600 space-y-1">
        <li>Order ID (UUID)</li>
        <li>User/Provider ID (UUID) or email</li>
        <li>Patient ID (UUID) or email</li>
        <li>Admin User ID (integer) or email</li>
        <li>Sales Rep ID (UUID)</li>
        <li>Shipment ID or tracking number</li>
        <li>Wholesale Order ID (UUID)</li>
      </ul>
    </div>
    <div>
      <h4 class="font-medium text-gray-700 mb-2">ID Formats:</h4>
      <ul class="list-disc list-inside text-gray-600 space-y-1">
        <li><span class="font-mono text-xs">75336d506a18ede889c580c8e73bcd92</span> - UUID (orders, users, patients)</li>
        <li><span class="font-mono text-xs">123</span> - Integer (admin_users)</li>
        <li><span class="font-mono text-xs">1Z999AA10123456784</span> - Tracking number</li>
      </ul>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../_footer.php'; ?>
