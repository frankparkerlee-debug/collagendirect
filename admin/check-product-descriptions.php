<?php
/**
 * Check if description column has any data
 */
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

echo "=== CHECKING PRODUCT DESCRIPTIONS ===\n\n";

// Check if description column exists
$colCheck = $pdo->query("
  SELECT column_name
  FROM information_schema.columns
  WHERE table_name = 'products' AND column_name = 'description'
")->fetchColumn();

if (!$colCheck) {
  echo "❌ Description column does NOT exist in products table\n";
  exit;
}

echo "✓ Description column exists\n\n";

// Check for non-null, non-empty descriptions
$stmt = $pdo->query("
  SELECT id, name, description
  FROM products
  WHERE description IS NOT NULL AND TRIM(description) != ''
  ORDER BY id
");

$withDescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($withDescriptions)) {
  echo "✓ NO products have descriptions (column is NULL or empty for all products)\n";
  echo "✓ Safe to DROP description column\n";
} else {
  echo "⚠ FOUND " . count($withDescriptions) . " products WITH descriptions:\n\n";
  echo str_repeat("=", 120) . "\n";
  foreach ($withDescriptions as $p) {
    echo "ID: {$p['id']}\n";
    echo "Name: {$p['name']}\n";
    echo "Description: " . substr($p['description'], 0, 200) . (strlen($p['description']) > 200 ? '...' : '') . "\n";
    echo str_repeat("-", 120) . "\n";
  }
}

// Count all products
$totalCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$withDesc = count($withDescriptions);
$withoutDesc = $totalCount - $withDesc;

echo "\n\nSUMMARY:\n";
echo "Total products: $totalCount\n";
echo "With descriptions: $withDesc\n";
echo "Without descriptions: $withoutDesc\n";
