<?php
/**
 * Background AI approval score generator
 *
 * This script is called asynchronously from auto_score.php to generate
 * approval scores without blocking the user's save operation.
 *
 * Usage: php background_score.php <patient_id>
 */

// Prevent timeout
set_time_limit(60);

// Get patient ID from command line argument
$patientId = $argv[1] ?? '';

if (empty($patientId)) {
  error_log('[background_score] No patient ID provided');
  exit(1);
}

try {
  // Load dependencies
  require_once __DIR__ . '/../db.php';
  require_once __DIR__ . '/../lib/ai_service.php';
  require_once __DIR__ . '/../lib/file_utils.php';

  // Fetch patient data
  $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
  $stmt->execute([$patientId]);
  $patient = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$patient) {
    error_log("[background_score] Patient not found: $patientId");
    exit(1);
  }

  // Create AI service for document extraction
  $aiService = new AIService();

  // Prepare documents array and extract text from each
  $documents = [];

  // Helper function to extract text from document
  $extractDocumentText = function($path, $mime) use ($aiService) {
    if (empty($path)) return null;

    $fullPath = getUploadAbsolutePath($path);
    if (!file_exists($fullPath)) {
      error_log("[background_score] File not found: $fullPath");
      return null;
    }

    // Extract text based on file type
    if (strpos($mime, 'image/') === 0) {
      $result = $aiService->extractTextFromImage($fullPath, $mime);
      return $result['text'] ?? null;
    } elseif ($mime === 'application/pdf') {
      $result = $aiService->extractTextFromPDF($fullPath);
      return $result['text'] ?? null;
    } elseif (strpos($mime, 'text/') === 0) {
      return file_get_contents($fullPath);
    }

    return null;
  };

  if (!empty($patient['id_card_path'])) {
    $extractedText = $extractDocumentText($patient['id_card_path'], $patient['id_card_mime'] ?? 'unknown');
    $documents[] = [
      'type' => 'Photo ID',
      'filename' => basename($patient['id_card_path']),
      'path' => $patient['id_card_path'],
      'mime' => $patient['id_card_mime'] ?? 'unknown',
      'extracted_text' => $extractedText
    ];
  }

  if (!empty($patient['ins_card_path'])) {
    $extractedText = $extractDocumentText($patient['ins_card_path'], $patient['ins_card_mime'] ?? 'unknown');
    $documents[] = [
      'type' => 'Insurance Card',
      'filename' => basename($patient['ins_card_path']),
      'path' => $patient['ins_card_path'],
      'mime' => $patient['ins_card_mime'] ?? 'unknown',
      'extracted_text' => $extractedText
    ];
  }

  // Get the most recent order with visit notes for this patient
  $orderStmt = $pdo->prepare("
    SELECT
      o.id,
      o.rx_note_path,
      o.rx_note_name,
      o.rx_note_mime,
      o.frequency_per_week,
      o.duration_days,
      o.qty_per_change,
      pr.name AS product_name,
      pr.hcpcs_code
    FROM orders o
    LEFT JOIN products pr ON pr.id = o.product_id
    WHERE o.patient_id = ?
    ORDER BY o.created_at DESC
    LIMIT 1
  ");
  $orderStmt->execute([$patientId]);
  $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

  // Include visit notes if available
  if ($order && !empty($order['rx_note_path'])) {
    $extractedText = $extractDocumentText($order['rx_note_path'], $order['rx_note_mime'] ?? 'unknown');
    $documents[] = [
      'type' => 'Clinical Notes',
      'filename' => $order['rx_note_name'] ?? basename($order['rx_note_path']),
      'path' => $order['rx_note_path'],
      'mime' => $order['rx_note_mime'] ?? 'unknown',
      'extracted_text' => $extractedText
    ];
  }

  // Generate score (AI service already created above)
  $result = $aiService->generateApprovalScore($patient, $documents, $order ?? null);

  if (isset($result['error'])) {
    error_log("[background_score] AI service error for patient $patientId: " . $result['error']);
    exit(1);
  }

  // Save score to database
  $updateStmt = $pdo->prepare("
    UPDATE patients
    SET approval_score_color = ?,
        approval_score_at = NOW()
    WHERE id = ?
  ");
  $updateStmt->execute([$result['score'], $patientId]);

  error_log("[background_score] Successfully generated score for patient $patientId: " . $result['score']);
  exit(0);

} catch (Exception $e) {
  error_log("[background_score] Failed to generate score for patient $patientId: " . $e->getMessage());
  error_log("[background_score] Stack trace: " . $e->getTraceAsString());
  exit(1);
}
