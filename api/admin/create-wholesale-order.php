<?php
/**
 * Admin API: Create Wholesale Order on Behalf of Practice
 * Handles 3-step workflow with patients, products, and shipping
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

// Only superadmin and manufacturer can create orders on behalf of practices
$adminRole = $admin['role'] ?? '';
if (!in_array($adminRole, ['superadmin', 'manufacturer'])) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'forbidden', 'message' => 'Only superadmin and manufacturer can create orders on behalf of practices']);
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

  if (empty($patients) || empty($products)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'no_items']);
    exit;
  }

  // Verify practice exists and is valid
  $practiceStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND user_type IN ('practice_admin', 'physician', 'dme_wholesale')");
  $practiceStmt->execute([$practiceId]);
  $practice = $practiceStmt->fetch(PDO::FETCH_ASSOC);

  if (!$practice) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_practice']);
    exit;
  }

  // Begin transaction
  $pdo->beginTransaction();

  $ordersCreated = 0;
  $billedBy = $practice['practice_name'] ?? ($practice['first_name'] . ' ' . $practice['last_name']);

  // Process each patient and their products
  foreach ($patients as $patientIndex => $patientData) {
    $patientProducts = $products[$patientIndex] ?? [];

    if (empty($patientProducts)) {
      continue; // Skip patients with no products
    }

    $patientFirstName = safe($patientData['first_name'] ?? null);
    $patientLastName = safe($patientData['last_name'] ?? null);
    $patientDob = safe($patientData['dob'] ?? null);

    if (!$patientFirstName || !$patientLastName || !$patientDob) {
      continue; // Skip incomplete patients
    }

    // Check if patient exists for this practice
    $patientCheckStmt = $pdo->prepare("
      SELECT id FROM patients
      WHERE user_id = ? AND first_name = ? AND last_name = ? AND dob = ?
      LIMIT 1
    ");
    $patientCheckStmt->execute([$practiceId, $patientFirstName, $patientLastName, $patientDob]);
    $existingPatient = $patientCheckStmt->fetch(PDO::FETCH_ASSOC);

    $patientId = null;
    if ($existingPatient) {
      $patientId = $existingPatient['id'];
    } else {
      // Create new patient
      $patientId = guid();
      $patientInsertStmt = $pdo->prepare("
        INSERT INTO patients (id, user_id, first_name, last_name, dob, address, city, state, zip, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
      ");
      $patientInsertStmt->execute([
        $patientId,
        $practiceId,
        $patientFirstName,
        $patientLastName,
        $patientDob,
        safe($patientData['address'] ?? null),
        safe($patientData['city'] ?? null),
        safe($patientData['state'] ?? null),
        safe($patientData['zip'] ?? null)
      ]);
    }

    // Create orders for each product for this patient
    foreach ($patientProducts as $productData) {
      $productId = safe($productData['product_id'] ?? null);
      $boxes = (int)($productData['boxes'] ?? 0);

      if (!$productId || $boxes <= 0) {
        continue; // Skip invalid items
      }

      // Get product details and pricing
      $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
      $productStmt->execute([$productId]);
      $product = $productStmt->fetch(PDO::FETCH_ASSOC);

      if (!$product) {
        continue; // Skip if product doesn't exist
      }

      $piecesPerBox = (int)($product['pieces_per_box'] ?? 1);

      // Check for custom pricing
      $customPriceStmt = $pdo->prepare("
        SELECT custom_price
        FROM practice_pricing
        WHERE user_id = ? AND product_id = ?
      ");
      $customPriceStmt->execute([$practiceId, $productId]);
      $customPricing = $customPriceStmt->fetch(PDO::FETCH_ASSOC);

      if ($customPricing && $customPricing['custom_price'] > 0) {
        $pricePerPiece = (float)$customPricing['custom_price'];
        $pricePerBox = $pricePerPiece * $piecesPerBox;
      } else {
        $pricePerBox = (float)($product['price_wholesale'] ?? 0);
      }

      $totalPieces = $boxes * $piecesPerBox;
      $orderTotal = $boxes * $pricePerBox;

      // Create order
      $orderId = guid();
      $orderInsertStmt = $pdo->prepare("
        INSERT INTO orders (
          id, user_id, patient_id, product_id, product_name, quantity_ordered,
          pieces_per_box, price_per_box, order_total, status, ordered_at,
          billed_by, notes, created_by_admin, created_by_admin_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, TRUE, ?)
      ");
      $orderInsertStmt->execute([
        $orderId,
        $practiceId,
        $patientId,
        $product['id'],
        $product['name'],
        $totalPieces,
        $piecesPerBox,
        $pricePerBox,
        $orderTotal,
        'pending',
        $billedBy,
        $notes,
        $adminId
      ]);

      $ordersCreated++;
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
    'orders_created' => $ordersCreated
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log('Admin wholesale order creation error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'server_error',
    'message' => 'An error occurred while creating the order: ' . $e->getMessage()
  ]);
}
