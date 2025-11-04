<?php
/**
 * Migration: Update existing orders with proper review_status
 * Maps old 'status' values to new 'review_status' workflow
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== Migrating Order Statuses ===\n\n";

try {
  $pdo->beginTransaction();

  // Check if review_status column exists
  $check = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = 'review_status'
  ");

  if ($check->rowCount() === 0) {
    echo "✗ review_status column does not exist. Run add-order-lifecycle-fields.php first.\n";
    exit(1);
  }

  echo "✓ review_status column found\n\n";

  // Get counts before migration
  echo "Current order counts:\n";
  $counts = $pdo->query("
    SELECT status, COUNT(*) as cnt
    FROM orders
    GROUP BY status
  ")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($counts as $row) {
    echo "  {$row['status']}: {$row['cnt']}\n";
  }
  echo "\n";

  // Migration logic:
  // - approved/active → review_status='approved', locked
  // - submitted → review_status='pending_admin_review'
  // - stopped → review_status='rejected', locked
  // - completed → review_status='approved', locked
  // - NULL/other → review_status='pending_admin_review'

  echo "Migrating statuses...\n";

  // 1. Approved/Active orders → Accepted (locked)
  $stmt = $pdo->prepare("
    UPDATE orders
    SET review_status = 'approved',
        locked_at = NOW(),
        locked_by = 'system_migration'
    WHERE (status IN ('approved', 'active', 'completed'))
      AND (review_status IS NULL OR review_status = 'draft')
  ");
  $stmt->execute();
  $approved = $stmt->rowCount();
  echo "  ✓ Migrated $approved orders to 'approved' (Accepted)\n";

  // 2. Stopped orders → Rejected (locked)
  $stmt = $pdo->prepare("
    UPDATE orders
    SET review_status = 'rejected',
        locked_at = NOW(),
        locked_by = 'system_migration'
    WHERE status = 'stopped'
      AND (review_status IS NULL OR review_status = 'draft')
  ");
  $stmt->execute();
  $rejected = $stmt->rowCount();
  echo "  ✓ Migrated $rejected orders to 'rejected'\n";

  // 3. Submitted/Other orders → Pending review
  $stmt = $pdo->prepare("
    UPDATE orders
    SET review_status = 'pending_admin_review'
    WHERE (review_status IS NULL OR review_status = 'draft')
      AND status NOT IN ('approved', 'active', 'completed', 'stopped')
  ");
  $stmt->execute();
  $pending = $stmt->rowCount();
  echo "  ✓ Migrated $pending orders to 'pending_admin_review' (Submitted)\n";

  // 4. Handle any remaining NULL review_status
  $stmt = $pdo->prepare("
    UPDATE orders
    SET review_status = 'pending_admin_review'
    WHERE review_status IS NULL OR review_status = 'draft'
  ");
  $stmt->execute();
  $remaining = $stmt->rowCount();
  if ($remaining > 0) {
    echo "  ✓ Set $remaining remaining orders to 'pending_admin_review'\n";
  }

  $pdo->commit();

  echo "\n";
  echo "New review_status counts:\n";
  $newCounts = $pdo->query("
    SELECT review_status, COUNT(*) as cnt
    FROM orders
    GROUP BY review_status
  ")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($newCounts as $row) {
    echo "  {$row['review_status']}: {$row['cnt']}\n";
  }

  echo "\n=== Migration Complete ===\n";
  echo "Total orders migrated: " . ($approved + $rejected + $pending + $remaining) . "\n";
  echo "\nStatus mapping:\n";
  echo "  approved/active/completed → Accepted (locked, not editable)\n";
  echo "  stopped → Rejected (locked, not editable)\n";
  echo "  submitted/other → Submitted (editable until admin reviews)\n";

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
