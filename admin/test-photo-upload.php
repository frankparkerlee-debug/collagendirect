<?php
/**
 * Test photo upload and file system access
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 Photo Upload Diagnostic\n";
echo "=========================\n\n";

// 1. Check database records
echo "1. DATABASE RECORDS:\n";
echo "-------------------\n";
$stmt = $pdo->query("
    SELECT id, photo_path, uploaded_via, uploaded_at, patient_id
    FROM wound_photos
    ORDER BY uploaded_at DESC
    LIMIT 3
");
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($photos as $photo) {
    echo "Photo ID: {$photo['id']}\n";
    echo "  Path in DB: {$photo['photo_path']}\n";
    echo "  Uploaded: {$photo['uploaded_at']} via {$photo['uploaded_via']}\n";

    // Try to find the file
    $paths = [
        __DIR__ . '/../' . ltrim($photo['photo_path'], '/'),
        __DIR__ . '/../../' . ltrim($photo['photo_path'], '/'),
        '/var/www/html/' . ltrim($photo['photo_path'], '/'),
    ];

    $found = false;
    foreach ($paths as $path) {
        if (file_exists($path)) {
            echo "  ✓ FOUND: $path (" . filesize($path) . " bytes)\n";
            $found = true;
            break;
        }
    }

    if (!$found) {
        echo "  ✗ FILE NOT FOUND. Tried:\n";
        foreach ($paths as $path) {
            echo "    - $path\n";
        }
    }
    echo "\n";
}

// 2. Check upload directory
echo "\n2. UPLOAD DIRECTORY:\n";
echo "-------------------\n";

$uploadDirs = [
    __DIR__ . '/../uploads/wound_photos',
    '/var/www/html/uploads/wound_photos',
    '/var/www/html/api/twilio/../../uploads/wound_photos'
];

foreach ($uploadDirs as $dir) {
    $realPath = realpath($dir);
    echo "Directory: $dir\n";
    echo "  Real path: " . ($realPath ?: 'NOT RESOLVED') . "\n";

    if (is_dir($dir)) {
        echo "  ✓ EXISTS\n";

        // Check permissions
        $perms = fileperms($dir);
        echo "  Permissions: " . decoct($perms & 0777) . "\n";
        echo "  Writable: " . (is_writable($dir) ? "✓ YES" : "✗ NO") . "\n";

        // List files
        $files = scandir($dir);
        $imageFiles = array_filter($files, function($f) {
            return preg_match('/\.(jpg|png|heic)$/i', $f);
        });

        echo "  Files: " . count($imageFiles) . " images\n";
        if (count($imageFiles) > 0) {
            echo "  Recent: " . implode(", ", array_slice($imageFiles, 0, 3)) . "\n";
        }
    } else {
        echo "  ✗ DOES NOT EXIST\n";
    }
    echo "\n";
}

// 3. Test write capability
echo "\n3. WRITE TEST:\n";
echo "-------------\n";

$testDir = '/var/www/html/uploads/wound_photos';
$testFile = $testDir . '/test-' . time() . '.txt';

try {
    // Ensure directory exists
    if (!is_dir($testDir)) {
        mkdir($testDir, 0755, true);
        echo "✓ Created directory: $testDir\n";
    }

    // Try to write
    $bytes = file_put_contents($testFile, 'Test content ' . date('Y-m-d H:i:s'));

    if ($bytes) {
        echo "✓ Successfully wrote $bytes bytes to: $testFile\n";
        echo "  File exists: " . (file_exists($testFile) ? "YES" : "NO") . "\n";

        // Clean up
        unlink($testFile);
        echo "✓ Test file deleted\n";
    } else {
        echo "✗ Failed to write to: $testFile\n";
    }

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

// 4. Check recent error logs
echo "\n4. RECENT ERRORS (last 10 related to photos):\n";
echo "---------------------------------------------\n";

$logFile = '/var/log/apache2/error.log';
if (file_exists($logFile) && is_readable($logFile)) {
    $cmd = "grep -i 'twilio\|photo\|wound' $logFile | tail -10";
    exec($cmd, $output);

    if (empty($output)) {
        echo "No recent photo-related errors found\n";
    } else {
        foreach ($output as $line) {
            echo $line . "\n";
        }
    }
} else {
    echo "Cannot access error log: $logFile\n";
}

echo "\n=== Diagnostic Complete ===\n";
