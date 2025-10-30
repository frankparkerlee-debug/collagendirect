<?php
/**
 * Add comment read tracking to patients table
 * Tracks when provider reads manufacturer comments and when admin reads provider responses
 */

require_once __DIR__ . '/../api/db.php';

try {
    // Add columns to track read status
    $pdo->exec("
        ALTER TABLE patients
        ADD COLUMN IF NOT EXISTS provider_comment_read_at TIMESTAMP,
        ADD COLUMN IF NOT EXISTS admin_response_read_at TIMESTAMP
    ");

    echo "✓ Successfully added comment read tracking columns to patients table\n";
    echo "  - provider_comment_read_at: Tracks when provider reads manufacturer comment\n";
    echo "  - admin_response_read_at: Tracks when admin reads provider response\n";

} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
