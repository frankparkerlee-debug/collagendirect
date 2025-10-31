<?php
// API endpoint to generate AI approval score for patient profile
// Called automatically when physician completes patient documentation

// Prevent any HTML/PHP errors from breaking JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

try {
  require_once __DIR__ . '/../db.php';
  require_once __DIR__ . '/../lib/ai_service.php';
} catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => 'Failed to load required files: ' . $e->getMessage()]);
  exit;
}

// Check authentication
if (empty($_SESSION['portal_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = $_SESSION['portal_user_id'];
$userRole = isset($_SESSION['portal_user_role']) ? $_SESSION['portal_user_role'] : 'physician';

// Get patient ID
$patientId = '';
if (isset($_POST['patient_id'])) {
  $patientId = trim($_POST['patient_id']);
} elseif (isset($_GET['patient_id'])) {
  $patientId = trim($_GET['patient_id']);
}

if (empty($patientId)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Patient ID required']);
  exit;
}

try {
  // Fetch complete patient data (without trying to read file content via SQL)
  if ($userRole === 'superadmin') {
    $stmt = $pdo->prepare("SELECT p.* FROM patients p WHERE p.id = ?");
    $stmt->execute([$patientId]);
  } else {
    $stmt = $pdo->prepare("SELECT p.* FROM patients p WHERE p.id = ? AND p.user_id = ?");
    $stmt->execute([$patientId, $userId]);
  }

  $patient = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$patient) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found or access denied']);
    exit;
  }

  // If notes are stored in a file path, try to read them (optional - may fail if file doesn't exist)
  $patient['notes_text'] = '';
  if (!empty($patient['notes_path']) && file_exists($patient['notes_path'])) {
    $patient['notes_text'] = @file_get_contents($patient['notes_path']) ?: '';
  }

  // Prepare documents array for AI analysis
  $documents = [];

  // Add ID card document
  if (!empty($patient['id_card_path'])) {
    $documents[] = [
      'type' => 'Photo ID',
      'filename' => basename($patient['id_card_path']),
      'path' => $patient['id_card_path'],
      'mime' => isset($patient['id_card_mime']) ? $patient['id_card_mime'] : 'unknown',
      'extracted_text' => '' // TODO: Add OCR extraction if needed
    ];
  }

  // Add insurance card document
  if (!empty($patient['ins_card_path'])) {
    $documents[] = [
      'type' => 'Insurance Card',
      'filename' => basename($patient['ins_card_path']),
      'path' => $patient['ins_card_path'],
      'mime' => isset($patient['ins_card_mime']) ? $patient['ins_card_mime'] : 'unknown',
      'extracted_text' => '' // TODO: Add OCR extraction if needed
    ];
  }

  // Add clinical notes document
  if (!empty($patient['notes_path'])) {
    $documents[] = [
      'type' => 'Clinical Notes',
      'filename' => basename($patient['notes_path']),
      'path' => $patient['notes_path'],
      'mime' => isset($patient['notes_mime']) ? $patient['notes_mime'] : 'unknown',
      'extracted_text' => isset($patient['notes_text']) ? $patient['notes_text'] : ''
    ];
  }

  // Initialize AI service
  $aiService = new AIService();

  // Generate approval score
  $result = $aiService->generateApprovalScore($patient, $documents);

  if (isset($result['error'])) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
  }

  // Note: We're skipping database storage for now to avoid JSONB issues
  // The score will be generated fresh each time (which is fine for now)
  // TODO: Add proper score caching after migration is run

  // Return the score
  echo json_encode([
    'ok' => true,
    'score' => $result['score'],
    'score_numeric' => $result['score_numeric'],
    'summary' => $result['summary'],
    'missing_items' => $result['missing_items'],
    'complete_items' => $result['complete_items'],
    'recommendations' => $result['recommendations'],
    'concerns' => $result['concerns'],
    'document_analysis' => $result['document_analysis']
  ]);

} catch (Exception $e) {
  error_log("Approval score generation error: " . $e->getMessage());
  error_log("Stack trace: " . $e->getTraceAsString());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Failed to generate approval score', 'details' => $e->getMessage()]);
} catch (Throwable $e) {
  error_log("Fatal error in approval score: " . $e->getMessage());
  error_log("Stack trace: " . $e->getTraceAsString());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}
