<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

header('Content-Type: application/json');

$result = [];

// Get a test practice user ID (first non-admin user)
$testUserId = $pdo->query("
  SELECT id FROM users
  WHERE role = 'practice_admin'
  LIMIT 1
")->fetchColumn();

$result['test_user_id'] = $testUserId;

// ADMIN QUERY (from products.php)
$adminProducts = $pdo->query("
  SELECT id, name, size, hcpcs_code, category, active, price_wholesale, price_referral, pieces_per_box
  FROM products
  WHERE active = TRUE
    AND (name NOT ILIKE '%deprecated%' OR name IS NULL)
    AND (category NOT ILIKE '%deprecated%' OR category IS NULL)
  ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$result['admin_products_count'] = count($adminProducts);
$result['admin_products'] = array_slice($adminProducts, 0, 20); // First 20

// WHOLESALE QUERY (from portal/wholesale-new.php)
$wholesaleStmt = $pdo->prepare("
  SELECT DISTINCT ON (
    CASE
      WHEN p.hcpcs_code IS NOT NULL AND p.hcpcs_code != '' THEN p.hcpcs_code || '|' || LOWER(TRIM(COALESCE(p.size, '')))
      ELSE 'NO_HCPCS|' || LOWER(TRIM(p.name)) || '|' || LOWER(TRIM(COALESCE(p.size, '')))
    END
  )
    p.id,
    p.name,
    p.size,
    p.hcpcs_code,
    p.category,
    p.active,
    p.price_wholesale,
    p.price_referral,
    p.pieces_per_box,
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
$wholesaleStmt->execute([$testUserId]);
$wholesaleProducts = $wholesaleStmt->fetchAll(PDO::FETCH_ASSOC);

$result['wholesale_products_count'] = count($wholesaleProducts);
$result['wholesale_products'] = array_slice($wholesaleProducts, 0, 20); // First 20

// Find products in admin but NOT in wholesale
$adminIds = array_column($adminProducts, 'id');
$wholesaleIds = array_column($wholesaleProducts, 'id');
$missingInWholesale = array_diff($adminIds, $wholesaleIds);

$result['missing_in_wholesale_count'] = count($missingInWholesale);

if (!empty($missingInWholesale)) {
  $placeholders = implode(',', array_fill(0, count($missingInWholesale), '?'));
  $stmt = $pdo->prepare("
    SELECT id, name, size, hcpcs_code, category, price_wholesale, price_referral, pieces_per_box
    FROM products
    WHERE id IN ($placeholders)
  ");
  $stmt->execute(array_values($missingInWholesale));
  $result['missing_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check for specific product (Calcium Alginate)
$calciumCheck = $pdo->query("
  SELECT id, name, size, hcpcs_code, active
  FROM products
  WHERE name ILIKE '%calcium%alginate%'
  ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$result['calcium_alginate_check'] = $calciumCheck;

echo json_encode($result, JSON_PRETTY_PRINT);
