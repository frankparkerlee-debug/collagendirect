<?php
/**
 * Test Order Creation - Debug Script
 * Simulates an order creation request to help diagnose issues
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../db.php';

echo "=== Order Creation Test ===\n\n";

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    echo "✗ Not logged in\n";
    echo "Please log in to the portal first, then run this script.\n";
    exit(1);
}

$user_id = $_SESSION['user_id'];
echo "✓ Logged in as user: $user_id\n\n";

// Get user info
$user_stmt = $pdo->prepare("SELECT first_name, last_name, email, role FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "User details:\n";
    echo "  Name: {$user['first_name']} {$user['last_name']}\n";
    echo "  Email: {$user['email']}\n";
    echo "  Role: {$user['role']}\n\n";
} else {
    echo "✗ User not found in database\n";
    exit(1);
}

// Check for active products
$products_stmt = $pdo->query("SELECT id, name, price_admin, cpt_code FROM products WHERE active = TRUE LIMIT 5");
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Active products (" . count($products) . " found):\n";
foreach ($products as $prod) {
    echo "  ID: {$prod['id']}, Name: {$prod['name']}, Price: {$prod['price_admin']}, CPT: {$prod['cpt_code']}\n";
}
echo "\n";

// Check for patients
$patients_stmt = $pdo->prepare("SELECT id, first_name, last_name, mrn FROM patients WHERE user_id = ? LIMIT 5");
$patients_stmt->execute([$user_id]);
$patients = $patients_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Your patients (" . count($patients) . " found):\n";
foreach ($patients as $patient) {
    echo "  ID: {$patient['id']}, Name: {$patient['first_name']} {$patient['last_name']}, MRN: {$patient['mrn']}\n";
}
echo "\n";

// Simulate a minimal wounds_data payload
if (count($products) > 0 && count($patients) > 0) {
    $test_product = $products[0];
    $test_patient = $patients[0];

    echo "=== Sample wounds_data JSON ===\n\n";

    $wounds_data = [[
        'product_id' => $test_product['id'],
        'product_name' => $test_product['name'],
        'product_cpt' => $test_product['cpt_code'],
        'product_price' => $test_product['price_admin'],
        'pieces_per_box' => 10,

        'frequency_per_week' => 3,
        'qty_per_change' => 1,
        'duration_days' => 30,

        'location' => 'Left Heel',
        'laterality' => 'Left',
        'length_cm' => 5.0,
        'width_cm' => 3.0,
        'depth_cm' => 0.5,
        'type' => 'Pressure Ulcer',
        'stage' => 'III',
        'exudate_level' => 'moderate',
        'icd10_primary' => 'L89.622',
        'icd10_secondary' => '',
        'notes' => 'Test wound for debugging'
    ]];

    echo json_encode($wounds_data, JSON_PRETTY_PRINT) . "\n\n";

    echo "=== Test POST Data ===\n\n";
    echo "patient_id: {$test_patient['id']}\n";
    echo "payment_type: insurance\n";
    echo "wounds_data: [JSON array above]\n";
    echo "delivery_to: patient\n";
    echo "shipping_name: {$test_patient['first_name']} {$test_patient['last_name']}\n";
    echo "sign_name: {$user['first_name']} {$user['last_name']}\n";
    echo "sign_title: Physician\n";
    echo "esign_confirm: 1\n\n";

    echo "=== To test manually ===\n";
    echo "Use the browser console or Postman to POST this data to:\n";
    echo "https://collagendirect.health/api/portal/orders.create.php\n\n";

    echo "Or use the portal's order form with these values.\n";
}

echo "\n=== Check Complete ===\n";
