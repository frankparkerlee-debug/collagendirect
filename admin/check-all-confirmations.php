<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

$orderId = 'bbf4f52b8af9f5d2cdd8f273e4ec0b6a';

echo "All delivery confirmations for order: {$orderId}\n\n";

$stmt = $pdo->prepare("
    SELECT * FROM delivery_confirmations
    WHERE order_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$orderId]);
$confirmations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($confirmations)) {
    echo "No records found\n";
} else {
    foreach ($confirmations as $i => $conf) {
        echo "Record #" . ($i + 1) . ":\n";
        echo "  ID: {$conf['id']}\n";
        echo "  Patient Phone: {$conf['patient_phone']}\n";
        echo "  Token: " . substr($conf['confirmation_token'], 0, 20) . "...\n";
        echo "  SMS Sent At: " . ($conf['sms_sent_at'] ?: 'NULL') . "\n";
        echo "  SMS SID: " . ($conf['sms_sid'] ?: 'NULL') . "\n";
        echo "  Confirmed At: " . ($conf['confirmed_at'] ?: 'NULL') . "\n";
        echo "  Confirmation Method: " . ($conf['confirmation_method'] ?: 'NULL') . "\n";
        echo "  Notes: " . ($conf['notes'] ?: 'NULL') . "\n";
        echo "  Created: {$conf['created_at']}\n";
        echo "\n";
    }
}
