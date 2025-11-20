<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== ALL PRODUCTS (ACTIVE AND INACTIVE) ===\n\n";

$stmt = $pdo->query("
  SELECT id, name, size, price_wholesale, pieces_per_box, hcpcs_code, active
  FROM products
  ORDER BY name ASC, size ASC, active DESC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $p) {
  $id = substr($p['id'], 0, 12);
  $active = $p['active'] ? 'ACTIVE' : 'inactive';
  $name = $p['name'];
  $size = $p['size'] ?? 'N/A';
  $hcpcs = $p['hcpcs_code'] ?? 'N/A';
  $price = $p['price_wholesale'] ?? 0;
  $pieces = $p['pieces_per_box'] ?? 0;
  
  echo "$id... | $active | $name | Size: $size | HCPCS: $hcpcs | \$$price | $pieces pcs/box\n";
}

echo "\n\nTotal: " . count($products) . " products\n";
