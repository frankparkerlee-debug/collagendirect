<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Twilio Webhook Activity Check ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Check recent wound photos received
echo "RECENT WOUND PHOTOS (Last 24 hours):\n";
echo "-------------------------------------\n";
$stmt = $pdo->query("
    SELECT wp.id, wp.photo_path, wp.uploaded_via, wp.uploaded_at,
           p.first_name, p.last_name, p.phone
    FROM wound_photos wp
    JOIN patients p ON p.id = wp.patient_id
    WHERE wp.uploaded_at > NOW() - INTERVAL '24 hours'
    ORDER BY wp.uploaded_at DESC
    LIMIT 10
");
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($photos)) {
    echo "✗ No photos received in last 24 hours\n\n";
} else {
    foreach ($photos as $photo) {
        $filename = basename($photo['photo_path']);
        echo "Photo: {$filename}\n";
        echo "  Patient: {$photo['first_name']} {$photo['last_name']} ({$photo['phone']})\n";
        echo "  Source: {$photo['uploaded_via']}\n";
        echo "  Time: {$photo['uploaded_at']}\n\n";
    }
}

// Check recent photo requests
echo "RECENT PHOTO REQUESTS (Last 24 hours):\n";
echo "---------------------------------------\n";
$stmt = $pdo->query("
    SELECT pr.id, pr.created_at, pr.sms_sent, pr.sms_sent_at,
           p.first_name, p.last_name, p.phone
    FROM photo_requests pr
    JOIN patients p ON p.id = pr.patient_id
    WHERE pr.created_at > NOW() - INTERVAL '24 hours'
    ORDER BY pr.created_at DESC
    LIMIT 10
");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($requests)) {
    echo "✗ No photo requests in last 24 hours\n\n";
} else {
    foreach ($requests as $req) {
        echo "Request: {$req['id']}\n";
        echo "  Patient: {$req['first_name']} {$req['last_name']} ({$req['phone']})\n";
        echo "  Created: {$req['created_at']}\n";
        echo "  SMS Sent: " . ($req['sms_sent'] ? "Yes ({$req['sms_sent_at']})" : "No") . "\n\n";
    }
}

// Check all patients with phone numbers
echo "ALL PATIENTS WITH PHONE NUMBERS:\n";
echo "---------------------------------\n";
$stmt = $pdo->query("
    SELECT id, first_name, last_name, phone
    FROM patients
    WHERE phone IS NOT NULL AND phone != ''
    ORDER BY created_at DESC
    LIMIT 20
");
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($patients as $p) {
    echo "{$p['first_name']} {$p['last_name']}: {$p['phone']}\n";
}

echo "\n=== Check Complete ===\n\n";

echo "TROUBLESHOOTING:\n";
echo "----------------\n";
echo "1. Verify Twilio webhook is set to: https://collagendirect.health/api/twilio/receive-mms.php\n";
echo "2. Check Twilio webhook logs: https://console.twilio.com/us1/monitor/logs/webhooks\n";
echo "3. Check Twilio SMS logs: https://console.twilio.com/us1/monitor/logs/sms\n";
echo "4. If webhook is being called but photo not saving, check server error logs\n";
