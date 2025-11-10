<?php
// Debug specific order photo
declare(strict_types=1);
require_once __DIR__ . '/db.php';

$orderId = 'c9d66e3c27f310481d1b7c27ca030ce7';

echo "=== Order Photo Debug for $orderId ===\n\n";

// Get order details
$orderStmt = $pdo->prepare("SELECT id, patient_id, created_at FROM orders WHERE id = ?");
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    echo "Order NOT FOUND\n";
    exit;
}

echo "Order ID: {$order['id']}\n";
echo "Patient ID: {$order['patient_id']}\n";
echo "Created: {$order['created_at']}\n\n";

// Get photos for this order
$photoStmt = $pdo->prepare("
    SELECT id, photo_path, photo_mime, photo_size_bytes, uploaded_via, uploaded_at, reviewed_at
    FROM wound_photos
    WHERE order_id = ?
");
$photoStmt->execute([$orderId]);
$photos = $photoStmt->fetchAll();

echo "Photos found: " . count($photos) . "\n\n";

foreach ($photos as $photo) {
    echo "Photo ID: {$photo['id']}\n";
    echo "  Path: {$photo['photo_path']}\n";
    echo "  MIME: {$photo['photo_mime']}\n";
    echo "  Size: {$photo['photo_size_bytes']} bytes\n";
    echo "  Via: {$photo['uploaded_via']}\n";
    echo "  Uploaded: {$photo['uploaded_at']}\n";
    echo "  Reviewed: " . ($photo['reviewed_at'] ?? 'NULL (NOT MARKED AS BASELINE!)') . "\n";

    // Check if file exists
    $path = $photo['photo_path'];
    $possiblePaths = [
        __DIR__ . '/../' . ltrim($path, '/'),
        '/opt/render/project/src/' . ltrim($path, '/'),
    ];

    $found = false;
    foreach ($possiblePaths as $p) {
        if (file_exists($p)) {
            echo "  File EXISTS at: $p\n";
            $found = true;
            break;
        }
    }

    if (!$found) {
        echo "  File NOT FOUND. Tried:\n";
        foreach ($possiblePaths as $p) {
            echo "    - $p\n";
        }
    }

    echo "\n";
}

// Check wound_photos directory
echo "=== Checking wound-photos directory ===\n";
$woundPhotosDir = __DIR__ . '/../uploads/wound-photos';
if (is_dir($woundPhotosDir)) {
    echo "Directory exists at: $woundPhotosDir\n";
    $files = scandir($woundPhotosDir);
    $orderFiles = array_filter($files, function($f) use ($orderId) {
        return strpos($f, substr($orderId, 0, 6)) !== false;
    });

    if (count($orderFiles) > 0) {
        echo "Files matching order:\n";
        foreach ($orderFiles as $f) {
            echo "  - $f\n";
        }
    } else {
        echo "No files found matching order ID\n";
    }
} else {
    echo "Directory does NOT exist\n";
}
