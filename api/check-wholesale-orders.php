<?php
/**
 * Check which orders are filtering into wholesale orders bucket
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== WHOLESALE ORDERS ANALYSIS ===\n\n";

try {
  global $pdo;

  // Check all orders with billed_by column
  echo "1. Distribution of billed_by values:\n";
  $dist = $pdo->query("
    SELECT
      billed_by,
      COUNT(*) as count
    FROM orders
    GROUP BY billed_by
    ORDER BY count DESC
  ")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($dist as $row) {
    echo "   {$row['billed_by']}: {$row['count']} orders\n";
  }
  echo "\n";

  // Show wholesale orders details
  echo "2. Wholesale orders (billed_by='practice_dme'):\n";
  $wholesale = $pdo->query("
    SELECT
      o.id,
      o.created_at,
      o.product,
      o.status,
      o.review_status,
      u.practice_name,
      u.first_name || ' ' || u.last_name as physician_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
    ORDER BY o.created_at DESC
    LIMIT 20
  ")->fetchAll(PDO::FETCH_ASSOC);

  if (empty($wholesale)) {
    echo "   No wholesale orders found.\n";
    echo "   (All existing orders default to 'collagen_direct' billing)\n\n";
    echo "   To create wholesale orders, physicians need to:\n";
    echo "   1. Select 'Wholesale' billing route in order form\n";
    echo "   2. This sets billed_by='practice_dme'\n";
    echo "   3. Orders will then appear in /admin/wholesale-orders.php\n";
  } else {
    echo "   Found " . count($wholesale) . " wholesale orders:\n\n";
    foreach ($wholesale as $order) {
      echo "   Order #{$order['id']}\n";
      echo "   - Created: {$order['created_at']}\n";
      echo "   - Practice: {$order['practice_name']}\n";
      echo "   - Physician: {$order['physician_name']}\n";
      echo "   - Product: {$order['product']}\n";
      echo "   - Status: {$order['status']} / {$order['review_status']}\n\n";
    }
  }

  // Show recent orders regardless of billing type
  echo "3. Recent orders (all types):\n";
  $recent = $pdo->query("
    SELECT
      o.id,
      o.created_at,
      o.billed_by,
      o.product,
      u.practice_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 10
  ")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($recent as $order) {
    echo "   [{$order['billed_by']}] {$order['practice_name']} - {$order['product']} ({$order['created_at']})\n";
  }

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
