#!/usr/bin/env php
<?php
/**
 * Automated SMS Delivery Confirmation Sender
 *
 * Runs daily to catch any delivered orders that didn't get SMS sent immediately.
 * This is a backup/safety net - SMS should be sent immediately when marked delivered.
 *
 * Usage: php api/cron/send-delivery-confirmations.php
 * Cron: 0 10 * * * (Daily at 10 AM UTC)
 */

declare(strict_types=1);

// Load dependencies
require_once __DIR__ . '/../../admin/db.php';
require_once __DIR__ . '/../lib/twilio_sms.php';

echo "=== Delivery Confirmation SMS Sender (Backup) ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
  // Find orders that are delivered but haven't been sent confirmations yet
  // This catches any orders that were missed by the immediate send
  $stmt = $pdo->prepare("
    SELECT
      o.id AS order_id,
      o.status,
      o.tracking_number,
      o.updated_at,
      o.delivered_at,
      p.id AS patient_id,
      p.first_name,
      p.last_name,
      p.phone,
      p.email
    FROM orders o
    INNER JOIN patients p ON p.id = o.patient_id
    WHERE o.status = 'delivered'
      AND p.phone IS NOT NULL
      AND p.phone != ''
      AND NOT EXISTS (
        SELECT 1 FROM delivery_confirmations dc
        WHERE dc.order_id = o.id
      )
    ORDER BY o.delivered_at DESC NULLS LAST, o.updated_at DESC
  ");

  $stmt->execute();
  $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Found " . count($orders) . " delivered orders without SMS confirmations\n\n";

  if (empty($orders)) {
    echo "No orders to process. Exiting.\n";
    exit(0);
  }

  $successCount = 0;
  $failCount = 0;

  foreach ($orders as $order) {
    $orderId = $order['order_id'];
    $patientName = trim($order['first_name'] . ' ' . $order['last_name']);
    $patientPhone = $order['phone'];
    $patientEmail = $order['email'] ?? null;

    echo "Processing order #{$orderId} for {$patientName} ({$patientPhone})...\n";

    // Generate unique confirmation token
    $token = bin2hex(random_bytes(32));

    // Insert confirmation record
    try {
      $insertStmt = $pdo->prepare("
        INSERT INTO delivery_confirmations (
          order_id,
          patient_phone,
          patient_email,
          confirmation_token,
          created_at,
          updated_at
        ) VALUES (?, ?, ?, ?, NOW(), NOW())
        RETURNING id
      ");

      $insertStmt->execute([
        $orderId,
        $patientPhone,
        $patientEmail,
        $token
      ]);

      $confirmationId = $insertStmt->fetchColumn();

      // Send SMS
      $smsResult = send_delivery_confirmation_sms(
        $patientPhone,
        $patientName,
        $orderId,
        $token
      );

      if ($smsResult['success']) {
        // Update with SMS details
        $updateStmt = $pdo->prepare("
          UPDATE delivery_confirmations
          SET sms_sent_at = NOW(),
              sms_sid = ?,
              sms_status = ?,
              updated_at = NOW()
          WHERE id = ?
        ");

        $updateStmt->execute([
          $smsResult['sid'],
          $smsResult['status'],
          $confirmationId
        ]);

        echo "  ✓ SMS sent successfully (SID: {$smsResult['sid']})\n";
        $successCount++;
      } else {
        // Update with error
        $updateStmt = $pdo->prepare("
          UPDATE delivery_confirmations
          SET notes = ?,
              updated_at = NOW()
          WHERE id = ?
        ");

        $updateStmt->execute([
          "SMS send failed: " . ($smsResult['error'] ?? 'Unknown error'),
          $confirmationId
        ]);

        echo "  ✗ SMS send failed: " . ($smsResult['error'] ?? 'Unknown error') . "\n";
        $failCount++;
      }
    } catch (Throwable $e) {
      echo "  ✗ Database error: " . $e->getMessage() . "\n";
      $failCount++;
      error_log("[send-delivery-confirmations] Error processing order {$orderId}: " . $e->getMessage());
    }

    echo "\n";

    // Add small delay to avoid rate limiting (100ms)
    usleep(100000);
  }

  echo "========================================\n";
  echo "Summary\n";
  echo "========================================\n";
  echo "Total orders processed: " . count($orders) . "\n";
  echo "SMS sent successfully: {$successCount}\n";
  echo "Failed: {$failCount}\n";
  echo "\n";

  if ($successCount > 0) {
    echo "✓ Delivery confirmation SMS sent to {$successCount} patient(s)\n";
  }

  if ($failCount > 0) {
    echo "⚠ {$failCount} SMS failed to send. Check error logs for details.\n";
  }

} catch (Throwable $e) {
  echo "\n✗ Fatal error: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  error_log("[send-delivery-confirmations] Fatal error: " . $e->getMessage());
  exit(1);
}

echo "\n=== SMS Sender Complete ===\n";
