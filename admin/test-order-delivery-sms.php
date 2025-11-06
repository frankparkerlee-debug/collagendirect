<?php
declare(strict_types=1);

/**
 * Test SMS sending for a specific order
 * Usage: curl 'https://collagendirect.health/admin/test-order-delivery-sms.php?order_id=xxx&send=1'
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../api/lib/twilio_sms.php';
require_once __DIR__ . '/../api/lib/env.php';

header('Content-Type: text/plain; charset=utf-8');

$orderId = $_GET['order_id'] ?? 'bbf4f52b8af9f5d2cdd8f273e4ec0b6a';
$actualSend = isset($_GET['send']) && $_GET['send'] === '1';

echo "=== Delivery SMS Test ===\n";
echo "Order ID: {$orderId}\n";
echo "Mode: " . ($actualSend ? "LIVE (will send SMS)" : "TEST (dry run)") . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Get order and patient details
    $stmt = $pdo->prepare("
        SELECT o.id, o.product, o.status, o.delivered_at,
               p.id as patient_id, p.first_name, p.last_name, p.phone, p.email,
               u.first_name AS phys_first, u.last_name AS phys_last, u.practice_name
        FROM orders o
        LEFT JOIN patients p ON p.id = o.patient_id
        LEFT JOIN users u ON u.id = o.user_id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo "✗ ORDER NOT FOUND\n";
        exit(1);
    }

    echo "ORDER INFO:\n";
    echo "----------------------------------------\n";
    echo "Product: {$order['product']}\n";
    echo "Status: {$order['status']}\n";
    echo "Delivered: " . ($order['delivered_at'] ?: '(not delivered)') . "\n\n";

    echo "PATIENT INFO:\n";
    echo "----------------------------------------\n";
    echo "Patient ID: {$order['patient_id']}\n";
    echo "Name: {$order['first_name']} {$order['last_name']}\n";
    echo "Phone: " . ($order['phone'] ?: '(NONE)') . "\n";
    echo "Email: " . ($order['email'] ?: '(none)') . "\n\n";

    // Check if patient has phone
    if (empty($order['phone'])) {
        echo "✗ CANNOT SEND SMS - Patient has no phone number\n";
        echo "\nTo fix:\n";
        echo "1. Edit patient in portal: https://collagendirect.health/portal\n";
        echo "2. Add phone number for patient {$order['first_name']} {$order['last_name']}\n";
        echo "3. Try marking order as delivered again\n";
        exit(1);
    }

    // Normalize phone
    $normalized = normalize_phone_number($order['phone']);
    if (!$normalized) {
        echo "✗ INVALID PHONE FORMAT: {$order['phone']}\n";
        echo "  Cannot normalize to E.164 format\n";
        exit(1);
    }

    echo "✓ Phone is valid: {$normalized}\n\n";

    // Check physician
    $physicianName = trim(($order['phys_last'] ?? '') . ($order['phys_first'] ? ', ' . $order['phys_first'] : ''));
    if (empty($physicianName) && !empty($order['practice_name'])) {
        $physicianName = $order['practice_name'];
    }
    echo "PHYSICIAN:\n";
    echo "----------------------------------------\n";
    echo ($physicianName ?: '(none)') . "\n\n";

    // Check existing confirmation
    $confStmt = $pdo->prepare("SELECT id, sms_sent_at, sms_sid, confirmed_at FROM delivery_confirmations WHERE order_id = ?");
    $confStmt->execute([$orderId]);
    $existing = $confStmt->fetch(PDO::FETCH_ASSOC);

    echo "EXISTING CONFIRMATION:\n";
    echo "----------------------------------------\n";
    if ($existing) {
        echo "✓ Record exists (ID: {$existing['id']})\n";
        echo "  SMS Sent: " . ($existing['sms_sent_at'] ?: '(not sent)') . "\n";
        echo "  SMS SID: " . ($existing['sms_sid'] ?: '(none)') . "\n";
        echo "  Confirmed: " . ($existing['confirmed_at'] ?: '(not confirmed)') . "\n\n";

        if ($existing['sms_sent_at'] && !$actualSend) {
            echo "NOTE: SMS was already sent previously.\n";
            echo "      Add &send=1 to force resend.\n\n";
        }
    } else {
        echo "(none)\n\n";
    }

    // Check Twilio config
    $twilioSid = env('TWILIO_ACCOUNT_SID');
    $twilioToken = env('TWILIO_AUTH_TOKEN');
    $twilioFrom = env('TWILIO_FROM_PHONE');

    echo "TWILIO CONFIG:\n";
    echo "----------------------------------------\n";
    echo "Account SID: " . ($twilioSid ? substr($twilioSid, 0, 10) . "..." : '✗ NOT SET') . "\n";
    echo "Auth Token: " . ($twilioToken ? '✓ SET' : '✗ NOT SET') . "\n";
    echo "From Phone: " . ($twilioFrom ?: '✗ NOT SET') . "\n\n";

    if (!$twilioSid || !$twilioToken || !$twilioFrom) {
        echo "✗ TWILIO NOT CONFIGURED\n";
        exit(1);
    }

    // Generate message
    $token = bin2hex(random_bytes(32));
    $confirmUrl = "https://collagendirect.health/api/confirm-delivery.php?token={$token}";
    $patientName = trim($order['first_name'] . ' ' . $order['last_name']);

    if (!empty($physicianName)) {
        $message = "Hi {$patientName}, your wound care supplies from Dr. {$physicianName} were delivered. Please confirm receipt: {$confirmUrl}";
    } else {
        $message = "Hi {$patientName}, your wound care supplies were delivered. Please confirm receipt: {$confirmUrl}";
    }

    echo "MESSAGE TO SEND:\n";
    echo "----------------------------------------\n";
    echo "To: {$normalized}\n";
    echo "From: {$twilioFrom}\n";
    echo "Body:\n";
    echo $message . "\n\n";

    if (!$actualSend) {
        echo "✓ DRY RUN COMPLETE - No SMS sent\n";
        echo "\nTo actually send SMS, add: &send=1\n";
        exit(0);
    }

    // Actually send SMS
    echo "SENDING SMS...\n";
    echo "----------------------------------------\n";

    $result = twilio_send_sms($normalized, $message);

    if ($result['success']) {
        echo "✓ SUCCESS!\n";
        echo "  Message SID: {$result['sid']}\n";
        echo "  Status: {$result['status']}\n\n";

        echo "Check Twilio console:\n";
        echo "https://console.twilio.com/us1/monitor/logs/sms\n";
    } else {
        echo "✗ FAILED!\n";
        echo "  Error: {$result['error']}\n\n";

        echo "Check:\n";
        echo "1. Twilio account has credits\n";
        echo "2. Phone number +18884156880 is active\n";
        echo "3. Recipient number {$normalized} is valid\n";
    }

} catch (Throwable $e) {
    echo "\n✗ EXCEPTION: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Test Complete ===\n";
