<?php
/**
 * Cron Job: Cleanup Expired Demo Data
 *
 * This script should be run via cron every hour to clean up expired
 * demo sessions and their associated data (for HIPAA compliance).
 *
 * Example crontab entry (every hour):
 * 0 * * * * /usr/bin/php /path/to/collagendirect/cron/cleanup-demo-data.php >> /var/log/demo-cleanup.log 2>&1
 *
 * Or via wget/curl:
 * 0 * * * * curl -s https://collagendirect.health/cron/cleanup-demo-data.php?key=YOUR_SECRET_KEY > /dev/null
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

// Load database connection
require_once __DIR__ . '/../admin/db.php';

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');
$results = [
    'sessions_deleted' => 0,
    'patients_deleted' => 0,
    'orders_deleted' => 0
];

try {
    // Begin transaction for atomic cleanup
    $pdo->beginTransaction();

    // First, count what we're about to delete for logging
    $countStmt = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM demo_sessions WHERE expires_at < NOW()) as sessions,
            (SELECT COUNT(*) FROM demo_patients WHERE demo_session_id IN (SELECT id FROM demo_sessions WHERE expires_at < NOW())) as patients,
            (SELECT COUNT(*) FROM demo_orders WHERE demo_session_id IN (SELECT id FROM demo_sessions WHERE expires_at < NOW())) as orders
    ");
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

    if ($counts) {
        $results['sessions_deleted'] = (int)$counts['sessions'];
        $results['patients_deleted'] = (int)$counts['patients'];
        $results['orders_deleted'] = (int)$counts['orders'];
    }

    // Delete expired sessions (CASCADE will delete related patients and orders)
    // The FK constraints with ON DELETE CASCADE handle cleanup automatically
    $deleteStmt = $pdo->prepare("DELETE FROM demo_sessions WHERE expires_at < NOW()");
    $deleteStmt->execute();

    $pdo->commit();

    $elapsed = round((microtime(true) - $startTime) * 1000, 2);
    $message = "[{$timestamp}] Demo data cleanup complete: " .
               "{$results['sessions_deleted']} sessions, " .
               "{$results['patients_deleted']} patients, " .
               "{$results['orders_deleted']} orders deleted ({$elapsed}ms)";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $elapsed = round((microtime(true) - $startTime) * 1000, 2);
    $message = "[{$timestamp}] Demo data cleanup FAILED: " . $e->getMessage() . " ({$elapsed}ms)";
    error_log($message);

    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
    }
}

// Output result
if (php_sapi_name() === 'cli') {
    echo $message . "\n";
} else {
    header('Content-Type: text/plain');
    echo $message;
}
