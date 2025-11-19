<?php
/**
 * Create Order Group (Multi-Product Orders)
 *
 * Handles creating orders with multiple products for treating a single wound.
 * Backward compatible: Falls back to single order if only one product provided.
 */
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

function dir_from_docroot(string $subdir): string {
  if (is_dir('/opt/render/project/src/uploads')) {
    $subdir = '/' . ltrim($subdir, '/');
    return '/var/www/html' . $subdir;
  }
  $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
  if ($root === '') $root = dirname(__DIR__, 2);
  $subdir = '/' . ltrim($subdir, '/');
  return $root . $subdir;
}

function save_upload(string $field, string $subdir): array {
  if (empty($_FILES[$field]) || empty($_FILES[$field]['tmp_name'])) return [null,null];
  if (!is_uploaded_file($_FILES[$field]['tmp_name'])) return [null,null];

  $tmp  = $_FILES[$field]['tmp_name'];
  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = $fi->file($tmp) ?: 'application/octet-stream';

  $allowed = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'image/heic'      => 'heic',
    'text/plain'      => 'txt'
  ];
  if (!isset($allowed[$mime])) throw new RuntimeException("unsupported_file_type_$field");

  $orig = $_FILES[$field]['name'] ?? 'file';
  $clean = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', $orig);
  $name  = bin2hex(random_bytes(8)) . '-' . $clean;

  $root = dir_from_docroot($subdir);
  if (!is_dir($root)) { @mkdir($root, 0775, true); }
  if (!is_dir($root) || !is_writable($root)) throw new RuntimeException("upload_dir_unwritable_$field");

  $dest = rtrim($root, '/') . '/' . $name;
  if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException("failed_to_move_upload_$field");

  $rel = rtrim($subdir, '/') . '/' . $name;
  return [$rel, $mime];
}

function respond_now(array $payload): void {
  if (function_exists('fastcgi_finish_request')) {
    echo json_encode($payload);
    fastcgi_finish_request();
  } else {
    echo json_encode($payload);
    @ob_flush(); @flush();
  }
}

/* -------------------- Main -------------------- */
try {
  // 1) Check if draft
  $is_draft = isset($_POST['save_as_draft']) && $_POST['save_as_draft'] === '1';

  // Required: e-sign confirmation (except for drafts)
  if (!$is_draft && ($_POST['esign_confirm'] ?? '') !== '1') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'esign_required']); exit;
  }

  // 2) Parse products array
  $products_json = $_POST['products'] ?? null;
  if (!$products_json) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'missing_products']); exit;
  }

  $products = json_decode($products_json, true);
  if (!is_array($products) || empty($products)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'invalid_products']); exit;
  }

  // Validate products exist and are active
  $product_ids = array_column($products, 'product_id');
  $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
  $ps = $pdo->prepare("SELECT id, name, price_admin, cpt_code FROM products WHERE id IN ($placeholders) AND active=TRUE");
  $ps->execute($product_ids);
  $valid_products = $ps->fetchAll(PDO::FETCH_ASSOC);

  if (count($valid_products) !== count($product_ids)) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'invalid_product']); exit;
  }

  // Index products by ID for quick lookup
  $products_by_id = [];
  foreach ($valid_products as $p) {
    $products_by_id[$p['id']] = $p;
  }

  // 3) Patient: existing or create
  $patient_id = safe($_POST['patient_id'] ?? null);
  $created_new_patient = false;

  if ($patient_id) {
    $chk = $pdo->prepare("SELECT id FROM patients WHERE id=? AND user_id=?");
    $chk->execute([$patient_id, $uid]);
    if (!$chk->fetch()) {
      http_response_code(404);
      echo json_encode(['ok'=>false,'error'=>'patient_not_found']); exit;
    }
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

  // 4) Signer info
  $sign_name  = safe($_POST['sign_name']  ?? null);
  $sign_title = safe($_POST['sign_title'] ?? null);
  if (!$sign_name || !$sign_title) {
    $u = $pdo->prepare("SELECT first_name, last_name, sign_title FROM users WHERE id=?");
    $u->execute([$uid]);
    $ud = $u->fetch(PDO::FETCH_ASSOC);
    if (!$sign_name && $ud)  $sign_name  = trim(($ud['first_name'] ?? '').' '.($ud['last_name'] ?? '')) ?: 'Physician';
    if (!$sign_title && $ud) $sign_title = $ud['sign_title'] ?? 'Physician';
  }

  // 5) Determine if this is a multi-product order
  $is_multi_product = count($products) > 1;
  $order_group_id = null;

  $pdo->beginTransaction();

  if ($is_multi_product) {
    // Create order group
    $order_group_id = guid();

    $group_sql = "INSERT INTO order_groups
      (id, user_id, patient_id,
       visit_note_path, visit_note_mime, baseline_wound_photo_path, baseline_wound_photo_mime,
       wound_location, wound_laterality, wound_type, wound_stage,
       wound_length_cm, wound_width_cm, wound_depth_cm, wound_notes,
       icd10_primary, icd10_secondary, last_eval_date, start_date,
       shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip,
       insurer_name, member_id, group_id, payer_phone, prior_auth, payment_type,
       status, sign_name, sign_title, signed_at, signed_ip, additional_instructions,
       created_at, updated_at)
      VALUES
      (?,?,?, NULL,NULL,NULL,NULL, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?, ?, NOW(), NOW())";

    $pdo->prepare($group_sql)->execute([
      $order_group_id, $uid, $patient_id,
      safe($_POST['wound_location'] ?? null),
      safe($_POST['wound_laterality'] ?? null),
      safe($_POST['wound_type'] ?? null),
      safe($_POST['wound_stage'] ?? null),
      safe($_POST['wound_length_cm'] ?? null) ? (float)$_POST['wound_length_cm'] : null,
      safe($_POST['wound_width_cm'] ?? null) ? (float)$_POST['wound_width_cm'] : null,
      safe($_POST['wound_depth_cm'] ?? null) ? (float)$_POST['wound_depth_cm'] : null,
      safe($_POST['wound_notes'] ?? null),
      safe($_POST['icd10_primary'] ?? null),
      safe($_POST['icd10_secondary'] ?? null),
      safe($_POST['last_eval_date'] ?? null),
      safe($_POST['start_date'] ?? null),
      safe($_POST['shipping_name'] ?? null),
      safe($_POST['shipping_phone'] ?? null),
      safe($_POST['shipping_address'] ?? null),
      safe($_POST['shipping_city'] ?? null),
      safe($_POST['shipping_state'] ?? null),
      safe($_POST['shipping_zip'] ?? null),
      safe($_POST['insurance_provider'] ?? null),
      safe($_POST['insurance_member_id'] ?? null),
      safe($_POST['insurance_group_id'] ?? null),
      safe($_POST['insurance_payer_phone'] ?? null),
      safe($_POST['prior_auth'] ?? null),
      safe($_POST['payment_type'] ?? 'insurance'),
      $is_draft ? 'draft' : 'submitted',
      $sign_name, $sign_title,
      $is_draft ? null : date('Y-m-d H:i:s'),
      $is_draft ? null : ($_SERVER['REMOTE_ADDR'] ?? null),
      safe($_POST['additional_instructions'] ?? null)
    ]);
  }

  // 6) Create individual order records for each product
  $order_ids = [];
  $delivery_mode = (safe($_POST['delivery_to'] ?? 'patient') === 'office') ? 'office' : 'patient';
  $status = $is_draft ? 'draft' : 'submitted';
  $review_status = $is_draft ? 'draft' : 'pending_admin_review';

  foreach ($products as $prod_data) {
    $product_id = (int)$prod_data['product_id'];
    $quantity = (int)($prod_data['quantity'] ?? 1);

    $prod = $products_by_id[$product_id];
    $order_id = guid();
    $order_ids[] = $order_id;

    $order_sql = "INSERT INTO orders
      (id, patient_id, user_id, product, product_id, product_price, cpt, status, frequency, delivery_mode,
       order_group_id, shipments_remaining, created_at, updated_at,
       insurer_name, member_id, group_id, payer_phone, prior_auth, payment_type,
       wound_location, wound_laterality, wound_notes,
       last_eval_date, start_date, qty_per_change, duration_days,
       additional_instructions, shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip,
       e_sign_user_id, e_sign_name, e_sign_title, e_sign_at, e_sign_ip, review_status)
      VALUES
      (?,?,?,?,?,?,?,?,?,?, ?,0,NOW(),NOW(), ?,?,?,?,?,?, ?,?,?, ?,?,?,?, ?, ?,?,?,?,?,?, ?,?,?,?,?, ?)";

    $pdo->prepare($order_sql)->execute([
      $order_id, $patient_id, $uid,
      $prod['name'], $prod['id'], $prod['price_admin'], $prod['cpt_code'],
      $status, safe($_POST['frequency_per_week'] ?? null), $delivery_mode,
      $order_group_id,
      // Insurance
      safe($_POST['insurance_provider'] ?? null),
      safe($_POST['insurance_member_id'] ?? null),
      safe($_POST['insurance_group_id'] ?? null),
      safe($_POST['insurance_payer_phone'] ?? null),
      safe($_POST['prior_auth'] ?? null),
      safe($_POST['payment_type'] ?? 'insurance'),
      // Wound (for backward compatibility - stored on both group and individual orders)
      safe($_POST['wound_location'] ?? null),
      safe($_POST['wound_laterality'] ?? null),
      safe($_POST['wound_notes'] ?? null),
      // Order details
      safe($_POST['last_eval_date'] ?? null),
      safe($_POST['start_date'] ?? null),
      $quantity,
      safe($_POST['duration_days'] ?? null),
      safe($_POST['additional_instructions'] ?? null),
      // Shipping
      safe($_POST['shipping_name'] ?? null),
      safe($_POST['shipping_phone'] ?? null),
      safe($_POST['shipping_address'] ?? null),
      safe($_POST['shipping_city'] ?? null),
      safe($_POST['shipping_state'] ?? null),
      safe($_POST['shipping_zip'] ?? null),
      // E-sign
      $uid, $sign_name, $sign_title,
      $is_draft ? null : date('Y-m-d H:i:s'),
      $is_draft ? null : ($_SERVER['REMOTE_ADDR'] ?? null),
      $review_status
    ]);
  }

  $pdo->commit();

  // 7) Respond to client ASAP
  respond_now([
    'ok' => true,
    'data' => [
      'order_group_id' => $order_group_id,
      'order_ids' => $order_ids,
      'patient_id' => $patient_id,
      'is_multi_product' => $is_multi_product
    ]
  ]);

  /* -------------------- POST-RESPONSE: uploads & attachments -------------------- */
  try {
    // Upload files (visit note and baseline photo go to order group if multi-product)
    [$rx_path,  $rx_mime]  = save_upload('file_rx_note',  '/uploads/notes');
    if (!$rx_path) [$rx_path,  $rx_mime]  = save_upload('rx_note',  '/uploads/notes');
    [$ins_path, $ins_mime] = save_upload('ins_card','/uploads/insurance');
    [$id_path,  $id_mime]  = save_upload('id_card',  '/uploads/ids');
    [$wound_photo_path, $wound_photo_mime] = save_upload('baseline_wound_photo', '/uploads/wound_photos');

    if ($is_multi_product && $order_group_id) {
      // Update order group with visit note and baseline photo
      if ($rx_path || $wound_photo_path) {
        $sets=[]; $params=[];
        if ($rx_path)  {
          $sets[]='visit_note_path=?';
          $params[]=$rx_path;
          $sets[]='visit_note_mime=?';
          $params[]=$rx_mime;
        }
        if ($wound_photo_path) {
          $sets[]='baseline_wound_photo_path=?';
          $params[]=$wound_photo_path;
          $sets[]='baseline_wound_photo_mime=?';
          $params[]=$wound_photo_mime;
        }
        $params[] = $order_group_id;
        $pdo->prepare("UPDATE order_groups SET ".implode(', ',$sets).", updated_at=NOW() WHERE id=?")->execute($params);
      }
    } else {
      // Single product: update individual order
      if ($rx_path || $ins_path || $id_path || $wound_photo_path) {
        $sets=[]; $params=[];
        if ($rx_path)  { $sets[]='rx_note_path=?';  $params[]=$rx_path;  $sets[]='rx_note_mime=?';  $params[]=$rx_mime; }
        if ($ins_path) { $sets[]='ins_card_path=?'; $params[]=$ins_path; $sets[]='ins_card_mime=?'; $params[]=$ins_mime; }
        if ($id_path)  { $sets[]='id_card_path=?';  $params[]=$id_path;  $sets[]='id_card_mime=?';  $params[]=$id_mime; }
        if ($wound_photo_path) { $sets[]='baseline_wound_photo_path=?'; $params[]=$wound_photo_path; $sets[]='baseline_wound_photo_mime=?'; $params[]=$wound_photo_mime; }
        $params[] = $order_ids[0]; $params[] = $uid;
        $pdo->prepare("UPDATE orders SET ".implode(', ',$sets).", updated_at=NOW() WHERE id=? AND user_id=?")->execute($params);
      }
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
    // Swallow upload errors post-response
    error_log('[order-group.create upload] '.$upErr->getMessage());
  }

  // TODO: Send email notifications

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
  exit;
}
