<?php
// Add sample data to test the portal
// Run via: https://collagendirect.onrender.com/add-sample-data.php?token=temp-setup-token-2024

$token = $_GET['token'] ?? '';
if ($token !== 'temp-setup-token-2024') {
    http_response_code(403);
    die('Access denied');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== Adding Sample Data ===\n\n";

require __DIR__ . '/api/db.php';

// Get user ID
$user = $pdo->query("SELECT id FROM users WHERE email = 'sparkingmatt@gmail.com'")->fetch();
if (!$user) {
    die("User not found\n");
}
$userId = $user['id'];

echo "User ID: $userId\n\n";

// Add sample patients
$patients = [
    ['first_name' => 'John', 'last_name' => 'Smith', 'dob' => '1965-03-15', 'phone' => '5551234567'],
    ['first_name' => 'Mary', 'last_name' => 'Wilson', 'dob' => '1972-07-22', 'phone' => '5559876543'],
    ['first_name' => 'Robert', 'last_name' => 'Johnson', 'dob' => '1958-11-30', 'phone' => '5555551234'],
];

echo "Adding patients...\n";
foreach ($patients as $p) {
    $patientId = rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=');
    $pdo->prepare("INSERT INTO patients (id, user_id, first_name, last_name, dob, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())")
        ->execute([$patientId, $userId, $p['first_name'], $p['last_name'], $p['dob'], $p['phone']]);
    echo "  ✓ {$p['first_name']} {$p['last_name']}\n";
}

echo "\n=== Sample Data Added! ===\n";
echo "Refresh your dashboard to see the data.\n";
echo "\n⚠️  DELETE this file after use!\n";
