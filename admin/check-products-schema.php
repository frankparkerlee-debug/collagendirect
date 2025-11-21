<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

// Get table schema
$schema = $pdo->query("
  SELECT column_name, data_type, is_nullable, column_default
  FROM information_schema.columns
  WHERE table_name = 'products'
  ORDER BY ordinal_position
")->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($schema, JSON_PRETTY_PRINT);
