<?php
// Check actual column names in orders table
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/db.php';

echo "=== Orders Table Column Check ===\n\n";

try {
  $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'orders' ORDER BY ordinal_position";
  $stmt = $pdo->query($sql);
  $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

  echo "Total columns: " . count($columns) . "\n\n";
  echo "All columns:\n";
  foreach ($columns as $col) {
    echo "  - $col\n";
  }

  echo "\n\nFile-related columns:\n";
  foreach ($columns as $col) {
    if (stripos($col, 'note') !== false ||
        stripos($col, 'card') !== false ||
        stripos($col, 'insurance') !== false ||
        stripos($col, 'id_') !== false ||
        stripos($col, 'rx') !== false) {
      echo "  - $col\n";
    }
  }

} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";
?>
