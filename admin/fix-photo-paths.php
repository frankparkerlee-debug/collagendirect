<?php
/**
 * Fix wound photo paths in database
 * Check actual file locations and update paths to be web-accessible
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 Checking wound photo paths...\n\n";

// Get all wound photos
$stmt = $pdo->query("SELECT id, photo_path FROM wound_photos ORDER BY uploaded_at DESC");
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($photos) . " photos in database\n\n";

foreach ($photos as $photo) {
    echo "Photo ID: {$photo['id']}\n";
    echo "  Current path: {$photo['photo_path']}\n";

    // Check if file exists at current path
    $fullPath = __DIR__ . '/../' . ltrim($photo['photo_path'], '/');
    echo "  Full server path: $fullPath\n";
    echo "  File exists: " . (file_exists($fullPath) ? "✓ YES" : "✗ NO") . "\n";

    // Try alternative paths
    $alt1 = __DIR__ . '/../../uploads/wound_photos/' . basename($photo['photo_path']);
    echo "  Alternative 1: $alt1 - " . (file_exists($alt1) ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";

    $alt2 = '/var/www/html/uploads/wound_photos/' . basename($photo['photo_path']);
    echo "  Alternative 2: $alt2 - " . (file_exists($alt2) ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";

    echo "\n";
}

echo "\n📁 Checking upload directories...\n";
$uploadDirs = [
    __DIR__ . '/../uploads/wound_photos',
    __DIR__ . '/../../uploads/wound_photos',
    '/var/www/html/uploads/wound_photos'
];

foreach ($uploadDirs as $dir) {
    echo "\nDirectory: $dir\n";
    if (is_dir($dir)) {
        echo "  ✓ EXISTS\n";
        $files = scandir($dir);
        $imageFiles = array_filter($files, function($f) {
            return preg_match('/\.(jpg|png|heic)$/i', $f);
        });
        echo "  Image files: " . count($imageFiles) . "\n";
        if (count($imageFiles) > 0) {
            echo "  Files: " . implode(", ", array_slice($imageFiles, 0, 5)) . "\n";
        }
    } else {
        echo "  ✗ NOT FOUND\n";
    }
}
