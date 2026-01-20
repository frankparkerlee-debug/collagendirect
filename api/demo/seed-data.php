<?php
/**
 * Demo Data Seeder
 * Generates synthetic patient and order data for demo sessions
 */
declare(strict_types=1);

/**
 * Generate a random UUID
 */
function demoGuid(): string {
    return bin2hex(random_bytes(16));
}

/**
 * Get random item from array
 */
function randomItem(array $arr) {
    return $arr[array_rand($arr)];
}

/**
 * Seed demo data for a new session
 */
function seedDemoData(PDO $pdo, string $sessionId): array {
    // Synthetic first names
    $firstNames = ['James', 'Mary', 'Robert', 'Patricia', 'Michael', 'Jennifer', 'William', 'Linda', 'David', 'Elizabeth'];

    // Synthetic last names
    $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];

    // Insurance providers
    $insurers = ['Medicare', 'Blue Cross Blue Shield', 'Aetna', 'UnitedHealthcare', 'Cigna', 'Humana'];

    // Wound locations
    $woundLocations = ['Left Lower Leg', 'Right Lower Leg', 'Left Foot', 'Right Foot', 'Left Ankle', 'Right Ankle', 'Sacrum', 'Left Heel', 'Right Heel'];

    // Wound types
    $woundTypes = ['Venous Ulcer', 'Diabetic Ulcer', 'Pressure Ulcer', 'Surgical Wound', 'Arterial Ulcer'];

    // Cities by state
    $locations = [
        ['city' => 'Los Angeles', 'state' => 'CA', 'zip' => '90001'],
        ['city' => 'Houston', 'state' => 'TX', 'zip' => '77001'],
        ['city' => 'Phoenix', 'state' => 'AZ', 'zip' => '85001'],
        ['city' => 'Chicago', 'state' => 'IL', 'zip' => '60601'],
        ['city' => 'Miami', 'state' => 'FL', 'zip' => '33101'],
    ];

    // Street names for addresses
    $streets = ['Main St', 'Oak Ave', 'Maple Dr', 'Cedar Ln', 'Pine Rd', 'Elm St', 'Washington Blvd', 'Park Ave'];

    $patientIds = [];
    $orderIds = [];

    // Create 5 demo patients
    for ($i = 0; $i < 5; $i++) {
        $patientId = demoGuid();
        $firstName = randomItem($firstNames);
        $lastName = randomItem($lastNames);
        $location = randomItem($locations);

        // Generate DOB (ages 45-85)
        $age = rand(45, 85);
        $dob = date('Y-m-d', strtotime("-{$age} years -" . rand(0, 364) . " days"));

        // Generate MRN
        $mrn = 'DEMO-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);

        // Generate phone
        $phone = sprintf('(%03d) %03d-%04d', rand(200, 999), rand(200, 999), rand(1000, 9999));

        // Generate address
        $address = rand(100, 9999) . ' ' . randomItem($streets);

        $stmt = $pdo->prepare("
            INSERT INTO demo_patients (
                id, demo_session_id, first_name, last_name, dob, sex, mrn,
                phone, email, address, city, state, zip,
                insurance_provider, insurance_member_id, insurance_group_number,
                wound_location, wound_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $patientId,
            $sessionId,
            $firstName,
            $lastName,
            $dob,
            rand(0, 1) ? 'Male' : 'Female',
            $mrn,
            $phone,
            strtolower($firstName) . '.' . strtolower($lastName) . '@example.com',
            $address,
            $location['city'],
            $location['state'],
            $location['zip'],
            randomItem($insurers),
            strtoupper(substr($lastName, 0, 3)) . rand(100000, 999999),
            'GRP' . rand(10000, 99999),
            randomItem($woundLocations),
            randomItem($woundTypes)
        ]);

        $patientIds[] = $patientId;
    }

    // Get real products from the database for realistic orders
    $products = [];
    try {
        $productStmt = $pdo->query("SELECT id, name, size FROM products WHERE is_active = TRUE LIMIT 5");
        $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Fallback products if table doesn't exist or is empty
        $products = [
            ['id' => 1, 'name' => 'CollagenMatrix', 'size' => '2x2 cm'],
            ['id' => 2, 'name' => 'CollagenMatrix', 'size' => '4x4 cm'],
            ['id' => 3, 'name' => 'CollagenMatrix', 'size' => '5x5 cm'],
        ];
    }

    // Order statuses for variety
    $statuses = ['submitted', 'approved', 'in_transit', 'delivered', 'pending'];

    // Create 3 demo orders for different patients
    $orderCount = min(3, count($patientIds));
    for ($i = 0; $i < $orderCount; $i++) {
        $orderId = demoGuid();
        $patientId = $patientIds[$i];
        $product = randomItem($products);
        $status = $statuses[$i]; // First 3 statuses for variety

        // Get patient data for shipping
        $patientStmt = $pdo->prepare("SELECT * FROM demo_patients WHERE id = ?");
        $patientStmt->execute([$patientId]);
        $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

        // Generate order number
        $orderNumber = 'DEMO-' . date('Ymd') . '-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO demo_orders (
                id, demo_session_id, demo_patient_id, order_number,
                product, product_id, product_size, quantity, status,
                payment_type, billed_by, delivery_mode, frequency,
                shipping_name, shipping_address, shipping_city, shipping_state, shipping_zip,
                tracking_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $trackingNumber = null;
        if ($status === 'in_transit' || $status === 'delivered') {
            $trackingNumber = '1Z' . strtoupper(bin2hex(random_bytes(8)));
        }

        $stmt->execute([
            $orderId,
            $sessionId,
            $patientId,
            $orderNumber,
            $product['name'] ?? 'CollagenMatrix',
            $product['id'] ?? 1,
            $product['size'] ?? '4x4 cm',
            1,
            $status,
            'referral',
            'collagen_direct',
            'patient',
            'Weekly',
            ($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''),
            $patient['address'] ?? '',
            $patient['city'] ?? '',
            $patient['state'] ?? '',
            $patient['zip'] ?? '',
            $trackingNumber
        ]);

        $orderIds[] = $orderId;
    }

    return [
        'patients_created' => count($patientIds),
        'orders_created' => count($orderIds),
        'patient_ids' => $patientIds,
        'order_ids' => $orderIds
    ];
}

// If called directly (for manual seeding/testing)
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');

    require_once __DIR__ . '/../../admin/db.php';

    $sessionId = $_GET['session_id'] ?? $_SESSION['demo_session_id'] ?? null;

    if (!$sessionId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No session ID provided']);
        exit;
    }

    try {
        $result = seedDemoData($pdo, $sessionId);
        echo json_encode(['ok' => true, 'data' => $result]);
    } catch (Throwable $e) {
        error_log('[demo/seed-data] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to seed data']);
    }
}
