<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== CHECKING FOR SIMILAR PRODUCT NAMES ===\n\n";

$stmt = $pdo->query("
  SELECT id, name, size, hcpcs_code, price_wholesale, pieces_per_box, active
  FROM products
  WHERE active = TRUE
    AND name NOT ILIKE '%deprecated%'
  ORDER BY 
    CASE 
      WHEN name ILIKE '%collagen dressing%' THEN 1
      WHEN name ILIKE '%collagen drx%' THEN 2
      ELSE 3
    END,
    size ASC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Products with 'Collagen Dressing' or 'Collagen Drx':\n\n";

foreach ($products as $p) {
  if (stripos($p['name'], 'collagen') !== false) {
    echo "Name: " . $p['name'] . "\n";
    echo "  Size: " . ($p['size'] ?? 'N/A') . "\n";
    echo "  HCPCS: " . ($p['hcpcs_code'] ?? 'N/A') . "\n";
    echo "  Price: $" . ($p['price_wholesale'] ?? 0) . "\n";
    echo "  ID: " . substr($p['id'], 0, 12) . "...\n\n";
  }
}
