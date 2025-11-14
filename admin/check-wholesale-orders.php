<?php
/**
 * Diagnostic: Check wholesale orders status
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== WHOLESALE ORDERS DIAGNOSTIC ===\n\n";

try {
  global $pdo;

  // 1. Check if billed_by column exists
  echo "1. Checking if billed_by column exists...\n";
  $checkCol = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='orders' AND column_name='billed_by'");
  $hasBilledBy = $checkCol->rowCount() > 0;

  if ($hasBilledBy) {
    echo "   ✓ billed_by column EXISTS\n\n";
  } else {
    echo "   ✗ billed_by column DOES NOT EXIST\n";
    echo "   Run migration: /admin/add-billed-by-column.php\n\n";
    exit(1);
  }

  // 2. Check total orders in last 7 days
  echo "2. Recent orders (last 7 days)...\n";
  $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE created_at > NOW() - INTERVAL '7 days'");
  $totalRecent = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
  echo "   Total orders: $totalRecent\n\n";

  // 3. Count wholesale orders
  echo "3. Wholesale orders (billed_by='practice_dme')...\n";
  $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE billed_by='practice_dme'");
  $wholesaleCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
  echo "   Total wholesale orders: $wholesaleCount\n\n";

  // 4. Show recent wholesale orders
  if ($wholesaleCount > 0) {
    echo "4. Recent wholesale orders (last 10)...\n";
    $stmt = $pdo->query("
      SELECT
        o.id,
        o.created_at,
        o.product,
        o.shipments_remaining as boxes,
        o.billed_by,
        o.review_status,
        o.status,
        u.practice_name,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name
      FROM orders o
      LEFT JOIN users u ON o.user_id = u.id
      LEFT JOIN patients p ON o.patient_id = p.id
      WHERE o.billed_by='practice_dme'
      ORDER BY o.created_at DESC
      LIMIT 10
    ");

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orders as $order) {
      echo "\n   Order ID: " . $order['id'] . "\n";
      echo "   Created: " . $order['created_at'] . "\n";
      echo "   Practice: " . ($order['practice_name'] ?? 'N/A') . "\n";
      echo "   Patient: " . ($order['patient_name'] ?? 'N/A') . "\n";
      echo "   Product: " . $order['product'] . "\n";
      echo "   Boxes: " . $order['boxes'] . "\n";
      echo "   Status: " . $order['status'] . "\n";
      echo "   Review Status: " . ($order['review_status'] ?? 'NULL') . "\n";
      echo "   Billed By: " . $order['billed_by'] . "\n";
      echo "   ---\n";
    }
  } else {
    echo "4. No wholesale orders found.\n";
    echo "   This likely means:\n";
    echo "   - No orders have been created via the wholesale form yet\n";
    echo "   - OR orders were created before the billed_by column was added\n";
    echo "   - OR orders were created with a different billed_by value\n\n";

    // Check for orders with other billed_by values
    echo "5. Checking all billed_by values...\n";
    $stmt = $pdo->query("SELECT billed_by, COUNT(*) as cnt FROM orders WHERE billed_by IS NOT NULL GROUP BY billed_by");
    $billedByValues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($billedByValues)) {
      echo "   All orders have billed_by = NULL\n";
      echo "   This means the column exists but no orders have been marked with billed_by yet.\n\n";
    } else {
      foreach ($billedByValues as $row) {
        echo "   billed_by = '" . $row['billed_by'] . "': " . $row['cnt'] . " orders\n";
      }
    }
  }

  echo "\n=== DIAGNOSTIC COMPLETE ===\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
