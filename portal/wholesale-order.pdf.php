<?php
// /public/portal/wholesale-order.pdf.php — Wholesale Order Form/Invoice PDF
declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';

// Check if user is an admin (via admin session)
$isAdmin = !empty($_SESSION['admin_id']) || !empty($_SESSION['admin']);

// Require authentication (portal or admin)
if (empty($_SESSION['user_id']) && !$isAdmin) {
  http_response_code(401);
  echo "Not authenticated";
  exit;
}

$userId = $_SESSION['user_id'] ?? null;

// CSRF check (skip for admin if they have a valid admin session)
if (!$isAdmin) {
  if (empty($_GET['csrf']) || $_GET['csrf'] !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(403);
    echo "forbidden";
    exit;
  }
}

// Get order_group parameter - this will be used to fetch all orders in a wholesale batch
$order_group = $_GET['order_group'] ?? '';
if ($order_group === '') {
  http_response_code(400);
  echo "missing order_group parameter";
  exit;
}

try {
  // Fetch all orders in this wholesale order group
  // Use order_number field directly, with fallback to additional_instructions for legacy orders
  $sql = "SELECT
            o.*,
            p.first_name, p.last_name, p.phone AS patient_phone, p.address, p.city, p.state, p.zip,
            u.first_name AS doc_first, u.last_name AS doc_last, u.practice_name, u.address AS practice_address,
            u.city AS practice_city, u.state AS practice_state, u.zip AS practice_zip, u.phone AS practice_phone,
            pr.pieces_per_box, pr.price_wholesale, pr.name AS product_name, pr.size AS product_size,
            pp.custom_price AS practice_custom_price,
            pp.discount_percentage AS practice_discount_percentage
          FROM orders o
          LEFT JOIN patients p ON p.id=o.patient_id
          LEFT JOIN users u ON u.id=o.user_id
          LEFT JOIN products pr ON pr.id=o.product_id
          LEFT JOIN practice_pricing pp ON pp.user_id = o.user_id AND pp.product_id = o.product_id
          WHERE (o.order_number = ? OR o.additional_instructions LIKE ?)
            AND o.billed_by = 'practice_dme'";

  $params = [$order_group, "%Wholesale Order #$order_group%"];

  // For non-admin, restrict to their own orders
  if (!$isAdmin && $userId) {
    $sql .= " AND o.user_id = ?";
    $params[] = $userId;
  }

  $sql .= " ORDER BY o.created_at ASC, p.last_name, p.first_name";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $orders = $st->fetchAll();

  if (empty($orders)) {
    http_response_code(404);
    echo "wholesale order group not found or access denied";
    exit;
  }

  // Get practice info from first order
  $first_order = $orders[0];
  $practice_name = $first_order['practice_name'] ?? 'Practice';
  $practice_address = $first_order['practice_address'] ?? '';
  $practice_city = $first_order['practice_city'] ?? '';
  $practice_state = $first_order['practice_state'] ?? '';
  $practice_zip = $first_order['practice_zip'] ?? '';
  $practice_phone = $first_order['practice_phone'] ?? '';

  // Extract wholesale order number
  $wholesale_order_number = $order_group;
  $order_date = date('M j, Y', strtotime($first_order['created_at']));

} catch (Throwable $e) {
  http_response_code(500);
  echo "query_failed: " . $e->getMessage();
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

$today = date('F j, Y');

// Calculate totals
$grand_total = 0;
$total_boxes = 0;
$patient_groups = [];

// Group orders by patient
foreach ($orders as $order) {
  $patient_name = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
  if ($patient_name === ' ') $patient_name = 'Office Stock';

  if (!isset($patient_groups[$patient_name])) {
    $patient_groups[$patient_name] = [
      'ship_name' => $order['shipping_name'] ?? '',
      'address' => $order['shipping_address'] ?? $order['address'] ?? '',
      'city' => $order['shipping_city'] ?? $order['city'] ?? '',
      'state' => $order['shipping_state'] ?? $order['state'] ?? '',
      'zip' => $order['shipping_zip'] ?? $order['zip'] ?? '',
      'phone' => $order['shipping_phone'] ?: ($order['patient_phone'] ?? ''),
      'delivery_mode' => $order['delivery_mode'] ?? 'patient',
      'products' => []
    ];
  }

  $boxes = (int)($order['qty_per_change'] ?? 1);
  $pieces_per_box = (int)($order['pieces_per_box'] ?? 10); // Get from products table JOIN

  // Calculate price per box using practice-specific pricing
  $practice_custom_price = (float)($order['practice_custom_price'] ?? 0);
  $practice_discount = (float)($order['practice_discount_percentage'] ?? 0);
  $base_price = (float)($order['price_wholesale'] ?? 0);

  if ($practice_custom_price > 0) {
    // Custom price per piece → convert to per box
    $price_per_box = $practice_custom_price * $pieces_per_box;
  } elseif ($practice_discount != 0) {
    // Apply discount/upcharge percentage to base wholesale price
    $price_per_box = $base_price * (1 - $practice_discount / 100);
  } else {
    // Use product_price from order or fall back to wholesale price
    $price_per_piece = (float)($order['product_price'] ?? 0);
    $price_per_box = $price_per_piece > 0 ? $price_per_piece * $pieces_per_box : $base_price;
  }

  $line_total = $boxes * $price_per_box;

  // Build product label with size for fulfillment clarity
  $productLabel = $order['product_name'] ?? $order['product'] ?? 'Product';
  if (!empty($order['product_size'])) {
    $productLabel .= ' (' . $order['product_size'] . ')';
  }

  $patient_groups[$patient_name]['products'][] = [
    'product' => $productLabel,
    'boxes' => $boxes,
    'price_per_box' => $price_per_box,
    'line_total' => $line_total
  ];

  $grand_total += $line_total;
  $total_boxes += $boxes;
}

// Build HTML for patient sections
$patients_html = '';
foreach ($patient_groups as $patient_name => $patient_data) {
  $is_office = $patient_data['delivery_mode'] === 'office' || $patient_name === 'Office Stock';
  $delivery_label = $is_office ? 'Ship to Office' : 'Ship to Patient';
  $ship_name = trim((string)($patient_data['ship_name'] ?? ''));

  // Always prefer the delivery address the practice actually selected on the order;
  // only fall back to the practice address for office orders with no selected address.
  $selected_addr = implode(', ', array_filter([$patient_data['address'], $patient_data['city'], $patient_data['state'], $patient_data['zip']]));
  if ($selected_addr !== '') {
    $address_line = $selected_addr;
  } elseif ($is_office) {
    $address_line = implode(', ', array_filter([$practice_address, $practice_city, $practice_state, $practice_zip]));
  } else {
    $address_line = '';
  }

  $patients_html .= '
  <div class="patient-section">
    <div class="patient-header">
      <strong>'.h($patient_name).'</strong>
      <span class="delivery-badge">'.h($delivery_label).'</span>
    </div>';

  if ($ship_name !== '') {
    $patients_html .= '<div class="patient-address"><strong>Deliver To:</strong> '.h($ship_name).'</div>';
  }
  if ($address_line) {
    $patients_html .= '<div class="patient-address">'.h($address_line).'</div>';
  }
  if ($patient_data['phone']) {
    $patients_html .= '<div class="patient-phone">Phone: '.h($patient_data['phone']).'</div>';
  }

  $patients_html .= '
    <table class="products-table">
      <thead>
        <tr>
          <th style="text-align:left">Product</th>
          <th style="text-align:center">Boxes</th>
          <th style="text-align:right">Price/Box</th>
          <th style="text-align:right">Total</th>
        </tr>
      </thead>
      <tbody>';

  foreach ($patient_data['products'] as $product) {
    $patients_html .= '
        <tr>
          <td>'.h($product['product']).'</td>
          <td style="text-align:center">'.h((string)$product['boxes']).'</td>
          <td style="text-align:right">$'.number_format($product['price_per_box'], 2).'</td>
          <td style="text-align:right"><strong>$'.number_format($product['line_total'], 2).'</strong></td>
        </tr>';
  }

  $patients_html .= '
      </tbody>
    </table>
  </div>';
}

$html = '
<!doctype html><html><head><meta charset="utf-8">
<title>Wholesale Order #'.h($wholesale_order_number).' — CollagenDirect</title>
<style>
 body{ font-family:-apple-system, Segoe UI, Arial, sans-serif; font-size:12px; color:#111; margin:20px; }
 h1{ font-size:20px; margin:0 0 8px 0; font-weight:600; }
 h2{ font-size:14px; margin:16px 0 8px 0; font-weight:600; border-bottom:1px solid #ddd; padding-bottom:4px; }
 .header{ display:flex; justify-content:space-between; align-items:start; margin-bottom:20px; padding-bottom:12px; border-bottom:2px solid #10b981; }
 .header-left{ }
 .header-right{ text-align:right; }
 .company-name{ font-size:18px; font-weight:700; color:#10b981; margin-bottom:4px; }
 .order-number{ font-size:16px; font-weight:700; margin-bottom:4px; }
 .box{ border:1px solid #ddd; border-radius:4px; padding:8px; margin-bottom:12px; background:#fafafa; }
 .info-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:16px; }
 .info-box{ border:1px solid #e0e0e0; padding:10px; border-radius:4px; background:white; }
 .info-label{ font-size:10px; text-transform:uppercase; color:#666; margin-bottom:4px; font-weight:600; }
 .patient-section{ margin-bottom:16px; border:1px solid #e0e0e0; border-radius:4px; padding:12px; background:white; }
 .patient-header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #e0e0e0; }
 .delivery-badge{ font-size:10px; padding:4px 8px; background:#dbeafe; color:#1e40af; border-radius:4px; font-weight:600; }
 .patient-address{ font-size:11px; color:#666; margin-bottom:4px; }
 .patient-phone{ font-size:11px; color:#666; margin-bottom:8px; }
 .products-table{ width:100%; border-collapse:collapse; margin-top:8px; }
 .products-table th{ text-align:left; font-size:10px; text-transform:uppercase; color:#666; padding:6px 8px; border-bottom:2px solid #e0e0e0; font-weight:600; }
 .products-table td{ padding:8px; border-bottom:1px solid #f0f0f0; font-size:11px; }
 .products-table tbody tr:last-child td{ border-bottom:none; }
 .total-section{ background:#f0fdf4; border:2px solid #10b981; border-radius:4px; padding:12px; margin-top:16px; }
 .total-row{ display:flex; justify-content:space-between; padding:4px 0; }
 .grand-total{ font-size:18px; font-weight:700; border-top:2px solid #10b981; padding-top:8px; margin-top:8px; }
 .footer{ margin-top:24px; padding-top:12px; border-top:1px solid #ddd; font-size:10px; color:#666; text-align:center; }
 @media print {
   body{ margin:10px; }
   .no-print{ display:none; }
 }
</style>
</head><body>

<div class="header">
  <div class="header-left">
    <div class="company-name">CollagenDirect</div>
    <div style="font-size:11px;color:#666;">Wholesale Order Form</div>
  </div>
  <div class="header-right">
    <div class="order-number">Order #'.h($wholesale_order_number).'</div>
    <div style="font-size:11px;color:#666;">Date: '.h($order_date).'</div>
  </div>
</div>

<div class="info-grid">
  <div class="info-box">
    <div class="info-label">Practice Information</div>
    <div><strong>'.h($practice_name).'</strong></div>
    <div style="font-size:11px;color:#666;margin-top:4px;">'.h($practice_address).'</div>
    <div style="font-size:11px;color:#666;">'.h($practice_city).', '.h($practice_state).' '.h($practice_zip).'</div>
    '.($practice_phone ? '<div style="font-size:11px;color:#666;margin-top:4px;">Phone: '.h($practice_phone).'</div>' : '').'
  </div>

  <div class="info-box">
    <div class="info-label">Order Summary</div>
    <div style="margin-top:4px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:2px;">
        <span>Total Patients:</span>
        <strong>'.count($patient_groups).'</strong>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:2px;">
        <span>Total Items:</span>
        <strong>'.array_sum(array_map(function($p){ return count($p['products']); }, $patient_groups)).'</strong>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span>Total Boxes:</span>
        <strong>'.h((string)$total_boxes).'</strong>
      </div>
    </div>
  </div>
</div>

<h2>Order Details</h2>

'.$patients_html.'

<div class="total-section">
  <div class="total-row grand-total">
    <span>GRAND TOTAL:</span>
    <span>$'.number_format($grand_total, 2).'</span>
  </div>
  <div style="font-size:10px;color:#059669;margin-top:8px;">
    Payment Terms: Net 30 | Invoice will be sent separately
  </div>
</div>

<div class="footer">
  <div>CollagenDirect Health | www.collagendirect.health</div>
  <div style="margin-top:4px;">For questions about this order, please contact support</div>
  <div style="margin-top:8px;font-size:9px;">Generated: '.h($today).'</div>
</div>

</body></html>
';

echo $html;
