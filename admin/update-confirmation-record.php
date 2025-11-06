<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../api/lib/twilio_sms.php';

header('Content-Type: text/plain; charset=utf-8');

$orderId = $_GET['order_id'] ?? 'bbf4f52b8af9f5d2cdd8f273e4ec0b6a';

echo "=== Updating Delivery Confirmation Record ===\n";
echo "Order ID: {$orderId}\n\n";

try {
    // Get current order and patient info
    $stmt = $pdo->prepare("
        SELECT o.id, p.phone, p.email, p.first_name, p.last_name
        FROM orders o
        JOIN patients p ON p.id = o.patient_id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo "✗ Order not found\n";
        exit(1);
    }

    echo "Current patient phone: {$order['phone']}\n";
    echo "Current patient email: " . ($order['email'] ?: '(none)') . "\n\n";

    // Get existing confirmation record
    $confStmt = $pdo->prepare("
        SELECT id, patient_phone, confirmation_token, sms_sent_at, sms_sid
        FROM delivery_confirmations
        WHERE order_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $confStmt->execute([$orderId]);
    $conf = $confStmt->fetch(PDO::FETCH_ASSOC);

    if (!$conf) {
        echo "✗ No confirmation record found\n";
        exit(1);
    }

    echo "Existing confirmation record:\n";
    echo "  ID: {$conf['id']}\n";
    echo "  Old Phone: {$conf['patient_phone']}\n";
    echo "  Token: " . substr($conf['confirmation_token'], 0, 20) . "...\n";
    echo "  SMS Sent: " . ($conf['sms_sent_at'] ?: 'NULL') . "\n\n";

    // Update the record with current patient phone
    $updateStmt = $pdo->prepare("
        UPDATE delivery_confirmations
        SET patient_phone = ?,
            patient_email = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $updateStmt->execute([
        $order['phone'],
        $order['email'],
        $conf['id']
    ]);

    echo "✓ Updated confirmation record:\n";
    echo "  New Phone: {$order['phone']}\n";
    echo "  New Email: " . ($order['email'] ?: '(none)') . "\n\n";

    // Now send SMS with the SAME token from database
    echo "Sending SMS with existing token...\n";

    $patientName = trim($order['first_name'] . ' ' . $order['last_name']);
    $confirmUrl = "https://collagendirect.health/api/confirm-delivery.php?token=" . urlencode($conf['confirmation_token']);

    $message = "Hi {$patientName}, your wound care supplies were delivered. Please confirm receipt: {$confirmUrl}";

    $result = twilio_send_sms($order['phone'], $message);

    if ($result['success']) {
        // Update with SMS details
        $smsUpdateStmt = $pdo->prepare("
            UPDATE delivery_confirmations
            SET sms_sent_at = NOW(),
                sms_sid = ?,
                sms_status = ?,
                notes = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");

        $smsUpdateStmt->execute([
            $result['sid'],
            $result['status'],
            $conf['id']
        ]);

        echo "✓ SMS sent successfully!\n";
        echo "  Message SID: {$result['sid']}\n";
        echo "  Status: {$result['status']}\n";
        echo "  To: {$order['phone']}\n\n";
        echo "Patient can now click the link to confirm delivery.\n";
    } else {
        echo "✗ SMS failed: {$result['error']}\n";
        exit(1);
    }

} catch (Throwable $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Complete ===\n";
