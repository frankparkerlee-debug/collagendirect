<?php
/**
 * Debug Upload Paths
 * Shows exactly what paths the system is using for file uploads
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Upload Paths Debug ===\n\n";

echo "1. Portal Upload Paths (from portal/index.php logic):\n";

// Simulate portal/index.php logic
if (is_dir('/opt/render/project/src/uploads')) {
    $UPLOAD_ROOT = '/opt/render/project/src/uploads';
    echo "   Using: PERSISTENT DISK\n";
} else {
    $UPLOAD_ROOT = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
    echo "   Using: LOCAL FALLBACK\n";
}

echo "   \$UPLOAD_ROOT = $UPLOAD_ROOT\n\n";

$DIRS = [
    'ids'          => $UPLOAD_ROOT . '/ids',
    'insurance'    => $UPLOAD_ROOT . '/insurance',
    'notes'        => $UPLOAD_ROOT . '/notes',
    'aob'          => $UPLOAD_ROOT . '/aob',
    'wound_photos' => $UPLOAD_ROOT . '/wound-photos',
];

foreach ($DIRS as $type => $path) {
    $exists = is_dir($path) ? '✓' : '✗';
    $writable = is_writable($path) ? 'writable' : 'NOT writable';
    echo "   $exists \$DIRS['$type'] = $path ($writable)\n";
}

echo "\n2. API Upload Paths (from api/portal/orders.create.php logic):\n";

function dir_from_docroot_test(string $subdir): string {
    if (is_dir('/opt/render/project/src/uploads')) {
        $subdir = '/' . ltrim($subdir, '/');
        $subdir = preg_replace('#^/uploads/#', '/', $subdir);
        return '/opt/render/project/src/uploads' . $subdir;
    }
    $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    if ($root === '') {
        $root = dirname(__DIR__, 2);
    }
    $subdir = '/' . ltrim($subdir, '/');
    return $root . $subdir;
}

$api_paths = [
    'notes' => dir_from_docroot_test('/uploads/notes'),
    'insurance' => dir_from_docroot_test('/uploads/insurance'),
    'ids' => dir_from_docroot_test('/uploads/ids'),
    'wound-photos' => dir_from_docroot_test('/uploads/wound-photos'),
];

foreach ($api_paths as $type => $path) {
    $exists = is_dir($path) ? '✓' : '✗';
    $writable = is_writable($path) ? 'writable' : 'NOT writable';
    $persistent = (strpos($path, '/opt/render/project/src/uploads') === 0) ? 'PERSISTENT' : 'EPHEMERAL';
    echo "   $exists $type: $path ($writable, $persistent)\n";
}

echo "\n3. Environment Info:\n";
echo "   DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set') . "\n";
echo "   __DIR__: " . __DIR__ . "\n";
echo "   dirname(__DIR__, 2): " . dirname(__DIR__, 2) . "\n";

echo "\n4. Test File Creation:\n";
$test_path = $UPLOAD_ROOT . '/test-debug-' . bin2hex(random_bytes(4)) . '.txt';
if (file_put_contents($test_path, "Debug test at " . date('Y-m-d H:i:s'))) {
    echo "   ✓ Successfully created: $test_path\n";
    unlink($test_path);
    echo "   ✓ Successfully deleted test file\n";
} else {
    echo "   ✗ Failed to create test file\n";
}

echo "\n=== Summary ===\n";
if (is_dir('/opt/render/project/src/uploads')) {
    $mount_check = shell_exec('mount | grep ' . escapeshellarg('/opt/render/project/src/uploads'));
    if ($mount_check) {
        echo "✅ System is configured to use PERSISTENT DISK\n";
        echo "   Mount: " . trim($mount_check) . "\n";
    } else {
        echo "⚠️  WARNING: Using /opt/render/project/src/uploads but it's NOT mounted\n";
    }
} else {
    echo "❌ System is using EPHEMERAL storage (files will be lost!)\n";
}

echo "\nDebug complete! Timestamp: " . date('Y-m-d H:i:s') . "\n";
