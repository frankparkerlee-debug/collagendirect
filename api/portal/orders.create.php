<?php
// /public/api/portal/orders.create.php
declare(strict_types=1);
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}
$uid = $_SESSION['user_id'];

/* -------------------- Helpers -------------------- */
function guid(): string { return bin2hex(random_bytes(16)); }
function safe(?string $s): ?string {
  if ($s === null) return null;
  $s = trim($s);
  return $s === '' ? null : $s;
}
/** Normalize a filesystem directory from a web-root relative path */
function dir_from_docroot(string $subdir): string {
  // Check for persistent disk first (Render)
  if (is_dir('/var/data/uploads')) {
    // Use persistent disk - subdir format: /uploads/notes -> /var/data/uploads/notes
    $subdir = '/' . ltrim($subdir, '/'); // ensure single leading slash
    return '/var/data' . $subdir;
  }

  // Fallback to local document root (development)
  $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
  if ($root === '') { // Fallback: apache/nginx misconfig
    $root = dirname(__DIR__, 2); // /public
  }
  $subdir = '/' . ltrim($subdir, '/'); // ensure single leading slash
  return $root . $subdir;
}
/** Save a single uploaded file into /public/uploads/{ids|insurance|notes} */
function save_upload(string $field, string $subdir): array {
  if (empty($_FILES[$field]) || empty($_FILES[$field]['tmp_name'])) return [null,null];
  if (!is_uploaded_file($_FILES[$field]['tmp_name'])) return [null,null];

  $tmp  = $_FILES[$field]['tmp_name'];
  // Trust finfo for MIME (more reliable than $_FILES['type'])
  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = $fi->file($tmp) ?: 'application/octet-stream';

  // Allow-list
  $allowed = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'image/heic'      => 'heic',
    'text/plain'      => 'txt'
  ];
  if (!isset($allowed[$mime])) throw new RuntimeException("unsupported_file_type_$field");

  // Clean original name, prepend random token
  $orig = $_FILES[$field]['name'] ?? 'file';
  $clean = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', $orig);
  $name  = bin2hex(random_bytes(8)) . '-' . $clean;

  $root = dir_from_docroot($subdir);
  if (!is_dir($root)) { @mkdir($root, 0775, true); }
  if (!is_dir($root) || !is_writable($root)) throw new RuntimeException("upload_dir_unwritable_$field");

  $dest = rtrim($root, '/') . '/' . $name;
  if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException("failed_to_move_upload_$field");

  $rel = rtrim($subdir, '/') . '/' . $name; // web path
  return [$rel, $mime];
}

/** Flush response to client and continue (if FastCGI) */
function respond_now(array $payload): void {
  // Make sure buffering doesn't block flush
  if (function_exists('fastcgi_finish_request')) {
    echo json_encode($payload);
    // End user request immediately; PHP continues running below
    fastcgi_finish_request();
  } else {
    // Best-effort flush on non-FPM SAPIs
    echo json_encode($payload);
    @ob_flush(); @flush();
  }
}

/* -------------------- Main -------------------- */
try {
  // 1) Required: e-sign confirmation
  if (($_POST['esign_confirm'] ?? '') !== '1') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'esign_required']); exit;
  }

  // 2) Resolve product
  $product_id = (int)($_POST['product_id'] ?? 0);
  if ($product_id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_product']); exit; }
  $ps = $pdo->prepare("SELECT id, name, price_admin, cpt_code FROM products WHERE id=? AND active=TRUE");
  $ps->execute([$product_id]);
  $prod = $ps->fetch(PDO::FETCH_ASSOC);
  if (!$prod) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'invalid_product']); exit; }

  // 3) Patient: existing or create
  $patient_id = safe($_POST['patient_id'] ?? null);
  $created_new_patient = false;

  if ($patient_id) {
    $chk = $pdo->prepare("SELECT id FROM patients WHERE id=? AND user_id=?");
    $chk->execute([$patient_id, $uid]);
    if (!$chk->fetch()) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'patient_not_found']); exit; }
  } else {
    $patient_id = guid();
    $sql = "INSERT INTO patients
      (id, user_id, first_name, last_name, dob, mrn, phone, email, address, city, state, zip,
       insurance_provider, insurance_member_id, insurance_group_id, insurance_payer_phone,
       created_at, updated_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())";
    $pdo->prepare($sql)->execute([
      $patient_id, $uid,
      safe($_POST['first_name'] ?? null),
      safe($_POST['last_name'] ?? null),
      safe($_POST['dob'] ?? null),
      safe($_POST['mrn'] ?? null),
      safe($_POST['phone'] ?? null),
      safe($_POST['email'] ?? null),
      safe($_POST['address'] ?? null),
      safe($_POST['city'] ?? null),
      safe($_POST['state'] ?? null),
      safe($_POST['zip'] ?? null),
      safe($_POST['insurance_provider'] ?? null),
      safe($_POST['insurance_member_id'] ?? null),
      safe($_POST['insurance_group_id'] ?? null),
      safe($_POST['insurance_payer_phone'] ?? null),
    ]);
    $created_new_patient = true;
  }

  // 4) Delivery details
  $shipping_name    = safe($_POST['shipping_name'] ?? null);
  $shipping_phone   = safe($_POST['shipping_phone'] ?? null);
  $shipping_address = safe($_POST['shipping_address'] ?? null);
  $shipping_city    = safe($_POST['shipping_city'] ?? null);
  $shipping_state   = safe($_POST['shipping_state'] ?? null);
  $shipping_zip     = safe($_POST['shipping_zip'] ?? null);

  // 5) Order meta
  $order_id       = guid();
  $status         = 'submitted';
  $delivery_mode  = safe($_POST['delivery_mode'] ?? 'standard');
  $frequency      = safe($_POST['frequency'] ?? null);
  $wound_location = safe($_POST['wound_location'] ?? null);
  $wound_laterality = safe($_POST['wound_laterality'] ?? null);
  $wound_notes    = safe($_POST['wound_notes'] ?? null);
  $payment_type   = safe($_POST['payment_type'] ?? 'insurance');
  $prior_auth     = safe($_POST['prior_auth'] ?? null);

  // Signer
  $sign_name  = safe($_POST['sign_name']  ?? null);
  $sign_title = safe($_POST['sign_title'] ?? null);
  if (!$sign_name || !$sign_title) {
    $u = $pdo->prepare("SELECT first_name, last_name, sign_title FROM users WHERE id=?");
    $u->execute([$uid]);
    $ud = $u->fetch(PDO::FETCH_ASSOC);
    if (!$sign_name && $ud)  $sign_name  = trim(($ud['first_name'] ?? '').' '.($ud['last_name'] ?? '')) ?: 'Physician';
    if (!$sign_title && $ud) $sign_title = $ud['sign_title'] ?? 'Physician';
  }

  // 6) Insert order FIRST (no file I/O yet)
  // Note: Orders start in 'pending_admin_review' status for AI-assisted approval workflow
  $sql = "INSERT INTO orders
    (id, patient_id, user_id, product, product_id, product_price, cpt, status, frequency, delivery_mode,
     shipments_remaining, created_at, updated_at,
     insurer_name, member_id, group_id, payer_phone, prior_auth, payment_type,
     wound_location, wound_laterality, wound_notes,
     shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip,
     rx_note_path, rx_note_mime, ins_card_path, ins_card_mime, id_card_path, id_card_mime,
     e_sign_user_id, e_sign_name, e_sign_title, e_sign_at, e_sign_ip,
     review_status)
    VALUES
    (?,?,?,?,?,?,?,?,?,?,0,NOW(),NOW(),
     ?,?,?,?, ?,?,?,
     ?,?,?,
     ?,?,?,?,?,?,
     NULL,NULL,NULL,NULL,NULL,NULL,
     ?,?,?,?,?,
     'pending_admin_review')";

  $pdo->prepare($sql)->execute([
    $order_id, $patient_id, $uid,
    $prod['name'], $prod['id'], $prod['price_admin'], $prod['cpt_code'],
    $status, $frequency, $delivery_mode,
    // insurance & payment
    safe($_POST['insurance_provider'] ?? null),
    safe($_POST['insurance_member_id'] ?? null),
    safe($_POST['insurance_group_id'] ?? null),
    safe($_POST['insurance_payer_phone'] ?? null),
    $prior_auth, $payment_type,
    // wound
    $wound_location, $wound_laterality, $wound_notes,
    // shipping
    $shipping_name, $shipping_phone, $shipping_address, $shipping_city, $shipping_state, $shipping_zip,
    // e-sign
    $uid, $sign_name, $sign_title, date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'] ?? null
  ]);

  // 7) Respond to client ASAP; then continue with uploads/updates
  respond_now(['ok'=>true,'data'=>['order_id'=>$order_id,'patient_id'=>$patient_id]]);

  /* -------------------- POST-RESPONSE: uploads & attachments -------------------- */
  // Files (optional). Keep your existing subdirectories.
  // These are WEB paths; filesystem destination is resolved from DOCUMENT_ROOT.
  try {
    [$rx_path,  $rx_mime]  = save_upload('rx_note',  '/uploads/notes');
    [$ins_path, $ins_mime] = save_upload('ins_card','/uploads/insurance');
    [$id_path,  $id_mime]  = save_upload('id_card',  '/uploads/ids');

    // Validate insurance docs if insurance payment and files were not uploaded
    if ($payment_type === 'insurance') {
      // If the physician didn't upload in this request, we leave them NULL.
      // Admin portal can enforce before shipping; if you prefer hard-blocking,
      // move this check BEFORE respond_now().
      // Example (strict):
      // if (!$ins_path || !$id_path) throw new RuntimeException('missing_insurance_docs');
    }

    // Update order with file columns if present
    if ($rx_path || $ins_path || $id_path) {
      $sets=[]; $params=[];
      if ($rx_path)  { $sets[]='rx_note_path=?';  $params[]=$rx_path;  $sets[]='rx_note_mime=?';  $params[]=$rx_mime; }
      if ($ins_path) { $sets[]='ins_card_path=?'; $params[]=$ins_path; $sets[]='ins_card_mime=?'; $params[]=$ins_mime; }
      if ($id_path)  { $sets[]='id_card_path=?';  $params[]=$id_path;  $sets[]='id_card_mime=?';  $params[]=$id_mime; }
      $params[] = $order_id; $params[] = $uid;
      $pdo->prepare("UPDATE orders SET ".implode(', ',$sets).", updated_at=NOW() WHERE id=? AND user_id=?")->execute($params);
    }

    // Mirror to patient profile (if requested or for newly created patients)
    $attach_to_patient = ($_POST['attach_to_patient'] ?? '1') === '1' || $created_new_patient;
    if ($attach_to_patient && ($rx_path || $ins_path || $id_path)) {
      $sets=[]; $params=[];
      if ($rx_path)  { $sets[]='note_path=?';     $params[]=$rx_path;  $sets[]='note_mime=?';     $params[]=$rx_mime; }
      if ($ins_path) { $sets[]='ins_card_path=?'; $params[]=$ins_path; $sets[]='ins_card_mime=?'; $params[]=$ins_mime; }
      if ($id_path)  { $sets[]='id_card_path=?';  $params[]=$id_path;  $sets[]='id_card_mime=?';  $params[]=$id_mime; }
      if ($sets) {
        $params[]=$patient_id; $params[]=$uid;
        $pdo->prepare("UPDATE patients SET ".implode(', ',$sets).", updated_at=NOW() WHERE id=? AND user_id=?")->execute($params);
      }
    }
  } catch (Throwable $upErr) {
    // Swallow upload errors post-response to avoid breaking the already-submitted order.
    // Consider logging to a file if desired:
    // error_log('[orders.create upload] '.$upErr->getMessage());
  }

  // Send email notifications
  try {
    require_once __DIR__ . '/../lib/email_notifications.php';

    // Get physician and practice info for emails
    $physicianStmt = $pdo->prepare("
      SELECT first_name, last_name, email, practice_name, npi
      FROM users
      WHERE id = ?
    ");
    $physicianStmt->execute([$uid]);
    $physician = $physicianStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Get patient info
    $patientStmt = $pdo->prepare("
      SELECT first_name, last_name, email, dob, address, city, state, zip, insurance_provider
      FROM patients
      WHERE id = ? AND user_id = ?
    ");
    $patientStmt->execute([$patient_id, $uid]);
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Get product info
    $productStmt = $pdo->prepare("
      SELECT name, size FROM products WHERE id = ?
    ");
    $productStmt->execute([$product_id]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $physicianName = trim(($physician['first_name'] ?? '') . ' ' . ($physician['last_name'] ?? ''));
    $patientName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
    $patientAddress = trim(($patient['address'] ?? '') . ', ' . ($patient['city'] ?? '') . ' ' . ($patient['state'] ?? '') . ' ' . ($patient['zip'] ?? ''));
    $productName = trim(($product['name'] ?? '') . ' ' . ($product['size'] ?? ''));

    // 1. Send order received email to patient
    if (!empty($patient['email'])) {
      send_order_received_email([
        'patient_email' => $patient['email'],
        'patient_name' => $patientName,
        'order_id' => $order_id,
        'order_date' => date('m/d/Y'),
        'physician_name' => $physicianName,
        'practice_name' => $physician['practice_name'] ?? '',
        'product_name' => $productName,
        'quantity' => '1'
      ]);
    }

    // 2. Send new order notification to manufacturer
    send_manufacturer_order_email([
      'manufacturer_email' => 'orders@collagendirect.health', // Configure as needed
      'order_id' => $order_id,
      'order_date' => date('m/d/Y'),
      'patient_name' => $patientName,
      'patient_dob' => $patient['dob'] ?? '',
      'patient_address' => $patientAddress,
      'insurance_provider' => $patient['insurance_provider'] ?? '',
      'physician_name' => $physicianName,
      'physician_npi' => $physician['npi'] ?? '',
      'practice_name' => $physician['practice_name'] ?? '',
      'product_name' => $productName,
      'quantity' => '1',
      'frequency' => $frequency ?? '',
      'duration_days' => ''
    ]);

  } catch (Throwable $notifyErr) {
    error_log('[orders.create email notification] ' . $notifyErr->getMessage());
  }

  // Done.
  exit;

} catch (Throwable $e) {
  // Avoid exposing internals
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}
