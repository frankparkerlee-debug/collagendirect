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
      require_once __DIR__ . '/file_utils.php';

      // Fetch patient data
      $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
      $stmt->execute([$patientId]);
      $patient = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$patient) {
        error_log("[auto_score] Patient not found: $patientId");
        return false;
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
          error_log("[auto_score] File not found: $fullPath");
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

      // Generate score with order data
      $result = $aiService->generateApprovalScore($patient, $documents, $order ?? null);

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
