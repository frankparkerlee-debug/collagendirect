<?php
/**
 * Test File Persistence
 * Creates test files and verifies they persist in the mounted disk
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== File Persistence Test ===\n\n";

$upload_path = '/var/www/html/uploads';
$test_dirs = ['ids', 'insurance', 'notes', 'wound-photos'];

// Check if upload path exists
if (!is_dir($upload_path)) {
    echo "✗ ERROR: Upload path does not exist: $upload_path\n";
    exit(1);
}

echo "✓ Upload path exists: $upload_path\n";

// Check if it's writable
if (!is_writable($upload_path)) {
    echo "✗ ERROR: Upload path is not writable\n";
    exit(1);
}

echo "✓ Upload path is writable\n\n";

// Test file creation in each subdirectory
echo "Creating test files...\n";
$test_files = [];

foreach ($test_dirs as $dir) {
    $dir_path = "$upload_path/$dir";

    // Create directory if it doesn't exist
    if (!is_dir($dir_path)) {
        if (!mkdir($dir_path, 0755, true)) {
            echo "✗ Failed to create directory: $dir\n";
            continue;
        }
        echo "  Created directory: $dir/\n";
    }

    // Create test file
    $filename = "test-persist-" . date('Ymd-His') . "-" . bin2hex(random_bytes(4)) . ".txt";
    $filepath = "$dir_path/$filename";
    $content = "Test file created at " . date('Y-m-d H:i:s') . "\n";
    $content .= "Directory: $dir\n";
    $content .= "This file tests persistent disk functionality.\n";

    if (file_put_contents($filepath, $content)) {
        echo "  ✓ Created: /$dir/$filename\n";
        $test_files[] = [
            'path' => $filepath,
            'relative' => "/uploads/$dir/$filename",
            'size' => strlen($content),
            'created' => time()
        ];
    } else {
        echo "  ✗ Failed to create file in $dir/\n";
    }
}

echo "\n";
echo "Test files created: " . count($test_files) . "\n\n";

// Save test file info for later verification
$manifest_path = "$upload_path/test-manifest.json";
file_put_contents($manifest_path, json_encode([
    'created_at' => date('Y-m-d H:i:s'),
    'timestamp' => time(),
    'files' => $test_files
], JSON_PRETTY_PRINT));

echo "✓ Test manifest saved: $manifest_path\n\n";

// Verify files immediately
echo "Verifying files exist immediately after creation...\n";
$verified = 0;
foreach ($test_files as $file) {
    if (file_exists($file['path'])) {
        $size = filesize($file['path']);
        echo "  ✓ {$file['relative']} ($size bytes)\n";
        $verified++;
    } else {
        echo "  ✗ {$file['relative']} NOT FOUND\n";
    }
}

echo "\n";
echo "=== Immediate Verification Summary ===\n";
echo "Files created: " . count($test_files) . "\n";
echo "Files verified: $verified\n";

if ($verified === count($test_files)) {
    echo "✓ All files verified immediately\n\n";
} else {
    echo "✗ Some files failed verification\n\n";
}

echo "=== Next Steps ===\n";
echo "1. Wait 10-15 minutes (previous issue: files disappeared after 5-10 min)\n";
echo "2. Run: https://collagendirect.health/admin/verify-file-persistence.php\n";
echo "3. Check if test files still exist\n\n";

echo "Test complete! Timestamp: " . date('Y-m-d H:i:s') . "\n";
