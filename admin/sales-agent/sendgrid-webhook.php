<?php
/**
 * SendGrid Event Webhook Handler
 *
 * This endpoint receives webhook events from SendGrid for:
 * - Email opens
 * - Link clicks
 * - Bounces
 * - Unsubscribes
 * - Spam reports
 *
 * Setup in SendGrid:
 * 1. Go to Settings > Mail Settings > Event Webhook
 * 2. Enable Event Webhook
 * 3. Set HTTP Post URL to: https://collagendirect.health/admin/sales-agent/sendgrid-webhook.php
 * 4. Select events: Opened, Clicked, Bounced, Dropped, Unsubscribed, Spam Report
 * 5. Add Authorization Header with a secret token
 */

require_once('../config.php');
require_once('sendgrid-integration.php');

// Verify webhook authenticity (optional but recommended)
$webhook_secret = 'your_webhook_secret_token'; // Set this in config.php
$authorization_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if ($authorization_header !== 'Bearer ' . $webhook_secret) {
    // For production, uncomment this to enforce authentication
    // http_response_code(401);
    // exit('Unauthorized');
}

// Get POST data
$json = file_get_contents('php://input');
$events = json_decode($json, true);

if (!$events) {
    http_response_code(400);
    exit('Invalid JSON');
}

// Initialize database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed');
}

// Process each event
$processed_count = 0;

foreach ($events as $event) {
    $event_type = $event['event'] ?? '';
    $timestamp = $event['timestamp'] ?? time();
    $email = $event['email'] ?? '';

    // Extract custom args (lead_id, campaign_id)
    $lead_id = null;
    $campaign_id = null;

    if (isset($event['lead_id'])) {
        $lead_id = (int)$event['lead_id'];
    }
    if (isset($event['campaign_id'])) {
        $campaign_id = (int)$event['campaign_id'];
    }

    // If no lead_id in custom args, try to find by email
    if (!$lead_id && $email) {
        $stmt = $pdo->prepare("SELECT id FROM leads WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lead) {
            $lead_id = $lead['id'];
        }
    }

    if (!$lead_id) {
        continue; // Skip if we can't identify the lead
    }

    // Process event based on type
    switch ($event_type) {
        case 'open':
            // Update outreach_log with open timestamp
            $stmt = $pdo->prepare("
                UPDATE outreach_log
                SET opened_at = FROM_UNIXTIME(?)
                WHERE lead_id = ?
                  AND outreach_type = 'email'
                  AND opened_at IS NULL
                ORDER BY sent_at DESC
                LIMIT 1
            ");
            $stmt->execute([$timestamp, $lead_id]);

            // Update campaign metrics
            if ($campaign_id) {
                $stmt = $pdo->prepare("
                    UPDATE outreach_campaigns
                    SET total_opened = total_opened + 1
                    WHERE id = ?
                ");
                $stmt->execute([$campaign_id]);
            }

            // Log activity
            logActivity($pdo, $lead_id, 'Email opened', $event);
            break;

        case 'click':
            // Update outreach_log with click timestamp
            $stmt = $pdo->prepare("
                UPDATE outreach_log
                SET clicked_at = FROM_UNIXTIME(?)
                WHERE lead_id = ?
                  AND outreach_type = 'email'
                  AND clicked_at IS NULL
                ORDER BY sent_at DESC
                LIMIT 1
            ");
            $stmt->execute([$timestamp, $lead_id]);

            // Update campaign metrics
            if ($campaign_id) {
                $stmt = $pdo->prepare("
                    UPDATE outreach_campaigns
                    SET total_clicked = total_clicked + 1
                    WHERE id = ?
                ");
                $stmt->execute([$campaign_id]);
            }

            // Extract clicked URL
            $url = $event['url'] ?? '';
            logActivity($pdo, $lead_id, "Clicked link: $url", $event);
            break;

        case 'bounce':
        case 'dropped':
            // Mark email as bounced in lead notes
            $reason = $event['reason'] ?? 'Unknown';
            $stmt = $pdo->prepare("
                UPDATE leads
                SET notes = CONCAT(IFNULL(notes, ''), '\n[', NOW(), '] EMAIL BOUNCED: ', ?)
                WHERE id = ?
            ");
            $stmt->execute([$reason, $lead_id]);

            logActivity($pdo, $lead_id, "Email bounced: $reason", $event);
            break;

        case 'deferred':
            // Temporary failure - log but don't mark as bounced
            $reason = $event['reason'] ?? 'Unknown';
            logActivity($pdo, $lead_id, "Email deferred: $reason", $event);
            break;

        case 'delivered':
            // Email successfully delivered
            logActivity($pdo, $lead_id, 'Email delivered', $event);
            break;

        case 'unsubscribe':
            // Move lead to do_not_contact status
            $stmt = $pdo->prepare("
                UPDATE leads
                SET status = 'do_not_contact',
                    notes = CONCAT(IFNULL(notes, ''), '\n[', NOW(), '] Unsubscribed from emails')
                WHERE id = ?
            ");
            $stmt->execute([$lead_id]);

            logActivity($pdo, $lead_id, 'Unsubscribed', $event);
            break;

        case 'spamreport':
            // Mark as spam complaint
            $stmt = $pdo->prepare("
                UPDATE leads
                SET status = 'do_not_contact',
                    notes = CONCAT(IFNULL(notes, ''), '\n[', NOW(), '] SPAM COMPLAINT - DO NOT CONTACT')
                WHERE id = ?
            ");
            $stmt->execute([$lead_id]);

            logActivity($pdo, $lead_id, 'Marked as spam', $event);
            break;

        default:
            // Unknown event type
            logActivity($pdo, $lead_id, "Unknown event: $event_type", $event);
    }

    $processed_count++;
}

// Log webhook receipt
file_put_contents(
    __DIR__ . '/logs/webhook_' . date('Y-m-d') . '.log',
    date('Y-m-d H:i:s') . " - Processed $processed_count events\n",
    FILE_APPEND
);

// Return 200 OK to SendGrid
http_response_code(200);
echo json_encode([
    'success' => true,
    'processed' => $processed_count
]);

/**
 * Log activity to a webhook activity log table (optional)
 */
function logActivity($pdo, $lead_id, $activity, $event_data) {
    // You can create a webhook_events table to store all raw events
    // For now, we'll just add to outreach_log notes

    $event_json = json_encode($event_data);

    $stmt = $pdo->prepare("
        INSERT INTO outreach_log
        (lead_id, outreach_type, subject, message, sent_at)
        VALUES (?, 'email_event', ?, ?, NOW())
    ");

    $stmt->execute([
        $lead_id,
        $activity,
        substr($event_json, 0, 500) // Store first 500 chars of event data
    ]);
}
?>
