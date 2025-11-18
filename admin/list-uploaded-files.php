<?php
/**
 * List All Uploaded Files - Diagnostic
 * Shows all files currently in the uploads directory
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Uploaded Files Diagnostic ===\n\n";

$persistent_path = '/var/www/html/uploads';
$fallback_path = ($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html') . '/uploads';

// Check persistent disk first
if (is_dir($persistent_path)) {
    echo "Using persistent disk: $persistent_path\n\n";
    $upload_root = $persistent_path;
} else {
    echo "Persistent disk not found, using fallback: $fallback_path\n\n";
    $upload_root = $fallback_path;
}

if (!is_dir($upload_root)) {
    echo "✗ Upload directory does not exist: $upload_root\n";
    exit(1);
}

// List all subdirectories and files
$subdirs = scandir($upload_root);
$total_files = 0;
$total_size = 0;

foreach ($subdirs as $item) {
    if ($item === '.' || $item === '..') continue;

    $full_path = "$upload_root/$item";

    if (is_dir($full_path)) {
        echo "Directory: /$item/\n";

        $files = glob("$full_path/*");
        $file_count = count($files);
        $dir_size = 0;

        echo "  Files: $file_count\n";

        if ($file_count > 0) {
            echo "  Contents:\n";
            foreach ($files as $file) {
                if (is_file($file)) {
                    $size = filesize($file);
                    $dir_size += $size;
                    $name = basename($file);
                    $modified = date('Y-m-d H:i:s', filemtime($file));
                    echo "    - $name (" . round($size / 1024, 2) . " KB, modified: $modified)\n";
                }
            }
        }

        $total_files += $file_count;
        $total_size += $dir_size;

        echo "  Total: " . round($dir_size / 1024 / 1024, 2) . " MB\n\n";
    }
}

echo "=== Summary ===\n";
echo "Total files: $total_files\n";
echo "Total size: " . round($total_size / 1024 / 1024, 2) . " MB\n";
echo "Upload root: $upload_root\n";
