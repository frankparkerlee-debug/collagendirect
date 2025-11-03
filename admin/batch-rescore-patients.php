<?php
/**
 * Batch Re-score Patients
 *
 * This script re-generates AI approval scores for all patients (or specific subset).
 * Can be run manually via browser or scheduled via cron.
 *
 * Usage:
 * - Browser: https://collagendirect.health/admin/batch-rescore-patients.php
 * - CLI: php admin/batch-rescore-patients.php
 * - Cron: 0 2 * * * cd /path/to/app && php admin/batch-rescore-patients.php
 *
 * Query parameters:
 * - limit: Max number of patients to process (default: 100)
 * - offset: Start position (default: 0)
 * - force: Re-score even if already scored recently (default: false)
 */

set_time_limit(300); // 5 minutes max
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Detect if running from CLI
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
  echo "<pre>\n";
}

echo "=== Batch AI Approval Score Re-generator ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
  require_once __DIR__ . '/../api/db.php';
  require_once __DIR__ . '/../api/lib/ai_service.php';

  // Parse parameters
  $limit = isset($_GET['limit']) ? min(500, max(1, (int)$_GET['limit'])) : 100;
  $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
  $force = isset($_GET['force']) && $_GET['force'] === 'true';

  echo "Configuration:\n";
  echo "  Limit: $limit patients\n";
  echo "  Offset: $offset\n";
  echo "  Force re-score: " . ($force ? 'Yes' : 'No (only stale scores)') . "\n\n";

  // Build query
  $sql = "SELECT id, first_name, last_name, approval_score_color, approval_score_at
          FROM patients
          WHERE 1=1";

  // Only re-score stale scores (older than 7 days) unless force=true
  if (!$force) {
    $sql .= " AND (approval_score_at IS NULL OR approval_score_at < NOW() - INTERVAL '7 days')";
  }

  $sql .= " ORDER BY updated_at DESC
            LIMIT $limit OFFSET $offset";

  $stmt = $pdo->query($sql);
  $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $total = count($patients);
  echo "Found $total patients to process\n\n";

  if ($total === 0) {
    echo "No patients need re-scoring. All done!\n";
    exit(0);
  }

  // Initialize AI service
  $aiService = new AIService();

  $successCount = 0;
  $errorCount = 0;
  $skippedCount = 0;

  foreach ($patients as $index => $patient) {
    $patientId = $patient['id'];
    $name = $patient['first_name'] . ' ' . $patient['last_name'];
    $num = $index + 1;

    echo "[$num/$total] Processing $name (ID: " . substr($patientId, 0, 8) . "...)";

    try {
      // Fetch full patient data
      $fullStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
      $fullStmt->execute([$patientId]);
      $fullPatient = $fullStmt->fetch(PDO::FETCH_ASSOC);

      if (!$fullPatient) {
        echo " [SKIP: Not found]\n";
        $skippedCount++;
        continue;
      }

      // Check if patient has minimum required data
      if (empty($fullPatient['first_name']) || empty($fullPatient['last_name']) || empty($fullPatient['dob'])) {
        echo " [SKIP: Missing required data]\n";
        $skippedCount++;
        continue;
      }

      // Prepare documents
      $documents = [];

      if (!empty($fullPatient['id_card_path'])) {
        $documents[] = [
          'type' => 'Photo ID',
          'filename' => basename($fullPatient['id_card_path']),
          'path' => $fullPatient['id_card_path'],
          'mime' => $fullPatient['id_card_mime'] ?? 'unknown'
        ];
      }

      if (!empty($fullPatient['ins_card_path'])) {
        $documents[] = [
          'type' => 'Insurance Card',
          'filename' => basename($fullPatient['ins_card_path']),
          'path' => $fullPatient['ins_card_path'],
          'mime' => $fullPatient['ins_card_mime'] ?? 'unknown'
        ];
      }

      if (!empty($fullPatient['notes_path'])) {
        $documents[] = [
          'type' => 'Clinical Notes',
          'filename' => basename($fullPatient['notes_path']),
          'path' => $fullPatient['notes_path'],
          'mime' => $fullPatient['notes_mime'] ?? 'unknown'
        ];
      }

      // Generate score
      $result = $aiService->generateApprovalScore($fullPatient, $documents);

      if (isset($result['error'])) {
        echo " [ERROR: " . $result['error'] . "]\n";
        $errorCount++;
        continue;
      }

      // Save score
      $updateStmt = $pdo->prepare("
        UPDATE patients
        SET approval_score_color = ?,
            approval_score_at = NOW()
        WHERE id = ?
      ");
      $updateStmt->execute([$result['score'], $patientId]);

      echo " [SUCCESS: " . $result['score'] . "]\n";
      $successCount++;

      // Small delay to avoid overwhelming the AI API
      usleep(500000); // 0.5 seconds

    } catch (Exception $e) {
      echo " [ERROR: " . $e->getMessage() . "]\n";
      error_log("[batch-rescore] Error processing patient $patientId: " . $e->getMessage());
      $errorCount++;
    }
  }

  echo "\n=== Summary ===\n";
  echo "Total processed: $total\n";
  echo "Successful: $successCount\n";
  echo "Errors: $errorCount\n";
  echo "Skipped: $skippedCount\n";
  echo "Completed at: " . date('Y-m-d H:i:s') . "\n";

  if ($total === $limit) {
    echo "\nNOTE: There may be more patients to process. Run again with offset=$limit to continue.\n";
  }

} catch (Exception $e) {
  echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
  error_log("[batch-rescore] Fatal error: " . $e->getMessage());
  exit(1);
}

if (!$isCLI) {
  echo "</pre>\n";
}
