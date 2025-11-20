<?php
// Debug wholesale orders page
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
  <title>Debug Wholesale Orders</title>
  <style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
    .error { color: red; }
    .success { color: green; }
    pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
  </style>
</head>
<body>
  <h1>Wholesale Orders Debug</h1>
  
  <?php
  echo '<div class="section">';
  echo '<h2>1. Check billed_by column</h2>';
  try {
    $hasBilledBy = $pdo->query("
      SELECT column_name
      FROM information_schema.columns
      WHERE table_name = 'orders' AND column_name = 'billed_by'
    ")->fetchColumn();
    
    if ($hasBilledBy) {
      echo '<p class="success">✓ billed_by column exists</p>';
    } else {
      echo '<p class="error">✗ billed_by column DOES NOT exist</p>';
    }
  } catch (Exception $e) {
    echo '<p class="error">Error checking column: ' . htmlspecialchars($e->getMessage()) . '</p>';
  }
  echo '</div>';
  
  echo '<div class="section">';
  echo '<h2>2. Run wholesale orders query</h2>';
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
    
    echo '<p>Executing query...</p>';
    $orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<p class="success">✓ Query executed successfully</p>';
    echo '<p>Found: <strong>' . count($orders) . '</strong> order(s)</p>';
    
    if (count($orders) > 0) {
      echo '<h3>Orders:</h3>';
      foreach ($orders as $idx => $order) {
        echo '<pre>';
        echo 'Order ' . ($idx + 1) . ":\n";
        echo '  ID: ' . htmlspecialchars($order['id']) . "\n";
        echo '  Invoice #: ' . htmlspecialchars($order['invoice_number'] ?? 'NULL') . "\n";
        echo '  Practice: ' . htmlspecialchars($order['practice_name'] ?? 'NULL') . "\n";
        echo '  Physician: ' . htmlspecialchars($order['phys_first'] . ' ' . $order['phys_last']) . "\n";
        echo '  Patient: ' . htmlspecialchars($order['pat_first'] . ' ' . $order['pat_last']) . "\n";
        echo '  Product: ' . htmlspecialchars($order['product']) . "\n";
        echo '  Boxes: ' . htmlspecialchars($order['shipments_remaining'] ?? '0') . "\n";
        echo '  Status: ' . htmlspecialchars($order['status']) . "\n";
        echo '  Created: ' . htmlspecialchars($order['created_at']) . "\n";
        echo '</pre>';
      }
    } else {
      echo '<p class="error">✗ No orders returned</p>';
    }
    
  } catch (Exception $e) {
    echo '<p class="error">✗ Query failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
  }
  echo '</div>';
  
  echo '<div class="section">';
  echo '<h2>3. Check what wholesale-orders.php will show</h2>';
  echo '<p>If query returned 0 orders, the page will show the empty state.</p>';
  echo '<p>If query returned orders, they should display in the table.</p>';
  echo '<p><a href="/admin/wholesale-orders.php">→ Go to Wholesale Orders page</a></p>';
  echo '</div>';
  ?>
</body>
</html>
