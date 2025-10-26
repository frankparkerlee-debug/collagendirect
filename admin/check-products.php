<?php
require __DIR__ . '/../api/db.php';
header('Content-Type: text/plain');

echo "=== Current Products ===\n\n";

$stmt = $pdo->query("SELECT id, name, size, sku, cpt_code, price_admin, active FROM products ORDER BY name, size");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $p) {
    echo "ID: {$p['id']}\n";
    echo "Name: {$p['name']}\n";
    echo "Size: {$p['size']}\n";
    echo "SKU: {$p['sku']}\n";
    echo "CPT/HCPCS: {$p['cpt_code']}\n";
    echo "Price: \${$p['price_admin']}\n";
    echo "Active: " . ($p['active'] ? 'Yes' : 'No') . "\n";
    echo "---\n\n";
}

echo "\nTotal products: " . count($products) . "\n";
