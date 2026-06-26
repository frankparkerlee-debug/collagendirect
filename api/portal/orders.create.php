<?php
// /public/api/portal/orders.create.php
declare(strict_types=1);
require __DIR__ . '/../db.php';

// Must set headers BEFORE any output to prevent "headers already sent" errors
header('Content-Type: application/json');

// Prevent any warnings/notices from breaking JSON response
ini_set('display_errors', '0');

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
  if (is_dir('/opt/render/project/src/uploads')) {
    // Use persistent disk - subdir format: /uploads/notes -> /opt/render/project/src/uploads/notes
    $subdir = '/' . ltrim($subdir, '/'); // ensure single leading slash
    // Remove /uploads prefix if present, then append to persistent disk path
    $subdir = preg_replace('#^/uploads/#', '/', $subdir);
    return '/opt/render/project/src/uploads' . $subdir;
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
  error_log("[save_upload] Starting upload for field: $field");

  if (!isset($_FILES[$field]) || empty($_FILES[$field])) {
    error_log("[save_upload] $_FILES[$field] is not set or empty");
    return [null,null];
  }

  if (empty($_FILES[$field]['tmp_name'])) {
    error_log("[save_upload] $_FILES[$field]['tmp_name'] is empty. Error code: " . ($_FILES[$field]['error'] ?? 'unknown'));
    return [null,null];
  }

  $tmp  = $_FILES[$field]['tmp_name'];
  error_log("[save_upload] tmp_name: $tmp, exists: " . (file_exists($tmp) ? 'yes' : 'no'));

  if (!is_uploaded_file($tmp)) {
    error_log("[save_upload] is_uploaded_file() returned false for $tmp");
    return [null,null];
  }

  // Trust finfo for MIME (more reliable than $_FILES['type'])
  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = $fi->file($tmp) ?: 'application/octet-stream';
  error_log("[save_upload] Detected MIME type: $mime");

  // Allow-list
  $allowed = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'image/heic'      => 'heic',
    'text/plain'      => 'txt'
  ];
  if (!isset($allowed[$mime])) {
    error_log("[save_upload] Unsupported MIME type: $mime");
    throw new RuntimeException("unsupported_file_type_$field: $mime");
  }

  // Clean original name, prepend random token
  $orig = $_FILES[$field]['name'] ?? 'file';
  $clean = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', $orig);
  $name  = bin2hex(random_bytes(8)) . '-' . $clean;

  $root = dir_from_docroot($subdir);
  error_log("[save_upload] Target directory: $root");

  if (!is_dir($root)) {
    error_log("[save_upload] Directory doesn't exist, creating...");
    @mkdir($root, 0775, true);
  }

  if (!is_dir($root)) {
    error_log("[save_upload] Failed to create directory: $root");
    throw new RuntimeException("upload_dir_not_created_$field");
  }

  if (!is_writable($root)) {
    error_log("[save_upload] Directory not writable: $root");
    throw new RuntimeException("upload_dir_unwritable_$field");
  }

  $dest = rtrim($root, '/') . '/' . $name;
  error_log("[save_upload] Moving file to: $dest");

  if (!move_uploaded_file($tmp, $dest)) {
    error_log("[save_upload] move_uploaded_file() failed from $tmp to $dest");
    throw new RuntimeException("failed_to_move_upload_$field");
  }

  error_log("[save_upload] Upload successful: $dest");
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
  // 1) Check if this is a draft (drafts don't require e-signature)
  $is_draft = isset($_POST['save_as_draft']) && $_POST['save_as_draft'] === '1';

  // Required: e-sign confirmation (except for drafts)
  if (!$is_draft && ($_POST['esign_confirm'] ?? '') !== '1') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'esign_required']); exit;
  }

  // 2) Resolve product (only if using old single-product format, not wounds_data)
  $product_id = (int)($_POST['product_id'] ?? 0);
  $prod = null;

  // Skip product validation if using wounds_data (multi-wound format)
  $has_wounds_data = !empty($_POST['wounds_data']);

  if (!$has_wounds_data) {
    // Old format: requires product_id
    if ($product_id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_product']); exit; }
    $ps = $pdo->prepare("SELECT id, name, price_admin, cpt_code FROM products WHERE id=? AND active=TRUE");
    $ps->execute([$product_id]);
    $prod = $ps->fetch(PDO::FETCH_ASSOC);
    if (!$prod) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'invalid_product']); exit; }
  }

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
  // Check if this is a HealKit order
  $order_type = safe($_POST['order_type'] ?? null);
  $is_healkit = ($order_type === 'healkit');
  $billed_by = $is_healkit ? 'healkit' : null;

  // Frontend sends 'delivery_to' (patient/office), map to delivery_mode.
  // HealKit now sends 'office' (with shipping_* for the office or a custom location).
  $delivery_to    = safe($_POST['delivery_to'] ?? 'patient');
  $delivery_mode  = ($delivery_to === 'office') ? 'office' : 'patient';
  $frequency      = safe($_POST['frequency_per_week'] ?? null);
  $payment_type   = safe($_POST['payment_type'] ?? 'insurance');
  $prior_auth     = safe($_POST['prior_auth'] ?? null);

  // New order form fields
  $last_eval_date = safe($_POST['last_eval_date'] ?? null);
  $start_date     = safe($_POST['start_date'] ?? null);
  $qty_per_change = safe($_POST['qty_per_change'] ?? null);
  $duration_days  = safe($_POST['duration_days'] ?? null);
  $additional_instructions = safe($_POST['additional_instructions'] ?? null);
  $secondary_dressing = safe($_POST['secondary_dressing'] ?? null);
  $notes_text     = safe($_POST['notes_text'] ?? null);

  // Parse wounds_data JSON (multi-wound support)
  $wounds_data = null;
  $wound_location = null;
  $wound_laterality = null;
  $wound_notes    = null;
  $exudate_level  = null;

  if (!empty($_POST['wounds_data'])) {
    $wounds_json = $_POST['wounds_data'];
    $wounds_array = json_decode($wounds_json, true);

    if (is_array($wounds_array) && count($wounds_array) > 0) {
      // Store full wounds data as JSONB
      $wounds_data = $wounds_json;

      // For backward compatibility, populate legacy columns from first wound
      $first_wound = $wounds_array[0];
      $wound_location = safe($first_wound['location'] ?? null);
      $wound_laterality = safe($first_wound['laterality'] ?? null);
      $wound_notes = safe($first_wound['notes'] ?? null);
      $exudate_level = safe($first_wound['exudate_level'] ?? null);

      // Extract product info from first wound's primary product
      if (!$prod && !empty($first_wound['product_id'])) {
        $prod = [
          'id' => $first_wound['product_id'],
          'name' => $first_wound['product_name'] ?? '',
          'cpt_code' => $first_wound['product_cpt'] ?? null,
          'price_admin' => $first_wound['product_price'] ?? 0
        ];
        $product_id = (int)$first_wound['product_id'];

        // Also extract order-specific fields from first wound (if not already set)
        if ($frequency === null && isset($first_wound['frequency_per_week'])) {
          $frequency = (int)$first_wound['frequency_per_week'];
        }
        if ($qty_per_change === null && isset($first_wound['qty_per_change'])) {
          $qty_per_change = (int)$first_wound['qty_per_change'];
        }
        if ($duration_days === null && isset($first_wound['duration_days'])) {
          $duration_days = (int)$first_wound['duration_days'];
        }
      }
    }
  } else {
    // Fallback to individual wound fields (old format)
    $wound_location = safe($_POST['wound_location'] ?? null);
    $wound_laterality = safe($_POST['wound_laterality'] ?? null);
    $wound_notes    = safe($_POST['wound_notes'] ?? null);
    $exudate_level  = safe($_POST['exudate_level'] ?? null);
  }

  // Validate that we have product information from either old format or wounds_data
  if (!$prod || empty($prod['id'])) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'missing_product_data']);
    exit;
  }

  // Signer - handle practice physician selection for multi-physician practices
  $physician_id = safe($_POST['physician_id'] ?? null);
  $physician_npi = safe($_POST['physician_npi'] ?? null);
  $physician_license = safe($_POST['physician_license'] ?? null);
  $sign_name  = safe($_POST['sign_name']  ?? null);
  $sign_title = safe($_POST['sign_title'] ?? null);

  // If physician_id is provided (practice admin selected a physician from roster)
  if ($physician_id) {
    // Fetch physician details from practice_physicians (columns are physician_*; key is practice_manager_id).
    // Wrapped defensively — the form already submits physician_npi/license/sign_name as a fallback.
    try {
      $phys_stmt = $pdo->prepare("
        SELECT physician_first_name AS first_name, physician_last_name AS last_name,
               physician_npi AS npi, physician_license AS license_number, physician_license_state AS license_state
        FROM practice_physicians
        WHERE id = ? AND practice_manager_id = ?
      ");
      $phys_stmt->execute([$physician_id, $uid]);
      $selected_physician = $phys_stmt->fetch(PDO::FETCH_ASSOC);

      if ($selected_physician) {
        // Use selected physician's data
        if (!$sign_name) $sign_name = trim(($selected_physician['first_name'] ?? '') . ' ' . ($selected_physician['last_name'] ?? ''));
        $physician_npi = $selected_physician['npi'] ?: $physician_npi;
        $physician_license = $selected_physician['license_number'] ?: $physician_license;
        // Note: sign_title should come from form as it may vary per order
      }
    } catch (Throwable $e) {
      error_log('[orders.create] physician lookup failed: ' . $e->getMessage());
      // fall through using the physician_npi/license/sign_name already posted by the form
    }
  }

  // Fallback to current user if no physician selected or data missing
  if (!$sign_name || !$sign_title) {
    $u = $pdo->prepare("SELECT first_name, last_name, sign_title FROM users WHERE id=?");
    $u->execute([$uid]);
    $ud = $u->fetch(PDO::FETCH_ASSOC);
    if (!$sign_name && $ud)  $sign_name  = trim(($ud['first_name'] ?? '').' '.($ud['last_name'] ?? '')) ?: 'Physician';
    if (!$sign_title && $ud) $sign_title = $ud['sign_title'] ?? 'Physician';
  }

  // 6) Determine status and review_status based on save_as_draft parameter
  $save_as_draft = isset($_POST['save_as_draft']) && $_POST['save_as_draft'] === '1';
  $status = $save_as_draft ? 'draft' : 'pending';
  $review_status = $save_as_draft ? 'draft' : 'pending_admin_review';

  // 7) Generate referral order number (RF-YYYYMMDD-NNN format)
  $datePrefix = date('Ymd');
  $countStmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM orders
    WHERE payment_type != 'wholesale'
    AND DATE(created_at) = CURRENT_DATE
  ");
  $countStmt->execute();
  $todayCount = (int)$countStmt->fetchColumn();
  $referralOrderNumber = sprintf('RF-%s-%03d', $datePrefix, $todayCount + 1);

  // 8) Calculate and store order values at creation time
  // This ensures historical accuracy even if rates change later
  $fpw_int = (int)($frequency ?? 1);
  $qty_int = max(1, (int)($qty_per_change ?? 1));
  $days_int = (int)($duration_days ?? 30);
  $refills_int = 0; // Refills not typically set at initial order creation

  // Get product details for calculation
  $prodDetails = $pdo->prepare("SELECT pieces_per_box, cost_per_box, medicare_allowable, price_wholesale FROM products WHERE id = ?");
  $prodDetails->execute([$prod['id']]);
  $prodInfo = $prodDetails->fetch(PDO::FETCH_ASSOC);
  $pieces_per_box = max(1, (int)($prodInfo['pieces_per_box'] ?? 10));
  $cost_per_box = (float)($prodInfo['cost_per_box'] ?? 0);
  $medicare_rate = (float)($prodInfo['medicare_allowable'] ?? 0);
  $price_wholesale = (float)($prodInfo['price_wholesale'] ?? 0);

  // Calculate pieces and boxes (referral order calculation)
  if ($fpw_int === 0) $fpw_int = 1;
  if ($days_int === 0) $days_int = 30;
  $weeks = $days_int / 7.0;
  $calc_total_pieces = (int)ceil($weeks * $fpw_int * $qty_int * (1 + $refills_int));
  $calc_boxes_to_ship = (int)ceil($calc_total_pieces / $pieces_per_box);
  $calc_billable_pieces = $calc_total_pieces;

  // Calculate revenue and cost (per piece).
  if ($is_healkit) {
    // HealKit: wholesale price billed per piece (report applies practice pricing on read)
    $calc_cpt_rate = $price_wholesale > 0 ? $price_wholesale / $pieces_per_box : (float)($prod['price_admin'] ?? 0) / $pieces_per_box;
  } else {
    // Referral: medicare_allowable is a PER-BOX rate, so divide by pieces_per_box to get the per-piece rate.
    $calc_cpt_rate = $medicare_rate > 0 ? $medicare_rate / $pieces_per_box : (float)($prod['price_admin'] ?? 0) / $pieces_per_box;
  }
  $calc_expected_revenue = $calc_billable_pieces * $calc_cpt_rate;
  $calc_expected_cost = $calc_boxes_to_ship * $cost_per_box;

  // Insert order FIRST (no file I/O yet)
  // Note: Draft orders have status='draft', submitted orders have status='pending'
  // Force cache refresh - 2024-11-18
  $sql = "INSERT INTO orders
    (id, patient_id, user_id, product, product_id, product_price, cpt, status, frequency, delivery_mode,
     shipments_remaining, created_at, updated_at, order_number, billed_by,
     insurer_name, member_id, group_id, payer_phone, prior_auth, payment_type,
     wound_location, wound_laterality, wound_notes, exudate_level, wounds_data,
     last_eval_date, start_date, qty_per_change, duration_days,
     additional_instructions, secondary_dressing, notes_text,
     shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip,
     rx_note_path, rx_note_mime, ins_card_path, ins_card_mime, id_card_path, id_card_mime,
     e_sign_user_id, e_sign_name, e_sign_title, e_sign_at, e_sign_ip,
     physician_id, physician_npi, physician_license,
     review_status,
     total_pieces, boxes_to_ship, billable_pieces, expected_revenue, expected_cost, cpt_rate_used)
    VALUES
    (?,?,?,?,?,?,?,?,?,?,0,NOW(),NOW(),?,?,
     ?,?,?,?,?,?,
     ?,?,?,?,?,
     ?,?,?,?,
     ?,?,?,
     ?,?,?,?,?,?,
     NULL,NULL,NULL,NULL,NULL,NULL,
     ?,?,?,?,?,
     ?,?,?,
     ?,
     ?,?,?,?,?,?)";

  $pdo->prepare($sql)->execute([
    $order_id, $patient_id, $uid,
    $prod['name'], $prod['id'], $prod['price_admin'], $prod['cpt_code'],
    $status, $frequency, $delivery_mode,
    // order number (same for all products in this order)
    $referralOrderNumber,
    // billed_by (healkit for HealKit orders, null for referral)
    $billed_by,
    // insurance & payment
    safe($_POST['insurance_provider'] ?? null),
    safe($_POST['insurance_member_id'] ?? null),
    safe($_POST['insurance_group_id'] ?? null),
    safe($_POST['insurance_payer_phone'] ?? null),
    $prior_auth, $payment_type,
    // wound (legacy columns + new wounds_data JSONB)
    $wound_location, $wound_laterality, $wound_notes, $exudate_level, $wounds_data,
    // new order form fields
    $last_eval_date, $start_date, $qty_per_change, $duration_days,
    $additional_instructions, $secondary_dressing, $notes_text,
    // shipping
    $shipping_name, $shipping_phone, $shipping_address, $shipping_city, $shipping_state, $shipping_zip,
    // e-sign
    $uid, $sign_name, $sign_title, date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'] ?? null,
    // physician (for multi-physician practices)
    $physician_id, $physician_npi, $physician_license,
    // review status
    $review_status,
    // calculated values (stored at creation time for historical accuracy)
    $calc_total_pieces, $calc_boxes_to_ship, $calc_billable_pieces,
    $calc_expected_revenue, $calc_expected_cost, $calc_cpt_rate
  ]);

  // Create additional orders for secondary and additional products (multi-product support)
  $order_group_id = null;
  $all_order_ids = [$order_id];

  if (!empty($wounds_array)) {
    foreach ($wounds_array as $wound_index => $wound) {
      $products_to_create = [];

      // For wounds beyond the first (wound_index > 0), create a separate order for the primary product
      // The first wound's primary product was already created as the main order above
      if ($wound_index > 0 && !empty($wound['product_id'])) {
        $products_to_create[] = [
          'product_type' => 'primary',
          'product_id' => (int)$wound['product_id'],
          'product_name' => $wound['product_name'] ?? '',
          'product_cpt' => $wound['product_cpt'] ?? null,
          'product_price' => floatval($wound['product_price'] ?? 0)
        ];
      }

      // Check for secondary product
      if (!empty($wound['secondary_product_id']) && $wound['secondary_product_id'] !== '') {
        $products_to_create[] = [
          'product_type' => 'secondary',
          'product_id' => (int)$wound['secondary_product_id'],
          'product_name' => $wound['secondary_product_name'] ?? '',
          'product_cpt' => $wound['secondary_product_cpt'] ?? null,
          'product_price' => floatval($wound['secondary_product_price'] ?? 0)
        ];
      }

      // Check for additional product
      if (!empty($wound['additional_product_id']) && $wound['additional_product_id'] !== '') {
        $products_to_create[] = [
          'product_type' => 'additional',
          'product_id' => (int)$wound['additional_product_id'],
          'product_name' => $wound['additional_product_name'] ?? '',
          'product_cpt' => $wound['additional_product_cpt'] ?? null,
          'product_price' => floatval($wound['additional_product_price'] ?? 0)
        ];
      }

      // If we have products to create (secondary/additional, or primary for wound 2+), create order group
      if (!empty($products_to_create)) {
        // Create order group ID if not already created
        if (!$order_group_id) {
          $order_group_id = guid();

          // Create order_groups record FIRST (before updating orders with foreign key)
          $pdo->prepare("INSERT INTO order_groups (id, patient_id, user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())")
             ->execute([$order_group_id, $patient_id, $uid, $status]);

          // Now update primary order with order_group_id (foreign key constraint satisfied)
          $pdo->prepare("UPDATE orders SET order_group_id = ?, product_type = 'primary', wound_index = ? WHERE id = ?")
             ->execute([$order_group_id, $wound_index, $order_id]);
        }

        // Create orders for secondary and additional products
        foreach ($products_to_create as $product_info) {
          $new_order_id = guid();
          $all_order_ids[] = $new_order_id;

          // Box/piece calc for this dressing (same formula as the primary), using this wound's qty/freq/duration
          $sec_qty  = max(1, (int)($wound['qty_per_change'] ?? $qty_per_change ?? 1));
          $sec_fpw  = (int)($wound['frequency_per_week'] ?? $frequency ?? 1); if ($sec_fpw === 0) $sec_fpw = 1;
          $sec_days = (int)($wound['duration_days'] ?? $duration_days ?? 30); if ($sec_days === 0) $sec_days = 30;
          $ppbStmt = $pdo->prepare("SELECT pieces_per_box FROM products WHERE id = ?");
          $ppbStmt->execute([(int)$product_info['product_id']]);
          $sec_ppb = max(1, (int)($ppbStmt->fetchColumn() ?: 10));
          $sec_total_pieces = (int)ceil(($sec_days / 7.0) * $sec_fpw * $sec_qty);
          $sec_boxes = (int)ceil($sec_total_pieces / $sec_ppb);

          $pdo->prepare("INSERT INTO orders
            (id, patient_id, user_id, product, product_id, product_price, cpt, status, frequency, delivery_mode,
             order_group_id, product_type, wound_index, order_number, billed_by,
             shipments_remaining, created_at, updated_at,
             insurer_name, member_id, group_id, payer_phone, prior_auth, payment_type,
             wound_location, wound_laterality, wound_notes, exudate_level, wounds_data,
             last_eval_date, start_date, qty_per_change, duration_days,
             additional_instructions, secondary_dressing, notes_text,
             shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip,
             e_sign_user_id, e_sign_name, e_sign_title, e_sign_at, e_sign_ip,
             review_status, total_pieces, boxes_to_ship)
            VALUES
            (?,?,?,?,?,?,?,?,?,?,
             ?,?,?,?,?,
             0,NOW(),NOW(),
             ?,?,?,?,?,?,
             ?,?,?,?,?,
             ?,?,?,?,
             ?,?,?,
             ?,?,?,?,?,?,
             ?,?,?,?,?,
             ?,?,?)")->execute([
            $new_order_id, $patient_id, $uid,
            $product_info['product_name'], $product_info['product_id'], $product_info['product_price'], $product_info['product_cpt'],
            $status, $frequency, $delivery_mode,
            $order_group_id, $product_info['product_type'], $wound_index, $referralOrderNumber, $billed_by,
            // insurance & payment
            safe($_POST['insurance_provider'] ?? null),
            safe($_POST['insurance_member_id'] ?? null),
            safe($_POST['insurance_group_id'] ?? null),
            safe($_POST['insurance_payer_phone'] ?? null),
            $prior_auth, $payment_type,
            // wound (legacy columns + new wounds_data JSONB)
            $wound_location, $wound_laterality, $wound_notes, $exudate_level, $wounds_data,
            // new order form fields
            $last_eval_date, $start_date, $qty_per_change, $duration_days,
            $additional_instructions, $secondary_dressing, $notes_text,
            // shipping
            $shipping_name, $shipping_phone, $shipping_address, $shipping_city, $shipping_state, $shipping_zip,
            // e-sign
            $uid, $sign_name, $sign_title, date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'] ?? null,
            // review status
            $review_status,
            // calculated boxes/pieces for this dressing (stored like the primary)
            $sec_total_pieces, $sec_boxes
          ]);
        }
      }
    }
  }

  /* -------------------- File uploads & attachments -------------------- */
  // IMPORTANT: Process file uploads BEFORE responding to client
  // $_FILES is not available after fastcgi_finish_request()
  // Files (optional). Keep your existing subdirectories.
  // These are WEB paths; filesystem destination is resolved from DOCUMENT_ROOT.
  // Note: Frontend sends 'file_rx_note' but we check both 'file_rx_note' and 'rx_note' for compatibility
  $rx_path = null; $rx_mime = null;
  $ins_path = null; $ins_mime = null;
  $id_path = null; $id_mime = null;
  $wound_photo_path = null; $wound_photo_mime = null;
  $ivr_path = null; $ivr_mime = null;

  try {
    // Log what files were submitted for debugging (error log only, not in patient instructions)
    $filesDebug = json_encode(array_keys($_FILES));
    $filesCount = count($_FILES);
    error_log('[orders.create] Files submitted: ' . $filesDebug . ', count=' . $filesCount);

    // Log detailed file_rx_note info if present
    if (isset($_FILES['file_rx_note']) && is_array($_FILES['file_rx_note'])) {
      $f = $_FILES['file_rx_note'];
      $fileRxInfo = sprintf(
        "file_rx_note[error=%d, size=%d, type=%s, tmp=%s]",
        isset($f['error']) ? (int)$f['error'] : -1,
        isset($f['size']) ? (int)$f['size'] : 0,
        isset($f['type']) ? $f['type'] : 'unknown',
        !empty($f['tmp_name']) ? 'yes' : 'no'
      );
      error_log('[orders.create] ' . $fileRxInfo);
    }

    try {
      [$rx_path,  $rx_mime]  = save_upload('file_rx_note',  '/uploads/notes');
    } catch (Throwable $e) {
      error_log('[orders.create] ERROR saving file_rx_note: ' . $e->getMessage());
      $rx_path = null; $rx_mime = null;
    }

    if (!$rx_path) {
      try {
        [$rx_path,  $rx_mime]  = save_upload('rx_note',  '/uploads/notes'); // fallback
      } catch (Throwable $e) {
        error_log('[orders.create] ERROR saving rx_note (fallback): ' . $e->getMessage());
      }
    }

    if ($rx_path) {
      error_log('[orders.create] Visit note uploaded successfully: ' . $rx_path);
    } else {
      error_log('[orders.create] No visit note uploaded - file may have failed validation or upload');
    }

    try {
      [$ins_path, $ins_mime] = save_upload('ins_card','/uploads/insurance');
    } catch (Throwable $e) {
      error_log('[orders.create] ERROR saving ins_card: ' . $e->getMessage());
      $ins_path = null; $ins_mime = null;
    }

    try {
      [$id_path,  $id_mime]  = save_upload('id_card',  '/uploads/ids');
    } catch (Throwable $e) {
      error_log('[orders.create] ERROR saving id_card: ' . $e->getMessage());
      $id_path = null; $id_mime = null;
    }

    try {
      [$wound_photo_path, $wound_photo_mime] = save_upload('baseline_wound_photo', '/uploads/wound-photos');
    } catch (Throwable $e) {
      error_log('[orders.create] ERROR saving baseline_wound_photo: ' . $e->getMessage());
      $wound_photo_path = null; $wound_photo_mime = null;
    }

    try {
      [$ivr_path, $ivr_mime] = save_upload('file_ivr', '/uploads/ivr');
    } catch (Throwable $e) {
      error_log('[orders.create] ERROR saving file_ivr: ' . $e->getMessage());
      $ivr_path = null; $ivr_mime = null;
    }

    // Validate insurance docs if insurance payment and files were not uploaded
    if ($payment_type === 'insurance') {
      // If the physician didn't upload in this request, we leave them NULL.
      // Admin portal can enforce before shipping; if you prefer hard-blocking,
      // move this check BEFORE respond_now().
      // Example (strict):
      // if (!$ins_path || !$id_path) throw new RuntimeException('missing_insurance_docs');
    }

    // Process insurance card OCR if uploaded
    if ($ins_path) {
      require_once __DIR__ . '/../insurance-ocr.php';
      $insuranceOCR = new InsuranceOCR();

      // Only process if OCR is enabled and patient hasn't been processed yet
      if ($insuranceOCR->isEnabled() && !$insuranceOCR->hasBeenProcessed($pdo, $patient_id)) {
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . $ins_path;
        // Fallback to Render persistent disk path
        if (!file_exists($fullPath)) {
          $fullPath = '/opt/render/project/src' . $ins_path;
        }

        $insuranceData = $insuranceOCR->processInsuranceCard($fullPath);
        if ($insuranceData && $insuranceData['confidence'] >= 0.5) {
          $insuranceOCR->saveToPatient($pdo, $patient_id, $insuranceData);
          error_log("[orders.create] OCR processed insurance card for patient $patient_id");
        }
      }
    }

    // Update order with file columns if present
    // IMPORTANT: Update ALL orders in the group (for multi-product orders), not just the primary
    if ($rx_path || $ins_path || $id_path || $wound_photo_path || $ivr_path) {
      $sets=[]; $params=[];
      if ($rx_path)  { $sets[]='rx_note_path=?';  $params[]=$rx_path;  $sets[]='rx_note_name=?';  $params[]=basename($rx_path);  $sets[]='rx_note_mime=?';  $params[]=$rx_mime; }
      if ($ins_path) { $sets[]='ins_card_path=?'; $params[]=$ins_path; $sets[]='ins_card_mime=?'; $params[]=$ins_mime; }
      if ($id_path)  { $sets[]='id_card_path=?';  $params[]=$id_path;  $sets[]='id_card_mime=?';  $params[]=$id_mime; }
      if ($wound_photo_path) { $sets[]='baseline_wound_photo_path=?'; $params[]=$wound_photo_path; $sets[]='baseline_wound_photo_mime=?'; $params[]=$wound_photo_mime; }
      if ($ivr_path) { $sets[]='ivr_path=?'; $params[]=$ivr_path; $sets[]='ivr_name=?'; $params[]=basename($ivr_path); $sets[]='ivr_mime=?'; $params[]=$ivr_mime; }

      // Update ALL orders in this group (not just primary order)
      foreach ($all_order_ids as $oid) {
        $params_copy = $params;
        $params_copy[] = $oid;
        $params_copy[] = $uid;
        $pdo->prepare("UPDATE orders SET ".implode(', ',$sets).", updated_at=NOW() WHERE id=? AND user_id=?")->execute($params_copy);
      }
    }

    // Insert baseline wound photo into wound_photos table
    if ($wound_photo_path) {
      $photo_id = guid();
      $pdo->prepare("
        INSERT INTO wound_photos
          (id, patient_id, photo_path, photo_type, uploaded_via, uploaded_at, order_id, reviewed, reviewed_at)
        VALUES (?, ?, ?, 'baseline', 'portal_order', NOW(), ?, TRUE, NOW())
      ")->execute([
        $photo_id,
        $patient_id,
        $wound_photo_path,
        $order_id
      ]);
      error_log("[orders.create] Inserted baseline photo into wound_photos: $photo_id");
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
    // Log upload errors but don't fail the order creation
    error_log('[orders.create upload] ERROR: ' . $upErr->getMessage() . ' - Trace: ' . $upErr->getTraceAsString());

    // Also write error to debug file
    $debugFile = __DIR__ . '/../../uploads/order_upload_debug.log';
    $errorMsg = date('Y-m-d H:i:s') . " [Order: $order_id] ERROR: " . $upErr->getMessage() . "\n";
    @file_put_contents($debugFile, $errorMsg, FILE_APPEND);

    // Continue with order creation even if file upload fails
  }

  // 7) Respond to client with success
  respond_now(['ok'=>true,'data'=>['order_id'=>$order_id,'order_group_id'=>$order_group_id,'patient_id'=>$patient_id]]);

  /* -------------------- POST-RESPONSE: Additional processing -------------------- */
  // Everything after this point runs after the response is sent to the client

  // Trigger AI approval score generation if order has visit notes or is a submitted order
  // This ensures the approval score is based on the order data and visit notes
  if (!$save_as_draft && ($rx_path || $created_new_patient)) {
    try {
      require_once __DIR__ . '/../lib/auto_score.php';
      $patientData = [
        'first_name' => safe($_POST['first_name'] ?? null),
        'last_name' => safe($_POST['last_name'] ?? null),
        'dob' => safe($_POST['dob'] ?? null),
        'insurance_provider' => safe($_POST['insurance_provider'] ?? null),
        'ins_card_path' => $ins_path
      ];
      if (shouldAutoScore($patientData)) {
        queueApprovalScore($patient_id, $pdo, true); // async
        error_log("[orders.create] Queued approval score generation for patient $patient_id (order $order_id)");
      }
    } catch (Throwable $scoreErr) {
      error_log('[orders.create auto_score] ' . $scoreErr->getMessage());
    }
  }

  // Send email notifications
  try {
    require_once __DIR__ . '/../lib/email_notifications.php';

    // Get physician info - use physician_id if set (multi-physician practices), else use logged-in user
    $actualPhysicianId = !empty($physician_id) ? $physician_id : $uid;
    $physicianStmt = $pdo->prepare("
      SELECT first_name, last_name, email, practice_name, npi
      FROM users
      WHERE id = ?
    ");
    $physicianStmt->execute([$actualPhysicianId]);
    $physician = $physicianStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Get patient info
    $patientStmt = $pdo->prepare("
      SELECT first_name, last_name, email, dob, address, city, state, zip, insurance_provider
      FROM patients
      WHERE id = ?
    ");
    $patientStmt->execute([$patient_id]);
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Get product info
    $productStmt = $pdo->prepare("
      SELECT name, size FROM products WHERE id = ?
    ");
    $productStmt->execute([$product_id]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $physicianName = trim(($physician['first_name'] ?? '') . ' ' . ($physician['last_name'] ?? ''));
    $patientFullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
    // Privacy-safe patient name: First initial + Last name (e.g., "J. Smith")
    $patientFirstInitial = !empty($patient['first_name']) ? strtoupper(substr($patient['first_name'], 0, 1)) . '.' : '';
    $patientPrivacyName = trim($patientFirstInitial . ' ' . ($patient['last_name'] ?? ''));
    // Privacy-safe location: City, State only (no full address)
    $patientCityState = trim(($patient['city'] ?? '') . ', ' . ($patient['state'] ?? ''));
    $patientAddress = trim(($patient['address'] ?? '') . ', ' . ($patient['city'] ?? '') . ' ' . ($patient['state'] ?? '') . ' ' . ($patient['zip'] ?? ''));
    $productName = trim(($product['name'] ?? '') . ' ' . ($product['size'] ?? ''));

    // 1. Send order received email to patient (use full name for patient's own email)
    if (!empty($patient['email'])) {
      send_order_received_email([
        'patient_email' => $patient['email'],
        'patient_name' => $patientFullName,
        'order_id' => $order_id,
        'order_date' => date('m/d/Y'),
        'physician_name' => $physicianName,
        'practice_name' => $physician['practice_name'] ?? '',
        'product_name' => $productName,
        'quantity' => '1'
      ]);
    }

    // 2. Send new order notification to manufacturer (use privacy-safe patient info)
    send_manufacturer_order_email([
      'manufacturer_email' => 'orders@collagendirect.health', // Configure as needed
      'order_id' => $order_id,
      'order_date' => date('m/d/Y'),
      'patient_name' => $patientPrivacyName,
      'patient_dob' => $patient['dob'] ?? '',
      'patient_city_state' => $patientCityState,
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
  // Log error for debugging
  error_log("Order creation error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
  error_log("Stack trace: " . $e->getTraceAsString());

  // Return detailed error in development/debug mode
  http_response_code(500);
  echo json_encode([
    'ok'=>false,
    'error'=>'server_error',
    'debug_message' => $e->getMessage(),
    'debug_file' => basename($e->getFile()),
    'debug_line' => $e->getLine()
  ]);
}
