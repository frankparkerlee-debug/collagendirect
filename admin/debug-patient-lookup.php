<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== Patient Phone Lookup Debug ===\n\n";

// Get all patients and their phone numbers
$stmt = $pdo->query("
    SELECT id, first_name, last_name, phone
    FROM patients
    ORDER BY created_at DESC
    LIMIT 20
");
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Recent Patients:\n";
echo "----------------\n";
foreach ($patients as $p) {
    echo "{$p['first_name']} {$p['last_name']}\n";
    echo "  Phone: {$p['phone']}\n";
    echo "  ID: {$p['id']}\n\n";
}

// Test phone number lookup
$testPhone = '3057836633';
echo "\nTesting lookup for phone: {$testPhone}\n";
echo "Trying variants:\n";
echo "  1. {$testPhone}\n";
echo "  2. +1{$testPhone}\n";

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, phone
    FROM patients
    WHERE phone = ? OR phone = ? OR phone = ?
");
$stmt->execute([$testPhone, '+1' . $testPhone, preg_replace('/[^0-9]/', '', $testPhone)]);
$found = $stmt->fetch(PDO::FETCH_ASSOC);

if ($found) {
    echo "\n✓ Found: {$found['first_name']} {$found['last_name']}\n";
    echo "  Stored phone: {$found['phone']}\n";
} else {
    echo "\n✗ No patient found\n";
}
