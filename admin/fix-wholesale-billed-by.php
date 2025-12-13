<?php
/**
 * Fix wholesale orders - set billed_by based on practice account_type
 *
 * This script marks orders as wholesale (billed_by = 'practice_dme') based on
 * the practice's account_type rather than patient name patterns.
 *
 * Orders from users with account_type IN ('wholesale', 'dme_wholesale') should
 * have billed_by = 'practice_dme' so they:
 * 1. Appear in the wholesale billing dashboard
 * 2. Are excluded from referral billing/revenue metrics
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
if (function_exists('require_admin')) require_admin();

header('Content-Type: text/plain; charset=utf-8');

echo "=== Fix Wholesale Orders billed_by ===\n\n";

try {
  // Check current state
  echo "1. Current State Analysis\n";
  echo "   ----------------------\n\n";

  // Count orders by billed_by
  $stmt = $pdo->query("
    SELECT
      COALESCE(billed_by, 'NULL') as billed_by,
      COUNT(*) as count
    FROM orders
    GROUP BY billed_by
    ORDER BY count DESC
  ");
  echo "   Orders by billed_by:\n";
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "     - {$row['billed_by']}: {$row['count']}\n";
  }
  echo "\n";

  // Count users by account_type
  $stmt = $pdo->query("
    SELECT
      account_type,
      COUNT(*) as user_count,
      COUNT(DISTINCT o.id) as order_count
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    GROUP BY account_type
    ORDER BY user_count DESC
  ");
  echo "   Users by account_type:\n";
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "     - {$row['account_type']}: {$row['user_count']} users, {$row['order_count']} orders\n";
  }
  echo "\n";

  // Find orders that should be wholesale but aren't marked
  $stmt = $pdo->query("
    SELECT
      COUNT(*) as count,
      u.account_type
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE u.account_type IN ('wholesale', 'dme_wholesale')
      AND (o.billed_by IS NULL OR o.billed_by != 'practice_dme')
    GROUP BY u.account_type
  ");
  $unmarkedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($unmarkedOrders)) {
    echo "   All wholesale orders are already marked correctly.\n\n";
    echo "=== No Changes Needed ===\n";
    exit(0);
  }

  echo "   Orders needing billed_by update:\n";
  $totalUnmarked = 0;
  foreach ($unmarkedOrders as $row) {
    echo "     - {$row['account_type']}: {$row['count']} orders\n";
    $totalUnmarked += (int)$row['count'];
  }
  echo "\n   Total to update: {$totalUnmarked} orders\n\n";

  // Show sample orders that will be updated
  echo "2. Sample Orders to Update (first 10)\n";
  echo "   -----------------------------------\n\n";

  $stmt = $pdo->query("
    SELECT
      o.id,
      o.order_number,
      o.created_at,
      o.billed_by,
      o.status,
      u.practice_name,
      u.account_type
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE u.account_type IN ('wholesale', 'dme_wholesale')
      AND (o.billed_by IS NULL OR o.billed_by != 'practice_dme')
    ORDER BY o.created_at DESC
    LIMIT 10
  ");
  $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($samples as $order) {
    echo "   Order: " . ($order['order_number'] ?? substr($order['id'], 0, 8)) . "\n";
    echo "     Practice: {$order['practice_name']}\n";
    echo "     Account Type: {$order['account_type']}\n";
    echo "     Current billed_by: " . ($order['billed_by'] ?: 'NULL') . "\n";
    echo "     Status: {$order['status']}\n";
    echo "     Created: {$order['created_at']}\n\n";
  }

  // Check for confirmation
  if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo "==========================================\n";
    echo "Ready to update {$totalUnmarked} orders\n";
    echo "==========================================\n\n";
    echo "Add ?confirm=yes to the URL to proceed.\n";
    echo "Example: /admin/fix-wholesale-billed-by.php?confirm=yes\n\n";
    exit(0);
  }

  // Perform the update
  echo "3. Updating Orders\n";
  echo "   ----------------\n\n";

  $pdo->beginTransaction();

  $updateStmt = $pdo->prepare("
    UPDATE orders o
    SET billed_by = 'practice_dme'
    FROM users u
    WHERE u.id = o.user_id
      AND u.account_type IN ('wholesale', 'dme_wholesale')
      AND (o.billed_by IS NULL OR o.billed_by != 'practice_dme')
  ");
  $updateStmt->execute();
  $updatedCount = $updateStmt->rowCount();

  echo "   Updated {$updatedCount} orders to billed_by = 'practice_dme'\n\n";

  // Verify the update
  echo "4. Verification\n";
  echo "   ------------\n\n";

  $stmt = $pdo->query("
    SELECT
      COALESCE(billed_by, 'NULL') as billed_by,
      COUNT(*) as count
    FROM orders
    GROUP BY billed_by
    ORDER BY count DESC
  ");
  echo "   Orders by billed_by (after update):\n";
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "     - {$row['billed_by']}: {$row['count']}\n";
  }
  echo "\n";

  // Check if any wholesale accounts still have unmarked orders
  $stmt = $pdo->query("
    SELECT COUNT(*) as remaining
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE u.account_type IN ('wholesale', 'dme_wholesale')
      AND (o.billed_by IS NULL OR o.billed_by != 'practice_dme')
  ");
  $remaining = (int)$stmt->fetch()['remaining'];

  if ($remaining > 0) {
    echo "   WARNING: {$remaining} orders still unmarked. Please investigate.\n\n";
  } else {
    echo "   All wholesale orders are now marked correctly.\n\n";
  }

  $pdo->commit();

  echo "=== Migration Complete ===\n\n";
  echo "Results:\n";
  echo "- Updated {$updatedCount} orders to billed_by = 'practice_dme'\n";
  echo "- These orders will now appear in Billing > Wholesale\n";
  echo "- These orders are excluded from Referral billing metrics\n";

} catch (PDOException $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  http_response_code(500);
  exit(1);
}
