<?php
/**
 * Show actual products table schema
 */
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

echo "=== PRODUCTS TABLE SCHEMA ===\n\n";

$stmt = $pdo->query("
  SELECT column_name, data_type, character_maximum_length, column_default, is_nullable
  FROM information_schema.columns
  WHERE table_name = 'products'
  ORDER BY ordinal_position
");

$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
  echo str_pad($col['column_name'], 30);
  echo str_pad($col['data_type'], 20);
  if ($col['character_maximum_length']) {
    echo str_pad("({$col['character_maximum_length']})", 10);
  } else {
    echo str_pad("", 10);
  }
  echo str_pad($col['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL', 12);
  echo ($col['column_default'] ?? '');
  echo "\n";
}

echo "\n\n=== SAMPLE PRODUCT DATA ===\n\n";

$sample = $pdo->query("SELECT * FROM products WHERE active = TRUE LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if ($sample) {
  foreach ($sample as $key => $value) {
    echo str_pad($key . ':', 30) . ($value ?? 'NULL') . "\n";
  }
}
