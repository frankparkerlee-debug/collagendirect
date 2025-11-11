<?php
/**
 * DELETE BROKEN PHOTO - Run immediately
 * Removes the orphaned photo record for baseline-20251110-231200-c9d66e.jpg
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Delete Broken Photo Record ===\n\n";

try {
    // Find the broken photo
    $selectStmt = $pdo->prepare("
        SELECT id, order_id, patient_id, photo_path, uploaded_at
        FROM wound_photos
        WHERE photo_path LIKE ?
    ");
    $selectStmt->execute(['%baseline-20251110-231200-c9d66e%']);
    $photos = $selectStmt->fetchAll();

    if (count($photos) === 0) {
        echo "✓ No broken photo found - already cleaned up!\n";
        exit;
    }

    echo "Found broken photo record:\n";
    echo str_repeat('-', 80) . "\n";

    foreach ($photos as $photo) {
        echo "Photo ID: {$photo['id']}\n";
        echo "Order ID: {$photo['order_id']}\n";
        echo "Patient ID: {$photo['patient_id']}\n";
        echo "Path: {$photo['photo_path']}\n";
        echo "Uploaded: {$photo['uploaded_at']}\n";
        echo "\n";
    }

    echo "Deleting broken photo record...\n";

    $deleteStmt = $pdo->prepare("
        DELETE FROM wound_photos
        WHERE photo_path LIKE ?
    ");
    $deleteStmt->execute(['%baseline-20251110-231200-c9d66e%']);

    $deleted = $deleteStmt->rowCount();

    echo "✓ Deleted $deleted record(s)\n\n";

    // Verify
    $verifyStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM wound_photos
        WHERE order_id = ?
    ");
    $verifyStmt->execute(['c9d66e3c27f310481d1b7c27ca030ce7']);
    $result = $verifyStmt->fetch();

    echo str_repeat('=', 80) . "\n";
    echo "SUCCESS!\n\n";
    echo "Remaining photos for order c9d66e3c27f310481d1b7c27ca030ce7: {$result['count']}\n";
    echo "\nRefresh the patient page - the broken photo placeholder should be gone!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    http_response_code(500);
}
