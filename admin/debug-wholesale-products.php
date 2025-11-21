<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

header('Content-Type: application/json');

// Get first practice user for testing
$userId = $pdo->query("SELECT id FROM users WHERE role = 'practice_admin' LIMIT 1")->fetchColumn();

if (!$userId) {
  echo json_encode(['error' => 'No practice users found']);
  exit;
}

// Run the EXACT query from wholesale-new.php (lines 38-70)
$productsStmt = $pdo->prepare("
  SELECT DISTINCT ON (
    CASE
      WHEN p.hcpcs_code IS NOT NULL AND p.hcpcs_code != '' THEN p.hcpcs_code || '|' || LOWER(TRIM(COALESCE(p.size, '')))
      ELSE 'NO_HCPCS|' || LOWER(TRIM(p.name)) || '|' || LOWER(TRIM(COALESCE(p.size, '')))
    END
  )
    p.*,
    pp.custom_price,
    pp.discount_percentage,
    CASE
      WHEN pp.custom_price IS NOT NULL AND pp.custom_price > 0 THEN pp.custom_price
      WHEN pp.discount_percentage IS NOT NULL AND pp.discount_percentage > 0 THEN
        (p.price_wholesale / p.pieces_per_box) * (1 - pp.discount_percentage / 100)
      ELSE (p.price_wholesale / p.pieces_per_box)
    END as effective_price_per_piece
  FROM products p
  LEFT JOIN practice_pricing pp ON pp.product_id = p.id AND pp.user_id = ?
  WHERE p.active = true
    AND (p.name NOT ILIKE '%deprecated%' OR p.name IS NULL)
    AND (p.category NOT ILIKE '%deprecated%' OR p.category IS NULL)
  ORDER BY
    CASE
      WHEN p.hcpcs_code IS NOT NULL AND p.hcpcs_code != '' THEN p.hcpcs_code || '|' || LOWER(TRIM(COALESCE(p.size, '')))
      ELSE 'NO_HCPCS|' || LOWER(TRIM(p.name)) || '|' || LOWER(TRIM(COALESCE(p.size, '')))
    END,
    CASE WHEN p.hcpcs_code IS NOT NULL AND p.hcpcs_code != '' THEN 0 ELSE 1 END,
    CASE WHEN p.price_wholesale > 0 THEN 0 ELSE 1 END,
    LENGTH(p.name) DESC,
    p.id ASC
");
$productsStmt->execute([$userId]);
$wholesaleProducts = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Run the admin query (no deduplication, just active filter)
$adminProducts = $pdo->query("
  SELECT *
  FROM products
  WHERE active = true
    AND (name NOT ILIKE '%deprecated%' OR name IS NULL)
    AND (category NOT ILIKE '%deprecated%' OR category IS NULL)
  ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Look for Calcium Alginate specifically
$calciumQuery = $pdo->query("
  SELECT id, name, hcpcs_code, size, active, category
  FROM products
  WHERE name ILIKE '%calcium%' AND name ILIKE '%alginate%'
  ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$result = [
  'test_user_id' => $userId,
  'wholesale_product_count' => count($wholesaleProducts),
  'admin_product_count' => count($adminProducts),
  'difference' => count($adminProducts) - count($wholesaleProducts),
  'calcium_alginate_products' => $calciumQuery,
  'calcium_in_wholesale' => array_values(array_filter($wholesaleProducts, function($p) {
    return stripos($p['name'], 'calcium') !== false && stripos($p['name'], 'alginate') !== false;
  })),
  'sample_wholesale_products' => array_slice($wholesaleProducts, 0, 10),
  'sample_admin_products' => array_slice($adminProducts, 0, 10),
];

// Find products in admin but not in wholesale
$adminIds = array_column($adminProducts, 'id');
$wholesaleIds = array_column($wholesaleProducts, 'id');
$missingIds = array_diff($adminIds, $wholesaleIds);

if (!empty($missingIds)) {
  $placeholders = implode(',', array_fill(0, count($missingIds), '?'));
  $stmt = $pdo->prepare("SELECT id, name, hcpcs_code, size FROM products WHERE id IN ($placeholders)");
  $stmt->execute(array_values($missingIds));
  $result['products_missing_in_wholesale'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($result, JSON_PRETTY_PRINT);
