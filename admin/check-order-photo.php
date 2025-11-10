<?php
// Quick diagnostic: Check for photo from order 31f57fc42b8fea32633b8a657ffa9f97
declare(strict_types=1);
require_once __DIR__ . '/db.php';

$orderId = '31f57fc42b8fea32633b8a657ffa9f97';

echo "=== Order Photo Diagnostic ===\n\n";

// Check order existence
$orderStmt = $pdo->prepare("SELECT id, patient_id, created_at FROM orders WHERE id = ?");
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    echo "Order NOT FOUND: $orderId\n";
    exit;
}

echo "Order Found:\n";
echo "  ID: {$order['id']}\n";
echo "  Patient ID: {$order['patient_id']}\n";
echo "  Created: {$order['created_at']}\n\n";

// Check wound_photos table
$photoStmt = $pdo->prepare("
    SELECT id, photo_path, photo_mime, photo_size_bytes, uploaded_via, uploaded_at, reviewed_at
    FROM wound_photos
    WHERE order_id = ?
");
$photoStmt->execute([$orderId]);
$photos = $photoStmt->fetchAll();

echo "Photos in wound_photos table: " . count($photos) . "\n";
foreach ($photos as $photo) {
    echo "  - ID: {$photo['id']}\n";
    echo "    Path: {$photo['photo_path']}\n";
    echo "    MIME: {$photo['photo_mime']}\n";
    echo "    Size: {$photo['photo_size_bytes']} bytes\n";
    echo "    Via: {$photo['uploaded_via']}\n";
    echo "    Uploaded: {$photo['uploaded_at']}\n";
    echo "    Reviewed: {$photo['reviewed_at']}\n\n";
}

if (count($photos) === 0) {
    echo "\nNo photos found in database for this order.\n";
    echo "This indicates the photo was uploaded BEFORE the baseline_wound_photo\n";
    echo "handling code was deployed. The file was sent but not processed.\n";
}

// Check filesystem
echo "\n=== Filesystem Check ===\n";
$uploadRoot = __DIR__ . '/../uploads';
$woundPhotosDir = $uploadRoot . '/wound-photos';

if (!is_dir($woundPhotosDir)) {
    echo "wound-photos directory does NOT exist\n";
} else {
    echo "wound-photos directory exists\n";
    $files = scandir($woundPhotosDir);
    $relevantFiles = array_filter($files, function($f) use ($orderId) {
        return strpos($f, substr($orderId, 0, 6)) !== false;
    });

    echo "Files matching order ID: " . count($relevantFiles) . "\n";
    foreach ($relevantFiles as $file) {
        echo "  - $file\n";
    }
}

// Check other upload directories
echo "\n=== Other Upload Directories ===\n";
$otherDirs = ['notes', 'ids', 'insurance', 'aob'];
foreach ($otherDirs as $dir) {
    $path = $uploadRoot . '/' . $dir;
    if (is_dir($path)) {
        $files = scandir($path);
        $relevantFiles = array_filter($files, function($f) use ($orderId) {
            return strpos($f, substr($orderId, 0, 6)) !== false;
        });

        if (count($relevantFiles) > 0) {
            echo "$dir directory:\n";
            foreach ($relevantFiles as $file) {
                echo "  - $file\n";
            }
        }
    }
}
