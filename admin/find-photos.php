<?php
/**
 * Find where wound photos are actually stored on the server
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 Finding actual photo locations...\n\n";

// Search common upload locations
$searchPaths = [
    '/var/www/html',
    '/var/www',
    '/tmp',
    '/uploads',
    __DIR__ . '/..',
];

foreach ($searchPaths as $basePath) {
    echo "Searching in: $basePath\n";
    if (!is_dir($basePath)) {
        echo "  ✗ Directory does not exist\n\n";
        continue;
    }

    // Use find command to search for wound photo files
    $cmd = "find " . escapeshellarg($basePath) . " -name 'wound-*.jpg' 2>/dev/null | head -20";
    exec($cmd, $output, $returnCode);

    if (!empty($output)) {
        echo "  ✓ Found " . count($output) . " files:\n";
        foreach ($output as $file) {
            echo "    - $file\n";
            // Get file info
            $size = file_exists($file) ? filesize($file) : 0;
            $time = file_exists($file) ? date('Y-m-d H:i:s', filemtime($file)) : 'unknown';
            echo "      Size: " . number_format($size) . " bytes, Modified: $time\n";
        }
    } else {
        echo "  ✗ No wound photos found\n";
    }
    echo "\n";
}

echo "\n📁 Checking Twilio download location...\n";
echo "receive-mms.php saves to: __DIR__ . '/../../uploads/wound_photos/'\n";
echo "Which would be: " . realpath(__DIR__ . '/../api/twilio/../../uploads/wound_photos/') . "\n\n";

echo "upload.php saves to: __DIR__ . '/uploads/wound_photos/'\n";
echo "Which would be: " . realpath(__DIR__ . '/../uploads/wound_photos/') . "\n\n";
