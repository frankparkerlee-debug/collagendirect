<?php
/**
 * Clean up orphaned photo records
 * Finds wound_photos records where the file doesn't exist on disk
 * and deletes the database records
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Orphaned Photo Cleanup ===\n\n";

try {
    // Get all wound photos
    $stmt = $pdo->query("
        SELECT id, order_id, patient_id, photo_path, uploaded_at, uploaded_via
        FROM wound_photos
        ORDER BY uploaded_at DESC
    ");

    $photos = $stmt->fetchAll();

    echo "Total photos in database: " . count($photos) . "\n\n";

    $orphaned = [];
    $valid = [];

    foreach ($photos as $photo) {
        $path = $photo['photo_path'];

        // Try multiple possible locations
        $possiblePaths = [
            __DIR__ . '/../' . ltrim($path, '/'),
            '/opt/render/project/src/' . ltrim($path, '/'),
        ];

        $found = false;
        foreach ($possiblePaths as $p) {
            if (file_exists($p)) {
                $found = true;
                $valid[] = $photo;
                break;
            }
        }

        if (!$found) {
            $orphaned[] = $photo;
        }
    }

    echo "Valid photos (file exists): " . count($valid) . "\n";
    echo "Orphaned photos (file missing): " . count($orphaned) . "\n\n";

    if (count($orphaned) === 0) {
        echo "✓ No orphaned photos found - all database records have corresponding files!\n";
        exit;
    }

    echo "Orphaned photos to be deleted:\n";
    echo str_repeat('-', 80) . "\n";

    foreach ($orphaned as $photo) {
        echo "Photo ID: {$photo['id']}\n";
        echo "  Order ID: " . ($photo['order_id'] ?? 'N/A') . "\n";
        echo "  Patient ID: {$photo['patient_id']}\n";
        echo "  Path: {$photo['photo_path']}\n";
        echo "  Uploaded: {$photo['uploaded_at']}\n";
        echo "  Via: {$photo['uploaded_via']}\n";
        echo "\n";
    }

    echo str_repeat('=', 80) . "\n";
    echo "Deleting " . count($orphaned) . " orphaned photo record(s)...\n\n";

    // Delete orphaned photos
    $deleteStmt = $pdo->prepare("DELETE FROM wound_photos WHERE id = ?");

    $deleted = 0;
    foreach ($orphaned as $photo) {
        $deleteStmt->execute([$photo['id']]);
        $deleted++;
        echo "✓ Deleted photo {$photo['id']} (order: " . ($photo['order_id'] ?? 'N/A') . ")\n";
    }

    echo "\n" . str_repeat('=', 80) . "\n";
    echo "SUCCESS!\n\n";
    echo "Deleted $deleted orphaned photo record(s)\n";
    echo "Remaining valid photos: " . count($valid) . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    http_response_code(500);
}
