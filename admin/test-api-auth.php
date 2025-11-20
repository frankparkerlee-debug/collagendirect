<?php
/**
 * Test API Authentication
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== TESTING API AUTHENTICATION ===\n\n";

echo "Session ID: " . session_id() . "\n";
echo "Session data: " . print_r($_SESSION, true) . "\n";
echo "Current admin: " . print_r(current_admin(), true) . "\n";

if (function_exists('require_admin')) {
  echo "\n✓ require_admin() function exists\n";

  try {
    require_admin();
    echo "✓ require_admin() passed - user is authenticated\n";
  } catch (Exception $e) {
    echo "❌ require_admin() failed: " . $e->getMessage() . "\n";
  }
} else {
  echo "❌ require_admin() function does not exist\n";
}

echo "\n=== Testing api-order-detail.php requirements ===\n";
$testId = '0a80c973972cd218b9cee467231e5b6b';

try {
  $stmt = $pdo->prepare("
    SELECT
      o.id,
      o.created_at,
      o.product,
      o.qty_per_change as shipments_remaining
    FROM orders o
    WHERE o.id = ?
    LIMIT 1
  ");
  $stmt->execute([$testId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($order) {
    echo "✓ Query successful\n";
    echo "Order data: " . print_r($order, true) . "\n";
  } else {
    echo "❌ No order found with ID: $testId\n";
  }
} catch (Exception $e) {
  echo "❌ Query failed: " . $e->getMessage() . "\n";
}
