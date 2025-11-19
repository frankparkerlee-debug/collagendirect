<?php
/**
 * Disk Setup Diagnostic Script
 * Checks if persistent disk is properly configured on Render
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Persistent Disk Setup Diagnostic ===\n\n";

$upload_path = '/var/www/html/uploads';
$subdirs = ['notes', 'wound-photos', 'wound_photos', 'insurance', 'ids', 'aob', 'rx'];

echo "1. Checking if persistent disk exists...\n";
if (is_dir($upload_path)) {
    echo "   ✓ Persistent disk directory exists: $upload_path\n";

    // Check if this is actually a mounted volume
    $mount_output = shell_exec('mount | grep ' . escapeshellarg($upload_path));
    if ($mount_output) {
        echo "   ✓ Confirmed as mounted volume\n";
        echo "   Mount info: " . trim($mount_output) . "\n\n";
    } else {
        echo "   ⚠️  WARNING: Directory exists but is NOT a mounted volume\n";
        echo "   This means files will be stored in ephemeral container storage\n";
        echo "   Files will be LOST on container restart!\n\n";
    }

    echo "2. Checking disk permissions...\n";
    if (is_writable($upload_path)) {
        echo "   ✓ Directory is writable\n\n";
    } else {
        echo "   ✗ Directory is NOT writable\n";
        echo "   Owner: " . posix_getpwuid(fileowner($upload_path))['name'] . "\n";
        echo "   Permissions: " . substr(sprintf('%o', fileperms($upload_path)), -4) . "\n\n";
    }

    echo "3. Checking subdirectories...\n";
    foreach ($subdirs as $subdir) {
        $full_path = "$upload_path/$subdir";
        if (is_dir($full_path)) {
            $writable = is_writable($full_path) ? '✓' : '✗';
            echo "   $writable $subdir/\n";
        } else {
            echo "   - $subdir/ (will be created on first upload)\n";
        }
    }

    echo "\n4. Testing write capability...\n";
    $test_file = "$upload_path/test-" . bin2hex(random_bytes(4)) . ".txt";
    try {
        if (file_put_contents($test_file, "Test write at " . date('Y-m-d H:i:s'))) {
            echo "   ✓ Successfully wrote test file: $test_file\n";
            unlink($test_file);
            echo "   ✓ Successfully deleted test file\n\n";
        } else {
            echo "   ✗ Failed to write test file\n\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n\n";
    }

    echo "5. Checking existing uploads...\n";
    $total_files = 0;
    $total_size = 0;
    foreach ($subdirs as $subdir) {
        $full_path = "$upload_path/$subdir";
        if (is_dir($full_path)) {
            $files = glob("$full_path/*");
            $count = count($files);
            $size = 0;
            foreach ($files as $file) {
                if (is_file($file)) $size += filesize($file);
            }
            $total_files += $count;
            $total_size += $size;
            echo "   $subdir/: $count files (" . round($size / 1024 / 1024, 2) . " MB)\n";
        }
    }
    echo "   Total: $total_files files (" . round($total_size / 1024 / 1024, 2) . " MB)\n\n";

    echo "=== ✓ Persistent Disk is Properly Configured ===\n";

} else {
    echo "   ✗ Persistent disk directory does NOT exist\n\n";

    echo "=== SETUP REQUIRED ===\n\n";
    echo "The persistent disk is not configured on Render.\n";
    echo "Files are currently being saved to the ephemeral container filesystem\n";
    echo "and will be LOST on every deployment or restart.\n\n";

    echo "TO FIX THIS:\n\n";
    echo "1. Go to Render Dashboard: https://dashboard.render.com\n";
    echo "2. Select your web service\n";
    echo "3. Click 'Disks' tab in the left sidebar\n";
    echo "4. Click 'Add Disk'\n";
    echo "5. Configure:\n";
    echo "   - Name: uploads\n";
    echo "   - Mount Path: /var/www/html/uploads\n";
    echo "   - Size: 1 GB (or more based on needs)\n";
    echo "6. Click 'Save'\n";
    echo "7. Render will restart your service with the persistent disk mounted\n\n";

    echo "CURRENT FALLBACK BEHAVIOR:\n";
    echo "- Files are being saved to: " . ($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html') . "/uploads\n";
    echo "- This is ephemeral storage (lost on restart)\n";
    echo "- Check current fallback location:\n";

    $fallback = ($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html') . '/uploads';
    if (is_dir($fallback)) {
        echo "   ✓ Fallback directory exists: $fallback\n";
        echo "   Files currently here:\n";
        foreach ($subdirs as $subdir) {
            $full_path = "$fallback/$subdir";
            if (is_dir($full_path)) {
                $files = glob("$full_path/*");
                echo "   - $subdir/: " . count($files) . " files\n";
            }
        }
    } else {
        echo "   - Fallback directory does not exist yet\n";
        echo "   - Will be created on first upload\n";
    }

    echo "\n=== WARNING: Configure persistent disk ASAP to prevent data loss ===\n";
}
