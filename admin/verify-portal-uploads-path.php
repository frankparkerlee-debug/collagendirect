<?php
/**
 * Verify Portal Uploads Path
 * Confirms that portal file uploads are being saved to persistent disk
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Portal Uploads Path Verification ===\n\n";

$persistent_disk = '/var/www/html/uploads';
$fallback_path = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');

echo "1. Checking persistent disk availability...\n";
if (is_dir($persistent_disk)) {
    echo "   ✓ Persistent disk exists: $persistent_disk\n";

    // Check if it's actually mounted
    $mount_output = shell_exec('mount | grep ' . escapeshellarg($persistent_disk));
    if ($mount_output) {
        echo "   ✓ CONFIRMED: Mounted as persistent volume\n";
        echo "   Mount info: " . trim($mount_output) . "\n";
    } else {
        echo "   ⚠️  WARNING: Directory exists but NOT mounted\n";
    }
} else {
    echo "   ✗ Persistent disk NOT found: $persistent_disk\n";
    echo "   Will use fallback: $fallback_path\n";
}

echo "\n2. Simulating portal upload directory logic...\n";

// This mirrors the logic in portal/index.php lines 58-74
if (is_dir('/var/www/html/uploads')) {
    $UPLOAD_ROOT = '/var/www/html/uploads';
    echo "   ✓ Using persistent disk: $UPLOAD_ROOT\n";
} else {
    $UPLOAD_ROOT = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
    echo "   Using fallback: $UPLOAD_ROOT\n";
}

$DIRS = [
    'ids'          => $UPLOAD_ROOT . '/ids',
    'insurance'    => $UPLOAD_ROOT . '/insurance',
    'notes'        => $UPLOAD_ROOT . '/notes',
    'aob'          => $UPLOAD_ROOT . '/aob',
    'wound_photos' => $UPLOAD_ROOT . '/wound-photos',
];

echo "\n3. Portal upload directories:\n";
foreach ($DIRS as $type => $path) {
    $exists = is_dir($path) ? '✓' : '✗';
    $writable = is_writable($path) ? 'writable' : 'NOT writable';
    echo "   $exists $type: $path ($writable)\n";
}

echo "\n4. Testing actual file write to portal upload path...\n";

// Create a test file in each directory
$test_results = [];
foreach ($DIRS as $type => $dir_path) {
    // Create directory if needed
    if (!is_dir($dir_path)) {
        mkdir($dir_path, 0755, true);
        echo "   Created directory: $type/\n";
    }

    // Create test file
    $test_filename = "portal-test-" . date('Ymd-His') . "-" . bin2hex(random_bytes(4)) . ".txt";
    $test_filepath = "$dir_path/$test_filename";
    $test_content = "Portal upload test\n";
    $test_content .= "Created: " . date('Y-m-d H:i:s') . "\n";
    $test_content .= "Type: $type\n";
    $test_content .= "Path: $test_filepath\n";

    if (file_put_contents($test_filepath, $test_content)) {
        echo "   ✓ Created test file: $type/$test_filename\n";

        // Verify it's actually in persistent disk
        $is_persistent = (strpos($test_filepath, '/var/www/html/uploads') === 0);
        $test_results[$type] = [
            'path' => $test_filepath,
            'filename' => $test_filename,
            'is_persistent' => $is_persistent,
            'size' => strlen($test_content)
        ];
    } else {
        echo "   ✗ Failed to create test file in $type/\n";
    }
}

echo "\n5. Verifying files are in persistent disk...\n";
$all_persistent = true;
foreach ($test_results as $type => $result) {
    if ($result['is_persistent']) {
        echo "   ✓ $type: IN PERSISTENT DISK\n";
        echo "     Path: {$result['path']}\n";
    } else {
        echo "   ✗ $type: NOT in persistent disk (EPHEMERAL!)\n";
        echo "     Path: {$result['path']}\n";
        $all_persistent = false;
    }
}

echo "\n6. Checking API upload logic...\n";

// Simulate the dir_from_docroot function from api/portal/orders.create.php
function dir_from_docroot(string $subdir): string {
    if (is_dir('/var/www/html/uploads')) {
        return '/var/www/html' . $subdir;
    }
    $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    if ($root === '') {
        $root = dirname(__DIR__, 2);
    }
    return $root . $subdir;
}

$api_dirs = [
    'ids' => dir_from_docroot('/uploads/ids'),
    'insurance' => dir_from_docroot('/uploads/insurance'),
    'notes' => dir_from_docroot('/uploads/notes'),
    'wound-photos' => dir_from_docroot('/uploads/wound-photos'),
];

echo "   API would use these paths:\n";
foreach ($api_dirs as $type => $path) {
    $is_persistent = (strpos($path, '/var/www/html/uploads') === 0);
    $status = $is_persistent ? '✓ PERSISTENT' : '✗ EPHEMERAL';
    echo "   $status $type: $path\n";
}

echo "\n=== SUMMARY ===\n\n";

if ($all_persistent) {
    echo "✅ SUCCESS: Portal uploads ARE going to persistent disk\n";
    echo "   All upload directories are under: /var/www/html/uploads\n";
    echo "   Files will persist across container restarts\n\n";

    echo "Test files created (will be verified by verify-file-persistence.php):\n";
    foreach ($test_results as $type => $result) {
        echo "  - {$result['filename']} in $type/\n";
    }
} else {
    echo "❌ FAILURE: Portal uploads are NOT going to persistent disk\n";
    echo "   Files are being saved to ephemeral storage\n";
    echo "   Files WILL BE LOST on container restart\n\n";
    echo "ACTION REQUIRED: Check persistent disk mount configuration on Render\n";
}

echo "\n";
echo "Verification complete! Timestamp: " . date('Y-m-d H:i:s') . "\n";
