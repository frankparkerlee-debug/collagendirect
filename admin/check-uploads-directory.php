<?php
// Check uploads directory structure and permissions
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Uploads Directory Check ===\n\n";

$baseDir = __DIR__ . '/../uploads';

echo "Base directory: $baseDir\n";
echo "Exists: " . (is_dir($baseDir) ? 'YES' : 'NO') . "\n";

if (is_dir($baseDir)) {
    echo "Readable: " . (is_readable($baseDir) ? 'YES' : 'NO') . "\n";
    echo "Writable: " . (is_writable($baseDir) ? 'YES' : 'NO') . "\n";
    echo "Permissions: " . substr(sprintf('%o', fileperms($baseDir)), -4) . "\n\n";
}

$subdirs = [
    'wound-photos',
    'notes',
    'ids',
    'insurance',
    'aob'
];

foreach ($subdirs as $subdir) {
    $path = $baseDir . '/' . $subdir;
    echo "Directory: $subdir\n";
    echo "  Path: $path\n";
    echo "  Exists: " . (is_dir($path) ? 'YES' : 'NO') . "\n";

    if (is_dir($path)) {
        echo "  Readable: " . (is_readable($path) ? 'YES' : 'NO') . "\n";
        echo "  Writable: " . (is_writable($path) ? 'YES' : 'NO') . "\n";
        echo "  Permissions: " . substr(sprintf('%o', fileperms($path)), -4) . "\n";

        $files = scandir($path);
        $fileCount = count($files) - 2; // minus . and ..
        echo "  Files: $fileCount\n";

        if ($fileCount > 0 && $fileCount < 20) {
            echo "  Contents:\n";
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    echo "    - $file\n";
                }
            }
        }
    } else {
        echo "  ⚠ DIRECTORY DOES NOT EXIST\n";
        echo "  Attempting to create...\n";
        if (mkdir($path, 0755, true)) {
            echo "  ✓ Created successfully\n";
        } else {
            echo "  ✗ Failed to create\n";
        }
    }

    echo "\n";
}

// Check for the specific photo from the error
$specificPhoto = $baseDir . '/wound-photos/baseline-20251110-231200-c9d66e.jpg';
echo "=== Specific Photo Check ===\n";
echo "Looking for: $specificPhoto\n";
echo "Exists: " . (file_exists($specificPhoto) ? 'YES' : 'NO') . "\n";

if (!file_exists($specificPhoto)) {
    // Check parent directory
    $parentDir = dirname($specificPhoto);
    echo "\nParent directory: $parentDir\n";
    echo "Exists: " . (is_dir($parentDir) ? 'YES' : 'NO') . "\n";
    echo "Writable: " . (is_writable($parentDir) ? 'YES' : 'NO') . "\n";
}
