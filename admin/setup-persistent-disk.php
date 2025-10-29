<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Setting Up Persistent Disk for File Uploads ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Check if persistent disk is mounted
if (!is_dir('/var/data/uploads')) {
  echo "❌ ERROR: Persistent disk not found at /var/data/uploads\n";
  echo "Please add a Render disk first:\n";
  echo "  - Name: uploads\n";
  echo "  - Mount Path: /var/data/uploads\n";
  echo "  - Size: 1 GB or more\n\n";
  exit(1);
}

echo "✓ Persistent disk found at /var/data/uploads\n\n";

// Create subdirectories
$directories = [
  '/var/data/uploads/ids',
  '/var/data/uploads/insurance',
  '/var/data/uploads/notes',
  '/var/data/uploads/aob'
];

echo "Creating subdirectories...\n";
foreach ($directories as $dir) {
  if (is_dir($dir)) {
    echo "  ✓ $dir already exists\n";
  } else {
    if (@mkdir($dir, 0755, true)) {
      echo "  ✓ Created $dir\n";
    } else {
      echo "  ❌ Failed to create $dir\n";
      echo "     Error: " . error_get_last()['message'] . "\n";
    }
  }
}

echo "\n";

// Check permissions
echo "Checking permissions...\n";
foreach ($directories as $dir) {
  if (is_dir($dir)) {
    $perms = substr(sprintf('%o', fileperms($dir)), -4);
    $writable = is_writable($dir);
    $status = $writable ? '✓' : '❌';
    echo "  $status $dir - Permissions: $perms, Writable: " . ($writable ? 'YES' : 'NO') . "\n";

    if (!$writable) {
      echo "     Attempting to fix permissions...\n";
      if (@chmod($dir, 0755)) {
        echo "     ✓ Fixed permissions\n";
      } else {
        echo "     ❌ Could not fix permissions\n";
      }
    }
  }
}

echo "\n";

// Test write capability
echo "Testing write capability...\n";
$testFile = '/var/data/uploads/ids/test-' . time() . '.txt';
if (@file_put_contents($testFile, 'test')) {
  echo "  ✓ Successfully wrote test file: $testFile\n";
  if (@unlink($testFile)) {
    echo "  ✓ Successfully deleted test file\n";
  } else {
    echo "  ⚠️  Could not delete test file (but write works)\n";
  }
} else {
  echo "  ❌ Failed to write test file\n";
  echo "     Error: " . error_get_last()['message'] . "\n";
}

echo "\n";

// Show disk space
echo "Disk space information...\n";
$total = disk_total_space('/var/data/uploads');
$free = disk_free_space('/var/data/uploads');
$used = $total - $free;
$usedPercent = round(($used / $total) * 100, 2);

echo "  Total: " . round($total / 1024 / 1024 / 1024, 2) . " GB\n";
echo "  Used: " . round($used / 1024 / 1024, 2) . " MB ($usedPercent%)\n";
echo "  Free: " . round($free / 1024 / 1024 / 1024, 2) . " GB\n";

echo "\n=== Setup Complete ===\n";
echo "You can now upload patient files. They will be stored in /var/data/uploads/\n";
