<?php
/**
 * Investigation script for patient John Smith (CD-20251123-6386)
 * To determine why AI approval score is not working properly
 */

// Output as plain text
header('Content-Type: text/plain; charset=utf-8');

echo "=== INVESTIGATING PATIENT CD-20251123-6386 (John Smith) ===\n\n";

try {
  require_once __DIR__ . '/../api/db.php';
  require_once __DIR__ . '/../api/lib/ai_service.php';

  $patientId = 'CD-20251123-6386';

  // 1. Get patient data
  echo "1. PATIENT INFORMATION\n";
  echo str_repeat("-", 80) . "\n";

  $stmt = $pdo->prepare("
    SELECT
      id, first_name, last_name, dob,
      insurance_provider, insurance_member_id,
      approval_score_color, approval_score_at,
      id_card_path, id_card_mime,
      ins_card_path, ins_card_mime,
      created_at, updated_at
    FROM patients
    WHERE id = ?
  ");
  $stmt->execute([$patientId]);
  $patient = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$patient) {
    echo "ERROR: Patient not found!\n";
    exit(1);
  }

  echo "Name: {$patient['first_name']} {$patient['last_name']}\n";
  echo "DOB: {$patient['dob']}\n";
  echo "Insurance: {$patient['insurance_provider']}\n";
  echo "Member ID: {$patient['insurance_member_id']}\n";
  echo "Current Approval Score: {$patient['approval_score_color']}\n";
  echo "Score Last Updated: " . ($patient['approval_score_at'] ?? 'NEVER') . "\n";
  echo "Patient Created: {$patient['created_at']}\n\n";

  // 2. Get most recent order
  echo "2. MOST RECENT ORDER\n";
  echo str_repeat("-", 80) . "\n";

  $stmt = $pdo->prepare("
    SELECT
      o.id,
      o.created_at AS order_date,
      o.rx_note_path,
      o.rx_note_name,
      o.rx_note_mime,
      o.icd10_code,
      o.diagnosis,
      o.frequency_per_week,
      o.duration_days,
      o.qty_per_change,
      o.additional_instructions,
      pr.name AS product_name,
      pr.hcpcs_code
    FROM orders o
    LEFT JOIN products pr ON pr.id = o.product_id
    WHERE o.patient_id = ?
    ORDER BY o.created_at DESC
    LIMIT 1
  ");
  $stmt->execute([$patientId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    echo "No orders found for this patient.\n\n";
  } else {
    echo "Order ID: {$order['id']}\n";
    echo "Order Date: {$order['order_date']}\n";
    echo "Product: {$order['product_name']}\n";
    echo "HCPCS Code: {$order['hcpcs_code']}\n";
    echo "ICD-10: {$order['icd10_code']}\n";
    echo "Diagnosis: {$order['diagnosis']}\n";
    echo "Visit Notes Path: " . ($order['rx_note_path'] ?? 'NOT UPLOADED') . "\n\n";
  }

  // 3. Check file existence
  echo "3. DOCUMENT FILE VERIFICATION\n";
  echo str_repeat("-", 80) . "\n";

  $documents = [
    ['label' => 'Photo ID', 'path' => $patient['id_card_path'] ?? null, 'mime' => $patient['id_card_mime'] ?? null],
    ['label' => 'Insurance Card', 'path' => $patient['ins_card_path'] ?? null, 'mime' => $patient['ins_card_mime'] ?? null],
    ['label' => 'Visit Notes', 'path' => $order['rx_note_path'] ?? null, 'mime' => $order['rx_note_mime'] ?? null],
  ];

  foreach ($documents as $doc) {
    $label = $doc['label'];
    $path = $doc['path'];
    $mime = $doc['mime'];

    if (empty($path)) {
      echo sprintf("%-20s: NOT UPLOADED\n\n", $label);
      continue;
    }

    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $path;
    $exists = file_exists($fullPath);

    echo sprintf("%-20s: %s\n", $label, $exists ? "✓ EXISTS" : "✗ MISSING");
    echo sprintf("  Path: %s\n", $path);
    echo sprintf("  Full Path: %s\n", $fullPath);
    echo sprintf("  MIME Type: %s\n", $mime ?? 'unknown');

    if ($exists) {
      $size = filesize($fullPath);
      echo sprintf("  Size: %s bytes\n", number_format($size));
      echo sprintf("  Readable: %s\n", is_readable($fullPath) ? 'YES' : 'NO');
    }
    echo "\n";
  }

  // 4. Test document text extraction
  echo "4. DOCUMENT TEXT EXTRACTION TEST\n";
  echo str_repeat("-", 80) . "\n";

  $aiService = new AIService();

  $extractDocumentText = function($path, $mime, $label) use ($aiService) {
    echo "\n{$label}:\n";

    if (empty($path)) {
      echo "  Skipped: No path provided\n";
      return null;
    }

    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $path;
    if (!file_exists($fullPath)) {
      echo "  ✗ Error: File not found at {$fullPath}\n";
      return null;
    }

    try {
      $startTime = microtime(true);

      // Extract text based on file type
      if (strpos($mime, 'image/') === 0) {
        echo "  Type: Image - Using AI vision extraction\n";
        $result = $aiService->extractTextFromImage($fullPath, $mime);
        $text = $result['text'] ?? null;
      } elseif ($mime === 'application/pdf') {
        echo "  Type: PDF - Using pdftotext + AI\n";
        $result = $aiService->extractTextFromPDF($fullPath);
        $text = $result['text'] ?? null;
      } elseif (strpos($mime, 'text/') === 0) {
        echo "  Type: Text file - Direct read\n";
        $text = file_get_contents($fullPath);
      } else {
        echo "  ✗ Error: Unsupported MIME type: {$mime}\n";
        return null;
      }

      $duration = round((microtime(true) - $startTime) * 1000);

      if ($text) {
        echo "  ✓ Extraction successful ({$duration}ms)\n";
        echo "  Extracted length: " . number_format(strlen($text)) . " characters\n";
        echo "  Preview (first 300 chars):\n";
        echo "  " . str_repeat("-", 76) . "\n";
        $preview = substr($text, 0, 300);
        $lines = explode("\n", $preview);
        foreach ($lines as $line) {
          echo "  " . $line . "\n";
        }
        if (strlen($text) > 300) {
          echo "  ...\n";
        }
        echo "  " . str_repeat("-", 76) . "\n";
        return $text;
      } else {
        echo "  ✗ Error: No text extracted\n";
        return null;
      }

    } catch (Exception $e) {
      echo "  ✗ Error: " . $e->getMessage() . "\n";
      return null;
    }
  };

  // Extract text from each document
  $extractDocumentText($patient['id_card_path'] ?? null, $patient['id_card_mime'] ?? null, 'Photo ID');
  $extractDocumentText($patient['ins_card_path'] ?? null, $patient['ins_card_mime'] ?? null, 'Insurance Card');
  $extractDocumentText($order['rx_note_path'] ?? null, $order['rx_note_mime'] ?? null, 'Visit Notes');

  // 5. Check recent error logs
  echo "\n\n5. RECENT BACKGROUND SCORING LOGS\n";
  echo str_repeat("-", 80) . "\n";

  $errorLog = '/var/log/apache2/error.log';
  if (file_exists($errorLog) && is_readable($errorLog)) {
    $command = "grep -i 'background_score.*{$patientId}\\|auto_score.*{$patientId}' {$errorLog} 2>/dev/null | tail -n 20";
    $logs = shell_exec($command);

    if (empty($logs)) {
      echo "No scoring logs found for this patient.\n";
    } else {
      echo $logs;
    }
  } else {
    echo "Error log not accessible.\n";
  }

  echo "\n\n6. DIAGNOSIS\n";
  echo str_repeat("=", 80) . "\n";

  if (empty($patient['approval_score_color'])) {
    echo "⚠️  ISSUE: No approval score has been generated yet.\n";
    echo "   This suggests the background scoring never ran or failed silently.\n\n";
  } elseif ($patient['approval_score_color'] === 'red') {
    echo "⚠️  LOW SCORE: Patient has red (25/100) approval score.\n";
    echo "   This could mean:\n";
    echo "   - AI found issues with documentation\n";
    echo "   - Documents weren't extracted properly\n";
    echo "   - Missing critical clinical information\n\n";
  }

  if (!empty($order['rx_note_path'])) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $order['rx_note_path'];
    if (!file_exists($fullPath)) {
      echo "❌ CRITICAL: Visit notes file does not exist at expected path!\n";
      echo "   Expected: {$fullPath}\n\n";
    }
  } else {
    echo "❌ CRITICAL: No visit notes uploaded with the order!\n\n";
  }

  if (empty($order['icd10_code']) || empty($order['diagnosis'])) {
    echo "⚠️  WARNING: Order missing ICD-10 or diagnosis information.\n\n";
  }

  echo "\n=== INVESTIGATION COMPLETE ===\n";

} catch (Exception $e) {
  echo "\n\n✗ FATAL ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
