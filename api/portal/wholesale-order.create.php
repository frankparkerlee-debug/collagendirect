<?php
// /public/api/portal/wholesale-order.create.php
// Creates multiple wholesale orders from bulk order form
declare(strict_types=1);

// Set JSON header and error handling first
header('Content-Type: application/json');
error_reporting(0); // Suppress PHP warnings/notices that could break JSON
ini_set('display_errors', '0');

try {
  require __DIR__ . '/../../admin/db.php';
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'database_connection_failed', 'details' => $e->getMessage()]);
  exit;
}

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'unauthorized']);
  exit;
}
$uid = $_SESSION['user_id'];

// Check if practice is blocked from ordering due to overdue balance
$blockCheck = $pdo->prepare("
  SELECT ordering_blocked, blocked_reason, balance_over_90_days
  FROM practice_balances
  WHERE user_id = ?
");
$blockCheck->execute([$uid]);
$balanceStatus = $blockCheck->fetch(PDO::FETCH_ASSOC);

if ($balanceStatus && $balanceStatus['ordering_blocked']) {
  http_response_code(403);
  echo json_encode([
    'ok' => false,
    'error' => 'ordering_blocked',
    'message' => $balanceStatus['blocked_reason'] ?? 'Wholesale ordering is currently blocked for your account.',
    'overdue_amount' => $balanceStatus['balance_over_90_days'] ?? 0
  ]);
  exit;
}

/* -------------------- Helpers -------------------- */
function guid(): string {
  return bin2hex(random_bytes(16));
}

function safe(?string $s): ?string {
  if ($s === null) return null;
  $s = trim($s);
  return $s === '' ? null : $s;
}

/* -------------------- Main -------------------- */
try {
  // Get JSON input
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);

  if (!$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
  }

  $patients = $data['patients'] ?? [];
  $products = $data['products'] ?? [];
  $items = $data['items'] ?? [];
  $notes = safe($data['notes'] ?? null);

  if (empty($items)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'no_items']);
    exit;
  }

  // Begin transaction
  $pdo->beginTransaction();

  // Generate a wholesale order number sequence for this batch
  // Format: WS-YYYYMMDD-NNN (e.g., WS-20250119-001)
  $datePrefix = date('Ymd');

  // Find the highest wholesale order number for today
  $countStmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM orders
    WHERE payment_type = 'wholesale'
    AND DATE(created_at) = CURRENT_DATE
  ");
  $countStmt->execute();
  $todayCount = (int)$countStmt->fetchColumn();
  $startingNumber = $todayCount + 1;

  $ordersCreated = 0;
  $patientIds = []; // Track created/reused patient IDs
  $orderCounter = $startingNumber;

  foreach ($items as $item) {
    $patientIndex = $item['patient_index'];
    $patientData = $patients[$patientIndex] ?? null;
    $productData = $item['product'] ?? null;
    $boxes = (int)($item['boxes'] ?? 0);

    if (!$patientData || !$productData || $boxes <= 0) {
      continue; // Skip invalid items
    }

    // 1) Get or create patient
    $patientId = null;

    // Check if this is office stock (multiple ways it can be indicated)
    $isOfficeStock = (
      ($patientData['delivery_preference'] ?? '') === 'office_stock' ||
      ($patientData['is_office_stock'] ?? '') == '1' ||
      strtolower($patientData['first_name'] ?? '') === 'office'
    );

    if (!$isOfficeStock) {
      // For real patients, try to find existing patient first

      // Check if patient already exists by ID (from search/autocomplete)
      if (isset($patientData['id']) && !empty($patientData['id'])) {
        // Verify this patient belongs to the user
        $chk = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
        $chk->execute([$patientData['id'], $uid]);
        if ($chk->fetch()) {
          $patientId = $patientData['id'];
        }
      }

      // If not found by ID, try to match by name and phone
      if (!$patientId) {
        $firstName = safe($patientData['first_name'] ?? null);
        $lastName = safe($patientData['last_name'] ?? null);
        $phone = safe($patientData['phone'] ?? null);

        if ($firstName && $lastName && $phone) {
          // Normalize phone for matching (remove formatting)
          $phoneDigits = preg_replace('/\D/', '', $phone);

          // Try to find matching patient by name and phone
          $matchStmt = $pdo->prepare("
            SELECT id FROM patients
            WHERE user_id = ?
              AND LOWER(first_name) = LOWER(?)
              AND LOWER(last_name) = LOWER(?)
              AND (
                REGEXP_REPLACE(phone, '[^0-9]', '', 'g') = ?
                OR phone = ?
              )
            LIMIT 1
          ");
          $matchStmt->execute([$uid, $firstName, $lastName, $phoneDigits, $phone]);
          $existingPatient = $matchStmt->fetch(PDO::FETCH_ASSOC);

          if ($existingPatient) {
            $patientId = $existingPatient['id'];
          }
        }
      }

      // Still not found? Create new patient (auto-approved for wholesale orders)
      if (!$patientId) {
        $patientId = guid();
        $pdo->prepare("
          INSERT INTO patients
            (id, user_id, first_name, last_name, phone, address, city, zip, state, created_at, updated_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW(), NOW())
        ")->execute([
          $patientId,
          $uid,
          safe($patientData['first_name'] ?? null),
          safe($patientData['last_name'] ?? null),
          safe($patientData['phone'] ?? null),
          safe($patientData['address'] ?? null),
          safe($patientData['city'] ?? null),
          safe($patientData['zip'] ?? null)
        ]);
      }
    } else {
      // For office stock, use or create a shared "Office Stock" patient for this user
      $officeStockStmt = $pdo->prepare("
        SELECT id FROM patients
        WHERE user_id = ? AND first_name = 'Office' AND last_name = 'Stock'
        LIMIT 1
      ");
      $officeStockStmt->execute([$uid]);
      $officeStock = $officeStockStmt->fetch(PDO::FETCH_ASSOC);

      if ($officeStock) {
        // Use existing Office Stock patient
        $patientId = $officeStock['id'];
      } else {
        // Create a single Office Stock patient for this practice (auto-approved)
        $patientId = guid();
        $pdo->prepare("
          INSERT INTO patients
            (id, user_id, first_name, last_name, phone, address, city, zip, state, created_at, updated_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW(), NOW())
        ")->execute([
          $patientId,
          $uid,
          'Office',
          'Stock',
          null,
          null,
          null,
          null
        ]);
      }
    }

    $patientIds[$patientIndex] = $patientId;

    // 2) Create order
    $orderId = guid();

    // Check if shipping to office (needed for shipping address)
    $shipToOffice = (
      ($patientData['delivery_mode'] ?? '') === 'ship_to_office' ||
      ($patientData['delivery_preference'] ?? '') === 'office_stock' ||
      ($patientData['is_office_stock'] ?? '') == '1' ||
      strtolower($patientData['first_name'] ?? '') === 'office'
    );

    // Determine shipping details - check if shipping to office
    if ($shipToOffice) {
      // Get practice address - check current user first, then practice admin
      $userStmt = $pdo->prepare("SELECT practice_name, address, city, state, zip, role FROM users WHERE id = ?");
      $userStmt->execute([$uid]);
      $user = $userStmt->fetch(PDO::FETCH_ASSOC);

      // If current user doesn't have address, find practice admin with same practice_name
      if ($user && empty($user['address']) && !empty($user['practice_name'])) {
        $adminStmt = $pdo->prepare("
          SELECT practice_name, address, city, state, zip
          FROM users
          WHERE practice_name = ?
            AND role = 'practice_admin'
            AND address IS NOT NULL
            AND address != ''
          LIMIT 1
        ");
        $adminStmt->execute([$user['practice_name']]);
        $adminUser = $adminStmt->fetch(PDO::FETCH_ASSOC);
        if ($adminUser) {
          $user = $adminUser; // Use practice admin's address
        }
      }

      $shippingName = $user['practice_name'] ?? 'Office Stock';
      $shippingAddress = $user['address'] ?? null;
      $shippingCity = $user['city'] ?? null;
      $shippingState = $user['state'] ?? null;
      $shippingZip = $user['zip'] ?? null;
      $shippingPhone = null;
    } else{
      // Use patient's address
      $shippingName = ($patientData['first_name'] ?? '') . ' ' . ($patientData['last_name'] ?? '');
      $shippingAddress = safe($patientData['address'] ?? null);
      $shippingCity = safe($patientData['city'] ?? null);
      $shippingState = safe($patientData['state'] ?? null);
      $shippingZip = safe($patientData['zip'] ?? null);
      $shippingPhone = safe($patientData['phone'] ?? null);
    }

    // Calculate pricing - check for custom pricing and discounts
    $piecesPerBox = (int)($productData['pieces_per_box'] ?? 1);

    // Check if this practice has custom pricing or discount for this product
    $customPriceStmt = $pdo->prepare("
      SELECT custom_price, discount_percentage
      FROM practice_pricing
      WHERE user_id = ? AND product_id = ?
    ");
    $customPriceStmt->execute([$uid, $productData['id']]);
    $customPricing = $customPriceStmt->fetch(PDO::FETCH_ASSOC);

    if ($customPricing && $customPricing['custom_price'] > 0) {
      // Use custom pricing (already stored as price per piece)
      $pricePerPiece = (float)$customPricing['custom_price'];
      $pricePerBox = $pricePerPiece * $piecesPerBox;
    } elseif ($customPricing && $customPricing['discount_percentage'] > 0) {
      // Apply percentage discount to default wholesale price
      $pricePerBox = (float)($productData['price_wholesale'] ?? 0); // price_wholesale is per BOX
      $discountMultiplier = 1 - ((float)$customPricing['discount_percentage'] / 100);
      $pricePerBox = $pricePerBox * $discountMultiplier;
      $pricePerPiece = $piecesPerBox > 0 ? $pricePerBox / $piecesPerBox : 0;
    } else {
      // Use default pricing from products table
      $pricePerBox = (float)($productData['price_wholesale'] ?? 0); // price_wholesale is per BOX
      $pricePerPiece = $piecesPerBox > 0 ? $pricePerBox / $piecesPerBox : 0;
    }

    $totalPieces = $boxes * $piecesPerBox;
    $orderTotal = $boxes * $pricePerBox; // Total amount for this order

    // Generate wholesale order number for this specific order
    $wholesaleOrderNumber = sprintf('WS-%s-%03d', $datePrefix, $orderCounter);
    $orderCounter++;

    // Combine order number with user notes
    $orderNotes = "Wholesale Order #$wholesaleOrderNumber";
    if (!empty($notes)) {
      $orderNotes .= "\n" . $notes;
    }

    // Insert order using standard columns (no invoice fields in orders table)
    $sql = "
      INSERT INTO orders (
        id,
        user_id,
        patient_id,
        product_id,
        product,
        product_price,
        qty_per_change,
        frequency,
        status,
        review_status,
        payment_type,
        billed_by,
        order_number,
        shipping_name,
        shipping_phone,
        shipping_address,
        shipping_city,
        shipping_state,
        shipping_zip,
        additional_instructions,
        delivery_mode,
        created_at,
        updated_at
      ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
      )
    ";

    $pdo->prepare($sql)->execute([
      $orderId,
      $uid,
      $patientId,
      $productData['id'],
      $productData['name'],
      $pricePerPiece, // Store per-piece wholesale price
      $boxes, // Number of boxes ordered (stored in qty_per_change)
      'one-time', // Wholesale orders are typically one-time
      'submitted', // Wholesale orders go straight to submitted
      'approved', // Auto-approve wholesale orders (no insurance review needed)
      'wholesale', // Mark as wholesale payment type
      'practice_dme', // Practice bills their own DME license
      $wholesaleOrderNumber, // Save the wholesale order number (WS-YYYYMMDD-XXX)
      $shippingName,
      $shippingPhone,
      $shippingAddress,
      $shippingCity,
      $shippingState,
      $shippingZip,
      $orderNotes, // Include wholesale order number in notes
      $shipToOffice ? 'office' : 'patient' // delivery_mode
    ]);

    $ordersCreated++;
  }

  // Commit transaction
  $pdo->commit();

  echo json_encode([
    'ok' => true,
    'orders_created' => $ordersCreated,
    'message' => "Successfully created $ordersCreated wholesale order(s)"
  ]);

} catch (PDOException $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log("Wholesale order creation error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'database_error', 'details' => $e->getMessage()]);
} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log("Wholesale order creation error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
