<?php
/**
 * Debug Portal Wholesale Orders - Check what physicians see
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== DEBUGGING PORTAL WHOLESALE ORDERS ===\n\n";

try {
  // Get all wholesale orders with user info
  $sql = "
    SELECT
      o.id,
      o.user_id,
      o.order_number,
      o.created_at,
      o.product,
      o.status,
      o.review_status,
      o.billed_by,
      u.email,
      u.practice_name,
      u.first_name,
      u.last_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.billed_by = 'practice_dme'
    ORDER BY o.created_at DESC
  ";

  $orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

  echo "Found " . count($orders) . " wholesale orders:\n\n";

  foreach ($orders as $idx => $order) {
    echo "Order " . ($idx + 1) . ":\n";
    echo "  Order Number: {$order['order_number']}\n";
    echo "  User ID: {$order['user_id']}\n";
    echo "  User Email: " . ($order['email'] ?? 'NULL') . "\n";
    echo "  Practice: " . ($order['practice_name'] ?? 'NULL') . "\n";
    echo "  Physician: " . ($order['first_name'] ?? '') . " " . ($order['last_name'] ?? '') . "\n";
    echo "  Product: {$order['product']}\n";
    echo "  Status: {$order['status']}\n";
    echo "  Review Status: " . ($order['review_status'] ?? 'NULL') . "\n";
    echo "  Created: {$order['created_at']}\n";
    echo "\n";

    // Now check if this would show in portal for this user
    if ($order['user_id']) {
      $portalQuery = "
        SELECT COUNT(*) as count
        FROM orders o
        WHERE o.user_id = ?
          AND o.billed_by = 'practice_dme'
          AND (o.review_status IS NULL OR o.review_status != 'draft')
      ";

      $stmt = $pdo->prepare($portalQuery);
      $stmt->execute([$order['user_id']]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      echo "  ✓ Portal query for user {$order['user_id']} returns: {$result['count']} order(s)\n";

      // Check grouped query
      $groupQuery = "
        SELECT
          COALESCE(o.order_number, o.id) as order_number,
          COUNT(DISTINCT o.id) as order_count
        FROM orders o
        WHERE o.user_id = ?
          AND o.billed_by = 'practice_dme'
          AND (o.review_status IS NULL OR o.review_status != 'draft')
        GROUP BY COALESCE(o.order_number, o.id)
      ";

      $stmt2 = $pdo->prepare($groupQuery);
      $stmt2->execute([$order['user_id']]);
      $groups = $stmt2->fetchAll(PDO::FETCH_ASSOC);

      echo "  ✓ Grouped portal query returns: " . count($groups) . " group(s)\n";
      foreach ($groups as $group) {
        echo "    - Group: {$group['order_number']} ({$group['order_count']} orders)\n";
      }
    } else {
      echo "  ❌ No user_id - order is orphaned!\n";
    }

    echo "\n";
  }

  echo "✓ Diagnostic complete!\n";

} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
