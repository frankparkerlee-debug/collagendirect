<?php
/**
 * Query products table to see actual product names and SKUs
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
  global $pdo;

  echo "=== Products in Database ===\n\n";

  $stmt = $pdo->query("
    SELECT id, name, sku, price_admin, price_wholesale, pieces_per_box
    FROM products
    ORDER BY name
  ");

  $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Total products: " . count($products) . "\n\n";

  foreach ($products as $product) {
    echo "ID: {$product['id']}\n";
    echo "Name: {$product['name']}\n";
    echo "SKU: " . ($product['sku'] ?: 'NULL') . "\n";
    echo "Price Admin: " . ($product['price_admin'] ?: 'NULL') . "\n";
    echo "Price Wholesale: " . ($product['price_wholesale'] ?: 'NULL') . "\n";
    echo "Pieces per Box: " . ($product['pieces_per_box'] ?: 'NULL') . "\n";
    echo "---\n";
  }

} catch (PDOException $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
