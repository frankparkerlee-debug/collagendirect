<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== CHECKING ORDER PRODUCT IDs ===\n\n";

$stmt = $pdo->query("
  SELECT
    id,
    product,
    product_id,
    product_price,
    billed_by,
    status
  FROM orders
  WHERE status NOT IN ('draft', 'rejected', 'cancelled')
  ORDER BY created_at DESC
  LIMIT 10
");

echo "Recent orders:\n\n";
foreach ($stmt->fetchAll() as $order) {
  $orderId = substr($order['id'], 0, 12);
  echo "Order: $orderId...\n";
  echo "  product (text): " . ($order['product'] ?: 'NULL') . "\n";
  echo "  product_id: " . ($order['product_id'] ?: 'NULL') . "\n";
  echo "  product_price: $" . ($order['product_price'] ?: '0') . "\n";
  echo "  billed_by: " . ($order['billed_by'] ?: 'NULL') . "\n";
  echo "  status: " . $order['status'] . "\n";

  if (!$order['product_id']) {
    echo "  ⚠️ PROBLEM: product_id is NULL - cannot join to products table!\n";
  }
  echo "\n";
}

echo "\n=== PRODUCTS TABLE ===\n";
$products = $pdo->query("SELECT id, name, pieces_per_box, price_admin FROM products WHERE active = TRUE LIMIT 5")->fetchAll();
echo "Sample products:\n\n";
foreach ($products as $p) {
  echo "ID: " . $p['id'] . "\n";
  echo "  Name: " . $p['name'] . "\n";
  echo "  Pieces/box: " . ($p['pieces_per_box'] ?: 'NULL') . "\n";
  echo "  Price admin: $" . ($p['price_admin'] ?: '0') . "\n\n";
}
