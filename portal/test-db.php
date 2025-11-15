<?php
/**
 * Database Connection Test Script
 */
header('Content-Type: text/plain');

echo "=== Database Connection Test ===\n\n";

try {
    require_once __DIR__ . '/../api/db.php';

    echo "✓ Database connection successful!\n\n";

    // Test 1: Count users
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Users in database: $userCount\n";

    // Test 2: Count products
    $productCount = $pdo->query("SELECT COUNT(*) FROM products WHERE active = TRUE")->fetchColumn();
    echo "Active products: $productCount\n";

    // Test 3: Count patients
    $patientCount = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    echo "Patients in database: $patientCount\n";

    // Test 4: List some products
    echo "\n--- Sample Products ---\n";
    $products = $pdo->query("SELECT id, name, sku, price_wholesale FROM products WHERE active = TRUE LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($products as $p) {
        echo "- {$p['name']} (SKU: {$p['sku']}, Price: \${$p['price_wholesale']})\n";
    }

    echo "\n✓ All database tests passed!\n";

} catch (Throwable $e) {
    echo "\n✗ Database connection FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    http_response_code(500);
}
