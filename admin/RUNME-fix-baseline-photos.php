<?php
/**
 * IMMEDIATE FIX: Mark baseline photos as reviewed
 * Run this script once to fix all baseline photos
 */
declare(strict_types=1);

// No auth required for this one-time fix script
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== BASELINE PHOTO FIX ===\n\n";

try {
    // Update all photos uploaded via portal_order to have reviewed_at set
    $updateStmt = $pdo->prepare("
        UPDATE wound_photos
        SET reviewed_at = uploaded_at
        WHERE uploaded_via = 'portal_order'
        AND reviewed_at IS NULL
    ");

    $updateStmt->execute();
    $count = $updateStmt->rowCount();

    echo "✓ Updated $count baseline photo(s)\n\n";

    if ($count > 0) {
        // Show what was fixed
        $selectStmt = $pdo->query("
            SELECT
                id,
                order_id,
                photo_path,
                uploaded_at,
                reviewed_at
            FROM wound_photos
            WHERE uploaded_via = 'portal_order'
            ORDER BY uploaded_at DESC
            LIMIT 20
        ");

        $photos = $selectStmt->fetchAll();

        echo "Recently fixed photos:\n";
        echo str_repeat('-', 80) . "\n";

        foreach ($photos as $photo) {
            echo "Order: {$photo['order_id']}\n";
            echo "  Photo ID: {$photo['id']}\n";
            echo "  Path: {$photo['photo_path']}\n";
            echo "  Uploaded: {$photo['uploaded_at']}\n";
            echo "  Reviewed: {$photo['reviewed_at']}\n";
            echo "\n";
        }
    } else {
        echo "No photos needed fixing - all baseline photos are already marked!\n";
    }

    echo str_repeat('=', 80) . "\n";
    echo "SUCCESS!\n\n";
    echo "Baseline photos will now:\n";
    echo "  ✓ Show 'Baseline' badge (gray)\n";
    echo "  ✓ NOT appear in 'Pending Review' queue\n";
    echo "  ✓ NOT be billable\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    http_response_code(500);
}
