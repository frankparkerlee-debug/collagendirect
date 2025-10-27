#!/usr/bin/env php
<?php
/**
 * Cron job: Send daily batched status update emails to physicians
 * Runs daily to notify physicians of:
 * 1. Order status changes (shipped, delivered) in the last 24 hours
 * 2. Orders expiring within 7 days
 *
 * Groups by physician to send one email per physician with all their updates
 *
 * Usage: php api/cron/send-physician-status-updates.php
 * Cron: 0 17 * * * (Daily at 5 PM UTC / 12 PM ET)
 */

declare(strict_types=1);

// Load dependencies
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/physician_status_notification.php';

echo "[physician-status-cron] Starting physician status update job - " . date('Y-m-d H:i:s') . "\n";

try {
  // ============================================================
  // Part 1: Find status changes that need notification
  // ============================================================

  echo "[physician-status-cron] Finding status changes from last 24 hours...\n";

  $statusChangesStmt = $pdo->query("
    SELECT sc.id, sc.order_id, sc.new_status, sc.tracking_code, sc.carrier,
           o.user_id, o.product,
           p.first_name, p.last_name
    FROM order_status_changes sc
    INNER JOIN orders o ON o.id = sc.order_id
    INNER JOIN patients p ON p.id = o.patient_id
    WHERE sc.notification_sent_at IS NULL
      AND sc.changed_at >= (NOW() - INTERVAL '24 hours')
      AND sc.new_status IN ('shipped', 'delivered')
    ORDER BY o.user_id, sc.changed_at DESC
  ");

  $allStatusChanges = $statusChangesStmt->fetchAll(PDO::FETCH_ASSOC);
  echo "[physician-status-cron] Found " . count($allStatusChanges) . " status changes\n";

  // Group by physician
  $changesByPhysician = [];
  $changeIds = [];

  foreach ($allStatusChanges as $change) {
    $userId = $change['user_id'];
    if (!isset($changesByPhysician[$userId])) {
      $changesByPhysician[$userId] = [];
    }

    $changesByPhysician[$userId][] = [
      'order_id' => $change['order_id'],
      'patient_name' => trim(($change['first_name'] ?? '') . ' ' . ($change['last_name'] ?? '')),
      'product' => $change['product'],
      'new_status' => $change['new_status'],
      'tracking_code' => $change['tracking_code'],
      'carrier' => $change['carrier']
    ];

    $changeIds[] = $change['id'];
  }

  // ============================================================
  // Part 2: Find orders expiring within 7 days
  // ============================================================

  echo "[physician-status-cron] Finding orders expiring within 7 days...\n";

  $expiringStmt = $pdo->query("
    SELECT o.id as order_id, o.user_id, o.product, o.created_at, o.duration_days,
           p.first_name, p.last_name,
           GREATEST(0, o.duration_days - EXTRACT(DAY FROM (NOW() - o.created_at))) as days_remaining
    FROM orders o
    INNER JOIN patients p ON p.id = o.patient_id
    WHERE o.status IN ('active', 'shipped', 'delivered')
      AND o.duration_days IS NOT NULL
      AND o.duration_days > 0
      AND (o.created_at + (o.duration_days || ' days')::interval) <= (NOW() + INTERVAL '7 days')
      AND (o.created_at + (o.duration_days || ' days')::interval) > NOW()
      AND NOT EXISTS (
        SELECT 1 FROM order_status_changes sc
        WHERE sc.order_id = o.id
          AND sc.notification_sent_at IS NOT NULL
          AND sc.notification_sent_at >= (NOW() - INTERVAL '7 days')
          AND sc.new_status = 'expiring'
      )
    ORDER BY o.user_id, days_remaining ASC
  ");

  $allExpiringOrders = $expiringStmt->fetchAll(PDO::FETCH_ASSOC);
  echo "[physician-status-cron] Found " . count($allExpiringOrders) . " expiring orders\n";

  // Group by physician
  $expiringByPhysician = [];
  $expiringOrderIds = [];

  foreach ($allExpiringOrders as $order) {
    $userId = $order['user_id'];
    if (!isset($expiringByPhysician[$userId])) {
      $expiringByPhysician[$userId] = [];
    }

    $expiringByPhysician[$userId][] = [
      'order_id' => $order['order_id'],
      'patient_name' => trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')),
      'product' => $order['product'],
      'days_remaining' => (int)$order['days_remaining']
    ];

    $expiringOrderIds[] = $order['order_id'];
  }

  // ============================================================
  // Part 3: Merge physicians and send batched emails
  // ============================================================

  $allPhysicians = array_unique(array_merge(
    array_keys($changesByPhysician),
    array_keys($expiringByPhysician)
  ));

  $totalPhysicians = count($allPhysicians);
  echo "\n[physician-status-cron] Sending emails to $totalPhysicians physicians...\n";

  if ($totalPhysicians === 0) {
    echo "[physician-status-cron] No physicians to notify. Exiting.\n";
    exit(0);
  }

  $sent = 0;
  $failed = 0;

  foreach ($allPhysicians as $userId) {
    $statusChanges = $changesByPhysician[$userId] ?? [];
    $expiringOrders = $expiringByPhysician[$userId] ?? [];

    $totalUpdates = count($statusChanges) + count($expiringOrders);

    echo "[physician-status-cron] Processing physician $userId ($totalUpdates updates)...\n";

    try {
      $result = send_physician_status_batch($pdo, $userId, $statusChanges, $expiringOrders);

      if ($result) {
        echo "[physician-status-cron]   ✓ Email sent successfully\n";
        $sent++;

        // Mark status changes as notified
        if (count($statusChanges) > 0) {
          $relevantChangeIds = [];
          foreach ($allStatusChanges as $change) {
            if ($change['user_id'] === $userId) {
              $relevantChangeIds[] = $change['id'];
            }
          }

          if (count($relevantChangeIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($relevantChangeIds), '?'));
            $markStmt = $pdo->prepare("
              UPDATE order_status_changes
              SET notification_sent_at = NOW()
              WHERE id IN ($placeholders)
            ");
            $markStmt->execute($relevantChangeIds);
          }
        }

        // Log expiring notifications
        if (count($expiringOrders) > 0) {
          foreach ($expiringOrders as $expOrder) {
            $pdo->prepare("
              INSERT INTO order_status_changes
              (order_id, old_status, new_status, notification_sent_at)
              VALUES (?, 'active', 'expiring', NOW())
            ")->execute([$expOrder['order_id']]);
          }
        }

      } else {
        echo "[physician-status-cron]   ✗ Failed to send email\n";
        $failed++;
      }

      // Small delay to avoid rate limiting
      usleep(100000);

    } catch (Throwable $e) {
      echo "[physician-status-cron]   ✗ Error: " . $e->getMessage() . "\n";
      $failed++;
    }
  }

  echo "\n[physician-status-cron] Summary:\n";
  echo "[physician-status-cron]   Total physicians: $totalPhysicians\n";
  echo "[physician-status-cron]   Emails sent: $sent\n";
  echo "[physician-status-cron]   Failed: $failed\n";
  echo "[physician-status-cron]   Status changes processed: " . count($allStatusChanges) . "\n";
  echo "[physician-status-cron]   Expiring orders processed: " . count($allExpiringOrders) . "\n";
  echo "[physician-status-cron] Job completed - " . date('Y-m-d H:i:s') . "\n";

  exit(0);

} catch (Throwable $e) {
  echo "[physician-status-cron] Fatal error: " . $e->getMessage() . "\n";
  echo "[physician-status-cron] Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
