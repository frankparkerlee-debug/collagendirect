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

  // Fetch patient data
  $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
  $stmt->execute([$patientId]);
  $patient = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$patient) {
    error_log("[background_score] Patient not found: $patientId");
    exit(1);
  }

  // Prepare documents array
  $documents = [];

  if (!empty($patient['id_card_path'])) {
    $documents[] = [
      'type' => 'Photo ID',
      'filename' => basename($patient['id_card_path']),
      'path' => $patient['id_card_path'],
      'mime' => $patient['id_card_mime'] ?? 'unknown'
    ];
  }

  if (!empty($patient['ins_card_path'])) {
    $documents[] = [
      'type' => 'Insurance Card',
      'filename' => basename($patient['ins_card_path']),
      'path' => $patient['ins_card_path'],
      'mime' => $patient['ins_card_mime'] ?? 'unknown'
    ];
  }

  if (!empty($patient['notes_path'])) {
    $documents[] = [
      'type' => 'Clinical Notes',
      'filename' => basename($patient['notes_path']),
      'path' => $patient['notes_path'],
      'mime' => $patient['notes_mime'] ?? 'unknown'
    ];
  }

  // Generate score
  $aiService = new AIService();
  $result = $aiService->generateApprovalScore($patient, $documents);

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
