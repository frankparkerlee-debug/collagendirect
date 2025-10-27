#!/usr/bin/env php
<?php
/**
 * Cron job: Send delivery confirmation emails to patients
 * Runs daily to check for orders that need delivery confirmation
 * Sends 2-3 days after order creation (targets day 3)
 *
 * Usage: php api/cron/send-delivery-confirmations.php
 * Cron: 0 10 * * * (Daily at 10 AM UTC)
 */

declare(strict_types=1);

// Load dependencies
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/patient_delivery_notification.php';

echo "[delivery-cron] Starting delivery confirmation email job - " . date('Y-m-d H:i:s') . "\n";

try {
  // Find orders that:
  // 1. Were created 3 days ago (72 hours)
  // 2. Have been shipped or delivered
  // 3. Haven't had confirmation email sent yet
  // 4. Have a patient with an email address

  $stmt = $pdo->query("
    SELECT o.id, o.created_at, o.status,
           p.first_name, p.last_name, p.email
    FROM orders o
    INNER JOIN patients p ON p.id = o.patient_id
    WHERE o.status IN ('shipped', 'delivered')
      AND o.created_at <= (NOW() - INTERVAL '3 days')
      AND o.created_at >= (NOW() - INTERVAL '4 days')
      AND p.email IS NOT NULL
      AND p.email != ''
      AND NOT EXISTS (
        SELECT 1 FROM order_delivery_confirmations odc
        WHERE odc.order_id = o.id
      )
    ORDER BY o.created_at ASC
  ");

  $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $totalOrders = count($orders);

  echo "[delivery-cron] Found $totalOrders orders needing confirmation emails\n";

  if ($totalOrders === 0) {
    echo "[delivery-cron] No orders to process. Exiting.\n";
    exit(0);
  }

  $sent = 0;
  $failed = 0;

  foreach ($orders as $order) {
    $orderId = (int)$order['id'];
    $patientName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
    $patientEmail = $order['email'];

    echo "[delivery-cron] Processing order #$orderId for $patientName ($patientEmail)...\n";

    try {
      $result = send_delivery_confirmation_email($pdo, $orderId);

      if ($result) {
        echo "[delivery-cron]   ✓ Email sent successfully\n";
        $sent++;
      } else {
        echo "[delivery-cron]   ✗ Failed to send email\n";
        $failed++;
      }

      // Add small delay to avoid rate limiting (100ms)
      usleep(100000);

    } catch (Throwable $e) {
      echo "[delivery-cron]   ✗ Error: " . $e->getMessage() . "\n";
      $failed++;
    }
  }

  echo "\n[delivery-cron] Summary:\n";
  echo "[delivery-cron]   Total orders: $totalOrders\n";
  echo "[delivery-cron]   Sent: $sent\n";
  echo "[delivery-cron]   Failed: $failed\n";
  echo "[delivery-cron] Job completed - " . date('Y-m-d H:i:s') . "\n";

  exit(0);

} catch (Throwable $e) {
  echo "[delivery-cron] Fatal error: " . $e->getMessage() . "\n";
  echo "[delivery-cron] Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
