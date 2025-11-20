<?php
/**
 * Debug Portal Wholesale View - Check what a physician sees
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== DEBUGGING PORTAL WHOLESALE VIEW ===\n\n";

// Get the user who created the most recent wholesale order
try {
  $recentOrder = $pdo->query("
    SELECT user_id, id, order_number, created_at
    FROM orders
    WHERE billed_by = 'practice_dme'
    ORDER BY created_at DESC
    LIMIT 1
  ")->fetch(PDO::FETCH_ASSOC);

  if (!$recentOrder) {
    echo "❌ No wholesale orders found\n";
    exit(1);
  }

  $userId = $recentOrder['user_id'];
  $orderNumber = $recentOrder['order_number'];
  $orderId = $recentOrder['id'];

  echo "Most recent wholesale order:\n";
  echo "  Order ID: $orderId\n";
  echo "  Order Number: $orderNumber\n";
  echo "  User ID: $userId\n";
  echo "  Created: {$recentOrder['created_at']}\n\n";

  // Simulate what the portal query does
  echo "=== SIMULATING PORTAL QUERY ===\n\n";

  // Step 1: Check if order_number column exists
  $hasOrderNumber = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = 'order_number'
  ")->fetchColumn();

  echo "Has order_number column: " . ($hasOrderNumber ? "YES" : "NO") . "\n\n";

  // Step 2: Run the grouped orders query
  echo "Step 1: Fetching grouped orders for user $userId...\n";

  $sql = "
    SELECT
      COALESCE(o.order_number, o.id) as order_number,
      MIN(o.created_at) as order_date,
      COUNT(DISTINCT o.id) as product_count,
      COUNT(DISTINCT o.patient_id) as patient_count,
      MAX(o.status) as status,
      MAX(o.delivery_mode) as delivery_mode,
      MAX(o.shipping_address) as shipping_address,
      MAX(o.shipping_city) as shipping_city,
      MAX(o.shipping_state) as shipping_state,
      MAX(o.shipping_zip) as shipping_zip,
      MAX(o.shipping_name) as shipping_name
    FROM orders o
    WHERE o.user_id = ?
      AND o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
    GROUP BY COALESCE(o.order_number, o.id)
    ORDER BY MIN(o.created_at) DESC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([$userId]);
  $groupedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Found " . count($groupedOrders) . " grouped order(s)\n\n";

  if (empty($groupedOrders)) {
    echo "❌ No grouped orders returned! This is why the portal shows no orders.\n";
    echo "\nDebugging filters:\n";

    // Check each filter
    echo "  - Orders for user $userId with billed_by='practice_dme': ";
    $count1 = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND billed_by = 'practice_dme'");
    $count1->execute([$userId]);
    echo $count1->fetchColumn() . "\n";

    echo "  - Orders with review_status != 'draft': ";
    $count2 = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND billed_by = 'practice_dme' AND (review_status IS NULL OR review_status != 'draft')");
    $count2->execute([$userId]);
    echo $count2->fetchColumn() . "\n";

    exit(1);
  }

  foreach ($groupedOrders as $order) {
    echo "Grouped Order:\n";
    echo "  Order Number: {$order['order_number']}\n";
    echo "  Date: {$order['order_date']}\n";
    echo "  Product Count: {$order['product_count']}\n";
    echo "  Status: {$order['status']}\n";
    echo "  Shipping Name: {$order['shipping_name']}\n\n";

    // Step 3: Fetch detail items for this order (this is what shows in the invoice)
    echo "Step 2: Fetching detail items for order {$order['order_number']}...\n";

    $detailStmt = $pdo->prepare("
      SELECT
        o.id as order_id,
        o.product,
        o.product_id,
        o.product_price,
        o.qty_per_change as boxes,
        p.first_name,
        p.last_name,
        p.mrn,
        prod.pieces_per_box,
        prod.price_wholesale,
        prod.product_name,
        pp.custom_price,
        pp.discount_percentage
      FROM orders o
      LEFT JOIN patients p ON o.patient_id = p.id
      LEFT JOIN products prod ON o.product_id = prod.id
      LEFT JOIN practice_pricing pp ON pp.product_id = o.product_id AND pp.user_id = ?
      WHERE (o.order_number = ? OR (o.order_number IS NULL AND o.id = ?)) AND o.user_id = ?
      ORDER BY o.created_at ASC
    ");
    $detailStmt->execute([$userId, $order['order_number'], $order['order_number'], $userId]);
    $orderItems = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($orderItems) . " line item(s)\n\n";

    if (empty($orderItems)) {
      echo "❌ NO LINE ITEMS! This is why the invoice is empty when expanded.\n\n";

      // Debug why no items
      echo "Debugging item query:\n";
      echo "  - Orders with order_number = '{$order['order_number']}': ";
      $debugStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
      $debugStmt->execute([$order['order_number']]);
      echo $debugStmt->fetchColumn() . "\n";

      echo "  - Orders with order_number = '{$order['order_number']}' AND user_id = '$userId': ";
      $debugStmt2 = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ? AND user_id = ?");
      $debugStmt2->execute([$order['order_number'], $userId]);
      echo $debugStmt2->fetchColumn() . "\n\n";

      // Show the actual order
      echo "Actual order data:\n";
      $actualOrder = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
      $actualOrder->execute([$order['order_number']]);
      $orderData = $actualOrder->fetch(PDO::FETCH_ASSOC);
      if ($orderData) {
        echo "  ID: {$orderData['id']}\n";
        echo "  User ID: {$orderData['user_id']}\n";
        echo "  Patient ID: {$orderData['patient_id']}\n";
        echo "  Product: {$orderData['product']}\n";
        echo "  Order Number: {$orderData['order_number']}\n";
      }

    } else {
      foreach ($orderItems as $idx => $item) {
        echo "  Line Item " . ($idx + 1) . ":\n";
        echo "    Product: {$item['product_name']}\n";
        echo "    Patient: {$item['first_name']} {$item['last_name']}\n";
        echo "    Boxes: {$item['boxes']}\n";
        echo "    Pieces/Box: {$item['pieces_per_box']}\n";
        echo "    Price/Piece: {$item['product_price']}\n";
        if ($item['discount_percentage']) {
          echo "    Discount: {$item['discount_percentage']}%\n";
        }
        echo "\n";
      }
    }
  }

  echo "\n✓ Diagnostic complete!\n";

} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
