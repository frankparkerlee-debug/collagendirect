<?php
// Helper function to automatically trigger AI approval score generation
// This can be called after patient save or document upload

/**
 * Queue automatic AI approval score generation for a patient
 *
 * @param string $patientId The patient UUID
 * @param PDO $pdo Database connection
 * @param bool $async Whether to run asynchronously (default: true)
 * @return bool Success status
 */
function queueApprovalScore($patientId, $pdo, $async = true) {
  try {
    if ($async) {
      // Trigger async generation using background PHP process
      // This allows the user's save operation to complete immediately
      $scriptPath = __DIR__ . '/../portal/background_score.php';

      // Use exec() to run in background without blocking
      // The > /dev/null 2>&1 & ensures it runs in background and doesn't wait
      $command = sprintf(
        'php %s %s > /dev/null 2>&1 &',
        escapeshellarg($scriptPath),
        escapeshellarg($patientId)
      );

      exec($command);

      return true;
    } else {
      // Synchronous generation - wait for completion
      require_once __DIR__ . '/ai_service.php';

      // Fetch patient data
      $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
      $stmt->execute([$patientId]);
      $patient = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$patient) {
        error_log("[auto_score] Patient not found: $patientId");
        return false;
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
        error_log("[auto_score] AI service error: " . $result['error']);
        return false;
      }

      // Save score to database
      $updateStmt = $pdo->prepare("
        UPDATE patients
        SET approval_score_color = ?,
            approval_score_at = NOW()
        WHERE id = ?
      ");
      $updateStmt->execute([$result['score'], $patientId]);

      return true;
    }
  } catch (Exception $e) {
    error_log("[auto_score] Failed to queue approval score: " . $e->getMessage());
    return false;
  }
}

/**
 * Check if a patient should be auto-scored based on data completeness
 *
 * @param array $patient Patient data array
 * @return bool Whether to auto-score
 */
function shouldAutoScore($patient) {
  // Only auto-score if patient has minimum required data
  $hasBasicInfo = !empty($patient['first_name']) &&
                  !empty($patient['last_name']) &&
                  !empty($patient['dob']);

  $hasInsurance = !empty($patient['insurance_provider']) ||
                  !empty($patient['ins_card_path']);

  // Auto-score if we have basic info and at least some insurance data
  return $hasBasicInfo && $hasInsurance;
}
