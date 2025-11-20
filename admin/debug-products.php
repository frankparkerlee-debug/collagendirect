<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== CHECKING FOR DUPLICATE PRODUCTS ===\n\n";

// Get all active products
$stmt = $pdo->query("
  SELECT id, name, size, price_wholesale, pieces_per_box, category, hcpcs_code, active
  FROM products
  ORDER BY name ASC, size ASC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total products: " . count($products) . "\n\n";

// Track product names to find duplicates
$productNames = [];
foreach ($products as $product) {
  $key = strtolower(trim($product['name']));
  if (!isset($productNames[$key])) {
    $productNames[$key] = [];
  }
  $productNames[$key][] = $product;
}

// Find duplicates
echo "=== DUPLICATE PRODUCT NAMES ===\n\n";
$foundDuplicates = false;
foreach ($productNames as $name => $items) {
  if (count($items) > 1) {
    $foundDuplicates = true;
    echo "Product Name: $name\n";
    echo "Count: " . count($items) . "\n";
    foreach ($items as $item) {
      echo "  - ID: " . substr($item['id'], 0, 12) . "...\n";
      echo "    Full Name: " . $item['name'] . "\n";
      echo "    Size: " . ($item['size'] ?? 'NULL') . "\n";
      echo "    HCPCS: " . ($item['hcpcs_code'] ?? 'NULL') . "\n";
      echo "    Price: $" . ($item['price_wholesale'] ?? '0') . "\n";
      echo "    Active: " . ($item['active'] ? 'YES' : 'NO') . "\n";
      echo "\n";
    }
    echo "---\n\n";
  }
}

if (!$foundDuplicates) {
  echo "No duplicate product names found.\n\n";
}

// Show products that match the pattern mentioned
echo "\n=== PRODUCTS MATCHING 'COLLAGEN' PATTERN ===\n\n";
foreach ($products as $product) {
  if (stripos($product['name'], 'collagen') !== false && stripos($product['name'], '2x2') !== false) {
    echo "ID: " . substr($product['id'], 0, 12) . "...\n";
    echo "Name: " . $product['name'] . "\n";
    echo "Size: " . ($product['size'] ?? 'NULL') . "\n";
    echo "HCPCS: " . ($product['hcpcs_code'] ?? 'NULL') . "\n";
    echo "Active: " . ($product['active'] ? 'YES' : 'NO') . "\n";
    echo "\n";
  }
}
