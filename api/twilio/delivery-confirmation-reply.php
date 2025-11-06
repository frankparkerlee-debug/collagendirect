<?php
declare(strict_types=1);

/**
 * Twilio Webhook: Delivery Confirmation SMS Reply Handler
 *
 * Handles patient SMS replies to delivery confirmation messages
 * Looks for keywords like "YES", "DELIVERED", "CONFIRM", "RECEIVED"
 *
 * Twilio Configuration:
 * - Phone Number: +18884156880
 * - A MESSAGE COMES IN: https://collagendirect.health/api/twilio/delivery-confirmation-reply.php
 * - HTTP POST
 */

require_once __DIR__ . '/../../admin/db.php';
require_once __DIR__ . '/../lib/timezone.php';
require_once __DIR__ . '/../lib/twilio_sms.php';

// Log all incoming requests for debugging
error_log("[delivery-confirmation-reply] Incoming request: " . json_encode($_POST));

// Get Twilio parameters
$from = $_POST['From'] ?? '';  // Patient's phone number
$body = $_POST['Body'] ?? '';  // SMS message text
$messageSid = $_POST['MessageSid'] ?? '';

if (empty($from) || empty($body)) {
    error_log("[delivery-confirmation-reply] Missing required parameters");
    http_response_code(400);
    exit;
}

// Normalize phone number
$normalizedPhone = normalize_phone_number($from);

try {
    // Look for pending confirmation for this phone number
    $stmt = $pdo->prepare("
        SELECT dc.id, dc.order_id, dc.patient_phone,
               p.first_name, p.last_name,
               o.product
        FROM delivery_confirmations dc
        JOIN orders o ON o.id = dc.order_id
        JOIN patients p ON p.id = o.patient_id
        WHERE dc.patient_phone = ?
          AND dc.confirmed_at IS NULL
          AND dc.sms_sent_at IS NOT NULL
          AND dc.sms_sent_at > NOW() - INTERVAL '7 days'
        ORDER BY dc.sms_sent_at DESC
        LIMIT 1
    ");

    $stmt->execute([$normalizedPhone]);
    $confirmation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$confirmation) {
        error_log("[delivery-confirmation-reply] No pending confirmation found for {$normalizedPhone}");

        // Send helpful reply
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<Response>';
        echo '<Message>Thank you for your message. If you need assistance, please contact your physician\'s office.</Message>';
        echo '</Response>';
        exit;
    }

    // Check if message contains confirmation keywords
    $bodyLower = strtolower(trim($body));
    $confirmKeywords = ['yes', 'delivered', 'confirm', 'confirmed', 'received', 'got it', 'got them'];

    $isConfirmation = false;
    foreach ($confirmKeywords as $keyword) {
        if (strpos($bodyLower, $keyword) !== false) {
            $isConfirmation = true;
            break;
        }
    }

    if (!$isConfirmation) {
        error_log("[delivery-confirmation-reply] Message from {$normalizedPhone} does not contain confirmation keyword: {$body}");

        // Send helpful reply
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<Response>';
        echo '<Message>To confirm delivery of your wound care supplies, please reply with "YES" or "DELIVERED". Thank you!</Message>';
        echo '</Response>';
        exit;
    }

    // Record confirmation
    $updateStmt = $pdo->prepare("
        UPDATE delivery_confirmations
        SET confirmed_at = NOW(),
            confirmation_method = 'sms_reply',
            sms_reply_text = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $updateStmt->execute([$body, $confirmation['id']]);

    $patientName = $confirmation['first_name'] . ' ' . $confirmation['last_name'];
    $orderId = substr($confirmation['order_id'], 0, 8);

    error_log("[delivery-confirmation-reply] Order {$confirmation['order_id']} confirmed via SMS reply by {$patientName}. Message: {$body}");

    // Send confirmation reply to patient
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response>';
    echo '<Message>Thank you ' . htmlspecialchars($confirmation['first_name']) . '! Your delivery confirmation has been recorded. If you have any questions, please contact your physician\'s office.</Message>';
    echo '</Response>';

} catch (Throwable $e) {
    error_log("[delivery-confirmation-reply] Error: " . $e->getMessage());
    error_log("[delivery-confirmation-reply] Stack trace: " . $e->getTraceAsString());

    // Send generic error response
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response>';
    echo '<Message>Thank you for your message. We encountered an error processing your confirmation. Please try again or contact your physician\'s office.</Message>';
    echo '</Response>';
}
