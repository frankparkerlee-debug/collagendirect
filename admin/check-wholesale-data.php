<?php
/**
 * Simple diagnostic to check wholesale orders data
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== CHECKING WHOLESALE ORDERS ===\n\n";

try {
  // Check if billed_by column exists
  $hasBilledBy = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = 'billed_by'
  ")->fetchColumn();

  echo "1. billed_by column exists: " . ($hasBilledBy ? "YES" : "NO") . "\n\n";

  if (!$hasBilledBy) {
    echo "ERROR: The billed_by column doesn't exist. Cannot filter wholesale orders.\n";
    exit(1);
  }

  // Count total orders
  $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
  echo "2. Total orders in database: $totalOrders\n\n";

  // Count wholesale orders
  $wholesaleCount = $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE billed_by = 'practice_dme'
  ")->fetchColumn();
  echo "3. Orders with billed_by='practice_dme': $wholesaleCount\n\n";

  // Count by review_status
  echo "4. Breakdown by review_status:\n";
  $statusBreakdown = $pdo->query("
    SELECT
      review_status,
      COUNT(*) as count
    FROM orders
    WHERE billed_by = 'practice_dme'
    GROUP BY review_status
  ")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($statusBreakdown as $row) {
    $status = $row['review_status'] ?? 'NULL';
    echo "   - $status: {$row['count']} order(s)\n";
  }
  echo "\n";

  // Sample some actual wholesale orders
  echo "5. Sample wholesale orders:\n";
  $sampleOrders = $pdo->query("
    SELECT
      o.id,
      o.created_at,
      o.order_number,
      o.billed_by,
      o.review_status,
      o.status,
      o.product,
      u.practice_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.billed_by = 'practice_dme'
    ORDER BY o.created_at DESC
    LIMIT 5
  ")->fetchAll(PDO::FETCH_ASSOC);

  if (empty($sampleOrders)) {
    echo "   No wholesale orders found!\n\n";
  } else {
    foreach ($sampleOrders as $idx => $order) {
      echo "   Order " . ($idx + 1) . ":\n";
      echo "     ID: {$order['id']}\n";
      echo "     Order Number: " . ($order['order_number'] ?? 'NULL') . "\n";
      echo "     Practice: " . ($order['practice_name'] ?? 'N/A') . "\n";
      echo "     Product: " . ($order['product'] ?? 'N/A') . "\n";
      echo "     Created: {$order['created_at']}\n";
      echo "     Status: {$order['status']}\n";
      echo "     Review Status: " . ($order['review_status'] ?? 'NULL') . "\n";
      echo "     Billed By: {$order['billed_by']}\n\n";
    }
  }

  // Check what admin query returns
  echo "6. Testing admin wholesale-orders.php query:\n";
  $adminQuery = $pdo->query("
    SELECT COUNT(*) as count
    FROM orders o
    WHERE o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
  ")->fetchColumn();

  echo "   Admin query returns: $adminQuery order(s)\n\n";

  if ($adminQuery == 0 && $wholesaleCount > 0) {
    echo "   WARNING: There are wholesale orders but they're all filtered out by review_status!\n";
    echo "   All wholesale orders have review_status='draft'\n\n";
  }

  echo "✓ Diagnostic complete!\n";

} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  exit(1);
}
