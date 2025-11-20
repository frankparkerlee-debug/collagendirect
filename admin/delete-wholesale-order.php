<?php
// Delete a wholesale order group and all associated orders
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

$order_group = $_GET['order_group'] ?? '';

if (empty($order_group)) {
  echo "ERROR: Missing order_group parameter\n";
  echo "Usage: /admin/delete-wholesale-order.php?order_group=WS-20251120-003\n";
  exit;
}

echo "=== DELETING WHOLESALE ORDER GROUP: $order_group ===\n\n";

try {
  // First, find all orders in this group
  $stmt = $pdo->prepare("
    SELECT
      o.id,
      o.product,
      o.qty_per_change,
      o.status,
      p.first_name,
      p.last_name,
      p.id as patient_id
    FROM orders o
    LEFT JOIN patients p ON p.id = o.patient_id
    WHERE o.additional_instructions LIKE ?
  ");
  $stmt->execute(["%Wholesale Order #$order_group%"]);
  $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($orders)) {
    echo "ERROR: No orders found for wholesale order group: $order_group\n";
    echo "\nSearching for any mention in additional_instructions...\n";

    $stmt2 = $pdo->prepare("SELECT id, additional_instructions FROM orders WHERE additional_instructions LIKE ? LIMIT 5");
    $stmt2->execute(["%$order_group%"]);
    $alt_orders = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    if (empty($alt_orders)) {
      echo "No orders found with '$order_group' in additional_instructions\n";
    } else {
      echo "Found orders with different format:\n";
      foreach ($alt_orders as $o) {
        echo "  - " . substr($o['id'], 0, 12) . ": " . $o['additional_instructions'] . "\n";
      }
    }
    exit;
  }

  echo "Found " . count($orders) . " order(s) to delete:\n\n";

  $patient_ids = [];
  foreach ($orders as $order) {
    $order_id = substr($order['id'], 0, 12);
    $patient_name = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
    if ($patient_name === ' ') $patient_name = 'Office Stock';

    echo "  - Order: $order_id... | Patient: $patient_name | Product: " . $order['product'] . " | Status: " . $order['status'] . "\n";

    if ($order['patient_id']) {
      $patient_ids[$order['patient_id']] = $patient_name;
    }
  }

  echo "\n";

  // Confirm deletion
  if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo "⚠️  DRY RUN MODE - No changes made\n\n";
    echo "To actually delete these orders, add &confirm=yes to the URL:\n";
    echo "/admin/delete-wholesale-order.php?order_group=$order_group&confirm=yes\n\n";

    if (!empty($patient_ids)) {
      echo "NOTE: The following patients will also be deleted (if they have no other orders):\n";
      foreach ($patient_ids as $pid => $pname) {
        echo "  - $pname (" . substr($pid, 0, 12) . "...)\n";
      }
    }
    exit;
  }

  // Begin transaction
  $pdo->beginTransaction();

  // Delete all orders in this group
  $delete_stmt = $pdo->prepare("DELETE FROM orders WHERE additional_instructions LIKE ?");
  $delete_stmt->execute(["%Wholesale Order #$order_group%"]);
  $deleted_count = $delete_stmt->rowCount();

  echo "✓ Deleted $deleted_count order(s)\n\n";

  // Delete patients if they have no other orders
  if (!empty($patient_ids)) {
    echo "Checking patients for deletion...\n";
    foreach ($patient_ids as $patient_id => $patient_name) {
      // Check if patient has any other orders
      $check_stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders WHERE patient_id = ?");
      $check_stmt->execute([$patient_id]);
      $result = $check_stmt->fetch(PDO::FETCH_ASSOC);

      if ($result['cnt'] == 0) {
        // Patient has no other orders, safe to delete
        $del_patient = $pdo->prepare("DELETE FROM patients WHERE id = ?");
        $del_patient->execute([$patient_id]);
        echo "  ✓ Deleted patient: $patient_name (no other orders)\n";
      } else {
        echo "  ⊘ Kept patient: $patient_name (has {$result['cnt']} other order(s))\n";
      }
    }
  }

  // Commit transaction
  $pdo->commit();

  echo "\n✓ DELETION COMPLETE\n";
  echo "Wholesale order group $order_group has been permanently deleted.\n";

} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "\n❌ ERROR: " . $e->getMessage() . "\n";
  echo "File: " . $e->getFile() . "\n";
  echo "Line: " . $e->getLine() . "\n";
}
