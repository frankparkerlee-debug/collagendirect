<?php
/**
 * Admin API: Create Wholesale Order on Behalf of Practice
 * Mirrors the portal wholesale order creation API with correct column names
 */
declare(strict_types=1);

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
  require __DIR__ . '/../../admin/db.php';
  require __DIR__ . '/../../admin/auth.php';
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'database_connection_failed']);
  exit;
}

// Verify admin authorization
$admin = current_admin();
if (!$admin) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'unauthorized']);
  exit;
}

// All admin users can create orders on behalf of practices
// (superadmin, manufacturer, admin, employee)
$adminRole = $admin['role'] ?? '';
error_log('[admin-wholesale-create] Admin user: ' . ($admin['email'] ?? 'unknown') . ' Role: ' . $adminRole);

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
  global $pdo;

  // Get JSON input
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);

  if (!$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
  }

  $practiceId = safe($data['practice_id'] ?? null);
  $orderType = safe($data['order_type'] ?? 'patient_orders');
  $isOfficeStock = ($orderType === 'office_stock');
  $patients = $data['patients'] ?? [];
  $products = $data['products'] ?? [];
  $shipping = $data['shipping'] ?? [];
  $notes = safe($data['notes'] ?? null);
  $adminId = safe($data['admin_id'] ?? null);

  if (!$practiceId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_practice_id']);
    exit;
  }

  if (empty($products)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'no_items']);
    exit;
  }

  // For office stock, patients can be empty
  if (!$isOfficeStock && empty($patients)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'no_patients']);
    exit;
  }

  // Verify practice exists and is valid
  $practiceStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $practiceStmt->execute([$practiceId]);
  $practice = $practiceStmt->fetch(PDO::FETCH_ASSOC);

  if (!$practice) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_practice']);
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
  $orderCounter = $todayCount + 1;

  $ordersCreated = 0;

  // Process orders
  if ($isOfficeStock) {
    // Office Stock: Create orders without real patients (use "Office Stock" patient)
    $officeProducts = $products[0] ?? [];

    // Get or create "Office Stock" patient for this practice
    $officeStockStmt = $pdo->prepare("
      SELECT id FROM patients
      WHERE user_id = ? AND first_name = 'Office' AND last_name = 'Stock'
      LIMIT 1
    ");
    $officeStockStmt->execute([$practiceId]);
    $officeStock = $officeStockStmt->fetch(PDO::FETCH_ASSOC);

    $patientId = null;
    if ($officeStock) {
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
        $practiceId,
        'Office',
        'Stock',
        null,
        null,
        null,
        null
      ]);
    }

    // Get practice address for shipping
    $shippingName = $practice['practice_name'] ?? 'Office Stock';
    $shippingAddress = $practice['address'] ?? null;
    $shippingCity = $practice['city'] ?? null;
    $shippingState = $practice['state'] ?? null;
    $shippingZip = $practice['zip'] ?? null;
    $shippingPhone = null;

    foreach ($officeProducts as $productData) {
      $productId = safe($productData['product_id'] ?? null);
      $boxes = (int)($productData['boxes'] ?? 0);

      if (!$productId || $boxes <= 0) {
        continue; // Skip invalid items
      }

      // Get product details
      $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
      $productStmt->execute([$productId]);
      $product = $productStmt->fetch(PDO::FETCH_ASSOC);

      if (!$product) {
        continue; // Skip if product doesn't exist
      }

      $piecesPerBox = (int)($product['pieces_per_box'] ?? 1);

      // Calculate pricing - check for custom pricing and discounts
      $customPriceStmt = $pdo->prepare("
        SELECT custom_price, discount_percentage
        FROM practice_pricing
        WHERE user_id = ? AND product_id = ?
      ");
      $customPriceStmt->execute([$practiceId, $productId]);
      $customPricing = $customPriceStmt->fetch(PDO::FETCH_ASSOC);

      if ($customPricing && $customPricing['custom_price'] > 0) {
        // Use custom pricing (stored as price per piece)
        $pricePerPiece = (float)$customPricing['custom_price'];
        $pricePerBox = $pricePerPiece * $piecesPerBox;
      } elseif ($customPricing && $customPricing['discount_percentage'] != 0) {
        // Apply percentage discount/upcharge to default wholesale price
        $pricePerBox = (float)($product['price_wholesale'] ?? 0);
        $discountMultiplier = 1 - ((float)$customPricing['discount_percentage'] / 100);
        $pricePerBox = $pricePerBox * $discountMultiplier;
        $pricePerPiece = $piecesPerBox > 0 ? $pricePerBox / $piecesPerBox : 0;
      } else {
        // Use default pricing from products table
        $pricePerBox = (float)($product['price_wholesale'] ?? 0);
        $pricePerPiece = $piecesPerBox > 0 ? $pricePerBox / $piecesPerBox : 0;
      }

      // Generate wholesale order number for this specific order
      $wholesaleOrderNumber = sprintf('WS-%s-%03d', $datePrefix, $orderCounter);
      $orderCounter++;

      // Combine order number with admin notes
      $orderNotes = "Wholesale Order #$wholesaleOrderNumber";
      if (!empty($notes)) {
        $orderNotes .= "\n" . $notes;
      }
      $orderNotes .= "\n[Created by admin: " . ($admin['email'] ?? 'unknown') . "]";

      // Create order
      $orderId = guid();
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
        $practiceId,
        $patientId,
        $product['id'],
        $product['name'],
        $pricePerPiece, // Store per-piece wholesale price
        $boxes, // Number of boxes ordered
        'one-time', // Wholesale orders are typically one-time
        'submitted', // Wholesale orders go straight to submitted
        'approved', // Auto-approve wholesale orders
        'wholesale', // Mark as wholesale payment type
        'practice_dme', // Practice bills their own DME license
        $wholesaleOrderNumber,
        $shippingName,
        $shippingPhone,
        $shippingAddress,
        $shippingCity,
        $shippingState,
        $shippingZip,
        $orderNotes,
        'office' // delivery_mode for office stock
      ]);

      $ordersCreated++;
    }
  } else {
    // Patient Orders: Process each patient and their products
    foreach ($patients as $patientIndex => $patientData) {
      $patientProducts = $products[$patientIndex] ?? [];

      if (empty($patientProducts)) {
        continue; // Skip patients with no products
      }

      $patientFirstName = safe($patientData['first_name'] ?? null);
      $patientLastName = safe($patientData['last_name'] ?? null);
      $patientPhone = safe($patientData['phone'] ?? null);

      if (!$patientFirstName || !$patientLastName) {
        continue; // Skip incomplete patients
      }

      // Check if patient already exists for this practice
      $patientId = null;

      // Try to match by name and phone
      if ($patientPhone) {
        $phoneDigits = preg_replace('/\D/', '', $patientPhone);

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
        $matchStmt->execute([$practiceId, $patientFirstName, $patientLastName, $phoneDigits, $patientPhone]);
        $existingPatient = $matchStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingPatient) {
          $patientId = $existingPatient['id'];
        }
      }

      // Create new patient if not found (auto-approved for wholesale orders)
      if (!$patientId) {
        $patientId = guid();
        $pdo->prepare("
          INSERT INTO patients
            (id, user_id, first_name, last_name, phone, address, city, zip, state, created_at, updated_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW(), NOW())
        ")->execute([
          $patientId,
          $practiceId,
          $patientFirstName,
          $patientLastName,
          $patientPhone,
          safe($patientData['address'] ?? null),
          safe($patientData['city'] ?? null),
          safe($patientData['zip'] ?? null)
        ]);
      }

      // Determine shipping details - check if shipping to office
      $shipToOffice = (($shipping['type'] ?? '') === 'practice');

      if ($shipToOffice) {
        // Get practice address
        $shippingName = $practice['practice_name'] ?? 'Office';
        $shippingAddress = $practice['address'] ?? null;
        $shippingCity = $practice['city'] ?? null;
        $shippingState = $practice['state'] ?? null;
        $shippingZip = $practice['zip'] ?? null;
        $shippingPhone = null;
      } else {
        // Use patient's address
        $shippingName = "$patientFirstName $patientLastName";
        $shippingAddress = safe($patientData['address'] ?? null);
        $shippingCity = safe($patientData['city'] ?? null);
        $shippingState = safe($patientData['state'] ?? null);
        $shippingZip = safe($patientData['zip'] ?? null);
        $shippingPhone = $patientPhone;
      }

      // Create orders for each product for this patient
      foreach ($patientProducts as $productData) {
        $productId = safe($productData['product_id'] ?? null);
        $boxes = (int)($productData['boxes'] ?? 0);

        if (!$productId || $boxes <= 0) {
          continue; // Skip invalid items
        }

        // Get product details
        $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $productStmt->execute([$productId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
          continue; // Skip if product doesn't exist
        }

        $piecesPerBox = (int)($product['pieces_per_box'] ?? 1);

        // Calculate pricing - check for custom pricing and discounts
        $customPriceStmt = $pdo->prepare("
          SELECT custom_price, discount_percentage
          FROM practice_pricing
          WHERE user_id = ? AND product_id = ?
        ");
        $customPriceStmt->execute([$practiceId, $productId]);
        $customPricing = $customPriceStmt->fetch(PDO::FETCH_ASSOC);

        if ($customPricing && $customPricing['custom_price'] > 0) {
          // Use custom pricing (stored as price per piece)
          $pricePerPiece = (float)$customPricing['custom_price'];
          $pricePerBox = $pricePerPiece * $piecesPerBox;
        } elseif ($customPricing && $customPricing['discount_percentage'] != 0) {
          // Apply percentage discount/upcharge to default wholesale price
          $pricePerBox = (float)($product['price_wholesale'] ?? 0);
          $discountMultiplier = 1 - ((float)$customPricing['discount_percentage'] / 100);
          $pricePerBox = $pricePerBox * $discountMultiplier;
          $pricePerPiece = $piecesPerBox > 0 ? $pricePerBox / $piecesPerBox : 0;
        } else {
          // Use default pricing from products table
          $pricePerBox = (float)($product['price_wholesale'] ?? 0);
          $pricePerPiece = $piecesPerBox > 0 ? $pricePerBox / $piecesPerBox : 0;
        }

        // Generate wholesale order number for this specific order
        $wholesaleOrderNumber = sprintf('WS-%s-%03d', $datePrefix, $orderCounter);
        $orderCounter++;

        // Combine order number with admin notes
        $orderNotes = "Wholesale Order #$wholesaleOrderNumber";
        if (!empty($notes)) {
          $orderNotes .= "\n" . $notes;
        }
        $orderNotes .= "\n[Created by admin: " . ($admin['email'] ?? 'unknown') . "]";

        // Create order
        $orderId = guid();
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
          $practiceId,
          $patientId,
          $product['id'],
          $product['name'],
          $pricePerPiece, // Store per-piece wholesale price
          $boxes, // Number of boxes ordered
          'one-time', // Wholesale orders are typically one-time
          'submitted', // Wholesale orders go straight to submitted
          'approved', // Auto-approve wholesale orders
          'wholesale', // Mark as wholesale payment type
          'practice_dme', // Practice bills their own DME license
          $wholesaleOrderNumber,
          $shippingName,
          $shippingPhone,
          $shippingAddress,
          $shippingCity,
          $shippingState,
          $shippingZip,
          $orderNotes,
          $shipToOffice ? 'office' : 'patient' // delivery_mode
        ]);

        $ordersCreated++;
      }
    }
  }

  if ($ordersCreated === 0) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'no_valid_items']);
    exit;
  }

  // Commit transaction
  $pdo->commit();

  // Success response
  echo json_encode([
    'ok' => true,
    'orders_created' => $ordersCreated,
    'message' => "Successfully created $ordersCreated wholesale order(s)"
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log('Admin wholesale order creation error: ' . $e->getMessage());
  error_log('Stack trace: ' . $e->getTraceAsString());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'server_error',
    'message' => 'An error occurred while creating the order: ' . $e->getMessage()
  ]);
}
