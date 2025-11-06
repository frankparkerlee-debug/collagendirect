<?php
// /public/admin/orders.php — full functionality, defensive
declare(strict_types=1);
$bootstrap = __DIR__.'/_bootstrap.php'; if (is_file($bootstrap)) require_once $bootstrap;
require_once __DIR__.'/db.php';
$auth = __DIR__.'/auth.php'; if (is_file($auth)) { require_once $auth; if (function_exists('require_admin')) require_admin(); }

// Get current admin user and role
$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';

/* Optional shipping helpers */
$shipLib = __DIR__.'/lib/shipping.php';
if (is_file($shipLib)) require_once $shipLib;
if (!function_exists('detect_carrier')) { // tiny fallback
  function detect_carrier(string $t): ?string {
    $t = strtoupper(trim($t));
    if (preg_match('/^1Z[0-9A-Z]{16}$/',$t)) return 'ups';
    if (preg_match('/^\d{12}$|^\d{15}$|^\d{20}$|^\d{22}$/',$t)) return 'fedex';
    if (preg_match('/^\d{20,22}$|^\d{26,34}$|^[A-Z]{2}\d{9}US$/',$t)) return 'usps';
    return null;
  }
}
if (!function_exists('fetch_tracking_status')) { function fetch_tracking_status(string $t, ?string $c=null){ return ['carrier'=>$c?:detect_carrier($t),'status'=>null,'eta'=>null,'delivered_at'=>null,'raw'=>null]; } }

/* Catalog (optional) + frequency */
try { $products = $pdo->query("SELECT id,name,size,sku,price_admin FROM products WHERE active=TRUE ORDER BY name,size")->fetchAll(); }
catch(Throwable $e){ $products = []; }
$freqOptions = ['Daily','Every other Day','Weekly'];

/* Actions */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();
  $action = $_POST['action'] ?? ''; $id = $_POST['id'] ?? '';
  if ($id && $action==='approve') {
    // Check if patient is approved before allowing order approval
    $patientCheck = $pdo->prepare("SELECT p.state, p.first_name, p.last_name
                                    FROM orders o
                                    JOIN patients p ON p.id = o.patient_id
                                    WHERE o.id = ?");
    $patientCheck->execute([$id]);
    $patient = $patientCheck->fetch();

    if ($patient && $patient['state'] !== 'approved') {
      $_SESSION['error_msg'] = 'Cannot approve order: Patient "' . $patient['first_name'] . ' ' . $patient['last_name'] . '" must be approved first. Current patient status: ' . ucfirst($patient['state']);
      header('Location: /admin/orders.php'); exit;
    }

    $pdo->prepare("UPDATE orders SET status='approved', updated_at=NOW() WHERE id=?")->execute([$id]);

    // Send order approved email to physician
    try {
      require_once __DIR__ . '/../api/lib/email_notifications.php';

      // Get order details for email
      $orderData = $pdo->prepare("
        SELECT o.id, o.product, o.quantity, o.frequency, o.duration_days, o.created_at,
               p.first_name AS patient_first, p.last_name AS patient_last,
               u.first_name AS phys_first, u.last_name AS phys_last, u.email AS phys_email,
               pr.name AS product_name, pr.size AS product_size
        FROM orders o
        LEFT JOIN patients p ON p.id = o.patient_id
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN products pr ON pr.id = o.product_id
        WHERE o.id = ?
      ");
      $orderData->execute([$id]);
      $order = $orderData->fetch(PDO::FETCH_ASSOC);

      if ($order && !empty($order['phys_email'])) {
        send_order_approved_email([
          'physician_email' => $order['phys_email'],
          'physician_name' => trim(($order['phys_first'] ?? '') . ' ' . ($order['phys_last'] ?? '')),
          'patient_name' => trim(($order['patient_first'] ?? '') . ' ' . ($order['patient_last'] ?? '')),
          'order_id' => $order['id'],
          'approved_datetime' => date('m/d/Y g:i A T'),
          'product_name' => trim(($order['product_name'] ?? $order['product'] ?? '') . ' ' . ($order['product_size'] ?? '')),
          'quantity' => $order['quantity'] ?? '1',
          'frequency' => $order['frequency'] ?? '',
          'duration_days' => $order['duration_days'] ?? ''
        ]);
      }
    } catch (Throwable $emailErr) {
      error_log('[orders.php] Order approved email failed: ' . $emailErr->getMessage());
    }

  } elseif ($id && $action==='reject') {
    $pdo->prepare("UPDATE orders SET status='rejected', updated_at=NOW() WHERE id=?")->execute([$id]);
  } elseif ($id && $action==='mark_delivered') {
    // Mark order as delivered and send SMS confirmation immediately
    $pdo->prepare("UPDATE orders SET status='delivered', delivered_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$id]);

    // Send delivery confirmation SMS immediately
    try {
      require_once __DIR__ . '/../api/lib/twilio_sms.php';

      // Get order and patient details including physician name
      $orderData = $pdo->prepare("
        SELECT o.id, o.product, o.frequency, o.delivered_at,
               p.id as patient_id, p.first_name, p.last_name, p.phone, p.email,
               u.first_name AS phys_first, u.last_name AS phys_last, u.practice_name
        FROM orders o
        LEFT JOIN patients p ON p.id = o.patient_id
        LEFT JOIN users u ON u.id = o.user_id
        WHERE o.id = ?
      ");
      $orderData->execute([$id]);
      $order = $orderData->fetch(PDO::FETCH_ASSOC);

      if (!$order) {
        error_log("[orders.php] Cannot send delivery SMS - order {$id} not found");
      } elseif (empty($order['phone'])) {
        error_log("[orders.php] Cannot send delivery SMS for order {$id} - patient has no phone number");
      } else {
        $patientName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
        $physicianName = trim(($order['phys_last'] ?? '') . ($order['phys_first'] ? ', ' . $order['phys_first'] : ''));
        if (empty($physicianName) && !empty($order['practice_name'])) {
          $physicianName = $order['practice_name'];
        }

        // Check if confirmation already exists
        $existingConfirmation = $pdo->prepare("SELECT id FROM delivery_confirmations WHERE order_id = ?");
        $existingConfirmation->execute([$id]);

        if (!$existingConfirmation->fetch()) {
          // Generate unique confirmation token
          $token = bin2hex(random_bytes(32));

          // Insert confirmation record
          $insertStmt = $pdo->prepare("
            INSERT INTO delivery_confirmations (
              order_id,
              patient_phone,
              patient_email,
              confirmation_token,
              created_at,
              updated_at
            ) VALUES (?, ?, ?, ?, NOW(), NOW())
            RETURNING id
          ");

          $insertStmt->execute([
            $id,
            $order['phone'],
            $order['email'] ?? null,
            $token
          ]);

          $confirmationId = $insertStmt->fetchColumn();

          // Send SMS
          $smsResult = send_delivery_confirmation_sms(
            $order['phone'],
            $patientName,
            $id,
            $token,
            $physicianName
          );

          if ($smsResult['success']) {
            // Update with SMS details
            $updateStmt = $pdo->prepare("
              UPDATE delivery_confirmations
              SET sms_sent_at = NOW(),
                  sms_sid = ?,
                  sms_status = ?,
                  updated_at = NOW()
              WHERE id = ?
            ");

            $updateStmt->execute([
              $smsResult['sid'],
              $smsResult['status'],
              $confirmationId
            ]);

            error_log("[orders.php] Delivery confirmation SMS sent for order {$id} (SID: {$smsResult['sid']})");
          } else {
            // Update with error
            $updateStmt = $pdo->prepare("
              UPDATE delivery_confirmations
              SET notes = ?,
                  updated_at = NOW()
              WHERE id = ?
            ");

            $updateStmt->execute([
              "SMS send failed: " . ($smsResult['error'] ?? 'Unknown error'),
              $confirmationId
            ]);

            error_log("[orders.php] Delivery confirmation SMS failed for order {$id}: " . ($smsResult['error'] ?? 'Unknown error'));
          }
        }

        // Create photo prompt schedule for wound photo updates
        require_once __DIR__ . '/../api/lib/photo_prompt_helpers.php';
        $deliveryDate = date('Y-m-d', strtotime($order['delivered_at'] ?? 'now'));
        create_photo_prompt_schedule(
          $pdo,
          $order['id'],
          $order['patient_id'],
          $order['frequency'],
          $order['product'],
          $deliveryDate
        );
      }
    } catch (Throwable $smsErr) {
      error_log('[orders.php] Delivery confirmation SMS error: ' . $smsErr->getMessage());
    }
  } elseif ($id && $action==='ship') {
    $tracking = trim((string)($_POST['tracking']??'')); $carrier = $_POST['carrier'] ?: detect_carrier($tracking);
    $pdo->prepare("UPDATE orders SET
      shipping_name=:n, shipping_phone=:ph, shipping_address=:a, shipping_city=:c, shipping_state=:s, shipping_zip=:z,
      carrier=:carrier, tracking_number=:tracking, status='in_transit',
      shipped_at=COALESCE(shipped_at, NOW()), updated_at=NOW()
    WHERE id=:id")->execute([
      'n'=>$_POST['shipping_name']??null,'ph'=>$_POST['shipping_phone']??null,'a'=>$_POST['shipping_address']??null,
      'c'=>$_POST['shipping_city']??null,'s'=>$_POST['shipping_state']??null,'z'=>$_POST['shipping_zip']??null,
      'carrier'=>$carrier,'tracking'=>$tracking,'id'=>$id
    ]);
    if ($tracking) {
      $trk = fetch_tracking_status($tracking,$carrier);
      if (!empty($trk['status'])) {
        $pdo->prepare("UPDATE orders SET carrier_status=?, carrier_eta=?, status=?, delivered_at=COALESCE(?, delivered_at), updated_at=NOW() WHERE id=?")
            ->execute([$trk['status'],$trk['eta'],$trk['status'],$trk['delivered_at'],$id]);
      }
    }

    // Send order shipped email to patient
    try {
      require_once __DIR__ . '/../api/lib/email_notifications.php';

      // Get order and patient details for email
      $shipData = $pdo->prepare("
        SELECT o.id, o.product, o.quantity, o.tracking_number, o.carrier,
               p.first_name AS patient_first, p.last_name AS patient_last, p.email AS patient_email,
               pr.name AS product_name, pr.size AS product_size
        FROM orders o
        LEFT JOIN patients p ON p.id = o.patient_id
        LEFT JOIN products pr ON pr.id = o.product_id
        WHERE o.id = ?
      ");
      $shipData->execute([$id]);
      $ship = $shipData->fetch(PDO::FETCH_ASSOC);

      if ($ship && !empty($ship['patient_email']) && !empty($ship['tracking_number'])) {
        send_order_shipped_email([
          'patient_email' => $ship['patient_email'],
          'patient_name' => trim(($ship['patient_first'] ?? '') . ' ' . ($ship['patient_last'] ?? '')),
          'order_id' => $ship['id'],
          'shipped_date' => date('m/d/Y'),
          'carrier' => $ship['carrier'] ?? $carrier,
          'tracking_number' => $ship['tracking_number'],
          'product_name' => trim(($ship['product_name'] ?? $ship['product'] ?? '') . ' ' . ($ship['product_size'] ?? '')),
          'quantity' => $ship['quantity'] ?? '1'
        ]);
      }
    } catch (Throwable $emailErr) {
      error_log('[orders.php] Order shipped email failed: ' . $emailErr->getMessage());
    }

  } elseif ($id && $action==='edit_order') {
    $qtySql = ""; $params = [
      'pid'=>($_POST['product_id'] ?: null),
      'pname'=>($_POST['product_label'] ?: ($_POST['product_text'] ?? null)),
      'freq'=>($_POST['frequency'] ?? null),
      'price'=>(float)($_POST['product_price'] ?? 0),
      'dmode'=>($_POST['delivery_mode'] ?? null),
      'id'=>$id
    ];
    // only update quantity if column exists
    try {
      $hasQty = (int)$pdo->query("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='orders' AND COLUMN_NAME='quantity'")->fetch()['c'] > 0;
      if ($hasQty && $_POST['quantity'] !== '') { $qtySql = ", quantity=:qty"; $params['qty'] = (int)$_POST['quantity']; }
    } catch(Throwable $e){}
    $pdo->prepare("UPDATE orders SET product_id=:pid, product=:pname, frequency=:freq, product_price=:price, delivery_mode=:dmode $qtySql, updated_at=NOW() WHERE id=:id")->execute($params);

    // shipping edits
    $pdo->prepare("UPDATE orders SET shipping_name=:n, shipping_phone=:ph, shipping_address=:a, shipping_city=:c, shipping_state=:s, shipping_zip=:z WHERE id=:id")->execute([
      'n'=>$_POST['shipping_name']??null,'ph'=>$_POST['shipping_phone']??null,'a'=>$_POST['shipping_address']??null,
      'c'=>$_POST['shipping_city']??null,'s'=>$_POST['shipping_state']??null,'z'=>$_POST['shipping_zip']??null,'id'=>$id
    ]);

    // patient demo
    if (!empty($_POST['patient_id'])) {
      $pdo->prepare("UPDATE patients SET dob=:dob, insurance_provider=:prov, insurance_member_id=:mid, insurance_group_id=:gid, insurance_payer_phone=:pp WHERE id=:pid")
          ->execute([
            'dob'=>($_POST['pat_dob'] ?: null),'prov'=>($_POST['ins_provider'] ?: null),'mid'=>($_POST['ins_member'] ?: null),
            'gid'=>($_POST['ins_group'] ?: null),'pp'=>($_POST['ins_payer_phone'] ?: null),'pid'=>$_POST['patient_id']
          ]);
    }
  }
  header('Location: /admin/orders.php'); exit;
}

/* Filter parameters */
$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$carrierFilter = trim((string)($_GET['carrier'] ?? ''));
$productFilter = trim((string)($_GET['product_id'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

/* List all orders */
// Check if carrier and tracking_number columns exist (they're added by migration)
$hasCarrierCol = false;
$hasTrackingCol = false;
try {
  $colCheck = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='orders' AND column_name IN ('carrier','tracking_number')")->fetchAll(PDO::FETCH_COLUMN);
  $hasCarrierCol = in_array('carrier', $colCheck);
  $hasTrackingCol = in_array('tracking_number', $colCheck);
} catch(Throwable $e){}

// Build query with conditional column selection
$carrierSelect = $hasCarrierCol ? "COALESCE(o.carrier, '') AS carrier" : "'' AS carrier";
$trackingSelect = $hasTrackingCol ? "COALESCE(o.tracking_number, '') AS tracking_number" : "'' AS tracking_number";

// Build WHERE clause
$where = [];
$params = [];

// IMPORTANT: Hide draft orders from all admin users
// Draft orders are physician-only until submitted
$where[] = "(o.review_status IS NULL OR o.review_status != 'draft')";

// Role-based access control
if ($adminRole === 'superadmin' || $adminRole === 'manufacturer' || $adminRole === 'admin') {
  // Superadmin, admin, and manufacturer see all orders - no additional filter
} else {
  // Sales, ops, and employees only see orders from assigned physicians
  $where[] = "EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = :admin_id AND ap.physician_user_id = o.user_id)";
  $params['admin_id'] = $adminId;
}

if ($search !== '') {
  $where[] = "(LOWER(p.first_name || ' ' || p.last_name) LIKE LOWER(:search) OR o.id LIKE :search_id)";
  $params['search'] = '%' . $search . '%';
  $params['search_id'] = '%' . $search . '%';
}

if ($statusFilter !== '') {
  $where[] = "o.status = :status";
  $params['status'] = $statusFilter;
}

if ($carrierFilter !== '' && $hasCarrierCol) {
  $where[] = "o.carrier = :carrier";
  $params['carrier'] = $carrierFilter;
}

if ($productFilter !== '') {
  $where[] = "o.product_id = :product_id";
  $params['product_id'] = $productFilter;
}

if ($dateFrom !== '') {
  $where[] = "o.created_at >= :date_from";
  $params['date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
  $where[] = "o.created_at <= :date_to";
  $params['date_to'] = $dateTo . ' 23:59:59';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
  SELECT o.*, p.first_name, p.last_name, p.id AS pid, p.dob, p.phone,
         p.insurance_provider, p.insurance_member_id, p.insurance_group_id, p.insurance_payer_phone,
         $carrierSelect,
         $trackingSelect,
         dc.id AS dc_id,
         dc.confirmed_at AS delivery_confirmed_at,
         dc.confirmation_method AS delivery_confirmation_method,
         dc.confirmed_ip AS delivery_confirmed_ip,
         dc.confirmed_user_agent AS delivery_confirmed_user_agent,
         dc.sms_sent_at AS delivery_sms_sent_at,
         dc.sms_sid AS delivery_sms_sid,
         dc.sms_status AS delivery_sms_status,
         dc.patient_phone AS delivery_patient_phone
  FROM orders o
  LEFT JOIN patients p ON p.id=o.patient_id
  LEFT JOIN delivery_confirmations dc ON dc.order_id=o.id
  $whereClause
  ORDER BY o.created_at DESC LIMIT 1000
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$header = __DIR__.'/_header.php'; $footer = __DIR__.'/_footer.php'; $hasLayout=is_file($header)&&is_file($footer);
if ($hasLayout) include $header; else echo '<!doctype html><meta charset="utf-8"><script src="https://cdn.tailwindcss.com"></script><div class="p-6">';
?>
<div class="flex items-center justify-between mb-4"><div class="text-xl font-semibold">Manage Orders</div></div>

<?php if (isset($_SESSION['error_msg'])): ?>
  <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
    <?=e($_SESSION['error_msg'])?>
  </div>
  <?php unset($_SESSION['error_msg']); ?>
<?php endif; ?>

<!-- Filter Form -->
<div class="bg-white border rounded-lg p-4 mb-4 shadow-sm">
  <form method="get" action="" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
    <div>
      <label class="text-xs text-slate-500 mb-1 block">Search</label>
      <input type="text" name="search" value="<?=e($search)?>" placeholder="Patient name or Order ID" class="w-full border rounded px-3 py-1.5 text-sm">
    </div>

    <div>
      <label class="text-xs text-slate-500 mb-1 block">Status</label>
      <select name="status" class="w-full border rounded px-3 py-1.5 text-sm">
        <option value="">All Statuses</option>
        <option value="pending" <?=$statusFilter==='pending'?'selected':''?>>Pending</option>
        <option value="submitted" <?=$statusFilter==='submitted'?'selected':''?>>Submitted</option>
        <option value="approved" <?=$statusFilter==='approved'?'selected':''?>>Approved</option>
        <option value="rejected" <?=$statusFilter==='rejected'?'selected':''?>>Rejected</option>
        <option value="in_transit" <?=$statusFilter==='in_transit'?'selected':''?>>In Transit</option>
        <option value="delivered" <?=$statusFilter==='delivered'?'selected':''?>>Delivered</option>
      </select>
    </div>

    <?php if ($hasCarrierCol): ?>
    <div>
      <label class="text-xs text-slate-500 mb-1 block">Carrier</label>
      <select name="carrier" class="w-full border rounded px-3 py-1.5 text-sm">
        <option value="">All Carriers</option>
        <option value="ups" <?=$carrierFilter==='ups'?'selected':''?>>UPS</option>
        <option value="fedex" <?=$carrierFilter==='fedex'?'selected':''?>>FedEx</option>
        <option value="usps" <?=$carrierFilter==='usps'?'selected':''?>>USPS</option>
      </select>
    </div>
    <?php endif; ?>

    <div>
      <label class="text-xs text-slate-500 mb-1 block">Product</label>
      <select name="product_id" class="w-full border rounded px-3 py-1.5 text-sm">
        <option value="">All Products</option>
        <?php foreach ($products as $p): ?>
          <option value="<?=$p['id']?>" <?=$productFilter===$p['id']?'selected':''?>>
            <?=e($p['name'].($p['size']?' ('.$p['size'].')':''))?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="text-xs text-slate-500 mb-1 block">Date From</label>
      <input type="date" name="date_from" value="<?=e($dateFrom)?>" class="w-full border rounded px-3 py-1.5 text-sm">
    </div>

    <div>
      <label class="text-xs text-slate-500 mb-1 block">Date To</label>
      <input type="date" name="date_to" value="<?=e($dateTo)?>" class="w-full border rounded px-3 py-1.5 text-sm">
    </div>

    <div class="flex items-end gap-2 <?=!$hasCarrierCol?'md:col-span-3 lg:col-span-2':''?>">
      <button type="submit" class="px-4 py-1.5 bg-brand text-white rounded text-sm hover:bg-brand/90 transition-colors">
        Apply Filters
      </button>
      <a href="/admin/orders.php" class="px-4 py-1.5 bg-slate-100 text-slate-700 rounded text-sm hover:bg-slate-200 transition-colors">
        Clear
      </a>
    </div>
  </form>
</div>

<div class="bg-white border rounded-2xl overflow-hidden shadow-soft">
  <table class="w-full text-sm">
    <thead class="border-b">
      <tr class="text-left">
        <th class="py-2">Patient</th>
        <th class="py-2">Order</th>
        <th class="py-2">Product / Frequency</th>
        <th class="py-2">Qty</th>
        <th class="py-2">Status</th>
        <th class="py-2">Delivered</th>
        <th class="py-2">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr class="border-b hover:bg-slate-50">
        <td class="py-3"><?=e(trim(($r['first_name']??'').' '.($r['last_name']??'')) ?: '—')?></td>
        <td class="py-3">#<?=e($r['id'])?></td>
        <td class="py-3">
          <?php
            $label = $r['product'] ?? '';
            if (!empty($r['product_id'])) {
              foreach ($products as $pr) { if ($pr['id']==$r['product_id']) { $label = $pr['name'].($pr['size']?(' '.$pr['size']):''); break; } }
            }
            echo e(($label ?: '—').' • '.($r['frequency'] ?? ''));
          ?>
        </td>
        <td class="py-3"><?=e(array_key_exists('quantity',$r)?($r['quantity'] ?? 1):1)?></td>
        <td class="py-3">
          <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold
            <?php $s=$r['status']??''; echo $s==='approved'?'bg-green-100 text-green-700':($s==='submitted'||$s==='pending'?'bg-yellow-100 text-yellow-800':($s==='rejected'?'bg-rose-100 text-rose-700':($s==='in_transit'?'bg-amber-100 text-amber-800':($s==='delivered'?'bg-teal-100 text-teal-700':'bg-gray-100 text-gray-700')))); ?>">
            <?=e(ucwords(str_replace('_',' ',$s ?: 'unknown')))?>
          </span>
        </td>
        <td class="py-3">
          <?php
          // Display delivery confirmation status with clickable details
          if (!empty($r['delivery_confirmed_at'])) {
            // GREEN: Patient confirmed delivery - CLICKABLE for audit details
            $dcData = json_encode([
              'order_id' => substr($r['id'], 0, 8),
              'patient_name' => $r['first_name'] . ' ' . $r['last_name'],
              'confirmed_at' => date('m/d/Y g:i A', strtotime($r['delivery_confirmed_at'])),
              'confirmation_method' => $r['delivery_confirmation_method'] ?: 'web_link',
              'confirmed_ip' => $r['delivery_confirmed_ip'] ?: 'N/A',
              'confirmed_user_agent' => $r['delivery_confirmed_user_agent'] ?: 'N/A',
              'sms_sent_at' => $r['delivery_sms_sent_at'] ? date('m/d/Y g:i A', strtotime($r['delivery_sms_sent_at'])) : 'N/A',
              'sms_sid' => $r['delivery_sms_sid'] ?: 'N/A',
              'patient_phone' => $r['delivery_patient_phone'] ?: 'N/A'
            ], JSON_HEX_APOS | JSON_HEX_QUOT);
            echo '<button onclick="showDeliveryDetails(' . htmlspecialchars($dcData, ENT_QUOTES) . ')" class="inline-block w-3 h-3 rounded-full bg-green-500 cursor-pointer hover:ring-2 hover:ring-green-300" title="Click for audit details"></button>';
          } elseif (!empty($r['delivery_sms_sent_at'])) {
            // Check how many days since SMS was sent
            $daysSinceSMS = (time() - strtotime($r['delivery_sms_sent_at'])) / 86400;
            if ($daysSinceSMS > 7) {
              // RED: More than 7 days without confirmation
              echo '<span class="inline-block w-3 h-3 rounded-full bg-red-500" title="SMS sent '.e(date('m/d/Y g:i A', strtotime($r['delivery_sms_sent_at']))).' - No confirmation after '.round($daysSinceSMS).' days"></span>';
            } else {
              // YELLOW: Waiting, within 7 days
              echo '<span class="inline-block w-3 h-3 rounded-full bg-yellow-500" title="SMS sent '.e(date('m/d/Y g:i A', strtotime($r['delivery_sms_sent_at']))).' - Waiting for confirmation ('.round($daysSinceSMS).' days ago)"></span>';
            }
          } else {
            // GRAY: SMS not sent yet
            echo '<span class="inline-block w-3 h-3 rounded-full bg-gray-400" title="Delivery SMS not sent"></span>';
          }
          ?>
        </td>
        <td class="py-3">
          <!-- Approve / Reject -->
          <form method="post" class="inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($r['id'])?>"><input type="hidden" name="action" value="approve"><button class="text-brand hover:underline">Approve</button></form>
          <form method="post" class="inline ml-2"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($r['id'])?>"><input type="hidden" name="action" value="reject"><button class="text-rose-600 hover:underline">Reject</button></form>

          <!-- Mark Delivered (sends SMS immediately) -->
          <?php if ($s === 'in_transit' || $s === 'approved'): ?>
            <form method="post" class="inline ml-2" onsubmit="return confirm('Mark as delivered and send SMS confirmation to patient?');"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($r['id'])?>"><input type="hidden" name="action" value="mark_delivered"><button class="text-green-600 hover:underline font-semibold">✓ Mark Delivered</button></form>
          <?php endif; ?>

          <!-- Ship -->
          <details class="inline-block ml-3">
            <summary class="cursor-pointer text-slate-700 hover:underline inline">Ship</summary>
            <form method="post" class="mt-2 p-3 bg-slate-50 border rounded">
              <?=csrf_field()?><input type="hidden" name="id" value="<?=e($r['id'])?>"/><input type="hidden" name="action" value="ship"/>
              <div class="grid grid-cols-2 gap-2">
                <input class="border rounded px-2 py-1" name="shipping_name"   placeholder="Recipient Name"  value="<?=e($r['shipping_name'] ?? '')?>"/>
                <input class="border rounded px-2 py-1" name="shipping_phone"  placeholder="Recipient Phone" value="<?=e($r['shipping_phone'] ?? '')?>"/>
                <input class="border rounded px-2 py-1 col-span-2" name="shipping_address" placeholder="Address" value="<?=e($r['shipping_address'] ?? '')?>"/>
                <input class="border rounded px-2 py-1" name="shipping_city"   placeholder="City"   value="<?=e($r['shipping_city'] ?? '')?>"/>
                <input class="border rounded px-2 py-1" name="shipping_state"  placeholder="State"  value="<?=e($r['shipping_state'] ?? '')?>"/>
                <input class="border rounded px-2 py-1" name="shipping_zip"    placeholder="Zip"    value="<?=e($r['shipping_zip'] ?? '')?>"/>
                <input class="border rounded px-2 py-1 col-span-2" name="tracking" placeholder="Tracking Number" value="<?=e($r['tracking_number'] ?? '')?>"/>
                <select class="border rounded px-2 py-1" name="carrier">
                  <option value="">Auto-detect</option>
                  <option value="ups"   <?=($r['carrier']==='ups'?'selected':'')?>>UPS</option>
                  <option value="fedex" <?=($r['carrier']==='fedex'?'selected':'')?>>FedEx</option>
                  <option value="usps"  <?=($r['carrier']==='usps'?'selected':'')?>>USPS</option>
                </select>
              </div>
              <div class="text-[11px] text-slate-500 mt-1">Carrier auto-detects; USPS will fetch live status if USPS_USERID is set.</div>
              <button class="mt-2 bg-brand text-white rounded px-3 py-1">Save & Update</button>
            </form>
          </details>

          <!-- Edit dialog -->
          <button onclick="document.getElementById('dlg-<?=e($r['id'])?>').showModal()" class="ml-3 text-slate-700 hover:underline">Edit</button>
          <dialog id="dlg-<?=e($r['id'])?>" class="rounded-2xl p-0 w-[860px]">
            <form method="dialog"><button class="absolute right-3 top-3 text-slate-500">✕</button></form>
            <div class="p-5 border-b font-semibold">Edit Order #<?=e($r['id'])?></div>
            <div class="p-5">
              <form method="post" class="grid grid-cols-2 gap-3">
                <?=csrf_field()?><input type="hidden" name="action" value="edit_order">
                <input type="hidden" name="id" value="<?=e($r['id'])?>"><input type="hidden" name="patient_id" value="<?=e($r['pid'])?>">
                <input type="hidden" name="product_text" value="<?=e($r['product'] ?? '')?>">
                <div class="col-span-2 text-sm font-medium text-slate-600">Order</div>

                <label class="text-xs text-slate-500">Product</label><span></span>
                <select name="product_id" class="border rounded px-2 py-1 col-span-2" onchange="this.dataset.lbl=this.options[this.selectedIndex].text; this.form.product_label.value=this.dataset.lbl; this.form.product_price.value=this.options[this.selectedIndex].dataset.price||'';">
                  <option value="">— choose —</option>
                  <?php foreach ($products as $p): $txt=$p['name'].($p['size']?(" ".$p['size']):'')." (".$p['sku'].")"; ?>
                    <option value="<?=$p['id']?>" data-price="<?=$p['price_admin']?>" <?=(!empty($r['product_id']) && $r['product_id']==$p['id']?'selected':'')?>><?=e($txt)?></option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="product_label" value="<?=e($r['product'] ?? '')?>">

                <label class="text-xs text-slate-500">Frequency</label>
                <label class="text-xs text-slate-500">Quantity</label>
                <select name="frequency" class="border rounded px-2 py-1">
                  <?php foreach($freqOptions as $f): ?><option <?=$r['frequency']===$f?'selected':''?>><?=$f?></option><?php endforeach; ?>
                </select>
                <input class="border rounded px-2 py-1" name="quantity" type="number" min="0" value="<?=e(array_key_exists('quantity',$r)?($r['quantity'] ?? 1):1)?>">

                <label class="text-xs text-slate-500">Unit Price (admin)</label>
                <label class="text-xs text-slate-500">Delivery Mode</label>
                <input class="border rounded px-2 py-1" name="product_price" type="number" step="0.01" value="<?=e($r['product_price'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="delivery_mode" value="<?=e($r['delivery_mode'] ?? '')?>" placeholder="e.g., Ship to patient">

                <div class="col-span-2 text-sm font-medium text-slate-600 mt-2">Shipping</div>
                <input class="border rounded px-2 py-1" name="shipping_name"  placeholder="Recipient Name"  value="<?=e($r['shipping_name'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="shipping_phone" placeholder="Recipient Phone" value="<?=e($r['shipping_phone'] ?? '')?>">
                <input class="border rounded px-2 py-1 col-span-2" name="shipping_address" placeholder="Address" value="<?=e($r['shipping_address'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="shipping_city"  placeholder="City"  value="<?=e($r['shipping_city'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="shipping_state" placeholder="State" value="<?=e($r['shipping_state'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="shipping_zip"   placeholder="Zip"   value="<?=e($r['shipping_zip'] ?? '')?>">

                <div class="col-span-2 text-sm font-medium text-slate-600 mt-2">Patient Demographics</div>
                <label class="text-xs text-slate-500">DOB</label><label class="text-xs text-slate-500">Insurance Provider</label>
                <input class="border rounded px-2 py-1" type="date" name="pat_dob" value="<?=e($r['dob'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="ins_provider" value="<?=e($r['insurance_provider'] ?? '')?>">
                <label class="text-xs text-slate-500">Member ID</label><label class="text-xs text-slate-500">Group ID</label>
                <input class="border rounded px-2 py-1" name="ins_member" value="<?=e($r['insurance_member_id'] ?? '')?>">
                <input class="border rounded px-2 py-1" name="ins_group"  value="<?=e($r['insurance_group_id'] ?? '')?>">
                <label class="text-xs text-slate-500">Payer Phone</label><span></span>
                <input class="border rounded px-2 py-1 col-span-2" name="ins_payer_phone" value="<?=e($r['insurance_payer_phone'] ?? '')?>">

                <div class="col-span-2"><button class="mt-2 bg-brand text-white rounded px-3 py-2">Save Changes</button></div>
              </form>
            </div>
          </dialog>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Delivery Confirmation Details Modal -->
<dialog id="deliveryDetailsModal" class="rounded-lg shadow-2xl p-0 backdrop:bg-black backdrop:bg-opacity-50" style="max-width: 600px; width: 90%;">
  <div class="bg-white rounded-lg">
    <div class="bg-gradient-to-r from-teal-600 to-teal-700 text-white px-6 py-4 flex justify-between items-center rounded-t-lg">
      <h2 class="text-xl font-semibold">📋 Delivery Confirmation Details</h2>
      <button onclick="document.getElementById('deliveryDetailsModal').close()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
    </div>

    <div class="p-6">
      <div class="space-y-4">
        <div class="bg-gray-50 rounded-lg p-4">
          <h3 class="font-semibold text-gray-700 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
              <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
            </svg>
            Order Information
          </h3>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span class="text-gray-500">Order ID:</span>
              <span class="ml-2 font-mono font-semibold" id="dc_order_id"></span>
            </div>
            <div>
              <span class="text-gray-500">Patient:</span>
              <span class="ml-2 font-semibold" id="dc_patient_name"></span>
            </div>
          </div>
        </div>

        <div class="bg-green-50 rounded-lg p-4 border border-green-200">
          <h3 class="font-semibold text-green-800 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            Confirmation Details (Insurance Audit)
          </h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Confirmed At:</span>
              <span class="font-semibold text-green-800" id="dc_confirmed_at"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Method:</span>
              <span class="font-semibold" id="dc_confirmation_method"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Patient IP Address:</span>
              <span class="font-mono text-xs" id="dc_confirmed_ip"></span>
            </div>
            <div class="flex flex-col">
              <span class="text-gray-600 mb-1">User Agent (Device/Browser):</span>
              <span class="font-mono text-xs bg-white p-2 rounded border" id="dc_confirmed_user_agent"></span>
            </div>
          </div>
        </div>

        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
          <h3 class="font-semibold text-blue-800 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
              <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
            </svg>
            SMS Details
          </h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">SMS Sent At:</span>
              <span class="font-semibold" id="dc_sms_sent_at"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Recipient Phone:</span>
              <span class="font-mono" id="dc_patient_phone"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Twilio Message SID:</span>
              <span class="font-mono text-xs" id="dc_sms_sid"></span>
            </div>
          </div>
        </div>

        <div class="text-xs text-gray-500 bg-gray-50 p-3 rounded border">
          <strong>For Insurance Compliance:</strong> This record shows when the patient confirmed delivery of wound care supplies by clicking the confirmation link in the SMS. The IP address and user agent verify the confirmation came from the patient's device.
        </div>
      </div>

      <div class="mt-6 flex justify-end">
        <button onclick="document.getElementById('deliveryDetailsModal').close()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Close</button>
      </div>
    </div>
  </div>
</dialog>

<script>
function showDeliveryDetails(data) {
  document.getElementById('dc_order_id').textContent = data.order_id;
  document.getElementById('dc_patient_name').textContent = data.patient_name;
  document.getElementById('dc_confirmed_at').textContent = data.confirmed_at;
  document.getElementById('dc_confirmation_method').textContent = data.confirmation_method === 'web_link' ? '🔗 Web Link' : '💬 SMS Reply';
  document.getElementById('dc_confirmed_ip').textContent = data.confirmed_ip;
  document.getElementById('dc_confirmed_user_agent').textContent = data.confirmed_user_agent;
  document.getElementById('dc_sms_sent_at').textContent = data.sms_sent_at;
  document.getElementById('dc_patient_phone').textContent = data.patient_phone;
  document.getElementById('dc_sms_sid').textContent = data.sms_sid;

  document.getElementById('deliveryDetailsModal').showModal();
}
</script>

<?php if ($hasLayout) include $footer; else echo '</div>'; ?>
