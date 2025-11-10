<?php
// Fix baseline photos that weren't properly marked as reviewed
declare(strict_types=1);
require_once __DIR__ . '/db.php';

echo "=== Fixing Baseline Photos ===\n\n";

// Find all photos uploaded via portal_order that don't have reviewed_at set
$stmt = $pdo->query("
    SELECT wp.id, wp.order_id, wp.photo_path, wp.uploaded_via, wp.uploaded_at, wp.reviewed_at
    FROM wound_photos wp
    WHERE wp.uploaded_via = 'portal_order'
    AND wp.reviewed_at IS NULL
    ORDER BY wp.uploaded_at DESC
");

$photos = $stmt->fetchAll();

echo "Found " . count($photos) . " baseline photos without reviewed_at timestamp\n\n";

if (count($photos) === 0) {
    echo "All baseline photos are correctly marked!\n";
    exit;
}

foreach ($photos as $photo) {
    echo "Photo ID: {$photo['id']}\n";
    echo "  Order ID: {$photo['order_id']}\n";
    echo "  Path: {$photo['photo_path']}\n";
    echo "  Uploaded: {$photo['uploaded_at']}\n";

    // Check if file exists
    $path = $photo['photo_path'];
    $possiblePaths = [
        __DIR__ . '/../' . ltrim($path, '/'),
        '/opt/render/project/src/' . ltrim($path, '/'),
    ];

    $fileExists = false;
    foreach ($possiblePaths as $p) {
        if (file_exists($p)) {
            $fileExists = true;
            echo "  File EXISTS: $p\n";
            break;
        }
    }

    if (!$fileExists) {
        echo "  File NOT FOUND - will mark as reviewed anyway\n";
    }

    // Set reviewed_at to uploaded_at (baseline photos are "pre-reviewed")
    $updateStmt = $pdo->prepare("
        UPDATE wound_photos
        SET reviewed_at = uploaded_at
        WHERE id = ?
    ");
    $updateStmt->execute([$photo['id']]);

    echo "  ✓ Set reviewed_at to {$photo['uploaded_at']}\n";
    echo "\n";
}

echo "=== Fix Complete ===\n";
echo "Updated " . count($photos) . " baseline photos\n";
