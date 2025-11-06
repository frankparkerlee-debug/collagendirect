<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

$orderId = 'bbf4f52b8af9f5d2cdd8f273e4ec0b6a';

echo "Checking order: {$orderId}\n\n";

// Check if delivery confirmation record exists
$stmt = $pdo->prepare("
    SELECT dc.*, p.first_name, p.last_name, p.phone
    FROM delivery_confirmations dc
    JOIN orders o ON o.id = dc.order_id
    JOIN patients p ON p.id = o.patient_id
    WHERE dc.order_id = ?
");
$stmt->execute([$orderId]);
$conf = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conf) {
    echo "Delivery confirmation record EXISTS:\n";
    echo "  Patient: {$conf['first_name']} {$conf['last_name']}\n";
    echo "  Phone in DB: {$conf['phone']}\n";
    echo "  Phone in DC: {$conf['patient_phone']}\n";
    echo "  SMS Sent At: " . ($conf['sms_sent_at'] ?: 'NULL') . "\n";
    echo "  SMS SID: " . ($conf['sms_sid'] ?: 'NULL') . "\n";
    echo "  SMS Status: " . ($conf['sms_status'] ?: 'NULL') . "\n";
    echo "  Notes: " . ($conf['notes'] ?: 'NULL') . "\n";
} else {
    echo "No delivery confirmation record found.\n";
    echo "This means either:\n";
    echo "1. Order was never marked as delivered\n";
    echo "2. Patient has no phone number\n";
    echo "3. An error occurred before record was created\n";
}
