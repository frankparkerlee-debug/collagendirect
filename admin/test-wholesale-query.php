<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== TESTING WHOLESALE ORDERS QUERY ===\n\n";

try {
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

  echo "Running query...\n\n";
  
  $orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  
  echo "Query returned " . count($orders) . " order(s)\n\n";
  
  if (empty($orders)) {
    echo "❌ No orders returned!\n";
  } else {
    foreach ($orders as $idx => $order) {
      echo "Order " . ($idx + 1) . ":\n";
      echo "  ID: {$order['id']}\n";
      echo "  Invoice #: {$order['invoice_number']}\n";
      echo "  Practice: {$order['practice_name']}\n";
      echo "  Patient: {$order['pat_first']} {$order['pat_last']}\n";
      echo "  Product: {$order['product']}\n";
      echo "  Boxes: {$order['shipments_remaining']}\n";
      echo "  Status: {$order['status']}\n\n";
    }
  }
  
  echo "✓ Test complete!\n";

} catch (Exception $e) {
  echo "❌ ERROR: " . $e->getMessage() . "\n";
  echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
