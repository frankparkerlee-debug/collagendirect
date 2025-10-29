<?php
/**
 * Diagnostic Script: Check Patient File Attachment Status
 *
 * Run via: https://collagendirect.health/admin/debug-file-attachments.php
 *
 * This script checks:
 * 1. Database paths for patient files
 * 2. Whether files actually exist on disk
 * 3. File permissions
 * 4. Potential issues causing "not found" errors
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

echo "<pre>";
echo "=== Patient File Attachment Diagnostics ===\n\n";

try {
  // Get all patients with file attachments
  $stmt = $pdo->query("
    SELECT
      id,
      first_name,
      last_name,
      id_card_path,
      ins_card_path,
      notes_path,
      aob_path,
      created_at,
      updated_at
    FROM patients
    WHERE id_card_path IS NOT NULL
       OR ins_card_path IS NOT NULL
       OR notes_path IS NOT NULL
       OR aob_path IS NOT NULL
    ORDER BY updated_at DESC
    LIMIT 20
  ");

  $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Found " . count($patients) . " patients with attachments\n";
  echo str_repeat("=", 80) . "\n\n";

  $issues = 0;
  $totalFiles = 0;

  foreach ($patients as $patient) {
    echo "Patient: {$patient['first_name']} {$patient['last_name']} (ID: {$patient['id']})\n";
    echo "Last Updated: {$patient['updated_at']}\n";

    // Check each file type
    $files = [
      'ID Card' => $patient['id_card_path'],
      'Insurance Card' => $patient['ins_card_path'],
      'Clinical Notes' => $patient['notes_path'],
      'AOB' => $patient['aob_path']
    ];

    foreach ($files as $type => $path) {
      if (!$path) continue;

      $totalFiles++;
      echo "\n  [$type]\n";
      echo "  DB Path: $path\n";

      // Build full filesystem path
      $fullPath = __DIR__ . '/../' . ltrim($path, '/');
      echo "  Full Path: $fullPath\n";

      // Check if file exists
      if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        $readable = is_readable($fullPath) ? 'YES' : 'NO';

        echo "  Status: ✓ EXISTS\n";
        echo "  Size: " . number_format($size) . " bytes\n";
        echo "  Permissions: $perms\n";
        echo "  Readable: $readable\n";

        if (!is_readable($fullPath)) {
          echo "  ⚠️  WARNING: File exists but is not readable!\n";
          $issues++;
        }
      } else {
        echo "  Status: ✗ NOT FOUND\n";
        echo "  ⚠️  ERROR: Database has path but file doesn't exist on disk!\n";

        // Check if directory exists
        $dir = dirname($fullPath);
        if (is_dir($dir)) {
          echo "  Directory exists: YES\n";
          echo "  Directory writable: " . (is_writable($dir) ? 'YES' : 'NO') . "\n";
        } else {
          echo "  Directory exists: NO\n";
        }

        $issues++;
      }
    }

    echo "\n" . str_repeat("-", 80) . "\n\n";
  }

  // Summary
  echo "\n" . str_repeat("=", 80) . "\n";
  echo "SUMMARY\n";
  echo str_repeat("=", 80) . "\n";
  echo "Total Patients Checked: " . count($patients) . "\n";
  echo "Total Files in Database: $totalFiles\n";
  echo "Issues Found: $issues\n";

  if ($issues > 0) {
    echo "\n⚠️  ISSUES DETECTED:\n";
    echo "- Files in database but missing from filesystem\n";
    echo "- OR files exist but have wrong permissions\n";
    echo "\nPossible Causes:\n";
    echo "1. Files were deleted from disk but database not updated\n";
    echo "2. Database UPDATE is failing during upload\n";
    echo "3. File permissions changed after upload\n";
    echo "4. Files uploaded to wrong directory\n";
  } else {
    echo "\n✓ No issues detected - all files accessible\n";
  }

  // Check upload directories
  echo "\n" . str_repeat("=", 80) . "\n";
  echo "UPLOAD DIRECTORIES CHECK\n";
  echo str_repeat("=", 80) . "\n";

  $uploadDirs = [
    '/uploads/ids/',
    '/uploads/insurance/',
    '/uploads/notes/',
    '/uploads/aob/'
  ];

  foreach ($uploadDirs as $dir) {
    $fullDir = __DIR__ . '/../' . ltrim($dir, '/');
    echo "\n$dir\n";
    echo "  Full Path: $fullDir\n";
    echo "  Exists: " . (is_dir($fullDir) ? 'YES' : 'NO') . "\n";

    if (is_dir($fullDir)) {
      echo "  Writable: " . (is_writable($fullDir) ? 'YES' : 'NO') . "\n";
      echo "  Permissions: " . substr(sprintf('%o', fileperms($fullDir)), -4) . "\n";

      // Count files
      $files = glob($fullDir . '*');
      echo "  Files: " . count($files) . "\n";
    }
  }

} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n</pre>";
