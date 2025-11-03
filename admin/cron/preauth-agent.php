#!/usr/bin/env php
<?php
/**
 * PreAuth Agent Cron Job
 *
 * This cron job runs the automated preauthorization agent tasks:
 * 1. Process retry queue for failed submissions
 * 2. Check status of pending preauth requests
 * 3. Send notifications for status changes
 * 4. Mark expired preauths
 *
 * CRON SCHEDULE RECOMMENDATIONS:
 * - Every 4 hours for retry queue: 0 */4 * * *
 * - Every 2 hours for status checks: 0 */2 * * *
 * - Daily for expiration checks: 0 3 * * *
 *
 * USAGE:
 * php /path/to/collagendirect/admin/cron/preauth-agent.php --task=all
 * php /path/to/collagendirect/admin/cron/preauth-agent.php --task=retry
 * php /path/to/collagendirect/admin/cron/preauth-agent.php --task=status
 * php /path/to/collagendirect/admin/cron/preauth-agent.php --task=expiration
 *
 * CRONTAB EXAMPLE:
 * # PreAuth Agent - Retry failed submissions every 4 hours
 * 0 */4 * * * php /path/to/collagendirect/admin/cron/preauth-agent.php --task=retry >> /var/log/preauth-cron.log 2>&1
 *
 * # PreAuth Agent - Check pending status every 2 hours
 * 0 */2 * * * php /path/to/collagendirect/admin/cron/preauth-agent.php --task=status >> /var/log/preauth-cron.log 2>&1
 *
 * # PreAuth Agent - Check expirations daily at 3am
 * 0 3 * * * php /path/to/collagendirect/admin/cron/preauth-agent.php --task=expiration >> /var/log/preauth-cron.log 2>&1
 *
 * @package CollagenDirect
 */

// Ensure this script is run from command line only
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line\n");
}

// Get the directory of this script
$scriptDir = dirname(__FILE__);
$rootDir = dirname(dirname($scriptDir)); // Go up to collagendirect root

// Load dependencies
require_once $rootDir . '/api/db.php';
require_once $rootDir . '/api/services/PreAuthAgent.php';

// Parse command line arguments
$options = getopt('', ['task:']);
$task = $options['task'] ?? 'all';

// Initialize agent
$agent = new PreAuthAgent();
$db = getDbConnection();

// Log start
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] PreAuth Agent Cron Job Started - Task: {$task}\n";

// Run tasks
try {
    switch ($task) {
        case 'retry':
            runRetryQueue($agent);
            break;

        case 'status':
            checkPendingStatus($agent);
            break;

        case 'expiration':
            checkExpirations($db);
            break;

        case 'all':
            runRetryQueue($agent);
            checkPendingStatus($agent);
            checkExpirations($db);
            break;

        default:
            echo "Unknown task: {$task}\n";
            echo "Valid tasks: retry, status, expiration, all\n";
            exit(1);
    }

    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] PreAuth Agent Cron Job Completed Successfully\n";
    exit(0);

} catch (Exception $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] PreAuth Agent Cron Job Failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Process retry queue for failed submissions
 */
function runRetryQueue($agent) {
    echo "  [RETRY] Processing retry queue...\n";

    $result = $agent->processRetryQueue();

    if ($result['ok']) {
        echo "  [RETRY] Processed {$result['processed']} requests\n";

        foreach ($result['results'] as $item) {
            $status = $item['result']['ok'] ? 'SUCCESS' : 'FAILED';
            echo "    - Preauth {$item['preauth_request_id']}: {$status}\n";
        }
    } else {
        echo "  [RETRY] Failed: {$result['error']}\n";
    }
}

/**
 * Check status of pending preauth requests
 */
function checkPendingStatus($agent) {
    echo "  [STATUS] Checking pending preauth requests...\n";

    $result = $agent->checkPendingRequests();

    if ($result['ok']) {
        echo "  [STATUS] Checked {$result['checked']} requests\n";

        $changedCount = 0;
        foreach ($result['results'] as $item) {
            if ($item['changed']) {
                $changedCount++;
                echo "    - Preauth {$item['preauth_request_id']}: Status changed to {$item['status']}\n";
            }
        }

        if ($changedCount === 0) {
            echo "  [STATUS] No status changes detected\n";
        }
    } else {
        echo "  [STATUS] Failed: {$result['error']}\n";
    }
}

/**
 * Check for expired preauth approvals
 */
function checkExpirations($db) {
    echo "  [EXPIRATION] Checking for expired preauth approvals...\n";

    // Find preauths that have passed their expiration date
    $stmt = $db->query("
        SELECT id, preauth_number, carrier_name, expiration_date
        FROM preauth_requests
        WHERE status = 'approved'
        AND expiration_date IS NOT NULL
        AND expiration_date < NOW()
    ");

    $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($expired) === 0) {
        echo "  [EXPIRATION] No expired preauths found\n";
        return;
    }

    echo "  [EXPIRATION] Found " . count($expired) . " expired preauths\n";

    // Update status to expired
    foreach ($expired as $preauth) {
        $updateStmt = $db->prepare("
            UPDATE preauth_requests
            SET status = 'expired'
            WHERE id = :id
        ");

        $updateStmt->execute([':id' => $preauth['id']]);

        // Log the expiration
        $logStmt = $db->prepare("
            SELECT log_preauth_action(:preauth_request_id, :action, :actor_type, :actor_id, :actor_name, :success, :error_message, :metadata)
        ");

        $logStmt->execute([
            ':preauth_request_id' => $preauth['id'],
            ':action' => 'expired',
            ':actor_type' => 'system',
            ':actor_id' => null,
            ':actor_name' => 'Cron Job',
            ':success' => true,
            ':error_message' => null,
            ':metadata' => json_encode([
                'expiration_date' => $preauth['expiration_date'],
                'preauth_number' => $preauth['preauth_number']
            ])
        ]);

        echo "    - Expired preauth {$preauth['preauth_number']} ({$preauth['carrier_name']})\n";

        // TODO: Send notification to manufacturer and physician
        // that preauth has expired and needs renewal
    }
}
