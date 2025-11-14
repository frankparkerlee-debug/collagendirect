<?php
// Debug wholesale orders query
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
if (function_exists('require_admin')) require_admin();

header('Content-Type: text/plain');

echo "=== Wholesale Orders Debug ===\n\n";

// Step 1: Check if billed_by column exists
echo "1. Checking billed_by column...\n";
try {
  $checkCol = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='orders' AND column_name='billed_by'");
  $hasBilledBy = $checkCol->rowCount() > 0;
  echo "   billed_by exists: " . ($hasBilledBy ? "YES" : "NO") . "\n\n";

  if (!$hasBilledBy) {
    echo "   ERROR: Column doesn't exist. Cannot proceed.\n";
    exit;
  }
} catch (Throwable $e) {
  echo "   ERROR: " . $e->getMessage() . "\n";
  exit;
}

// Step 2: Count all orders
echo "2. Total orders (non-draft)...\n";
try {
  $stmt = $pdo->query("SELECT COUNT(*) as c FROM orders WHERE (review_status IS NULL OR review_status != 'draft')");
  $total = $stmt->fetch()['c'];
  echo "   Total: {$total}\n\n";
} catch (Throwable $e) {
  echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Step 3: Check billed_by values
echo "3. Orders grouped by billed_by value...\n";
try {
  $stmt = $pdo->query("
    SELECT
      COALESCE(billed_by, 'NULL') as billed_by_value,
      COUNT(*) as count
    FROM orders
    WHERE (review_status IS NULL OR review_status != 'draft')
    GROUP BY billed_by
    ORDER BY count DESC
  ");
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($results as $row) {
    echo "   {$row['billed_by_value']}: {$row['count']} orders\n";
  }
  echo "\n";
} catch (Throwable $e) {
  echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Step 4: Try the exact query from wholesale-orders.php
echo "4. Running exact wholesale-orders.php query...\n";
try {
  $sql = "
    SELECT
      o.id,
      o.created_at,
      o.product,
      o.billed_by,
      o.status,
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

  $stmt = $pdo->query($sql);
  $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "   Found: " . count($orders) . " wholesale orders\n\n";

  if (count($orders) > 0) {
    echo "   Sample orders:\n";
    foreach ($orders as $order) {
      echo "   - Order #{$order['id']}\n";
      echo "     Created: {$order['created_at']}\n";
      echo "     Status: {$order['status']}\n";
      echo "     Billed By: {$order['billed_by']}\n";
      echo "     Practice: " . ($order['practice_name'] ?? 'N/A') . "\n";
      echo "     Patient: " . ($order['pat_first'] ?? '') . " " . ($order['pat_last'] ?? '') . "\n";
      echo "     Product: {$order['product']}\n\n";
    }
  } else {
    echo "   No orders found with billed_by='practice_dme'\n\n";
  }
} catch (Throwable $e) {
  echo "   ERROR: " . $e->getMessage() . "\n";
  echo "   SQL: {$sql}\n\n";
}

// Step 5: Search for orders that SHOULD be wholesale
echo "5. Looking for potential wholesale orders...\n";
try {
  // Check if there are any orders created through wholesale flow
  $stmt = $pdo->query("
    SELECT
      o.id,
      o.created_at,
      o.billed_by,
      o.product,
      u.practice_name,
      p.first_name || ' ' || p.last_name as patient_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN patients p ON o.patient_id = p.id
    WHERE (o.review_status IS NULL OR o.review_status != 'draft')
      AND (
        p.first_name = 'Office'
        OR o.delivery_mode = 'office'
        OR o.shipping_name LIKE '%Office%'
        OR o.billed_by = 'practice_dme'
      )
    ORDER BY o.created_at DESC
    LIMIT 10
  ");

  $potential = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "   Found: " . count($potential) . " potential wholesale orders\n\n";

  if (count($potential) > 0) {
    foreach ($potential as $order) {
      echo "   - Order #{$order['id']}\n";
      echo "     billed_by: " . ($order['billed_by'] ?? 'NULL') . "\n";
      echo "     Practice: {$order['practice_name']}\n";
      echo "     Patient: {$order['patient_name']}\n";
      echo "     Product: {$order['product']}\n\n";
    }
  }
} catch (Throwable $e) {
  echo "   ERROR: " . $e->getMessage() . "\n\n";
}

echo "=== Debug Complete ===\n";
