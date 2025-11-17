<?php
/**
 * Test file persistence - verify files are written to persistent disk
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== File Persistence Test ===\n\n";

// Test writing to uploads directory
$test_file = __DIR__ . '/../uploads/persistence-test-' . date('Y-m-d-His') . '.txt';
$test_content = "Test written at: " . date('Y-m-d H:i:s') . "\nPHP process: " . getmypid();

echo "1. Testing file write to uploads directory...\n";
echo "   Path: $test_file\n";

$written = @file_put_contents($test_file, $test_content);
if ($written === false) {
  echo "   ✗ FAILED to write file\n";
  echo "   Error: " . error_get_last()['message'] ?? 'Unknown error' . "\n";
} else {
  echo "   ✓ Successfully wrote {$written} bytes\n";
}

// Check if file exists
echo "\n2. Verifying file exists...\n";
if (file_exists($test_file)) {
  echo "   ✓ File exists\n";
  $content = file_get_contents($test_file);
  echo "   Content: " . $content . "\n";
} else {
  echo "   ✗ File does NOT exist after write\n";
}

// Check directory permissions
echo "\n3. Checking uploads directory permissions...\n";
$uploads_dir = __DIR__ . '/../uploads';
if (is_dir($uploads_dir)) {
  echo "   ✓ Uploads directory exists\n";
  echo "   Path: " . realpath($uploads_dir) . "\n";

  $perms = fileperms($uploads_dir);
  echo "   Permissions: " . substr(sprintf('%o', $perms), -4) . "\n";

  if (is_writable($uploads_dir)) {
    echo "   ✓ Directory is writable\n";
  } else {
    echo "   ✗ Directory is NOT writable\n";
  }
} else {
  echo "   ✗ Uploads directory does NOT exist\n";
}

// List existing test files
echo "\n4. Listing existing persistence test files...\n";
$test_files = glob($uploads_dir . '/persistence-test-*.txt');
if (empty($test_files)) {
  echo "   No previous test files found (files may not persist across restarts)\n";
} else {
  echo "   Found " . count($test_files) . " previous test file(s):\n";
  foreach ($test_files as $file) {
    $mtime = filemtime($file);
    echo "   - " . basename($file) . " (modified: " . date('Y-m-d H:i:s', $mtime) . ")\n";
  }
}

// Check subdirectories
echo "\n5. Checking uploads subdirectories...\n";
$subdirs = ['ids', 'insurance', 'notes', 'aob', 'rx', 'wound_photos'];
foreach ($subdirs as $subdir) {
  $path = $uploads_dir . '/' . $subdir;
  if (is_dir($path)) {
    $file_count = count(glob($path . '/*'));
    echo "   ✓ {$subdir}: exists ({$file_count} files)\n";
  } else {
    echo "   ✗ {$subdir}: MISSING\n";
  }
}

// Check mount information
echo "\n6. Checking mount point information...\n";
$mount_output = shell_exec('df -h ' . escapeshellarg($uploads_dir) . ' 2>&1');
echo $mount_output;

echo "\n=== Test Complete ===\n";
echo "If test files don't persist across container restarts, the persistent disk is not mounted correctly.\n";
