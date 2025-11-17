<?php
/**
 * Add Hydrapad products to the products table
 * Run this once to add missing Hydrapad products
 */
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

$hydrapadProducts = [
  ['name' => 'Hydrapad Adherent 2x2', 'sku' => 'HYDRA-ADH-22', 'pieces_per_box' => 10, 'price_wholesale' => 25.00],
  ['name' => 'Hydrapad Adherent 4x4', 'sku' => 'HYDRA-ADH-44', 'pieces_per_box' => 10, 'price_wholesale' => 45.00],
  ['name' => 'Hydrapad Adherent 6x6', 'sku' => 'HYDRA-ADH-66', 'pieces_per_box' => 5, 'price_wholesale' => 55.00],
  ['name' => 'Hydrapad Non-Adherent 2x2', 'sku' => 'HYDRA-NADH-22', 'pieces_per_box' => 10, 'price_wholesale' => 25.00],
  ['name' => 'Hydrapad Non-Adherent 4x4', 'sku' => 'HYDRA-NADH-44', 'pieces_per_box' => 10, 'price_wholesale' => 45.00],
  ['name' => 'Hydrapad Non-Adherent 6x6', 'sku' => 'HYDRA-NADH-66', 'pieces_per_box' => 5, 'price_wholesale' => 55.00],
];

$added = 0;
$skipped = 0;

foreach ($hydrapadProducts as $product) {
  // Check if product already exists
  $checkStmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
  $checkStmt->execute([$product['name']]);
  $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

  if ($existing) {
    echo "SKIPPED: {$product['name']} (already exists)\n";
    $skipped++;
    continue;
  }

  // Insert new product
  $insertStmt = $pdo->prepare("
    INSERT INTO products (name, sku, pieces_per_box, price_wholesale, active, created_at)
    VALUES (?, ?, ?, ?, TRUE, NOW())
  ");
  $insertStmt->execute([
    $product['name'],
    $product['sku'],
    $product['pieces_per_box'],
    $product['price_wholesale']
  ]);

  echo "ADDED: {$product['name']} (SKU: {$product['sku']}) - {$product['pieces_per_box']} pieces/box @ \${$product['price_wholesale']}\n";
  $added++;
}

echo "\n=== Summary ===\n";
echo "Added: $added products\n";
echo "Skipped: $skipped products (already existed)\n";
echo "\nDone!\n";
