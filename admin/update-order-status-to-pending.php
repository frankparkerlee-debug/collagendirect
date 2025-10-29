<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

echo "=== Updating Order Status to Pending ===\n\n";

try {
  // Step 1: Check current status distribution
  echo "Step 1: Checking current order statuses...\n";
  $statusCounts = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM orders
    GROUP BY status
    ORDER BY count DESC
  ")->fetchAll();

  echo "  Current status distribution:\n";
  foreach ($statusCounts as $row) {
    echo "    - {$row['status']}: {$row['count']}\n";
  }
  echo "\n";

  // Step 2: Update 'submitted' status to 'pending'
  echo "Step 2: Updating 'submitted' orders to 'pending'...\n";
  $updateStmt = $pdo->prepare("
    UPDATE orders
    SET status = 'pending',
        updated_at = NOW()
    WHERE status = 'submitted'
  ");
  $updateStmt->execute();
  $updatedCount = $updateStmt->rowCount();
  echo "  ✓ Updated {$updatedCount} orders from 'submitted' to 'pending'\n\n";

  // Step 3: Show new status distribution
  echo "Step 3: Verifying updated status distribution...\n";
  $newStatusCounts = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM orders
    GROUP BY status
    ORDER BY count DESC
  ")->fetchAll();

  echo "  New status distribution:\n";
  foreach ($newStatusCounts as $row) {
    echo "    - {$row['status']}: {$row['count']}\n";
  }
  echo "\n";

  echo "=== Migration Complete ===\n";
  echo "✓ All 'submitted' orders have been updated to 'pending'\n";
  echo "✓ New orders will automatically start with 'pending' status\n";
  echo "✓ Order workflow: pending → approved → in_transit → delivered\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}
