<?php
/**
 * Test: Check if wholesale-orders.php query works
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== TESTING WHOLESALE ORDERS QUERY ===\n\n";

try {
  global $pdo;

  // Test the exact query that should be in wholesale-orders.php after the fix
  echo "1. Testing query with CORRECT column names (shipping_ prefix)...\n";
  $sql = "
    SELECT
      o.id,
      o.created_at,
      o.product,
      o.shipments_remaining,
      o.product_price as unit_price,
      o.status,
      o.paid_at,
      u.practice_name,
      u.first_name as phys_first,
      u.last_name as phys_last,
      p.first_name as pat_first,
      p.last_name as pat_last,
      CONCAT_WS(', ', o.shipping_address, o.shipping_city, o.shipping_state, o.shipping_zip) as shipping_address,
      pr.pieces_per_box,
      pr.price_wholesale
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN patients p ON o.patient_id = p.id
    LEFT JOIN products pr ON o.product_id = pr.id
    WHERE o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
    ORDER BY o.created_at DESC
  ";

  $orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  echo "   ✓ Query succeeded! Returned " . count($orders) . " orders\n\n";

  if (count($orders) > 0) {
    echo "2. Order details:\n";
    foreach ($orders as $order) {
      $boxes = (int)($order['shipments_remaining'] ?? 0);
      $pieces_per_box = (int)($order['pieces_per_box'] ?? 10);
      $unit_price = (float)($order['unit_price'] ?? $order['price_wholesale'] ?? 0);
      $orderValue = $boxes * ($unit_price * $pieces_per_box);

      echo "\n   Order ID: " . substr($order['id'], 0, 8) . "...\n";
      echo "   Date: " . date('m/d/Y', strtotime($order['created_at'])) . "\n";
      echo "   Practice: " . ($order['practice_name'] ?: 'N/A') . "\n";
      echo "   Physician: " . trim(($order['phys_first'] ?? '') . ' ' . ($order['phys_last'] ?? '')) . "\n";
      echo "   Patient: " . trim(($order['pat_first'] ?? '') . ' ' . ($order['pat_last'] ?? '')) . "\n";
      echo "   Product: " . $order['product'] . "\n";
      echo "   Boxes: $boxes × \$$unit_price/pc × $pieces_per_box pc/box = \$" . number_format($orderValue, 2) . "\n";
      echo "   Status: " . $order['status'] . "\n";
      echo "   Shipping: " . ($order['shipping_address'] ?: 'N/A') . "\n";
      echo "   ---\n";
    }
  }

  echo "\n✅ Query works correctly! Wholesale orders should now display in admin panel.\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "This means the fix hasn't been deployed yet.\n";
  exit(1);
}
