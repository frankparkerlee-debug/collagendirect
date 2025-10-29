<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== File Persistence Diagnostic ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
  // Check all patients with file attachments
  $patients = $pdo->query("
    SELECT
      id,
      user_id,
      first_name,
      last_name,
      id_card_path,
      id_card_mime,
      ins_card_path,
      ins_card_mime,
      notes_path,
      notes_mime,
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
  ")->fetchAll(PDO::FETCH_ASSOC);

  echo "Found " . count($patients) . " patients with file attachments\n\n";

  foreach ($patients as $p) {
    echo "========================================\n";
    echo "Patient: {$p['first_name']} {$p['last_name']} (ID: {$p['id']})\n";
    echo "User ID: {$p['user_id']}\n";
    echo "Created: {$p['created_at']}\n";
    echo "Updated: {$p['updated_at']}\n";
    echo "----------------------------------------\n";

    $files = [
      'ID Card' => ['path' => $p['id_card_path'], 'mime' => $p['id_card_mime']],
      'Insurance' => ['path' => $p['ins_card_path'], 'mime' => $p['ins_card_mime']],
      'Clinical Notes' => ['path' => $p['notes_path'], 'mime' => $p['notes_mime']],
      'AOB' => ['path' => $p['aob_path'], 'mime' => null]
    ];

    foreach ($files as $type => $file) {
      if (empty($file['path'])) {
        continue;
      }

      echo "\n$type:\n";
      echo "  DB Path: {$file['path']}\n";

      // Check if path is relative or absolute
      if (substr($file['path'], 0, 1) === '/') {
        echo "  Path Type: Absolute\n";
        $fullPath = __DIR__ . '/../' . ltrim($file['path'], '/');
      } else {
        echo "  Path Type: Relative\n";
        $fullPath = __DIR__ . '/../' . $file['path'];
      }

      echo "  Full Path: $fullPath\n";

      // Check if file exists
      if (file_exists($fullPath)) {
        echo "  Status: ✓ EXISTS\n";
        echo "  Size: " . filesize($fullPath) . " bytes\n";
        echo "  Modified: " . date('Y-m-d H:i:s', filemtime($fullPath)) . "\n";
        echo "  Permissions: " . substr(sprintf('%o', fileperms($fullPath)), -4) . "\n";
        echo "  Readable: " . (is_readable($fullPath) ? 'YES' : 'NO') . "\n";

        // Check age of file vs database update
        $fileAge = time() - filemtime($fullPath);
        $dbUpdateTime = strtotime($p['updated_at']);
        $dbAge = time() - $dbUpdateTime;

        echo "  File Age: " . round($fileAge / 60, 1) . " minutes\n";
        echo "  DB Update Age: " . round($dbAge / 60, 1) . " minutes\n";

        if (abs($fileAge - $dbAge) > 300) { // More than 5 minutes difference
          echo "  ⚠️  WARNING: File timestamp and DB update are more than 5 minutes apart\n";
        }
      } else {
        echo "  Status: ✗ NOT FOUND\n";

        // Check if directory exists
        $dir = dirname($fullPath);
        if (is_dir($dir)) {
          echo "  Directory exists: YES\n";
          echo "  Directory permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "\n";

          // List files in directory
          $dirFiles = scandir($dir);
          echo "  Files in directory (" . count($dirFiles) - 2 . "):\n";
          foreach ($dirFiles as $f) {
            if ($f === '.' || $f === '..') continue;
            echo "    - $f\n";
          }
        } else {
          echo "  Directory exists: NO\n";
        }
      }

      if ($file['mime']) {
        echo "  MIME Type: {$file['mime']}\n";
      }
    }

    echo "\n";
  }

  // Check for any orphaned files (files on disk but not in database)
  echo "\n========================================\n";
  echo "Checking for orphaned files...\n";
  echo "========================================\n\n";

  $directories = [
    'ids' => __DIR__ . '/../uploads/ids/',
    'insurance' => __DIR__ . '/../uploads/insurance/',
    'notes' => __DIR__ . '/../uploads/notes/',
    'aob' => __DIR__ . '/../uploads/aob/'
  ];

  foreach ($directories as $type => $dir) {
    if (!is_dir($dir)) {
      echo "$type directory: NOT FOUND\n\n";
      continue;
    }

    echo "$type directory:\n";
    $files = array_diff(scandir($dir), ['.', '..', '.gitkeep', '.htaccess']);

    if (empty($files)) {
      echo "  (empty)\n\n";
      continue;
    }

    foreach ($files as $file) {
      $fullPath = $dir . $file;
      $relPath = '/uploads/' . $type . '/' . $file;

      // Check if this path exists in database
      $column = match($type) {
        'ids' => 'id_card_path',
        'insurance' => 'ins_card_path',
        'notes' => 'notes_path',
        'aob' => 'aob_path'
      };

      $stmt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE $column = ?");
      $stmt->execute([$relPath]);
      $count = $stmt->fetchColumn();

      $age = time() - filemtime($fullPath);
      $ageMinutes = round($age / 60, 1);

      if ($count == 0) {
        echo "  ⚠️  $file (orphaned, age: $ageMinutes min)\n";
      } else {
        echo "  ✓ $file (linked to $count patient(s), age: $ageMinutes min)\n";
      }
    }

    echo "\n";
  }

  // Check database connection pool settings
  echo "\n========================================\n";
  echo "Database Connection Information\n";
  echo "========================================\n\n";

  $dbInfo = $pdo->query("
    SELECT
      current_database() as database,
      current_user as user,
      inet_server_addr() as server_addr,
      inet_server_port() as server_port,
      version() as version
  ")->fetch(PDO::FETCH_ASSOC);

  foreach ($dbInfo as $key => $value) {
    echo "$key: $value\n";
  }

  echo "\n";

  // Check for any recent disconnections or errors in PostgreSQL logs
  echo "Active connections:\n";
  $connections = $pdo->query("
    SELECT count(*) as count, state, wait_event_type
    FROM pg_stat_activity
    WHERE datname = current_database()
    GROUP BY state, wait_event_type
  ")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($connections as $conn) {
    echo "  State: {$conn['state']}, Wait Event: {$conn['wait_event_type']}, Count: {$conn['count']}\n";
  }

} catch (Throwable $e) {
  echo "\n✗ Error: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== End of Diagnostic ===\n";
