<?php
declare(strict_types=1);

/**
 * Manual Test Endpoint for SMS Delivery Confirmation
 *
 * This allows you to manually trigger the SMS sender to test the system
 * without waiting for the cron job.
 *
 * Usage: https://collagendirect.health/admin/test-sms-delivery.php
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin(); // Only admins can access this

header('Content-Type: text/plain; charset=utf-8');

echo "=== SMS Delivery Confirmation Test ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Include the cron script
require_once __DIR__ . '/../api/cron/send-delivery-confirmations.php';
