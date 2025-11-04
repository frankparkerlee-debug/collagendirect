<?php
/**
 * Run all pending migrations
 * Access via: https://collagendirect.health/admin/run-all-migrations.php
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Running All Migrations ===\n\n";

$migrations = [
    'add-provider-response-field.php' => 'Add provider response fields',
    'add-comment-read-tracking.php' => 'Add comment read tracking',
    'add-wound-photo-tables.php' => 'Add wound photo upload and E/M billing tables',
    'add-order-id-to-wound-photos.php' => 'Link wound photos to treatment orders',
    'add-billing-fields-to-patients.php' => 'Add insurance and billing fields to patients table',
    'add-npi-to-users.php' => 'Add NPI field to users table',
    'add-credential-type.php' => 'Add credential type and supervising physician tracking',
    'add-approval-feedback-table.php' => 'Add table to store AI approval feedback for persistence',
    'add-order-lifecycle-fields.php' => 'Add order lifecycle fields for AI-assisted editing workflow',
    'add-order-revisions-table.php' => 'Create order revisions table for audit trail'
];

$success = 0;
$failed = 0;

foreach ($migrations as $file => $description) {
    echo "Running: $description\n";
    echo "File: $file\n";
    
    $path = __DIR__ . '/' . $file;
    
    if (!file_exists($path)) {
        echo "  ⚠️  File not found, skipping...\n\n";
        continue;
    }
    
    try {
        ob_start();
        include $path;
        $output = ob_get_clean();
        
        echo "  ✓ Success\n";
        if ($output) {
            echo "  Output: " . trim($output) . "\n";
        }
        $success++;
    } catch (Throwable $e) {
        $output = ob_get_clean();
        echo "  ✗ Failed: " . $e->getMessage() . "\n";
        if ($output) {
            echo "  Output: " . trim($output) . "\n";
        }
        $failed++;
    }
    
    echo "\n";
}

echo "=== Migration Summary ===\n";
echo "Success: $success\n";
echo "Failed: $failed\n";
echo "\n";

if ($failed === 0) {
    echo "✓ All migrations completed successfully!\n";
} else {
    echo "⚠️  Some migrations failed. Check errors above.\n";
}
