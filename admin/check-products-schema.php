<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

header('Content-Type: application/json');

$result = [];

// Get table schema
$result['schema'] = $pdo->query("
  SELECT column_name, data_type, is_nullable, column_default
  FROM information_schema.columns
  WHERE table_name = 'products'
  ORDER BY ordinal_position
")->fetchAll(PDO::FETCH_ASSOC);

// Check practice_pricing table
$ppExists = $pdo->query("
  SELECT EXISTS (
    SELECT FROM information_schema.tables
    WHERE table_name = 'practice_pricing'
  )
")->fetchColumn();

$result['practice_pricing_exists'] = (bool)$ppExists;

if ($ppExists) {
  $result['practice_pricing_schema'] = $pdo->query("
    SELECT column_name, data_type
    FROM information_schema.columns
    WHERE table_name = 'practice_pricing'
    ORDER BY ordinal_position
  ")->fetchAll(PDO::FETCH_ASSOC);

  $result['practice_pricing_count'] = $pdo->query("SELECT COUNT(*) FROM practice_pricing")->fetchColumn();
  $result['products_with_pricing'] = $pdo->query("SELECT COUNT(DISTINCT product_id) FROM practice_pricing")->fetchColumn();
}

// Count total products
$result['total_products'] = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$result['active_products'] = $pdo->query("SELECT COUNT(*) FROM products WHERE active = true")->fetchColumn();

// Sample products
$result['sample_products'] = $pdo->query("
  SELECT id, name, size, hcpcs_code, active, price_wholesale, price_referral, pieces_per_box
  FROM products
  WHERE active = true
  ORDER BY name
  LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($result, JSON_PRETTY_PRINT);
