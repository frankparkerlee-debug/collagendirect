<?php
/**
 * Debug Wholesale Order - Check the most recent wholesale order
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== CHECKING MOST RECENT WHOLESALE ORDER ===\n\n";

try {
  // Check if billed_by column exists
  $checkCol = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = 'billed_by'
  ")->fetchColumn();

  if (!$checkCol) {
    echo "❌ ERROR: billed_by column does not exist!\n";
    echo "Please run migration to add this column.\n";
    exit(1);
  }

  echo "✓ billed_by column exists\n\n";

  // Check if order_number column exists
  $checkOrderNum = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = 'order_number'
  ")->fetchColumn();

  if (!$checkOrderNum) {
    echo "⚠️  WARNING: order_number column does not exist!\n\n";
  } else {
    echo "✓ order_number column exists\n\n";
  }

  // Fetch all orders with billed_by='practice_dme'
  echo "=== ALL WHOLESALE ORDERS (billed_by='practice_dme') ===\n";
  $wholesaleOrders = $pdo->query("
    SELECT
      id,
      user_id,
      patient_id,
      product,
      status,
      review_status,
      billed_by,
      payment_type,
      created_at,
      " . ($checkOrderNum ? "order_number," : "") . "
      qty_per_change,
      product_price
    FROM orders
    WHERE billed_by = 'practice_dme'
    ORDER BY created_at DESC
    LIMIT 10
  ")->fetchAll(PDO::FETCH_ASSOC);

  if (empty($wholesaleOrders)) {
    echo "❌ No orders found with billed_by='practice_dme'\n\n";
  } else {
    echo "Found " . count($wholesaleOrders) . " wholesale order(s):\n\n";
    foreach ($wholesaleOrders as $idx => $order) {
      echo "Order #" . ($idx + 1) . ":\n";
      echo "  ID: " . $order['id'] . "\n";
      echo "  Created: " . $order['created_at'] . "\n";
      echo "  Product: " . $order['product'] . "\n";
      echo "  Status: " . $order['status'] . "\n";
      echo "  Review Status: " . ($order['review_status'] ?? 'NULL') . "\n";
      echo "  Billed By: " . $order['billed_by'] . "\n";
      echo "  Payment Type: " . ($order['payment_type'] ?? 'NULL') . "\n";
      if ($checkOrderNum) {
        echo "  Order Number: " . ($order['order_number'] ?? 'NULL') . "\n";
      }
      echo "  Boxes: " . ($order['qty_per_change'] ?? 'NULL') . "\n";
      echo "  Price Per Piece: " . ($order['product_price'] ?? 'NULL') . "\n";
      echo "\n";
    }
  }

  // Check what the admin query would return
  echo "\n=== WHAT ADMIN/WHOLESALE-ORDERS.PHP QUERY RETURNS ===\n";
  $adminQuery = "
    SELECT
      o.id,
      o.created_at,
      o.product,
      o.status,
      o.review_status,
      o.billed_by,
      o.payment_type,
      " . ($checkOrderNum ? "o.order_number," : "") . "
      u.practice_name,
      p.first_name as pat_first,
      p.last_name as pat_last
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN patients p ON o.patient_id = p.id
    WHERE o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
    ORDER BY o.created_at DESC
    LIMIT 10
  ";

  $adminResults = $pdo->query($adminQuery)->fetchAll(PDO::FETCH_ASSOC);

  if (empty($adminResults)) {
    echo "❌ Admin query returns NO results\n";
    echo "This means orders are being filtered out by the review_status condition.\n\n";

    // Check what review_status values exist
    echo "Review status values in wholesale orders:\n";
    $statusCheck = $pdo->query("
      SELECT review_status, COUNT(*) as count
      FROM orders
      WHERE billed_by = 'practice_dme'
      GROUP BY review_status
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($statusCheck as $row) {
      echo "  - " . ($row['review_status'] ?? 'NULL') . ": " . $row['count'] . " order(s)\n";
    }
  } else {
    echo "✓ Admin query returns " . count($adminResults) . " result(s):\n\n";
    foreach ($adminResults as $idx => $order) {
      echo "Order #" . ($idx + 1) . ":\n";
      echo "  ID: " . $order['id'] . "\n";
      echo "  Created: " . $order['created_at'] . "\n";
      echo "  Practice: " . ($order['practice_name'] ?? 'N/A') . "\n";
      echo "  Patient: " . ($order['pat_first'] ?? '') . " " . ($order['pat_last'] ?? '') . "\n";
      echo "  Product: " . $order['product'] . "\n";
      echo "  Status: " . $order['status'] . "\n";
      echo "  Review Status: " . ($order['review_status'] ?? 'NULL') . "\n";
      if ($checkOrderNum) {
        echo "  Order Number: " . ($order['order_number'] ?? 'NULL') . "\n";
      }
      echo "\n";
    }
  }

  // Check all orders regardless of billed_by
  echo "\n=== ALL RECENT ORDERS (any billed_by) ===\n";
  $allOrders = $pdo->query("
    SELECT
      id,
      billed_by,
      review_status,
      status,
      payment_type,
      product,
      created_at
    FROM orders
    ORDER BY created_at DESC
    LIMIT 20
  ")->fetchAll(PDO::FETCH_ASSOC);

  echo "Most recent 20 orders:\n\n";
  foreach ($allOrders as $idx => $order) {
    $isPotentialWholesale = ($order['billed_by'] === 'practice_dme' || $order['payment_type'] === 'wholesale');
    $prefix = $isPotentialWholesale ? "*** " : "    ";
    echo $prefix . "Order " . ($idx + 1) . ": " . $order['id'] . "\n";
    echo $prefix . "  Billed By: " . ($order['billed_by'] ?? 'NULL') . "\n";
    echo $prefix . "  Payment Type: " . ($order['payment_type'] ?? 'NULL') . "\n";
    echo $prefix . "  Review Status: " . ($order['review_status'] ?? 'NULL') . "\n";
    echo $prefix . "  Status: " . $order['status'] . "\n";
    echo $prefix . "  Created: " . $order['created_at'] . "\n";
    echo "\n";
  }

  echo "\n✓ Diagnostic complete!\n";

} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
