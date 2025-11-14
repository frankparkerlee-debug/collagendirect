<?php
/**
 * Debug: Why wholesale orders UI isn't showing orders
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== WHOLESALE ORDERS UI DEBUG ===\n\n";

try {
  global $pdo;

  // 1. Check billed_by column
  echo "1. Checking billed_by column...\n";
  $checkCol = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='orders' AND column_name='billed_by'");
  $hasBilledBy = $checkCol->rowCount() > 0;
  echo "   hasBilledBy = " . ($hasBilledBy ? 'TRUE' : 'FALSE') . "\n\n";

  // 2. Check shipping column names
  echo "2. Checking shipping column names...\n";
  $cols = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name='orders' AND column_name LIKE '%ship%'
    ORDER BY column_name
  ")->fetchAll(PDO::FETCH_COLUMN);
  foreach ($cols as $col) {
    echo "   - $col\n";
  }
  echo "\n";

  // 3. Run the exact query from wholesale-orders.php
  echo "3. Running the EXACT query from wholesale-orders.php...\n";
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
      CONCAT_WS(', ', o.ship_address, o.ship_city, o.ship_state, o.ship_zip) as shipping_address,
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

  try {
    $orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo "   ✓ Query succeeded! Returned " . count($orders) . " orders\n\n";

    if (count($orders) > 0) {
      foreach ($orders as $order) {
        echo "   Order ID: " . $order['id'] . "\n";
        echo "   Practice: " . ($order['practice_name'] ?? 'NULL') . "\n";
        echo "   Physician: " . ($order['phys_first'] ?? '') . " " . ($order['phys_last'] ?? '') . "\n";
        echo "   Patient: " . ($order['pat_first'] ?? '') . " " . ($order['pat_last'] ?? '') . "\n";
        echo "   Product: " . $order['product'] . "\n";
        echo "   Status: " . $order['status'] . "\n";
        echo "   ---\n";
      }
    }
  } catch (Throwable $e) {
    echo "   ❌ Query FAILED: " . $e->getMessage() . "\n";
    echo "   Error Code: " . $e->getCode() . "\n\n";

    // Try with correct column names
    echo "4. Trying with 'shipping_' prefix...\n";
    $sql2 = "
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

    $orders2 = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
    echo "   ✓ Corrected query succeeded! Returned " . count($orders2) . " orders\n\n";

    if (count($orders2) > 0) {
      foreach ($orders2 as $order) {
        echo "   Order ID: " . $order['id'] . "\n";
        echo "   Practice: " . ($order['practice_name'] ?? 'NULL') . "\n";
        echo "   Shipping: " . ($order['shipping_address'] ?? 'NULL') . "\n";
        echo "   ---\n";
      }
    }
  }

  echo "\n=== DEBUG COMPLETE ===\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
