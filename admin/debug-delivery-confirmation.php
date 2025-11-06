<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

$orderId = $_GET['order_id'] ?? 'bbf4f52b8af9f5d2cdd8f273e4ec0b6a';

echo "=== Delivery Confirmation Debug ===\n";
echo "Order ID: {$orderId}\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Check order details
    echo "ORDER DETAILS:\n";
    echo "----------------------------------------\n";
    $orderStmt = $pdo->prepare("
        SELECT o.id, o.status, o.product, o.delivered_at,
               p.first_name, p.last_name, p.phone, p.email,
               u.first_name AS phys_first, u.last_name AS phys_last, u.practice_name
        FROM orders o
        LEFT JOIN patients p ON p.id = o.patient_id
        LEFT JOIN users u ON u.id = o.user_id
        WHERE o.id = ?
    ");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo "✗ Order not found!\n";
        exit;
    }

    echo "Patient: {$order['first_name']} {$order['last_name']}\n";
    echo "Phone: " . ($order['phone'] ?: '(none)') . "\n";
    echo "Email: " . ($order['email'] ?: '(none)') . "\n";
    echo "Status: {$order['status']}\n";
    echo "Delivered At: " . ($order['delivered_at'] ?: '(not delivered)') . "\n";
    echo "Product: {$order['product']}\n";

    $physicianName = trim(($order['phys_last'] ?? '') . ($order['phys_first'] ? ', ' . $order['phys_first'] : ''));
    if (empty($physicianName) && !empty($order['practice_name'])) {
        $physicianName = $order['practice_name'];
    }
    echo "Physician: " . ($physicianName ?: '(none)') . "\n\n";

    // Check delivery confirmations
    echo "DELIVERY CONFIRMATION RECORDS:\n";
    echo "----------------------------------------\n";
    $confirmStmt = $pdo->prepare("
        SELECT id, patient_phone, confirmation_token,
               sms_sent_at, sms_status, sms_sid,
               confirmed_at, confirmation_method,
               notes, created_at
        FROM delivery_confirmations
        WHERE order_id = ?
        ORDER BY created_at DESC
    ");
    $confirmStmt->execute([$orderId]);
    $confirmations = $confirmStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($confirmations)) {
        echo "✗ No delivery confirmation records found\n";
        echo "  → This means the SMS send was never attempted\n";
        echo "  → Check if patient has a phone number\n";
        echo "  → Check PHP error logs for exceptions\n\n";
    } else {
        foreach ($confirmations as $i => $conf) {
            echo "\nRecord #" . ($i + 1) . ":\n";
            echo "  ID: {$conf['id']}\n";
            echo "  Phone: {$conf['patient_phone']}\n";
            echo "  Token: " . substr($conf['confirmation_token'], 0, 16) . "...\n";
            echo "  SMS Sent: " . ($conf['sms_sent_at'] ?: '(not sent)') . "\n";
            echo "  SMS SID: " . ($conf['sms_sid'] ?: '(none)') . "\n";
            echo "  SMS Status: " . ($conf['sms_status'] ?: '(none)') . "\n";
            echo "  Confirmed: " . ($conf['confirmed_at'] ?: '(not confirmed)') . "\n";
            echo "  Confirmation Method: " . ($conf['confirmation_method'] ?: '(none)') . "\n";
            echo "  Notes: " . ($conf['notes'] ?: '(none)') . "\n";
            echo "  Created: {$conf['created_at']}\n";
        }
        echo "\n";
    }

    // Check Twilio configuration
    echo "TWILIO CONFIGURATION:\n";
    echo "----------------------------------------\n";
    require_once __DIR__ . '/../api/lib/env.php';

    $accountSid = env('TWILIO_ACCOUNT_SID');
    $authToken = env('TWILIO_AUTH_TOKEN');
    $fromPhone = env('TWILIO_FROM_PHONE');

    echo "Account SID: " . ($accountSid ? substr($accountSid, 0, 10) . "..." : '(not set)') . "\n";
    echo "Auth Token: " . ($authToken ? '(set)' : '(not set)') . "\n";
    echo "From Phone: " . ($fromPhone ?: '(not set)') . "\n\n";

    if (empty($accountSid) || empty($authToken) || empty($fromPhone)) {
        echo "✗ Twilio credentials not properly configured!\n";
        echo "  → Check .env file has TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_PHONE\n\n";
    } else {
        echo "✓ Twilio credentials are configured\n\n";
    }

    // Simulate what would happen if we tried to send SMS
    echo "SIMULATION:\n";
    echo "----------------------------------------\n";
    if (empty($order['phone'])) {
        echo "✗ SMS would NOT be sent - patient has no phone number\n";
    } else {
        echo "✓ SMS would be sent to: {$order['phone']}\n";

        require_once __DIR__ . '/../api/lib/twilio_sms.php';
        $normalized = normalize_phone_number($order['phone']);
        echo "  Normalized to: " . ($normalized ?: '(invalid format)') . "\n";

        if ($normalized) {
            $token = 'test123';
            $confirmUrl = "https://collagendirect.health/confirm-delivery?token={$token}";
            $patientName = trim($order['first_name'] . ' ' . $order['last_name']);

            if (!empty($physicianName)) {
                $message = "Hi {$patientName}, your wound care supplies from Dr. {$physicianName} were delivered. Please confirm receipt: {$confirmUrl}";
            } else {
                $message = "Hi {$patientName}, your wound care supplies were delivered. Please confirm receipt: {$confirmUrl}";
            }

            echo "\n  Message that would be sent:\n";
            echo "  ---\n";
            echo "  " . str_replace("\n", "\n  ", $message) . "\n";
            echo "  ---\n";
        }
    }

    echo "\n✓ Debug complete!\n";

} catch (Throwable $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Debug Complete ===\n";
