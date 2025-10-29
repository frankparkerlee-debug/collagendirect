<?php
declare(strict_types=1);

/**
 * Twilio SMS Test Endpoint
 * Diagnose SMS sending issues
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin(); // Only admins can access

require_once __DIR__ . '/../api/lib/env.php';
require_once __DIR__ . '/../api/lib/twilio_sms.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Twilio SMS Configuration Test ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Check environment variables
echo "1. Checking Environment Variables:\n";
echo "-----------------------------------\n";

$accountSid = env('TWILIO_ACCOUNT_SID');
$authToken = env('TWILIO_AUTH_TOKEN');
$fromPhone = env('TWILIO_PHONE_NUMBER');

if (empty($accountSid)) {
    echo "❌ TWILIO_ACCOUNT_SID: NOT SET\n";
} else {
    echo "✓ TWILIO_ACCOUNT_SID: " . substr($accountSid, 0, 10) . "..." . substr($accountSid, -4) . "\n";
}

if (empty($authToken)) {
    echo "❌ TWILIO_AUTH_TOKEN: NOT SET\n";
} else {
    echo "✓ TWILIO_AUTH_TOKEN: " . str_repeat('*', 28) . substr($authToken, -4) . "\n";
}

if (empty($fromPhone)) {
    echo "❌ TWILIO_PHONE_NUMBER: NOT SET\n";
} else {
    echo "✓ TWILIO_PHONE_NUMBER: {$fromPhone}\n";
}

echo "\n";

if (empty($accountSid) || empty($authToken) || empty($fromPhone)) {
    echo "❌ ERROR: Missing Twilio credentials in environment variables\n";
    echo "\nPlease add these to Render environment:\n";
    echo "- TWILIO_ACCOUNT_SID\n";
    echo "- TWILIO_AUTH_TOKEN\n";
    echo "- TWILIO_PHONE_NUMBER\n";
    exit(1);
}

// Test phone number normalization
echo "2. Testing Phone Number Normalization:\n";
echo "---------------------------------------\n";

$testNumbers = [
    '5551234567' => '+15551234567',
    '15551234567' => '+15551234567',
    '+15551234567' => '+15551234567',
    '(555) 123-4567' => '+15551234567',
];

foreach ($testNumbers as $input => $expected) {
    $normalized = normalize_phone_number((string)$input);
    if ($normalized === $expected) {
        echo "✓ '{$input}' → '{$normalized}'\n";
    } else {
        echo "❌ '{$input}' → '{$normalized}' (expected: {$expected})\n";
    }
}

echo "\n";

// Get test phone number from query string
$testPhone = $_GET['phone'] ?? '';

if (empty($testPhone)) {
    echo "3. Ready to Send Test SMS:\n";
    echo "---------------------------\n";
    echo "Add ?phone=YOUR_PHONE_NUMBER to the URL to send a test SMS\n";
    echo "Example: ?phone=5551234567\n";
    echo "\nNote: Twilio trial accounts can only send to verified numbers\n";
} else {
    echo "3. Sending Test SMS:\n";
    echo "--------------------\n";
    echo "To: {$testPhone}\n";

    $normalizedPhone = normalize_phone_number($testPhone);
    if (!$normalizedPhone) {
        echo "❌ Invalid phone number format\n";
        exit(1);
    }

    echo "Normalized: {$normalizedPhone}\n\n";

    // Send test SMS
    $result = twilio_send_sms(
        $normalizedPhone,
        "Test message from CollagenDirect. If you receive this, Twilio SMS is working correctly! " . date('H:i:s')
    );

    if ($result['success']) {
        echo "✅ SMS SENT SUCCESSFULLY!\n";
        echo "Message SID: {$result['sid']}\n";
        echo "Status: {$result['status']}\n";
        echo "\nCheck your phone for the test message.\n";
        echo "Also check Twilio Console for delivery status:\n";
        echo "https://console.twilio.com/us1/monitor/logs/sms\n";
    } else {
        echo "❌ SMS SEND FAILED\n";
        echo "Error: {$result['error']}\n";
        echo "\nPossible issues:\n";
        echo "- Twilio credentials are incorrect\n";
        echo "- Phone number is not verified (trial account)\n";
        echo "- Twilio account has insufficient balance\n";
        echo "- From phone number is not active in Twilio\n";
        echo "\nCheck Twilio Console for more details:\n";
        echo "https://console.twilio.com/us1/monitor/logs/sms\n";
    }
}

echo "\n";
echo "4. Recent Delivery Confirmations:\n";
echo "----------------------------------\n";

try {
    $stmt = $pdo->query("
        SELECT dc.order_id, dc.patient_phone, dc.sms_sent_at, dc.sms_status, dc.sms_sid, dc.confirmed_at, dc.notes
        FROM delivery_confirmations dc
        ORDER BY dc.created_at DESC
        LIMIT 5
    ");
    $confirmations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($confirmations)) {
        echo "No delivery confirmations found in database.\n";
    } else {
        foreach ($confirmations as $conf) {
            echo "\nOrder ID: {$conf['order_id']}\n";
            echo "Phone: {$conf['patient_phone']}\n";
            echo "SMS Sent: " . ($conf['sms_sent_at'] ?? 'Not sent') . "\n";
            echo "SMS Status: " . ($conf['sms_status'] ?? 'N/A') . "\n";
            echo "SMS SID: " . ($conf['sms_sid'] ?? 'N/A') . "\n";
            echo "Confirmed: " . ($conf['confirmed_at'] ?? 'Not confirmed') . "\n";
            if ($conf['notes']) {
                echo "Notes: {$conf['notes']}\n";
            }
            echo "---\n";
        }
    }
} catch (Throwable $e) {
    echo "Error querying database: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
