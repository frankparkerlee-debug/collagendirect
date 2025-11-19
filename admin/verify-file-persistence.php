<?php
/**
 * Verify File Persistence
 * Checks if test files created earlier still exist
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== File Persistence Verification ===\n\n";

$upload_path = '/opt/render/project/src/uploads';
$manifest_path = "$upload_path/test-manifest.json";

// Check if manifest exists
if (!file_exists($manifest_path)) {
    echo "✗ ERROR: Test manifest not found\n";
    echo "Please run test-file-persistence.php first\n";
    exit(1);
}

echo "✓ Test manifest found\n";

// Load manifest
$manifest = json_decode(file_get_contents($manifest_path), true);

if (!$manifest) {
    echo "✗ ERROR: Failed to parse manifest\n";
    exit(1);
}

echo "✓ Manifest loaded\n";
echo "  Created at: {$manifest['created_at']}\n";
echo "  Files in manifest: " . count($manifest['files']) . "\n\n";

// Calculate time elapsed
$created_timestamp = $manifest['timestamp'];
$current_timestamp = time();
$elapsed_seconds = $current_timestamp - $created_timestamp;
$elapsed_minutes = round($elapsed_seconds / 60, 1);

echo "Time elapsed since test creation: $elapsed_minutes minutes\n\n";

if ($elapsed_minutes < 5) {
    echo "⚠️  WARNING: Less than 5 minutes have passed\n";
    echo "Previous issue: files disappeared after 5-10 minutes\n";
    echo "Recommendation: Wait at least 10 minutes before running this verification\n\n";
}

// Verify each file
echo "Verifying test files...\n";
$verified = 0;
$missing = 0;

foreach ($manifest['files'] as $file) {
    $path = $file['path'];
    $relative = $file['relative'];
    $expected_size = $file['size'];

    if (file_exists($path)) {
        $actual_size = filesize($path);
        $age_seconds = $current_timestamp - filemtime($path);
        $age_minutes = round($age_seconds / 60, 1);

        if ($actual_size === $expected_size) {
            echo "  ✓ $relative (age: $age_minutes min, size: $actual_size bytes)\n";
            $verified++;
        } else {
            echo "  ⚠️  $relative (SIZE MISMATCH: expected $expected_size, got $actual_size)\n";
            $verified++;
        }
    } else {
        echo "  ✗ $relative NOT FOUND (file disappeared!)\n";
        $missing++;
    }
}

echo "\n";
echo "=== Verification Summary ===\n";
echo "Time elapsed: $elapsed_minutes minutes\n";
echo "Files expected: " . count($manifest['files']) . "\n";
echo "Files found: $verified\n";
echo "Files missing: $missing\n\n";

if ($missing === 0) {
    echo "✅ SUCCESS: All files persisted!\n";
    if ($elapsed_minutes >= 10) {
        echo "✅ Files survived > 10 minutes - persistence CONFIRMED\n";
    } else {
        echo "ℹ️  Files exist but only $elapsed_minutes min elapsed\n";
        echo "   Run again after 10+ minutes to fully confirm persistence\n";
    }
} else {
    echo "❌ FAILURE: $missing files disappeared\n";
    echo "This indicates files are NOT being saved to persistent disk\n";
    echo "\nPossible causes:\n";
    echo "1. Persistent disk not properly mounted at /opt/render/project/src/uploads\n";
    echo "2. Files being saved to ephemeral container storage\n";
    echo "3. Container restarted and files were lost\n";
    echo "\nCheck: https://collagendirect.health/admin/check-disk-setup.php\n";
}

echo "\n";

// Check if persistent disk is actually mounted
echo "=== Persistent Disk Mount Check ===\n";
$mount_output = shell_exec('mount | grep ' . escapeshellarg($upload_path));
if ($mount_output) {
    echo "✓ /opt/render/project/src/uploads is a mounted volume\n";
    echo "  " . trim($mount_output) . "\n";
} else {
    echo "✗ /opt/render/project/src/uploads is NOT mounted\n";
    echo "  Files are in ephemeral container storage\n";
    echo "  THIS IS THE PROBLEM - Configure persistent disk on Render\n";
}

echo "\nVerification complete! Timestamp: " . date('Y-m-d H:i:s') . "\n";
