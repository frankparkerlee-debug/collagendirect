<?php
/**
 * Debug script for AI approval score issues
 * Access via: https://collagendirect.health/admin/debug-ai-approval.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== AI Approval Score Debug ===\n\n";

// Check environment
echo "Step 1: Environment Check\n";
echo "  PHP Version: " . phpversion() . "\n";
echo "  Anthropic API Key: " . (getenv('ANTHROPIC_API_KEY') ? 'SET (length: ' . strlen(getenv('ANTHROPIC_API_KEY')) . ')' : 'NOT SET') . "\n";
echo "  pdftotext available: ";
exec('which pdftotext', $output, $returnCode);
echo ($returnCode === 0 ? 'YES (' . $output[0] . ')' : 'NO') . "\n\n";

// Check database
try {
  require_once __DIR__ . '/../api/db.php';
  echo "Step 2: Database Connection\n";
  echo "  ✓ Database connected\n\n";

  // Check for test patient
  echo "Step 3: Loading Test Patient (CD-20251104-DE4D)\n";
  $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
  $stmt->execute(['b1acaaa5b4925b6a7f87a5aeb7c30637']);
  $patient = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$patient) {
    echo "  ✗ Test patient not found\n";
    exit(1);
  }

  echo "  ✓ Patient found: {$patient['first_name']} {$patient['last_name']}\n";
  echo "  ID Card Path: " . ($patient['id_card_path'] ?? 'NULL') . "\n";
  echo "  Insurance Card Path: " . ($patient['ins_card_path'] ?? 'NULL') . "\n";
  echo "  Notes Path: " . ($patient['notes_path'] ?? 'NULL') . "\n\n";

  // Check file paths
  echo "Step 4: Checking File Existence\n";

  $checkFile = function($path, $label) {
    if (empty($path)) {
      echo "  $label: NOT SET\n";
      return null;
    }

    // Try different path resolutions
    $attempts = [
      $path,
      '/var/data' . $path,
      '/var/www/html' . $path,
      __DIR__ . '/..' . $path
    ];

    foreach ($attempts as $attempt) {
      if (file_exists($attempt)) {
        $size = filesize($attempt);
        $readable = is_readable($attempt);
        echo "  $label: FOUND at $attempt\n";
        echo "    Size: " . number_format($size) . " bytes\n";
        echo "    Readable: " . ($readable ? 'YES' : 'NO') . "\n";
        return $attempt;
      }
    }

    echo "  $label: NOT FOUND (tried " . count($attempts) . " paths)\n";
    echo "    Original path: $path\n";
    return null;
  };

  $idPath = $checkFile($patient['id_card_path'] ?? '', 'ID Card');
  $insPath = $checkFile($patient['ins_card_path'] ?? '', 'Insurance Card');
  $notesPath = $checkFile($patient['notes_path'] ?? '', 'Clinical Notes');

  echo "\n";

  // Try to extract PDF text if notes exist
  if ($notesPath && pathinfo($notesPath, PATHINFO_EXTENSION) === 'pdf') {
    echo "Step 5: Testing PDF Text Extraction\n";
    $textFile = tempnam(sys_get_temp_dir(), 'pdf_test_');
    exec("pdftotext " . escapeshellarg($notesPath) . " " . escapeshellarg($textFile) . " 2>&1", $output, $returnCode);

    if ($returnCode === 0 && file_exists($textFile)) {
      $extractedText = file_get_contents($textFile);
      echo "  ✓ PDF extraction successful\n";
      echo "  Extracted text length: " . strlen($extractedText) . " characters\n";
      echo "  First 200 chars: " . substr($extractedText, 0, 200) . "...\n";
      unlink($textFile);
    } else {
      echo "  ✗ PDF extraction failed\n";
      echo "  Return code: $returnCode\n";
      echo "  Output: " . implode("\n", $output) . "\n";
    }
    echo "\n";
  }

  // Check approval scores table
  echo "Step 6: Checking Approval Scores Table\n";
  $tableCheck = $pdo->query("
    SELECT COUNT(*) as cnt
    FROM information_schema.tables
    WHERE table_name = 'patient_approval_scores'
  ");
  $tableExists = (int)$tableCheck->fetchColumn() > 0;

  if ($tableExists) {
    echo "  ✓ patient_approval_scores table exists\n";

    $scoreStmt = $pdo->prepare("
      SELECT score, score_numeric, created_at
      FROM patient_approval_scores
      WHERE patient_id = ?
      ORDER BY created_at DESC
      LIMIT 1
    ");
    $scoreStmt->execute(['b1acaaa5b4925b6a7f87a5aeb7c30637']);
    $score = $scoreStmt->fetch(PDO::FETCH_ASSOC);

    if ($score) {
      echo "  ✓ Stored score found\n";
      echo "    Score: {$score['score']} ({$score['score_numeric']}/100)\n";
      echo "    Created: {$score['created_at']}\n";
    } else {
      echo "  No stored score for this patient yet\n";
    }
  } else {
    echo "  ✗ patient_approval_scores table does NOT exist\n";
    echo "  Run migrations to create it\n";
  }

  echo "\n=== Debug Complete ===\n";

} catch (Exception $e) {
  echo "\n✗ ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
