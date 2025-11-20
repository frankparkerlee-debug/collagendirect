<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== VERIFYING WHOLESALE-ORDERS.PHP CODE ===\n\n";

// Check if the file exists
$filePath = __DIR__ . '/wholesale-orders.php';
if (!file_exists($filePath)) {
  echo "❌ wholesale-orders.php does not exist!\n";
  exit(1);
}

echo "✓ File exists\n\n";

// Read the SQL query from the file
$content = file_get_contents($filePath);

// Check if it has the updated query
if (strpos($content, 'o.qty_per_change as shipments_remaining') !== false) {
  echo "✓ Code has been updated with qty_per_change\n\n";
} else {
  echo "❌ Code still uses old column names!\n\n";
}

// Check if it has the simplified query
if (strpos($content, 'o.order_number as invoice_number') !== false) {
  echo "✓ Code uses order_number as invoice_number\n\n";
} else {
  echo "❌ Code doesn't have the simplified query!\n\n";
}

// Now test the actual query
echo "=== TESTING THE ACTUAL QUERY ===\n\n";

try {
  // Check if billed_by exists
  $hasBilledBy = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = 'billed_by'
  ")->fetchColumn();

  if (!$hasBilledBy) {
    echo "❌ billed_by column doesn't exist\n";
    exit(1);
  }

  // Run the exact query from wholesale-orders.php
  $sql = "
    SELECT
      o.id,
      o.created_at,
      o.product,
      o.qty_per_change as shipments_remaining,
      o.product_price as unit_price,
      o.status,
      o.paid_at,
      o.tracking_number,
      o.notes,
      o.billed_by,
      o.order_number as invoice_number,
      o.created_at as invoice_date,
      NULL as due_date,
      'Net 30' as payment_terms,
      0 as amount_due,
      0 as amount_paid,
      0 as balance_due,
      0 as aging_bucket,
      0 as days_past_due,
      u.practice_name,
      u.first_name as phys_first,
      u.last_name as phys_last,
      u.email as phys_email,
      p.first_name as pat_first,
      p.last_name as pat_last,
      CONCAT_WS(', ', o.shipping_address, o.shipping_city, o.shipping_state, o.shipping_zip) as shipping_address,
      pr.pieces_per_box,
      pr.price_wholesale
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN patients p ON o.patient_id = p.id
    LEFT JOIN products pr ON o.product_id = pr.id
    WHERE o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
    ORDER BY
      CASE
        WHEN o.status IN ('submitted', 'pending', 'awaiting_approval') THEN 1
        WHEN o.status = 'approved' THEN 2
        ELSE 3
      END,
      o.created_at DESC
  ";

  $orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  
  echo "Query executed successfully!\n";
  echo "Returned " . count($orders) . " order(s)\n\n";
  
  if (count($orders) > 0) {
    echo "✓ Orders found! They should display on wholesale-orders.php\n\n";
    
    foreach ($orders as $idx => $order) {
      echo "Order " . ($idx + 1) . ":\n";
      echo "  Invoice #: {$order['invoice_number']}\n";
      echo "  Practice: {$order['practice_name']}\n";
      echo "  Product: {$order['product']}\n";
      echo "  Status: {$order['status']}\n\n";
    }
  } else {
    echo "❌ No orders returned!\n";
  }

} catch (Exception $e) {
  echo "❌ Query failed: " . $e->getMessage() . "\n";
}

echo "✓ Verification complete!\n";
