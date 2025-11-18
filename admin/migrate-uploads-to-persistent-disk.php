<?php
/**
 * Migrate Uploads to Persistent Disk
 * Copies files from ephemeral container storage to persistent disk
 * Run this AFTER configuring the persistent disk on Render
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== Migrate Uploads to Persistent Disk ===\n\n";

$persistent_path = '/var/www/html/uploads';
$ephemeral_path = ($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html') . '/uploads';
$subdirs = ['notes', 'wound_photos', 'insurance', 'ids'];

echo "Source (ephemeral): $ephemeral_path\n";
echo "Target (persistent): $persistent_path\n\n";

// Check if persistent disk exists
if (!is_dir($persistent_path)) {
    echo "✗ ERROR: Persistent disk not found at $persistent_path\n";
    echo "Please configure the persistent disk on Render first.\n";
    echo "See instructions at: https://collagendirect.health/admin/check-disk-setup.php\n";
    exit(1);
}

if (!is_writable($persistent_path)) {
    echo "✗ ERROR: Persistent disk is not writable\n";
    echo "Permissions: " . substr(sprintf('%o', fileperms($persistent_path)), -4) . "\n";
    exit(1);
}

echo "✓ Persistent disk is ready\n\n";

// Check if ephemeral path exists
if (!is_dir($ephemeral_path)) {
    echo "✓ No ephemeral uploads directory found\n";
    echo "Nothing to migrate. All uploads are already using persistent disk.\n";
    exit(0);
}

echo "Starting migration...\n\n";

$total_files = 0;
$total_bytes = 0;
$errors = [];

foreach ($subdirs as $subdir) {
    $source_dir = "$ephemeral_path/$subdir";
    $target_dir = "$persistent_path/$subdir";

    if (!is_dir($source_dir)) {
        echo "Skipping $subdir/ (no source directory)\n";
        continue;
    }

    echo "Processing $subdir/...\n";

    // Create target directory if it doesn't exist
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0775, true)) {
            $errors[] = "Failed to create directory: $target_dir";
            echo "  ✗ Failed to create target directory\n";
            continue;
        }
        echo "  ✓ Created target directory\n";
    }

    // Get all files in source directory
    $files = glob("$source_dir/*");
    $file_count = count($files);

    if ($file_count === 0) {
        echo "  - No files to migrate\n";
        continue;
    }

    echo "  Found $file_count files\n";

    $copied = 0;
    $skipped = 0;

    foreach ($files as $source_file) {
        if (!is_file($source_file)) continue;

        $filename = basename($source_file);
        $target_file = "$target_dir/$filename";

        // Check if file already exists in target
        if (file_exists($target_file)) {
            $source_size = filesize($source_file);
            $target_size = filesize($target_file);

            if ($source_size === $target_size) {
                // Same size, assume it's already migrated
                $skipped++;
                continue;
            } else {
                // Different size, append timestamp to avoid overwrite
                $filename = pathinfo($filename, PATHINFO_FILENAME) . '-migrated-' . time() . '.' . pathinfo($filename, PATHINFO_EXTENSION);
                $target_file = "$target_dir/$filename";
            }
        }

        // Copy file
        if (copy($source_file, $target_file)) {
            $copied++;
            $size = filesize($source_file);
            $total_files++;
            $total_bytes += $size;
        } else {
            $errors[] = "Failed to copy: $source_file";
        }
    }

    echo "  ✓ Copied $copied files";
    if ($skipped > 0) echo " (skipped $skipped duplicates)";
    echo "\n";
}

echo "\n=== Migration Summary ===\n";
echo "Total files migrated: $total_files\n";
echo "Total size: " . round($total_bytes / 1024 / 1024, 2) . " MB\n";

if (count($errors) > 0) {
    echo "\nErrors encountered:\n";
    foreach ($errors as $error) {
        echo "  ✗ $error\n";
    }
}

echo "\n=== Next Steps ===\n";
echo "1. Verify files at: https://collagendirect.health/admin/check-disk-setup.php\n";
echo "2. Update database paths if needed (paths should already be correct)\n";
echo "3. Optionally delete ephemeral directory after confirming migration:\n";
echo "   rm -rf $ephemeral_path\n";
echo "\n✓ Migration complete!\n";
