<?php
// Debug file uploads functionality
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/db.php';

echo "=== File Uploads Debug ===\n\n";

// Check uploads directory structure
function uploads_root_abs() {
  $cands = [
    realpath(__DIR__ . '/../uploads'),
    isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT'] . '/public/uploads') : false,
    isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT'] . '/uploads') : false,
  ];
  foreach ($cands as $p) { if ($p && is_dir($p)) return rtrim($p, '/'); }
  return rtrim(__DIR__ . '/../uploads', '/');
}

$uploadsRoot = uploads_root_abs();
echo "1. Uploads root directory:\n";
echo "   Path: $uploadsRoot\n";
echo "   Exists: " . (is_dir($uploadsRoot) ? 'YES' : 'NO') . "\n";
echo "   Writable: " . (is_writable($uploadsRoot) ? 'YES' : 'NO') . "\n\n";

// Check bucket directories
$buckets = ['notes', 'ids', 'insurance'];
echo "2. Bucket directories:\n";
foreach ($buckets as $bucket) {
  $bucketPath = $uploadsRoot . '/' . $bucket;
  echo "   $bucket:\n";
  echo "     Path: $bucketPath\n";
  echo "     Exists: " . (is_dir($bucketPath) ? 'YES' : 'NO') . "\n";

  if (is_dir($bucketPath)) {
    $files = glob($bucketPath . '/*');
    echo "     Files: " . count($files) . "\n";
    if ($files && count($files) > 0) {
      echo "     Sample files:\n";
      foreach (array_slice($files, 0, 5) as $file) {
        echo "       - " . basename($file) . " (size: " . filesize($file) . " bytes)\n";
      }
    }
  }
  echo "\n";
}

// Get sample patient data
echo "3. Sample patient data:\n";
$patients = $pdo->query("
  SELECT id, first_name, last_name, email
  FROM patients
  ORDER BY created_at DESC
  LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($patients as $p) {
  $pid = $p['id'];
  $fullname = trim($p['first_name'] . ' ' . $p['last_name']);
  $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($fullname));
  $tokens = array_filter([$pid, $slug]);

  echo "   Patient: $fullname (ID: $pid)\n";
  echo "     Email: " . $p['email'] . "\n";
  echo "     Tokens: " . implode(', ', $tokens) . "\n";
  echo "     Slug: $slug\n\n";
}

echo "4. Check what files would match:\n";
// Check what's in each bucket and if it matches any patient tokens
foreach ($buckets as $bucket) {
  $bucketPath = $uploadsRoot . '/' . $bucket;
  if (!is_dir($bucketPath)) continue;

  $files = glob($bucketPath . '/*');
  if (!$files || count($files) === 0) continue;

  echo "   $bucket bucket:\n";
  foreach (array_slice($files, 0, 10) as $file) {
    $basename = basename($file);
    echo "     - $basename\n";

    // Check if it matches any patient
    $matched = false;
    foreach ($patients as $p) {
      $pid = $p['id'];
      $fullname = trim($p['first_name'] . ' ' . $p['last_name']);
      $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($fullname));
      $tokens = array_filter([$pid, $slug]);

      $name_lower = strtolower($basename);
      foreach ($tokens as $token) {
        if (strpos($name_lower, strtolower($token)) !== false) {
          echo "       ✓ Matches patient: $fullname (token: $token)\n";
          $matched = true;
          break 2;
        }
      }
    }
    if (!$matched) {
      echo "       ✗ No patient match found\n";
    }
  }
  echo "\n";
}

echo "=== Debug Complete ===\n";
