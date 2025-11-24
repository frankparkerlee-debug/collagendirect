<?php
/**
 * Check and fix pieces_per_box for common wound care products
 */
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

echo "=== PRODUCTS PIECES PER BOX ===\n\n";

$stmt = $pdo->query("
  SELECT id, name, hcpcs_code, pieces_per_box
  FROM products
  ORDER BY name
");

echo str_repeat("-", 100) . "\n";
printf("%-50s | %-12s | %s\n", "Product Name", "HCPCS", "Pieces/Box");
echo str_repeat("-", 100) . "\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  printf("%-50s | %-12s | %d\n",
    substr($row['name'], 0, 50),
    $row['hcpcs_code'] ?? 'N/A',
    $row['pieces_per_box']
  );
}

echo str_repeat("-", 100) . "\n\n";

echo "Common wound care products typically have:\n";
echo "- Collagen dressings: 1 piece per package\n";
echo "- Foam dressings: 1 piece per package\n";
echo "- Gauze: 10-100 pieces per box\n";
echo "- Tubing sets: 1 set per package\n\n";

echo "If pieces_per_box is wrong, update with:\n";
echo "UPDATE products SET pieces_per_box = X WHERE id = 'PRODUCT_ID';\n";
