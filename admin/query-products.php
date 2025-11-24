#!/usr/bin/env php
<?php
/**
 * Query and display complete products database
 */
require_once __DIR__ . '/../api/db.php';

$stmt = $pdo->query("
  SELECT *
  FROM products
  ORDER BY name
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
  echo "No products found in database.\n";
  exit(1);
}

echo "=== COMPLETE PRODUCTS DATABASE ===\n\n";
echo "Total Products: " . count($products) . "\n\n";
echo str_repeat("=", 120) . "\n";

foreach ($products as $idx => $product) {
  echo "PRODUCT #" . ($idx + 1) . "\n";
  echo str_repeat("-", 120) . "\n";

  foreach ($product as $key => $value) {
    $displayValue = $value ?? 'NULL';
    if (is_string($displayValue) && strlen($displayValue) > 100) {
      $displayValue = substr($displayValue, 0, 100) . '...';
    }
    echo str_pad($key . ':', 30) . $displayValue . "\n";
  }

  echo "\n";
}

echo "\n" . str_repeat("=", 120) . "\n";
echo "KEY FIELDS SUMMARY\n";
echo str_repeat("=", 120) . "\n";
printf("%-50s | %-15s | %-12s | %-12s | %-15s\n", "Product Name", "HCPCS", "Pieces/Box", "Price", "Wholesale");
echo str_repeat("-", 120) . "\n";

foreach ($products as $product) {
  printf("%-50s | %-15s | %-12s | $%-11s | $%-14s\n",
    substr($product['name'] ?? 'Unknown', 0, 50),
    $product['hcpcs_code'] ?? 'N/A',
    $product['pieces_per_box'] ?? 'N/A',
    number_format((float)($product['price'] ?? 0), 2),
    number_format((float)($product['price_wholesale'] ?? 0), 2)
  );
}
