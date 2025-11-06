<?php
declare(strict_types=1);

/**
 * Check if MMS was received for a specific patient/order
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== MMS Receipt Check ===\n\n";

// Find patient/order by order number
$orderNumber = 'CD-20251027-DCDA';
echo "Looking for order: {$orderNumber}\n\n";

try {
    // Get order and patient info
    $stmt = $pdo->prepare("
        SELECT o.id as order_id, o.order_number, o.status,
               p.id as patient_id, p.first_name, p.last_name, p.phone
        FROM orders o
        JOIN patients p ON p.id = o.patient_id
        WHERE o.order_number = ?
    ");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo "✗ Order not found\n";
        exit(1);
    }

    echo "ORDER INFO:\n";
    echo "-----------\n";
    echo "Order ID: {$order['order_id']}\n";
    echo "Status: {$order['status']}\n";
    echo "Patient: {$order['first_name']} {$order['last_name']}\n";
    echo "Phone: {$order['phone']}\n\n";

    // Check for photo requests sent to this patient
    echo "PHOTO REQUESTS:\n";
    echo "---------------\n";
    $stmt = $pdo->prepare("
        SELECT id, created_at, sms_sent, sms_sent_at, upload_token
        FROM photo_requests
        WHERE patient_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$order['patient_id']]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($requests)) {
        echo "✗ No photo requests found for this patient\n\n";
    } else {
        foreach ($requests as $req) {
            echo "Request ID: {$req['id']}\n";
            echo "  Created: {$req['created_at']}\n";
            echo "  SMS Sent: " . ($req['sms_sent'] ? "✓ Yes" : "✗ No") . "\n";
            if ($req['sms_sent_at']) {
                echo "  Sent At: {$req['sms_sent_at']}\n";
            }
            echo "  Upload Token: {$req['upload_token']}\n";
            echo "\n";
        }
    }

    // Check for wound photos from this patient
    echo "WOUND PHOTOS RECEIVED:\n";
    echo "---------------------\n";
    $stmt = $pdo->prepare("
        SELECT id, filename, created_at, source, order_id, twilio_message_sid
        FROM wound_photos
        WHERE patient_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$order['patient_id']]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($photos)) {
        echo "✗ No photos found for this patient\n\n";
    } else {
        echo "Found " . count($photos) . " photo(s):\n\n";
        foreach ($photos as $photo) {
            echo "Photo ID: {$photo['id']}\n";
            echo "  Filename: {$photo['filename']}\n";
            echo "  Created: {$photo['created_at']}\n";
            echo "  Source: {$photo['source']}\n";
            echo "  Order ID: " . ($photo['order_id'] ?: 'none') . "\n";
            if ($photo['twilio_message_sid']) {
                echo "  Twilio SID: {$photo['twilio_message_sid']}\n";
            }
            echo "\n";
        }
    }

    // Check recent Twilio webhook activity (if logged)
    echo "RECENT WEBHOOK ACTIVITY:\n";
    echo "------------------------\n";

    // Normalize phone for search
    $phoneDigits = preg_replace('/[^0-9]/', '', $order['phone']);
    if (strlen($phoneDigits) === 11 && substr($phoneDigits, 0, 1) === '1') {
        $phoneDigits = substr($phoneDigits, 1);
    }

    echo "Searching for phone: {$phoneDigits}, +1{$phoneDigits}, {$order['phone']}\n\n";

    // Check if there are any photos from ANY patient in last hour
    $stmt = $pdo->query("
        SELECT COUNT(*) as count, MAX(created_at) as latest
        FROM wound_photos
        WHERE created_at > NOW() - INTERVAL '1 hour'
    ");
    $recent = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "System-wide recent photos (last hour): {$recent['count']}\n";
    if ($recent['latest']) {
        echo "Latest photo received: {$recent['latest']}\n";
    }
    echo "\n";

} catch (Throwable $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "=== Check Complete ===\n";
echo "\nIf no photos are showing but patient sent MMS:\n";
echo "1. Check Twilio webhook configuration at: https://console.twilio.com/\n";
echo "2. Verify webhook URL is: https://collagendirect.health/api/twilio/receive-mms.php\n";
echo "3. Check webhook error logs: https://console.twilio.com/us1/monitor/logs/webhooks\n";
