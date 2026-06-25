<?php
// /public/portal/order.pdf.php — Order PDF for physicians (their own orders)
declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';

// Require portal authentication (physician must be logged in)
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo "Not authenticated";
  exit;
}

$userId = $_SESSION['user_id'];

// CSRF check
if (empty($_GET['csrf']) || $_GET['csrf'] !== ($_SESSION['csrf'] ?? '')) {
  http_response_code(403);
  echo "forbidden";
  exit;
}

$id = $_GET['id'] ?? '';
if ($id === '') {
  http_response_code(400);
  echo "missing id";
  exit;
}

try {
  $sql = "SELECT
            o.*,
            p.first_name, p.last_name, p.dob, p.address, p.city, p.state, p.zip, p.phone AS patient_phone,
            p.insurance_provider, p.insurance_member_id, p.insurance_group_id, p.insurance_payer_phone,
            u.first_name AS doc_first, u.last_name AS doc_last, u.license, u.license_state, u.npi,
            u.sign_name, u.sign_title, u.sign_date, u.practice_name
          FROM orders o
          LEFT JOIN patients p ON p.id=o.patient_id
          LEFT JOIN users u ON u.id=o.user_id
          WHERE o.id = ? AND o.user_id = ?";
  $st = $pdo->prepare($sql);
  $st->execute([$id, $userId]);
  $o = $st->fetch();

  if (!$o) {
    http_response_code(404);
    echo "order not found or access denied";
    exit;
  }

  // Fetch all products in this order group (primary, secondary, additional)
  $order_group_id = $o['order_group_id'];

  if (!empty($order_group_id)) {
    // Multi-product order: fetch all orders in the group with product details
    $sql_group = "SELECT o.product, o.product_type, o.wound_index, o.cpt,
                         o.frequency_per_week, o.qty_per_change, o.duration_days,
                         pr.name AS product_name, pr.size AS product_size, pr.hcpcs_code
                  FROM orders o
                  LEFT JOIN products pr ON pr.id = o.product_id
                  WHERE o.order_group_id = ? AND o.user_id = ?
                  ORDER BY o.wound_index,
                    CASE o.product_type
                      WHEN 'primary' THEN 1
                      WHEN 'secondary' THEN 2
                      WHEN 'additional' THEN 3
                      ELSE 4
                    END";
    $st_group = $pdo->prepare($sql_group);
    $st_group->execute([$order_group_id, $userId]);
    $all_products = $st_group->fetchAll();
  } else {
    // Single-product order: get product details from products table
    $product_details = [];
    if (!empty($o['product_id'])) {
      $prod_stmt = $pdo->prepare("SELECT name, size, hcpcs_code FROM products WHERE id = ?");
      $prod_stmt->execute([$o['product_id']]);
      $product_details = $prod_stmt->fetch() ?: [];
    }

    $all_products = [[
      'product' => $o['product'] ?? '',
      'product_type' => $o['product_type'] ?? 'primary',
      'wound_index' => $o['wound_index'] ?? 0,
      'cpt' => $o['cpt'] ?? '',
      'frequency_per_week' => $o['frequency_per_week'] ?? 0,
      'qty_per_change' => $o['qty_per_change'] ?? 1,
      'duration_days' => $o['duration_days'] ?? 0,
      'product_name' => $product_details['name'] ?? ($o['product'] ?? ''),
      'product_size' => $product_details['size'] ?? '',
      'hcpcs_code' => $product_details['hcpcs_code'] ?? ($o['cpt'] ?? '')
    ]];
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo "query_failed: " . $e->getMessage();
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function weeks_auth($days){ $d=(int)$days; return $d>0 ? (int)ceil($d/7) : 0; }

$weeks   = weeks_auth($o['duration_days'] ?? 0);
$refills = max(0, (int)($o['refills_allowed'] ?? 0));
$fpw     = (int)($o['frequency_per_week'] ?? 0);
if ($fpw<=0) {
  // fallback for legacy textual frequency
  $txt = strtolower((string)($o['frequency'] ?? ''));
  if ($txt==='daily') $fpw=7; elseif ($txt==='every other day') $fpw=4; elseif ($txt==='weekly') $fpw=1; else $fpw=1;
}
$qty     = max(1, (int)($o['qty_per_change'] ?? 1));
$weeks_all = max(1, $weeks) * (1 + $refills);
$units_total = $weeks_all * $fpw * $qty;

$today = date('Y-m-d');

$sec_patient = '
  <h2>Patient</h2>
  <div class="box"><table class="kv">
    <tr><td class="key">Name</td><td>'.h(($o['first_name']??"")." ".($o['last_name']??"")).'</td></tr>
    <tr><td class="key">DOB</td><td>'.h($o['dob'] ?? "").'</td></tr>
    <tr><td class="key">Address</td><td>'.h($o['address'] ?? "").', '.h($o['city'] ?? "").', '.h($o['state'] ?? "").' '.h($o['zip'] ?? "").'</td></tr>
  </table></div>
';

// Use physician data from order if available (multi-physician practice), otherwise from user
$physician_name = $o['e_sign_name'] ?? (($o['doc_first']??"")." ".($o['doc_last']??""));
$physician_npi = $o['physician_npi'] ?? $o['npi'] ?? "—";
$physician_license = $o['physician_license'] ?? $o['license'] ?? "—";
$physician_license_state = $o['physician_license_state'] ?? $o['license_state'] ?? "—";

$sec_physician = '
  <h2>Ordering Physician</h2>
  <div class="box"><table class="kv">
    <tr><td class="key">Name</td><td>'.h($physician_name).'</td></tr>
    <tr><td class="key">Practice</td><td>'.h($o['practice_name'] ?? "—").'</td></tr>
    <tr><td class="key">NPI</td><td>'.h($physician_npi).'</td></tr>
    <tr><td class="key">License</td><td>'.h($physician_license).' ('.h($physician_license_state).')</td></tr>
  </table></div>
';

// E-Signature Section with Compliance Notice
$eSignName = $o['e_sign_name'] ?? $o['sign_name'] ?? '—';
$eSignTitle = $o['e_sign_title'] ?? $o['sign_title'] ?? '—';
$eSignDate = $o['e_sign_at'] ?? $o['sign_date'] ?? '—';
$eSignIP = $o['e_sign_ip'] ?? '—';

$sec_esignature = '
  <h2>Electronic Signature</h2>
  <div class="box" style="background:#f9fafb">
    <table class="kv">
      <tr><td class="key">Signed By</td><td><strong>'.h($eSignName).'</strong></td></tr>
      <tr><td class="key">Title</td><td>'.h($eSignTitle).'</td></tr>
      <tr><td class="key">Date & Time</td><td>'.h($eSignDate).'</td></tr>
      <tr><td class="key">IP Address</td><td>'.h($eSignIP).'</td></tr>
    </table>
    <div style="margin-top:10px;padding:8px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;font-size:10px">
      <strong>E-Signature Notice:</strong> By electronically signing this order, I certify that I am the prescribing physician or authorized representative,
      and that this order is medically necessary and appropriate for this patient. This electronic signature has the same legal effect as a handwritten signature
      in accordance with the ESIGN Act (15 U.S.C. § 7001) and applicable state law.
    </div>
  </div>
';

// Insurance Information Section
$sec_insurance = '
  <h2>Insurance Information</h2>
  <div class="box"><table class="kv">
    <tr><td class="key">Insurance Provider</td><td>'.h($o['insurance_provider'] ?? $o['insurer_name'] ?? "—").'</td></tr>
    <tr><td class="key">Member ID</td><td>'.h($o['insurance_member_id'] ?? $o['member_id'] ?? "—").'</td></tr>
    <tr><td class="key">Group ID</td><td>'.h($o['insurance_group_id'] ?? $o['group_id'] ?? "—").'</td></tr>
    <tr><td class="key">Payer Phone</td><td>'.h($o['insurance_payer_phone'] ?? $o['payer_phone'] ?? "—").'</td></tr>
  </table></div>
';

// Parse wounds_data JSONB for multi-wound support
$wounds = [];
if (!empty($o['wounds_data'])) {
  $wounds_json = is_string($o['wounds_data']) ? $o['wounds_data'] : json_encode($o['wounds_data']);
  $wounds = json_decode($wounds_json, true) ?: [];
}

// If no wounds_data, fallback to legacy single-wound columns
if (empty($wounds)) {
  $wounds = [[
    'location' => $o['wound_location'] ?? '—',
    'laterality' => $o['wound_laterality'] ?? '—',
    'length_cm' => $o['wound_length_cm'] ?? '—',
    'width_cm' => $o['wound_width_cm'] ?? '—',
    'depth_cm' => $o['wound_depth_cm'] ?? '—',
    'type' => $o['wound_type'] ?? '—',
    'stage' => $o['wound_stage'] ?? '—',
    'exudate_level' => $o['exudate_level'] ?? '—',
    'icd10_primary' => $o['icd10_primary'] ?? '—',
    'icd10_secondary' => $o['icd10_secondary'] ?? '—',
    'product_name' => $o['product'] ?? '—',
    'frequency_per_week' => $fpw,
    'qty_per_change' => $qty,
    'notes' => $o['wound_notes'] ?? ''
  ]];
}

// Build wounds section with each wound and all its products
$sec_wound = '';
$wound_num = 1;
foreach ($wounds as $wound_idx => $wound) {
  $w_freq = (int)($wound['frequency_per_week'] ?? 0);
  $w_qty = (int)($wound['qty_per_change'] ?? 1);

  // Get all products for this wound from the order group
  $wound_products = array_filter($all_products, function($p) use ($wound_idx) {
    return (int)($p['wound_index'] ?? 0) === $wound_idx;
  });

  // Build product list display with boxes calculation
  $products_html = '';
  foreach ($wound_products as $prod) {
    $type_label = '';
    switch ($prod['product_type'] ?? 'primary') {
      case 'primary':
        $type_label = '<span style="color:#059669;font-weight:bold">Primary:</span>';
        break;
      case 'secondary':
        $type_label = '<span style="color:#2563eb;font-weight:bold">Secondary:</span>';
        break;
      case 'additional':
        $type_label = '<span style="color:#7c3aed;font-weight:bold">Additional:</span>';
        break;
    }

    // Calculate boxes for this product
    $prod_freq = (int)($prod['frequency_per_week'] ?? $w_freq);
    $prod_qty = (int)($prod['qty_per_change'] ?? $w_qty);
    $prod_days = (int)($prod['duration_days'] ?? ($o['duration_days'] ?? 0));
    $prod_weeks = $prod_days > 0 ? (int)ceil($prod_days / 7) : 0;
    $total_pieces = $prod_freq * $prod_qty * $prod_weeks;
    $boxes = $total_pieces > 0 ? (int)ceil($total_pieces / 10) : 0; // 10 items per box

    // Build product name with size and HCPCS
    $product_display = h($prod['product_name'] ?? $prod['product'] ?? '');
    if (!empty($prod['product_size'])) {
      $product_display .= ' ' . h($prod['product_size']);
    }
    if (!empty($prod['hcpcs_code'])) {
      $product_display .= ' (' . h($prod['hcpcs_code']) . ')';
    }

    $products_html .= $type_label . ' ' . $product_display;
    if ($total_pieces > 0) {
      $products_html .= ' <span style="color:#64748b">— ' . $total_pieces . ' pieces</span>';
    }
    $products_html .= '<br>';
  }

  if (empty($products_html)) {
    // Fallback for legacy orders
    $legacy_days = (int)($o['duration_days'] ?? 0);
    $legacy_weeks = $legacy_days > 0 ? (int)ceil($legacy_days / 7) : 0;
    $legacy_total = $w_freq * $w_qty * $legacy_weeks;
    $legacy_boxes = $legacy_total > 0 ? (int)ceil($legacy_total / 10) : 0;

    $products_html = h($wound['product_name'] ?? '—');
    if ($legacy_total > 0) {
      $products_html .= ' <span style="color:#64748b">(' . $legacy_total . ' pieces)</span>';
    }
  }

  $sec_wound .= '
  <h2>Wound #'.$wound_num.'</h2>
  <div class="box"><table class="kv">
    <tr><td class="key">Products Ordered</td><td><strong>'.$products_html.'</strong></td></tr>
    <tr><td class="key">Change Frequency</td><td>'.h((string)$w_freq).' × /week</td></tr>
    <tr><td class="key">Qty per Change</td><td>'.h((string)$w_qty).'</td></tr>
    <tr><td class="key">Location</td><td>'.h($wound['location'] ?? "—").'</td></tr>
    <tr><td class="key">Laterality</td><td>'.h($wound['laterality'] ?? "—").'</td></tr>
    <tr><td class="key">Dimensions</td><td>L: '.h($wound['length_cm'] ?? "—").' cm × W: '.h($wound['width_cm'] ?? "—").' cm × D: '.h($wound['depth_cm'] ?? "—").' cm</td></tr>
    <tr><td class="key">Type</td><td>'.h($wound['type'] ?? "—").'</td></tr>
    <tr><td class="key">Stage</td><td>'.h($wound['stage'] ?? "—").'</td></tr>
    <tr><td class="key">Exudate Level</td><td>'.h($wound['exudate_level'] ?? "—").'</td></tr>
    <tr><td class="key">ICD-10 Primary</td><td>'.h($wound['icd10_primary'] ?? "—").'</td></tr>
    <tr><td class="key">ICD-10 Secondary</td><td>'.h($wound['icd10_secondary'] ?? "—").'</td></tr>';

  if (!empty($wound['notes'])) {
    $sec_wound .= '
    <tr><td class="key">Patient Instructions</td><td>'.h($wound['notes']).'</td></tr>';
  }

  $sec_wound .= '
  </table></div>
  ';
  $wound_num++;
}

// Calculate aggregate totals for all wounds
$total_units_per_week = 0;
$product_list = [];
foreach ($wounds as $wound) {
  $w_freq = (int)($wound['frequency_per_week'] ?? 0);
  $w_qty = (int)($wound['qty_per_change'] ?? 1);
  $total_units_per_week += $w_freq * $w_qty;

  $p_name = $wound['product_name'] ?? 'Unknown';
  if (!in_array($p_name, $product_list)) {
    $product_list[] = $p_name;
  }
}

$duration_days = max(0, (int)($o['duration_days'] ?? 0));
$duration_weeks = $duration_days > 0 ? (int)ceil($duration_days / 7) : 0;
$total_units_for_duration = $total_units_per_week * $duration_weeks * (1 + $refills);

$products_summary = count($product_list) === 1 ? $product_list[0] : implode(', ', $product_list);

$sec_order = '
  <h2>Order Summary</h2>
  <div class="box"><table class="kv">
    <tr><td class="key">Order #</td><td>'.h($o['id']).'</td></tr>
    <tr><td class="key">Number of Wounds</td><td>'.count($wounds).'</td></tr>
    <tr><td class="key">Products</td><td>'.h($products_summary).'</td></tr>
    <tr><td class="key">Total Units per Week</td><td>'.h((string)$total_units_per_week).'</td></tr>
    <tr><td class="key">Duration (days)</td><td>'.h((string)$duration_days).' ('.h((string)$duration_weeks).' weeks)</td></tr>
    <tr><td class="key">Refills Allowed</td><td>'.h((string)$refills).'</td></tr>
    <tr><td class="key">Total Authorized Units</td><td><strong>'.h((string)$total_units_for_duration).'</strong> (across all wounds)</td></tr>
    <tr><td class="key">Delivery Mode</td><td>'.h($o['delivery_mode'] ?? "—").'</td></tr>
    <tr><td class="key">Status</td><td>'.h($o['status'] ?? "—").'</td></tr>
    <tr><td class="key">Created</td><td>'.h($o['created_at'] ?? "—").'</td></tr>
    <tr><td class="key">Updated</td><td>'.h($o['updated_at'] ?? "—").'</td></tr>
  </table></div>
';

// Patient Instructions Section
$patientInstructions = $o['additional_instructions'] ?? '';
$sec_instructions = '';
if (!empty($patientInstructions)) {
  $sec_instructions = '
  <h2>Patient Instructions</h2>
  <div class="box" style="background:#f0f9ff;border-color:#3b82f6">
    <div style="padding:8px;white-space:pre-wrap;">'.h($patientInstructions).'</div>
  </div>
';
}

// Secondary Dressing Section
$secondaryDressing = $o['secondary_dressing'] ?? '';
$sec_secondary = '';
if (!empty($secondaryDressing)) {
  $sec_secondary = '
  <h2>Secondary Dressing</h2>
  <div class="box"><table class="kv">
    <tr><td class="key">Item</td><td>'.h($secondaryDressing).'</td></tr>
  </table></div>
';
}

// Determine shipping details based on delivery_mode
$delivery_mode = strtolower($o['delivery_mode'] ?? 'patient');
$shipping_recipient = '';
$shipping_phone = '';
$shipping_address = '';

if ($delivery_mode === 'office') {
  // Ship to doctor's office - use practice name
  $shipping_recipient = h($o['practice_name'] ?? 'Doctor Office');
  $shipping_phone = '—';  // Practice phone not yet in database
  $shipping_address = 'Office Pickup';
} else {
  // Ship to patient (default)
  $shipping_recipient = h(($o['first_name'] ?? '').' '.($o['last_name'] ?? ''));
  $shipping_phone = h($o['patient_phone'] ?? '—');
  $shipping_address = h($o['address'] ?? '').', '.h($o['city'] ?? '').', '.h($o['state'] ?? '').' '.h($o['zip'] ?? '');
}

$sec_shipping = '
  <h2>Shipping</h2>
  <div class="box"><table class="kv">
    <tr><td class="key">Delivery To</td><td><strong>'.ucfirst($delivery_mode).'</strong></td></tr>
    <tr><td class="key">Recipient</td><td>'.$shipping_recipient.'</td></tr>
    '.($shipping_phone !== '—' ? '<tr><td class="key">Phone</td><td>'.$shipping_phone.'</td></tr>' : '').'
    <tr><td class="key">Address</td><td>'.$shipping_address.'</td></tr>
  </table></div>
';

$html = '
<!doctype html><html><head><meta charset="utf-8">
<title>Order #'.h($o['id']).' — CollagenDirect</title>
<style>
 body{ font-family:-apple-system, Segoe UI, Arial, sans-serif; font-size:12px; color:#111; }
 h1{ font-size:18px; margin:0 0 6px 0; }
 h2{ font-size:14px; margin:18px 0 6px 0; }
 .box{ border:1px solid #ccc; border-radius:8px; padding:10px; margin-bottom:10px; }
 table{ width:100%; border-collapse:collapse; }
 .kv td{ padding:4px 6px; vertical-align:top; }
 .kv td.key{ width:200px; color:#555; font-weight:500; }
 .footer{ margin-top:18px; color:#666; font-size:10px; text-align:center; border-top:1px solid #ddd; padding-top:10px; }
 @media print { .no-print{ display:none } }
</style></head><body>
  <h1>CollagenDirect — Physician Order</h1>
  <div style="color:#666;font-size:11px;margin-bottom:8px">Generated: '.h($today).' | Order #'.h($o['id']).'</div>
  '.$sec_patient.$sec_insurance.$sec_physician.$sec_esignature.$sec_wound.$sec_order.$sec_instructions.$sec_secondary.$sec_shipping.'
  <div class="footer">
    <strong>CONFIDENTIAL:</strong> This document contains Protected Health Information (PHI).
    Handle per HIPAA guidelines. Unauthorized disclosure is prohibited.<br>
    CollagenDirect | Medical Wound Care Products | © '.date('Y').'
  </div>
  <div class="no-print" style="margin-top:10px"><button onclick="window.print()">Print / Save as PDF</button></div>
</body></html>';

// Try Dompdf
$autoload = __DIR__.'/../admin/vendor/autoload.php';
if (is_file($autoload)) {
  try {
    require_once $autoload;
    $dompdf = new Dompdf\Dompdf(['isRemoteEnabled'=>true]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('Letter','portrait');
    $dompdf->render();
    $pdf = $dompdf->output();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="order-'.rawurlencode($o['id']).'.pdf"');
    header('Content-Length: '.strlen($pdf));
    echo $pdf; exit;
  } catch (Throwable $e) { /* fall through */ }
}
header('Content-Type: text/html; charset=utf-8'); echo $html;
