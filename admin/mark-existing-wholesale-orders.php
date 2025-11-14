<?php
/**
 * Mark existing wholesale orders with billed_by = 'practice_dme'
 *
 * This script identifies orders that were created through the wholesale flow
 * and marks them appropriately so they appear in the wholesale orders page.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
if (function_exists('require_admin')) require_admin();

header('Content-Type: text/plain');

echo "=== Marking Existing Wholesale Orders ===\n\n";

// Check if billed_by column exists
try {
  $checkCol = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='orders' AND column_name='billed_by'");
  if ($checkCol->rowCount() === 0) {
    echo "ERROR: billed_by column doesn't exist. Please run the add-billed-by-column.php migration first.\n";
    exit(1);
  }
  echo "✓ billed_by column exists\n\n";
} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  exit(1);
}

// Strategy: Mark orders as wholesale based on multiple criteria
// 1. Patient first name is "Office"
// 2. delivery_mode is "office"
// 3. shipping_name contains "Office"
// 4. Already marked as practice_dme (skip these)

echo "Finding wholesale orders to mark...\n\n";

try {
  // First, see what we have
  $currentStmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM orders
    WHERE billed_by = 'practice_dme'
  ");
  $currentCount = $currentStmt->fetch()['count'];
  echo "Currently marked as wholesale: {$currentCount} orders\n\n";

  // Find potential wholesale orders
  $findStmt = $pdo->query("
    SELECT
      o.id,
      o.created_at,
      o.product,
      o.billed_by,
      o.delivery_mode,
      o.shipping_name,
      p.first_name as pat_first,
      p.last_name as pat_last,
      u.practice_name
    FROM orders o
    LEFT JOIN patients p ON o.patient_id = p.id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE (o.review_status IS NULL OR o.review_status != 'draft')
      AND o.billed_by IS NULL
      AND (
        p.first_name = 'Office'
        OR o.delivery_mode = 'office'
        OR LOWER(o.shipping_name) LIKE '%office%'
      )
    ORDER BY o.created_at DESC
  ");

  $candidates = $findStmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($candidates)) {
    echo "No wholesale orders found to mark.\n";
    echo "This could mean:\n";
    echo "  - All wholesale orders are already marked\n";
    echo "  - No wholesale orders have been created yet\n";
    echo "  - Wholesale orders use different patterns\n";
    exit(0);
  }

  echo "Found {" . count($candidates) . "} potential wholesale orders:\n\n";

  foreach ($candidates as $order) {
    echo "Order #{$order['id']} - {$order['practice_name']}\n";
    echo "  Patient: {$order['pat_first']} {$order['pat_last']}\n";
    echo "  Product: {$order['product']}\n";
    echo "  Delivery Mode: " . ($order['delivery_mode'] ?? 'N/A') . "\n";
    echo "  Shipping Name: " . ($order['shipping_name'] ?? 'N/A') . "\n";
    echo "  Current billed_by: " . ($order['billed_by'] ?? 'NULL') . "\n";
    echo "\n";
  }

  // Ask for confirmation
  echo "\n";
  echo "==========================================\n";
  echo "Ready to mark " . count($candidates) . " orders as wholesale (practice_dme)\n";
  echo "==========================================\n";
  echo "\n";
  echo "Add ?confirm=yes to the URL to proceed with marking these orders.\n";
  echo "Example: /admin/mark-existing-wholesale-orders.php?confirm=yes\n";

  if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    echo "\nProceeding with update...\n\n";

    $updateStmt = $pdo->prepare("
      UPDATE orders
      SET billed_by = 'practice_dme'
      WHERE id = ?
    ");

    $updated = 0;
    foreach ($candidates as $order) {
      try {
        $updateStmt->execute([$order['id']]);
        echo "✓ Marked order #{$order['id']} as wholesale\n";
        $updated++;
      } catch (Throwable $e) {
        echo "✗ Failed to mark order #{$order['id']}: " . $e->getMessage() . "\n";
      }
    }

    echo "\n";
    echo "==========================================\n";
    echo "✓ Updated {$updated} orders as wholesale\n";
    echo "==========================================\n";

    // Show final count
    $finalStmt = $pdo->query("
      SELECT COUNT(*) as count
      FROM orders
      WHERE billed_by = 'practice_dme'
    ");
    $finalCount = $finalStmt->fetch()['count'];
    echo "\nTotal wholesale orders now: {$finalCount}\n";
  }

} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  exit(1);
}

echo "\n=== Complete ===\n";
