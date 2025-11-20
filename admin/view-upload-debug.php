<?php
header('Content-Type: text/plain');

$debugFile = __DIR__ . '/../uploads/order_upload_debug.log';

echo "=== ORDER UPLOAD DEBUG LOG ===\n\n";

if (file_exists($debugFile)) {
    echo "Log file exists at: $debugFile\n\n";
    echo "Last 50 lines:\n";
    echo "---\n";
    echo shell_exec("tail -50 " . escapeshellarg($debugFile));
} else {
    echo "Debug log file not found at: $debugFile\n";
    echo "This means either:\n";
    echo "1. No orders have been created since the debug logging was added\n";
    echo "2. The file path is incorrect\n";
    echo "3. The directory is not writable\n\n";

    // Check if uploads directory exists
    $uploadsDir = __DIR__ . '/../uploads';
    if (is_dir($uploadsDir)) {
        echo "✓ Uploads directory exists\n";
        echo "  Writable: " . (is_writable($uploadsDir) ? "YES" : "NO") . "\n";
    } else {
        echo "✗ Uploads directory does NOT exist\n";
    }
}
