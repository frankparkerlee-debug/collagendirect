<?php
require __DIR__ . '/auth.php'; require_admin();
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== Debug: John Smith Wholesale Orders ===\n\n";

// Find John Smith user
$userStmt = $pdo->query("
  SELECT id, practice_name, first_name, last_name, email, role
  FROM users
  WHERE first_name ILIKE '%john%' AND last_name ILIKE '%smith%'
  LIMIT 5
");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

echo "1. Users matching 'John Smith':\n";
if (empty($users)) {
  echo "   No users found!\n\n";
} else {
  foreach ($users as $u) {
    echo "   - ID: {$u['id']}\n";
    echo "     Name: {$u['first_name']} {$u['last_name']}\n";
    echo "     Practice: {$u['practice_name']}\n";
    echo "     Email: {$u['email']}\n";
    echo "     Role: {$u['role']}\n\n";
  }
}

// Check all orders for first user found
if (!empty($users)) {
  $userId = $users[0]['id'];

  echo "2. All orders for user {$userId}:\n";
  $ordersStmt = $pdo->prepare("
    SELECT id, patient_id, product, billed_by, payment_type, order_number,
           review_status, status, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
  ");
  $ordersStmt->execute([$userId]);
  $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($orders)) {
    echo "   No orders found!\n\n";
  } else {
    foreach ($orders as $o) {
      echo "   - Order ID: {$o['id']}\n";
      echo "     Product: {$o['product']}\n";
      echo "     Billed By: {$o['billed_by']}\n";
      echo "     Payment Type: {$o['payment_type']}\n";
      echo "     Order Number: {$o['order_number']}\n";
      echo "     Review Status: {$o['review_status']}\n";
      echo "     Status: {$o['status']}\n";
      echo "     Created: {$o['created_at']}\n\n";
    }
  }

  // Count wholesale orders
  echo "3. Wholesale orders count (billed_by='practice_dme'):\n";
  $countStmt = $pdo->prepare("
    SELECT COUNT(*) as cnt
    FROM orders
    WHERE user_id = ? AND billed_by = 'practice_dme'
  ");
  $countStmt->execute([$userId]);
  $count = $countStmt->fetchColumn();
  echo "   Total: {$count}\n\n";

  // Check for draft orders
  echo "4. Draft wholesale orders (review_status='draft'):\n";
  $draftStmt = $pdo->prepare("
    SELECT COUNT(*) as cnt
    FROM orders
    WHERE user_id = ? AND billed_by = 'practice_dme' AND review_status = 'draft'
  ");
  $draftStmt->execute([$userId]);
  $draftCount = $draftStmt->fetchColumn();
  echo "   Total drafts: {$draftCount}\n\n";

  // Run the actual wholesale orders query
  echo "5. Running actual wholesale orders query from portal:\n";
  $portalStmt = $pdo->prepare("
    SELECT
      COALESCE(o.order_number, o.id) as order_number,
      MIN(o.created_at) as order_date,
      COUNT(DISTINCT o.id) as product_count,
      COUNT(DISTINCT o.patient_id) as patient_count,
      MAX(o.status) as status
    FROM orders o
    WHERE o.user_id = ?
      AND o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
    GROUP BY COALESCE(o.order_number, o.id)
    ORDER BY MIN(o.created_at) DESC
  ");
  $portalStmt->execute([$userId]);
  $portalOrders = $portalStmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($portalOrders)) {
    echo "   No results returned from portal query!\n";
    echo "   This means wholesale orders are either:\n";
    echo "   - Not marked with billed_by='practice_dme'\n";
    echo "   - Marked as review_status='draft'\n";
    echo "   - Don't exist for this user\n\n";
  } else {
    echo "   Found " . count($portalOrders) . " wholesale order(s):\n";
    foreach ($portalOrders as $po) {
      echo "   - Order #: {$po['order_number']}\n";
      echo "     Date: {$po['order_date']}\n";
      echo "     Products: {$po['product_count']}\n";
      echo "     Patients: {$po['patient_count']}\n";
      echo "     Status: {$po['status']}\n\n";
    }
  }
}
