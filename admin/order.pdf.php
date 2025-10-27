<?php
// /public/admin/order.pdf.php — Compliant order packet (uses new order fields)
declare(strict_types=1);

require_once __DIR__ . '/db.php';
$auth = __DIR__ . '/auth.php';
if (is_file($auth) && function_exists('require_admin')) require_admin();

if (empty($_GET['csrf']) || $_GET['csrf'] !== ($_SESSION['csrf'] ?? '')) { http_response_code(403); echo "forbidden"; exit; }
$id = $_GET['id'] ?? '';
if ($id===''){ http_response_code(400); echo "missing id"; exit; }

try {
  $sql = "SELECT
            o.*,
            p.first_name, p.last_name, p.dob, p.address, p.city, p.state, p.zip,
            p.insurance_provider, p.insurance_member_id, p.insurance_group_id, p.insurance_payer_phone,
            u.first_name AS doc_first, u.last_name AS doc_last, u.license, u.license_state, u.npi,
            u.sign_name, u.sign_title, u.sign_date, u.practice_name
          FROM orders o
          LEFT JOIN patients p ON p.id=o.patient_id
          LEFT JOIN users u ON u.id=o.user_id
          WHERE o.id = ?";
  $st = $pdo->prepare($sql); $st->execute([$id]); $o = $st->fetch();
  if (!$o) { http_response_code(404); echo "order not found"; exit; }
} catch (Throwable $e) { http_response_code(500); echo "query_failed"; exit; }

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

$sec_physician = '
  <h2>Ordering Physician</h2>
  <div class="box"><table class="kv">
    <tr><td class="key">Name</td><td>'.h(($o['doc_first']??"")." ".($o['doc_last']??"")).'</td></tr>
    <tr><td class="key">Practice</td><td>'.h($o['practice_name'] ?? "—").'</td></tr>
    <tr><td class="key">NPI</td><td>'.h($o['npi'] ?? "—").'</td></tr>
    <tr><td class="key">License</td><td>'.h($o['license'] ?? "—").' ('.h($o['license_state'] ?? "—").')</td></tr>
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

$sec_wound = '
  <h2>Wound Details</h2>
  <div class="box"><table class="kv">
    <tr><td class="key">Location</td><td>'.h($o['wound_location'] ?? "—").'</td></tr>
    <tr><td class="key">Laterality</td><td>'.h($o['wound_laterality'] ?? "—").'</td></tr>
    <tr><td class="key">Length (cm)</td><td>'.h($o['wound_length_cm'] ?? "—").'</td></tr>
    <tr><td class="key">Width (cm)</td><td>'.h($o['wound_width_cm'] ?? "—").'</td></tr>
    <tr><td class="key">Depth (cm)</td><td>'.h($o['wound_depth_cm'] ?? "—").'</td></tr>
    <tr><td class="key">Type</td><td>'.h($o['wound_type'] ?? "—").'</td></tr>
    <tr><td class="key">Stage</td><td>'.h($o['wound_stage'] ?? "—").'</td></tr>
    <tr><td class="key">ICD-10 Primary</td><td>'.h($o['icd10_primary'] ?? "—").'</td></tr>
    <tr><td class="key">ICD-10 Secondary</td><td>'.h($o['icd10_secondary'] ?? "—").'</td></tr>
  </table></div>
';

$sec_order = '
  <h2>Order Details</h2>
  <div class="box"><table class="kv">
    <tr><td class="key">Order #</td><td>'.h($o['id']).'</td></tr>
    <tr><td class="key">Product</td><td>'.h($o['product'] ?? "").'</td></tr>
    <tr><td class="key">Change Frequency</td><td>'.h((string)$fpw).' × /week</td></tr>
    <tr><td class="key">Qty per Change</td><td>'.h((string)$qty).'</td></tr>
    <tr><td class="key">Duration (days)</td><td>'.h((string)max(0,(int)($o['duration_days'] ?? 0))).'</td></tr>
    <tr><td class="key">Refills Allowed</td><td>'.h((string)$refills).'</td></tr>
    <tr><td class="key">Total Authorized Units</td><td>'.h((string)$units_total).'</td></tr>
    <tr><td class="key">Delivery Mode</td><td>'.h($o['delivery_mode'] ?? "—").'</td></tr>
    <tr><td class="key">Status</td><td>'.h($o['status'] ?? "—").'</td></tr>
    <tr><td class="key">Created</td><td>'.h($o['created_at'] ?? "—").'</td></tr>
    <tr><td class="key">Updated</td><td>'.h($o['updated_at'] ?? "—").'</td></tr>
  </table></div>
';

$sec_shipping = '
  <h2>Shipping</h2>
  <div class="box"><table class="kv">
    <tr><td class="key">Recipient</td><td>'.h($o['shipping_name'] ?? "—").' • '.h($o['shipping_phone'] ?? "").'</td></tr>
    <tr><td class="key">Address</td><td>'.h($o['shipping_address'] ?? "").', '.h($o['shipping_city'] ?? "").', '.h($o['shipping_state'] ?? "").' '.h($o['shipping_zip'] ?? "").'</td></tr>
    <tr><td class="key">Tracking</td><td>'.h(($o['rx_note_mime'] ?? "—")).' '.h(($o['rx_note_name'] ?? "")).'</td></tr>
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
  '.$sec_patient.$sec_insurance.$sec_physician.$sec_esignature.$sec_wound.$sec_order.$sec_shipping.'
  <div class="footer">
    <strong>CONFIDENTIAL:</strong> This document contains Protected Health Information (PHI).
    Handle per HIPAA guidelines. Unauthorized disclosure is prohibited.<br>
    CollagenDirect | Medical Wound Care Products | © '.date('Y').'
  </div>
  <div class="no-print" style="margin-top:10px"><button onclick="window.print()">Print / Save as PDF</button></div>
</body></html>';
// Try Dompdf
$autoload = __DIR__.'/vendor/autoload.php';
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
