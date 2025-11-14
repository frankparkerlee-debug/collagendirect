<?php
/**
 * Debug: Check why wholesale orders aren't showing in admin panel
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== WHOLESALE ORDERS JOIN DEBUG ===\n\n";

try {
  global $pdo;

  // 1. Get wholesale orders with user and patient info
  echo "1. Testing the JOIN query from wholesale-orders.php...\n";
  $sql = "
    SELECT
      o.id,
      o.user_id,
      o.patient_id,
      o.created_at,
      o.product,
      o.billed_by,
      u.practice_name,
      u.first_name as phys_first,
      u.last_name as phys_last,
      p.first_name as pat_first,
      p.last_name as pat_last
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN patients p ON o.patient_id = p.id
    WHERE o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
    ORDER BY o.created_at DESC
  ";

  $stmt = $pdo->query($sql);
  $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "   Query returned " . count($orders) . " orders\n\n";

  if (count($orders) > 0) {
    foreach ($orders as $order) {
      echo "   Order ID: " . $order['id'] . "\n";
      echo "   User ID: " . $order['user_id'] . "\n";
      echo "   Patient ID: " . $order['patient_id'] . "\n";
      echo "   Practice: " . ($order['practice_name'] ?? 'NULL') . "\n";
      echo "   Physician: " . ($order['phys_first'] ?? 'NULL') . " " . ($order['phys_last'] ?? 'NULL') . "\n";
      echo "   Patient: " . ($order['pat_first'] ?? 'NULL') . " " . ($order['pat_last'] ?? 'NULL') . "\n";
      echo "   ---\n";
    }
  } else {
    echo "   ❌ No orders returned from JOIN query!\n\n";

    // 2. Check if orders exist without JOINs
    echo "2. Checking orders WITHOUT JOINs...\n";
    $stmt = $pdo->query("SELECT id, user_id, patient_id FROM orders WHERE billed_by='practice_dme'");
    $rawOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Found " . count($rawOrders) . " wholesale orders\n\n";

    if (count($rawOrders) > 0) {
      foreach ($rawOrders as $order) {
        echo "   Order ID: " . $order['id'] . "\n";
        echo "   User ID: " . $order['user_id'] . "\n";
        echo "   Patient ID: " . $order['patient_id'] . "\n";

        // Check if user exists
        $userCheck = $pdo->prepare("SELECT id, first_name, last_name, practice_name FROM users WHERE id=?");
        $userCheck->execute([$order['user_id']]);
        $user = $userCheck->fetch(PDO::FETCH_ASSOC);

        if ($user) {
          echo "   ✓ User EXISTS: " . ($user['practice_name'] ?? $user['first_name'] . ' ' . $user['last_name']) . "\n";
        } else {
          echo "   ❌ User NOT FOUND in users table!\n";
        }

        // Check if patient exists
        $patientCheck = $pdo->prepare("SELECT id, first_name, last_name FROM patients WHERE id=?");
        $patientCheck->execute([$order['patient_id']]);
        $patient = $patientCheck->fetch(PDO::FETCH_ASSOC);

        if ($patient) {
          echo "   ✓ Patient EXISTS: " . $patient['first_name'] . ' ' . $patient['last_name'] . "\n";
        } else {
          echo "   ❌ Patient NOT FOUND in patients table!\n";
        }

        echo "   ---\n";
      }
    }
  }

  echo "\n=== DEBUG COMPLETE ===\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
