<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

echo "=== ORDERS TABLE SCHEMA ===\n\n";

$stmt = $pdo->query("
  SELECT column_name, data_type, is_nullable, column_default
  FROM information_schema.columns
  WHERE table_name = 'orders'
  ORDER BY ordinal_position
");

while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
  echo "{$col['column_name']}\n";
  echo "  Type: {$col['data_type']}\n";
  echo "  Nullable: {$col['is_nullable']}\n";
  if ($col['column_default']) {
    echo "  Default: {$col['column_default']}\n";
  }
  echo "\n";
}
