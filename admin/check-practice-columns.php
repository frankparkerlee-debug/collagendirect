<?php
require_once __DIR__ . '/../api/db.php';

echo "<h1>Checking practice_address columns in users table</h1>\n";

try {
  $stmt = $pdo->query("
    SELECT column_name, data_type
    FROM information_schema.columns
    WHERE table_name = 'users'
    AND column_name LIKE 'practice%'
    ORDER BY column_name
  ");

  $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($columns)) {
    echo "<p style='color:red'>❌ No practice columns found in users table!</p>\n";
  } else {
    echo "<p style='color:green'>✓ Found " . count($columns) . " practice columns:</p>\n";
    echo "<table border='1' cellpadding='5'><tr><th>Column Name</th><th>Data Type</th></tr>\n";
    foreach ($columns as $col) {
      echo "<tr><td>{$col['column_name']}</td><td>{$col['data_type']}</td></tr>\n";
    }
    echo "</table>\n";
  }

} catch (PDOException $e) {
  echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
