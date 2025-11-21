<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

header('Content-Type: application/json');

$result = [];

// Check if practice_pricing table exists
$tableExists = $pdo->query("
  SELECT EXISTS (
    SELECT FROM information_schema.tables
    WHERE table_name = 'practice_pricing'
  )
")->fetchColumn();

$result['table_exists'] = (bool)$tableExists;

if ($tableExists) {
  // Get schema
  $result['schema'] = $pdo->query("
    SELECT column_name, data_type, is_nullable, column_default
    FROM information_schema.columns
    WHERE table_name = 'practice_pricing'
    ORDER BY ordinal_position
  ")->fetchAll(PDO::FETCH_ASSOC);
  
  // Get row count
  $result['total_rows'] = $pdo->query("SELECT COUNT(*) FROM practice_pricing")->fetchColumn();
  
  // Get sample rows
  $result['sample_rows'] = $pdo->query("
    SELECT pp.*, p.name as product_name, u.practice_name
    FROM practice_pricing pp
    LEFT JOIN products p ON p.id = pp.product_id
    LEFT JOIN users u ON u.id = pp.user_id
    LIMIT 10
  ")->fetchAll(PDO::FETCH_ASSOC);
  
  // Count how many products have pricing entries
  $result['products_with_pricing'] = $pdo->query("
    SELECT COUNT(DISTINCT product_id) FROM practice_pricing
  ")->fetchColumn();
  
  // Count total active products
  $result['total_active_products'] = $pdo->query("
    SELECT COUNT(*) FROM products WHERE active = true
  ")->fetchColumn();
}

echo json_encode($result, JSON_PRETTY_PRINT);
