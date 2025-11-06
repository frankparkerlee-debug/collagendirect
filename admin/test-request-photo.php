<?php
declare(strict_types=1);

/**
 * Test Photo Request Feature
 * Tests the manual "Request Photo" button functionality
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../api/lib/env.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Testing Request Photo Feature ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Get a test patient (the one you've been using)
$patientId = 'e290bb6939ec615e66484dba8fa9f3d1'; // Test 1029A

try {
    // Get patient details
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, phone, user_id
        FROM patients
        WHERE id = ?
    ");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo "✗ Patient not found\n";
        exit(1);
    }

    echo "PATIENT INFO:\n";
    echo "-------------\n";
    echo "Name: {$patient['first_name']} {$patient['last_name']}\n";
    echo "Phone: {$patient['phone']}\n";
    echo "Patient ID: {$patient['id']}\n\n";

    // Check Twilio configuration
    echo "TWILIO CONFIGURATION:\n";
    echo "--------------------\n";
    $accountSid = env('TWILIO_ACCOUNT_SID');
    $authToken = env('TWILIO_AUTH_TOKEN');
    $fromPhone = env('TWILIO_FROM_PHONE');

    echo "Account SID: " . ($accountSid ? substr($accountSid, 0, 10) . "..." : "✗ NOT SET") . "\n";
    echo "Auth Token: " . ($authToken ? "✓ SET" : "✗ NOT SET") . "\n";
    echo "From Phone: " . ($fromPhone ?: "✗ NOT SET") . "\n\n";

    if (!$accountSid || !$authToken || !$fromPhone) {
        echo "✗ Twilio not fully configured\n";
        exit(1);
    }

    // Create test photo request
    echo "CREATING PHOTO REQUEST:\n";
    echo "-----------------------\n";

    $requestId = bin2hex(random_bytes(16));
    $uploadToken = bin2hex(random_bytes(16));
    $tokenExpires = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60)); // 7 days

    $stmt = $pdo->prepare("
        INSERT INTO photo_requests
        (id, patient_id, physician_id, requested_by, wound_location, upload_token, token_expires_at, order_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())
    ");

    $stmt->execute([
        $requestId,
        $patient['id'],
        $patient['user_id'],
        $patient['user_id'],
        'wound',
        $uploadToken,
        $tokenExpires
    ]);

    echo "✓ Photo request created (ID: {$requestId})\n";
    echo "  Upload token: {$uploadToken}\n";
    echo "  Expires: {$tokenExpires}\n\n";

    // Test sending SMS
    echo "SENDING SMS:\n";
    echo "------------\n";

    require_once __DIR__ . '/../api/lib/twilio_helper.php';

    try {
        $twilioHelper = new TwilioHelper();

        $result = $twilioHelper->sendPhotoRequest(
            $patient['phone'],
            $patient['first_name'],
            $uploadToken
        );

        if ($result['success']) {
            echo "✓ SMS SENT SUCCESSFULLY!\n";
            echo "  Message SID: {$result['sid']}\n";
            echo "  Status: {$result['status']}\n";
            echo "  To: {$patient['phone']}\n\n";

            // Update request
            $pdo->prepare("UPDATE photo_requests SET sms_sent = TRUE, sms_sent_at = NOW() WHERE id = ?")
                ->execute([$requestId]);

            echo "✓ Photo request updated\n\n";

            echo "MESSAGE SENT:\n";
            echo "-------------\n";
            echo "Hi {$patient['first_name']}! Please send a photo of your wound by replying\n";
            echo "to this text message with the photo attached.\n\n";
            echo "Or use this link:\n";
            echo "https://collagendirect.health/upload/{$uploadToken}\n\n";
            echo "Reply STOP to opt out.\n\n";

            echo "✓ TEST PASSED!\n";
            echo "\nCheck Twilio logs: https://console.twilio.com/us1/monitor/logs/sms\n";

        } else {
            echo "✗ SMS FAILED\n";
            echo "  Error: {$result['error']}\n\n";

            // Clean up
            $pdo->prepare("DELETE FROM photo_requests WHERE id = ?")->execute([$requestId]);

            exit(1);
        }

    } catch (Exception $e) {
        echo "✗ EXCEPTION: {$e->getMessage()}\n\n";

        // Clean up
        $pdo->prepare("DELETE FROM photo_requests WHERE id = ?")->execute([$requestId]);

        exit(1);
    }

} catch (Throwable $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Test Complete ===\n";
