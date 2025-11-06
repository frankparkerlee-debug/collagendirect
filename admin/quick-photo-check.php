<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

$orderNum = 'CD-20251027-DCDA';

$stmt = $pdo->prepare("
    SELECT p.id, p.first_name, p.last_name, p.phone,
           (SELECT COUNT(*) FROM wound_photos WHERE patient_id = p.id) as photo_count,
           (SELECT MAX(created_at) FROM wound_photos WHERE patient_id = p.id) as latest_photo
    FROM patients p
    JOIN orders o ON o.patient_id = p.id
    WHERE o.order_number = ?
");
$stmt->execute([$orderNum]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if ($patient) {
    echo "Patient: {$patient['first_name']} {$patient['last_name']}\n";
    echo "Phone: {$patient['phone']}\n";
    echo "Photos received: {$patient['photo_count']}\n";
    echo "Latest: " . ($patient['latest_photo'] ?: 'none') . "\n\n";

    if ($patient['photo_count'] == 0) {
        echo "✗ No photos received\n";
        echo "\nLikely cause: Twilio webhook not configured\n";
        echo "Configure at: https://console.twilio.com/\n";
        echo "Webhook URL: https://collagendirect.health/api/twilio/receive-mms.php\n";
    }
} else {
    echo "Order not found\n";
}
