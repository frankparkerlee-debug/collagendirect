<?php
// API endpoint to create demo data
require __DIR__ . '/db.php';

// Get email from POST data or default to Parker
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? 'parker@senecawest.com';

// Get user ID
$stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    json_out(404, ['error' => 'User not found: ' . $email]);
}

$userId = $user['id'];
$results = ['user_id' => $userId, 'patients' => [], 'orders' => []];

// Helper function to generate ID
function generateId() {
    return rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=');
}

// Demo patients
$patients = [
    ['first_name' => 'Margaret', 'last_name' => 'Thompson', 'dob' => '1945-03-15', 'sex' => 'Female', 'phone' => '5551234567', 'email' => 'margaret.thompson@email.com', 'address' => '123 Oak Street', 'city' => 'Buffalo', 'state' => 'NY', 'zip' => '14201', 'insurance_provider' => 'Medicare', 'insurance_id' => 'MED123456789A', 'mrn' => 'MRN001234'],
    ['first_name' => 'Robert', 'last_name' => 'Johnson', 'dob' => '1952-07-22', 'sex' => 'Male', 'phone' => '5552345678', 'email' => 'robert.johnson@email.com', 'address' => '456 Maple Avenue', 'city' => 'Rochester', 'state' => 'NY', 'zip' => '14610', 'insurance_provider' => 'Blue Cross Blue Shield', 'insurance_id' => 'BCBS987654321', 'mrn' => 'MRN001235'],
    ['first_name' => 'Patricia', 'last_name' => 'Williams', 'dob' => '1958-11-30', 'sex' => 'Female', 'phone' => '5553456789', 'email' => 'patricia.williams@email.com', 'address' => '789 Pine Road', 'city' => 'Syracuse', 'state' => 'NY', 'zip' => '13202', 'insurance_provider' => 'United Healthcare', 'insurance_id' => 'UHC456789123', 'mrn' => 'MRN001236'],
    ['first_name' => 'James', 'last_name' => 'Davis', 'dob' => '1960-05-18', 'sex' => 'Male', 'phone' => '5554567890', 'email' => 'james.davis@email.com', 'address' => '321 Elm Street', 'city' => 'Albany', 'state' => 'NY', 'zip' => '12203', 'insurance_provider' => 'Aetna', 'insurance_id' => 'AET789456123', 'mrn' => 'MRN001237'],
    ['first_name' => 'Linda', 'last_name' => 'Martinez', 'dob' => '1948-09-08', 'sex' => 'Female', 'phone' => '5555678901', 'email' => 'linda.martinez@email.com', 'address' => '654 Cedar Lane', 'city' => 'Yonkers', 'state' => 'NY', 'zip' => '10701', 'insurance_provider' => 'Medicare', 'insurance_id' => 'MED987654321B', 'mrn' => 'MRN001238'],
    ['first_name' => 'Michael', 'last_name' => 'Garcia', 'dob' => '1955-12-25', 'sex' => 'Male', 'phone' => '5556789012', 'email' => 'michael.garcia@email.com', 'address' => '987 Birch Avenue', 'city' => 'White Plains', 'state' => 'NY', 'zip' => '10601', 'insurance_provider' => 'Cigna', 'insurance_id' => 'CIG123789456', 'mrn' => 'MRN001239'],
    ['first_name' => 'Barbara', 'last_name' => 'Rodriguez', 'dob' => '1950-04-14', 'sex' => 'Female', 'phone' => '5557890123', 'email' => 'barbara.rodriguez@email.com', 'address' => '159 Walnut Drive', 'city' => 'New Rochelle', 'state' => 'NY', 'zip' => '10801', 'insurance_provider' => 'Humana', 'insurance_id' => 'HUM456123789', 'mrn' => 'MRN001240'],
    ['first_name' => 'William', 'last_name' => 'Wilson', 'dob' => '1962-08-03', 'sex' => 'Male', 'phone' => '5558901234', 'email' => 'william.wilson@email.com', 'address' => '753 Spruce Court', 'city' => 'Ithaca', 'state' => 'NY', 'zip' => '14850', 'insurance_provider' => 'Medicare Advantage', 'insurance_id' => 'MEDA789123456', 'mrn' => 'MRN001241']
];

// Get or create products
global $pdo;
$products = $pdo->query("SELECT id, name, price_admin FROM products WHERE active = TRUE LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    $sampleProducts = [
        ['Collagen Matrix Sheet 4x4', 125.00],
        ['Collagen Powder 1g', 85.00],
        ['Collagen Gel 30ml', 95.00],
        ['Antimicrobial Collagen Sheet', 145.00],
        ['Collagen Wound Filler', 110.00]
    ];

    foreach ($sampleProducts as list($name, $price)) {
        $productId = generateId();
        $pdo->prepare("INSERT INTO products (id, name, price_admin, active, created_at) VALUES (?, ?, ?, TRUE, NOW())")->execute([$productId, $name, $price]);
    }

    $products = $pdo->query("SELECT id, name, price_admin FROM products WHERE active = TRUE LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
}

$orderStatuses = ['active', 'active', 'active', 'submitted', 'submitted', 'approved', 'shipped', 'stopped'];
$frequencies = ['daily', 'every 3 days', 'weekly', 'biweekly', 'monthly'];
$deliveryModes = ['patient', 'patient', 'patient', 'office'];

try {
    foreach ($patients as $patientData) {
        $patientId = generateId();

        $stmt = $pdo->prepare("
            INSERT INTO patients (
                id, user_id, first_name, last_name, dob, sex, phone, email,
                address, city, state, zip, insurance_provider, insurance_member_id, mrn,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $patientId, $userId,
            $patientData['first_name'], $patientData['last_name'],
            $patientData['dob'], $patientData['sex'],
            $patientData['phone'], $patientData['email'],
            $patientData['address'], $patientData['city'],
            $patientData['state'], $patientData['zip'],
            $patientData['insurance_provider'], $patientData['insurance_id'],
            $patientData['mrn']
        ]);

        $results['patients'][] = $patientData['first_name'] . ' ' . $patientData['last_name'];

        // Create 1-3 orders per patient
        $numOrders = rand(1, 3);
        for ($i = 0; $i < $numOrders; $i++) {
            $product = $products[array_rand($products)];
            $status = $orderStatuses[array_rand($orderStatuses)];
            $frequency = $frequencies[array_rand($frequencies)];
            $deliveryMode = $deliveryModes[array_rand($deliveryModes)];
            $shipmentsRemaining = ($status === 'stopped' || $status === 'shipped') ? 0 : rand(2, 12);
            $daysAgo = rand(1, 90);
            $orderDate = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));

            $orderId = generateId();

            $orderStmt = $pdo->prepare("
                INSERT INTO orders (
                    id, patient_id, user_id, product, product_id, product_price, status,
                    shipments_remaining, delivery_mode, frequency, payment_type,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $orderStmt->execute([
                $orderId, $patientId, $userId,
                $product['name'], $product['id'], $product['price_admin'],
                $status, $shipmentsRemaining,
                $deliveryMode, $frequency, 'insurance', $orderDate
            ]);

            $results['orders'][] = [
                'patient' => $patientData['first_name'] . ' ' . $patientData['last_name'],
                'product' => $product['name'],
                'status' => $status
            ];
        }
    }

    json_out(201, [
        'success' => true,
        'message' => 'Demo data created successfully',
        'email' => $email,
        'user_id' => $userId,
        'patients_created' => count($results['patients']),
        'orders_created' => count($results['orders']),
        'results' => $results
    ]);

} catch (PDOException $e) {
    json_out(500, ['error' => 'Database error: ' . $e->getMessage()]);
}
