<?php
declare(strict_types=1);
require __DIR__ . '/auth.php'; require_admin();

// Sales reps cannot access shipments
if (function_exists('deny_sales_rep')) deny_sales_rep();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/shipping.php';
require_once __DIR__ . '/lib/order_display.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $id = $_POST['id'] ?? '';
  if ($id === '') { header('Location: /admin/shipments.php'); exit; }

  if (($_POST['action'] ?? '') === 'save_tracking') {
    $tracking = trim((string)($_POST['tracking'] ?? ''));
    $carrier  = $_POST['carrier'] ?: detect_carrier($tracking);

    // Save tracking + inferred status
    $pdo->prepare(
      "UPDATE orders SET rx_note_mime=?, rx_note_name=?, status='in_transit',
         shipped_at = COALESCE(shipped_at, NOW()),
         updated_at = NOW()
       WHERE id=?"
    )->execute([$carrier ?: null, $tracking ?: null, $id]);

    // Optional: attempt status pull (stub now; ready for future API)
    if ($tracking) {
      $trk = fetch_tracking_status($tracking, $carrier);
      if (!empty($trk['status'])) {
        $pdo->prepare("UPDATE orders SET carrier_status=?, carrier_eta=?, status=?,
                         delivered_at = COALESCE(?, delivered_at),
                         updated_at=NOW()
                       WHERE id=?")
            ->execute([
              $trk['status'], $trk['eta'], $trk['status'],
              $trk['delivered_at'], $id
            ]);
      }
    }
  } elseif (($_POST['action'] ?? '') === 'set_status') {
    $pdo->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?")
        ->execute([$_POST['status'] ?? 'pending', $id]);
  } elseif (($_POST['action'] ?? '') === 'mark_delivered') {
    // Mark order as delivered and send SMS/email confirmation
    $pdo->prepare("UPDATE orders SET status='delivered', delivered_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$id]);

    // Send delivery confirmation notification
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

      if ($order) {
        $hasPhone = !empty($order['phone']);
        $hasEmail = !empty($order['email']);
        $patientName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
        $physicianName = trim(($order['phys_last'] ?? '') . ($order['phys_first'] ? ', ' . $order['phys_first'] : ''));
        if (empty($physicianName) && !empty($order['practice_name'])) {
          $physicianName = $order['practice_name'];
        }

        // Check if confirmation already exists
        $existingConfirmation = $pdo->prepare("SELECT id FROM delivery_confirmations WHERE order_id = ?");
        $existingConfirmation->execute([$id]);

        if (!$existingConfirmation->fetch() && ($hasPhone || $hasEmail)) {
          // Generate unique confirmation token
          $token = bin2hex(random_bytes(32));

          // Insert confirmation record
          $insertStmt = $pdo->prepare("
            INSERT INTO delivery_confirmations (
              order_id, patient_phone, patient_email, confirmation_token, created_at, updated_at
            ) VALUES (?, ?, ?, ?, NOW(), NOW())
            RETURNING id
          ");
          $insertStmt->execute([$id, $order['phone'] ?? null, $order['email'] ?? null, $token]);
          $confirmationId = $insertStmt->fetchColumn();

          $smsSent = false;
          $emailSent = false;

          // Try SMS first if patient has phone
          if ($hasPhone) {
            $smsResult = send_delivery_confirmation_sms($order['phone'], $patientName, $id, $token, $physicianName);
            if ($smsResult['success']) {
              $smsSent = true;
              $pdo->prepare("UPDATE delivery_confirmations SET sms_sent_at = NOW(), sms_sid = ?, sms_status = ?, updated_at = NOW() WHERE id = ?")
                  ->execute([$smsResult['sid'], $smsResult['status'], $confirmationId]);
            }
          }

          // Send email if: (1) no phone, OR (2) SMS failed
          if ($hasEmail && (!$hasPhone || !$smsSent)) {
            require_once __DIR__ . '/../api/lib/email_sender.php';

            $confirmUrl = "https://collagendirect.health/api/delivery-approval.php?token=" . urlencode($token);
            $productName = $order['product'] ?? 'wound care supplies';

            $emailSubject = "Confirm Receipt of Your Wound Care Supplies";
            $emailBody = email_template($emailSubject, "
              <h2 style='color: #1e293b; margin: 0 0 20px 0;'>Delivery Confirmation</h2>
              <p style='color: #475569; line-height: 1.6;'>Hi {$patientName},</p>
              <p style='color: #475569; line-height: 1.6;'>Your wound care supplies" . ($physicianName ? " from Dr. {$physicianName}" : "") . " have been delivered.</p>
              <p style='color: #475569; line-height: 1.6;'>Please confirm receipt by clicking the button below:</p>
              <div style='text-align: center; margin: 30px 0;'>
                <a href='{$confirmUrl}' style='display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 12px; font-weight: 600; font-size: 16px;'>
                  Confirm & Sign
                </a>
              </div>
            ");

            $textBody = "Delivery Confirmation\n\nHi {$patientName},\n\nYour wound care supplies" . ($physicianName ? " from Dr. {$physicianName}" : "") . " have been delivered.\n\nPlease confirm receipt by visiting:\n{$confirmUrl}";

            if (send_email($order['email'], $patientName, $emailSubject, $emailBody, $textBody)) {
              $emailSent = true;
              $pdo->prepare("UPDATE delivery_confirmations SET notes = COALESCE(notes, '') || 'Email sent: ' || NOW()::text, updated_at = NOW() WHERE id = ?")->execute([$confirmationId]);
            }
          }
        }

        // Create photo prompt schedule for wound photo updates
        require_once __DIR__ . '/../api/lib/photo_prompt_helpers.php';
        $deliveryDate = date('Y-m-d', strtotime($order['delivered_at'] ?? 'now'));
        create_photo_prompt_schedule($pdo, $order['id'], $order['patient_id'], $order['frequency'], $order['product'], $deliveryDate);
      }
    } catch (Throwable $notifyErr) {
      error_log('[shipments.php] Delivery confirmation error: ' . $notifyErr->getMessage());
    }
  }
  header('Location: /admin/shipments.php'); exit;
}

/* ================= Filters ================= */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$carrierFilter = isset($_GET['carrier']) ? trim($_GET['carrier']) : '';
$stateFilter = isset($_GET['state']) ? trim($_GET['state']) : '';
$hasTracking = isset($_GET['has_tracking']) ? $_GET['has_tracking'] : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$where = [];
$params = [];

if ($search !== '') {
  $where[] = "(o.shipping_name ILIKE :search OR o.id ILIKE :search_id OR o.rx_note_name ILIKE :search_track)";
  $params['search'] = '%' . $search . '%';
  $params['search_id'] = '%' . $search . '%';
  $params['search_track'] = '%' . $search . '%';
}

if ($statusFilter !== '') {
  $where[] = "o.status = :status";
  $params['status'] = $statusFilter;
}

if ($carrierFilter !== '') {
  $where[] = "o.rx_note_mime = :carrier";
  $params['carrier'] = $carrierFilter;
}

if ($stateFilter !== '') {
  $where[] = "o.shipping_state = :state";
  $params['state'] = $stateFilter;
}

if ($hasTracking === 'yes') {
  $where[] = "o.rx_note_name IS NOT NULL AND o.rx_note_name != ''";
} elseif ($hasTracking === 'no') {
  $where[] = "(o.rx_note_name IS NULL OR o.rx_note_name = '')";
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
  SELECT o.id, o.order_number, o.product, o.shipping_name, o.shipping_address, o.shipping_city,
         o.shipping_state, o.shipping_zip,
         o.rx_note_name, o.rx_note_mime, o.status, o.created_at,
         o.billed_by, o.payment_type,
         p.first_name as patient_first, p.last_name as patient_last,
         u.practice_name
  FROM orders o
  LEFT JOIN patients p ON p.id = o.patient_id
  LEFT JOIN users u ON u.id = o.user_id
  $whereClause
  ORDER BY o.created_at DESC
  LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

include __DIR__ . '/_header.php';
?>
<div class="flex justify-between items-center mb-4">
  <div class="text-xl font-semibold">Manage Shipments</div>
  <button id="exportBtn" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed" disabled>
    Export Selected to Excel
  </button>
</div>

<!-- Filter Form -->
<div class="bg-white border rounded-lg p-4 mb-4 shadow-sm">
  <form method="get" action="" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
    <div>
      <label class="text-xs text-slate-500 mb-1 block">Search</label>
      <input type="text" name="search" value="<?=e($search)?>" placeholder="Name, Order ID, Tracking" class="w-full border rounded px-3 py-1.5 text-sm">
    </div>

    <div>
      <label class="text-xs text-slate-500 mb-1 block">Status</label>
      <select name="status" class="w-full border rounded px-3 py-1.5 text-sm">
        <option value="">All Statuses</option>
        <option value="pending" <?=$statusFilter==='pending'?'selected':''?>>Pending</option>
        <option value="approved" <?=$statusFilter==='approved'?'selected':''?>>Approved</option>
        <option value="in_transit" <?=$statusFilter==='in_transit'?'selected':''?>>In Transit</option>
        <option value="delivered" <?=$statusFilter==='delivered'?'selected':''?>>Delivered</option>
      </select>
    </div>

    <div>
      <label class="text-xs text-slate-500 mb-1 block">Carrier</label>
      <select name="carrier" class="w-full border rounded px-3 py-1.5 text-sm">
        <option value="">All Carriers</option>
        <option value="ups" <?=$carrierFilter==='ups'?'selected':''?>>UPS</option>
        <option value="fedex" <?=$carrierFilter==='fedex'?'selected':''?>>FedEx</option>
        <option value="usps" <?=$carrierFilter==='usps'?'selected':''?>>USPS</option>
      </select>
    </div>

    <div>
      <label class="text-xs text-slate-500 mb-1 block">State</label>
      <input type="text" name="state" value="<?=e($stateFilter)?>" placeholder="State (e.g., CA)" maxlength="2" class="w-full border rounded px-3 py-1.5 text-sm">
    </div>

    <div>
      <label class="text-xs text-slate-500 mb-1 block">Has Tracking</label>
      <select name="has_tracking" class="w-full border rounded px-3 py-1.5 text-sm">
        <option value="">All</option>
        <option value="yes" <?=$hasTracking==='yes'?'selected':''?>>Has Tracking</option>
        <option value="no" <?=$hasTracking==='no'?'selected':''?>>Needs Tracking</option>
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

    <div class="flex items-end gap-2 md:col-span-3 lg:col-span-5">
      <button type="submit" class="px-4 py-1.5 bg-brand text-white rounded text-sm hover:bg-brand/90 transition-colors">
        Apply Filters
      </button>
      <?php if ($search || $statusFilter || $carrierFilter || $stateFilter || $hasTracking || $dateFrom || $dateTo): ?>
        <a href="/admin/shipments.php" class="px-4 py-1.5 bg-slate-100 text-slate-700 rounded text-sm hover:bg-slate-200 transition-colors">
          Clear
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="bg-white border rounded-2xl overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="border-b">
      <tr class="text-left">
        <th class="py-2 px-2">
          <input type="checkbox" id="selectAll" class="rounded">
        </th>
        <th class="py-2">ID</th>
        <th class="py-2">Type</th>
        <th class="py-2">Item</th>
        <th class="py-2">Deliver To</th>
        <th class="py-2">Carrier</th>
        <th class="py-2">Tracking</th>
        <th class="py-2">Quick Track</th>
        <th class="py-2">Status</th>
        <th class="py-2">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $rawTrack   = (string)($r['rx_note_name'] ?? '');
        $displayVal = looks_like_filename($rawTrack) ? '' : $rawTrack; // avoid showing filenames in field
        $carrier    = $r['rx_note_mime'] ?: detect_carrier($displayVal);
        $trackHref  = $displayVal ? tracking_url($displayVal, $carrier ?: null) : '';

        // Determine order type
        $isWholesale = ($r['billed_by'] === 'practice_dme' || $r['payment_type'] === 'wholesale');
        $orderType = $isWholesale ? 'Wholesale' : 'Referral';
        $typeBadgeClass = $isWholesale ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700';

        // Determine delivery recipient and full address
        if ($isWholesale) {
          $deliveryName = $r['practice_name'] ?? 'Practice';
        } else {
          $deliveryName = trim(($r['patient_first'] ?? '') . ' ' . ($r['patient_last'] ?? ''));
          if (empty($deliveryName)) {
            $deliveryName = $r['shipping_name'] ?? 'Patient';
          }
        }

        // Build full address for tooltip
        $fullAddress = trim(implode(', ', array_filter([
          $r['shipping_address'] ?? '',
          $r['shipping_city'] ?? '',
          $r['shipping_state'] ?? '',
          $r['shipping_zip'] ?? ''
        ])));
      ?>
      <tr class="border-b hover:bg-slate-50" data-order-id="<?=e($r['id'])?>"
          data-name="<?=e($deliveryName)?>"
          data-address="<?=e($fullAddress)?>"
          data-product="<?=e($r['product'] ?? '')?>">
        <td class="py-3 px-2">
          <input type="checkbox" class="order-checkbox rounded" value="<?=e($r['id'])?>">
        </td>
        <td class="py-3"><?=format_order_number_html($r)?></td>
        <td class="py-3">
          <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?=$typeBadgeClass?>">
            <?=$orderType?>
          </span>
        </td>
        <td class="py-3"><?=e($r['product'] ?? '')?></td>
        <td class="py-3">
          <button type="button" class="text-brand underline hover:text-brand/80 address-btn"
                  data-name="<?=e($deliveryName)?>"
                  data-address="<?=e($fullAddress)?>">
            <?=e($deliveryName)?>
          </button>
        </td>
        <td class="py-3">
          <form method="post" class="inline"><?=csrf_field()?>
            <input type="hidden" name="action" value="save_tracking">
            <input type="hidden" name="id" value="<?=e($r['id'])?>">
            <select name="carrier" class="border rounded px-2 py-1 text-xs">
              <option value="">Auto-detect</option>
              <option value="ups"   <?=($carrier==='ups'?'selected':'')?>>UPS</option>
              <option value="fedex" <?=($carrier==='fedex'?'selected':'')?>>FedEx</option>
              <option value="usps"  <?=($carrier==='usps'?'selected':'')?>>USPS</option>
            </select>
        </td>
        <td class="py-3">
            <input name="tracking" class="border rounded px-2 py-1 text-xs w-44" placeholder="Tracking number" value="<?=e($displayVal)?>">
            <button class="ml-2 text-brand text-xs">Save</button>
          </form>
        </td>
        <td class="py-3">
          <?php if ($trackHref): ?>
            <a class="text-brand underline text-xs" href="<?=e($trackHref)?>" target="_blank">Track</a>
          <?php else: ?>
            <span class="text-slate-400 text-xs">—</span>
          <?php endif; ?>
        </td>
        <td class="py-3">
          <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?=($r['status']==='delivered'?'bg-teal-100 text-teal-700':($r['status']==='in_transit'?'bg-amber-100 text-amber-800':'bg-gray-100 text-gray-700'))?>">
            <?=e(ucwords(str_replace('_',' ',$r['status'] ?? 'pending')))?>
          </span>
        </td>
        <td class="py-3 space-x-2">
          <form method="post" class="inline"><?=csrf_field()?>
            <input type="hidden" name="action" value="set_status">
            <input type="hidden" name="id" value="<?=e($r['id'])?>">
            <select name="status" class="border rounded px-2 py-1 text-xs">
              <option value="pending"     <?=($r['status']==='pending'?'selected':'')?>>Pending</option>
              <option value="in_transit"  <?=($r['status']==='in_transit'?'selected':'')?>>In Transit</option>
              <option value="delivered"   <?=($r['status']==='delivered'?'selected':'')?>>Delivered</option>
            </select>
            <button class="ml-2 text-slate-700 text-xs">Update</button>
          </form>
          <?php if ($r['status'] !== 'delivered'): ?>
          <form method="post" class="inline" onsubmit="return confirm('Mark as delivered and send confirmation to patient?');">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="mark_delivered">
            <input type="hidden" name="id" value="<?=e($r['id'])?>">
            <button class="text-green-600 hover:underline font-semibold text-xs whitespace-nowrap">Mark Delivered</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Address Popup Modal -->
<div id="addressModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
    <div class="flex justify-between items-start mb-4">
      <h3 class="text-lg font-semibold">Shipping Address</h3>
      <button id="closeModal" class="text-gray-500 hover:text-gray-700">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>
    <div class="space-y-2">
      <div>
        <div class="text-sm font-medium text-gray-500">Recipient</div>
        <div id="modalName" class="text-base"></div>
      </div>
      <div>
        <div class="text-sm font-medium text-gray-500">Address</div>
        <div id="modalAddress" class="text-base"></div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const selectAll = document.getElementById('selectAll');
  const checkboxes = document.querySelectorAll('.order-checkbox');
  const exportBtn = document.getElementById('exportBtn');
  const modal = document.getElementById('addressModal');
  const closeModal = document.getElementById('closeModal');
  const addressBtns = document.querySelectorAll('.address-btn');

  console.log('Shipments page loaded:', {
    selectAll: !!selectAll,
    checkboxCount: checkboxes.length,
    exportBtn: !!exportBtn,
    addressBtnCount: addressBtns.length
  });

  // Select all functionality
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      console.log('Select all clicked:', this.checked);
      checkboxes.forEach(cb => cb.checked = this.checked);
      updateExportButton();
    });
  }

  // Individual checkbox change
  checkboxes.forEach(cb => {
    cb.addEventListener('change', function() {
      console.log('Checkbox changed:', this.value, this.checked);
      const allChecked = Array.from(checkboxes).every(c => c.checked);
      const someChecked = Array.from(checkboxes).some(c => c.checked);
      if (selectAll) {
        selectAll.checked = allChecked;
        selectAll.indeterminate = someChecked && !allChecked;
      }
      updateExportButton();
    });
  });

  // Update export button state
  function updateExportButton() {
    if (!exportBtn) return;
    const selectedCount = Array.from(checkboxes).filter(c => c.checked).length;

    if (selectedCount === 0) {
      exportBtn.disabled = true;
      exportBtn.setAttribute('disabled', 'disabled');
      exportBtn.textContent = 'Export Selected to Excel';
    } else {
      exportBtn.disabled = false;
      exportBtn.removeAttribute('disabled');
      exportBtn.textContent = `Export ${selectedCount} Selected to Excel`;
    }

    console.log('Export button updated:', { selectedCount, disabled: exportBtn.disabled });
  }

  // Export functionality
  if (exportBtn) {
    exportBtn.addEventListener('click', function(e) {
      e.preventDefault(); // Prevent any default behavior
      console.log('Export button clicked');

      const selectedIds = Array.from(checkboxes)
        .filter(c => c.checked)
        .map(c => c.value);

      console.log('Selected IDs:', selectedIds);
      if (selectedIds.length === 0) {
        alert('Please select at least one shipment to export.');
        return;
      }

      // Create form and submit
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '/admin/export-shipments.php';

      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'order_ids';
      input.value = JSON.stringify(selectedIds);
      form.appendChild(input);

      const csrf = document.createElement('input');
      csrf.type = 'hidden';
      csrf.name = 'csrf';
      csrf.value = '<?=csrf_token()?>';
      form.appendChild(csrf);

      document.body.appendChild(form);
      console.log('Submitting form with', selectedIds.length, 'orders');
      form.submit();
    });
  }

  // Initialize button state
  updateExportButton();

  // Address popup functionality
  addressBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const name = this.getAttribute('data-name');
      const address = this.getAttribute('data-address');

      document.getElementById('modalName').textContent = name;
      document.getElementById('modalAddress').textContent = address || 'No address available';

      modal.classList.remove('hidden');
    });
  });

  // Close modal
  closeModal.addEventListener('click', function() {
    modal.classList.add('hidden');
  });

  // Close on background click
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      modal.classList.add('hidden');
    }
  });

  // Close on escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
      modal.classList.add('hidden');
    }
  });
});
</script>

<?php include __DIR__ . '/_footer.php'; ?>
