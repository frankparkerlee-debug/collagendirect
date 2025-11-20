<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== TESTING WHOLESALE QUERY ===\n\n";

try {
  echo "Testing simplified query...\n";

  $sql = "
    SELECT
      o.id,
      o.created_at,
      o.order_number,
      o.product,
      o.status
    FROM orders o
    WHERE o.billed_by = 'practice_dme'
    LIMIT 5
  ";

  $result = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  echo "✓ Simple query works! Found " . count($result) . " orders\n\n";

  foreach ($result as $order) {
    echo "  - {$order['order_number']} ({$order['product']})\n";
  }

  echo "\n\nNow testing with date arithmetic...\n";

  $sql2 = "
    SELECT
      o.id,
      o.created_at::date as created_date,
      (o.created_at::date + 30) as due_date,
      (CURRENT_DATE - o.created_at::date) as days_old
    FROM orders o
    WHERE o.billed_by = 'practice_dme'
    LIMIT 1
  ";

  $result2 = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
  echo "✓ Date arithmetic works!\n";
  print_r($result2);

} catch (Exception $e) {
  echo "❌ ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
