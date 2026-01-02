<?php
/**
 * Cron Job: Process Scheduled Emails
 *
 * This script should be run via cron every 15-30 minutes to send
 * scheduled onboarding emails to sales reps.
 *
 * Example crontab entry (every 15 minutes):
 * */15 * * * * /usr/bin/php /path/to/collagendirect/cron/process-scheduled-emails.php >> /var/log/scheduled-emails.log 2>&1
 *
 * Or via wget/curl:
 * */15 * * * * curl -s https://collagendirect.health/cron/process-scheduled-emails.php?key=YOUR_SECRET_KEY > /dev/null
 */

// Security: Only allow CLI or requests with valid key
$validKey = getenv('CRON_SECRET_KEY') ?: 'change-this-secret-key-in-production';

if (php_sapi_name() !== 'cli') {
    // Web request - require secret key
    $providedKey = $_GET['key'] ?? '';
    if ($providedKey !== $validKey) {
        http_response_code(403);
        die('Forbidden');
    }
}

// Load dependencies
require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/../api/lib/rep_notifications.php';

// Process scheduled emails
$startTime = microtime(true);
$sent = process_scheduled_emails($pdo);
$elapsed = round((microtime(true) - $startTime) * 1000, 2);

// Output result
$timestamp = date('Y-m-d H:i:s');
$message = "[{$timestamp}] Processed scheduled emails: {$sent} sent ({$elapsed}ms)";

if (php_sapi_name() === 'cli') {
    echo $message . "\n";
} else {
    header('Content-Type: text/plain');
    echo $message;
}
