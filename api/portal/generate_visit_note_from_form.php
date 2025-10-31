<?php
// /api/portal/generate_visit_note_from_form.php — Generate visit note from order form data
session_start();
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../lib/ai_service.php';

header('Content-Type: application/json');

// Authentication check
if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = (string)$_SESSION['user_id'];

// Get user data for physician info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$physician = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$physician) {
  echo json_encode(['ok' => false, 'error' => 'User not found']);
  exit;
}

/**
 * Generate visit note from form data (before order is saved)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // Get patient ID
    $patientId = trim($_POST['patient_id'] ?? '');
    if (empty($patientId)) {
      echo json_encode(['ok' => false, 'error' => 'Patient ID is required']);
      exit;
    }

    // Get patient data
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ? AND user_id = ?");
    $stmt->execute([$patientId, $userId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
      echo json_encode(['ok' => false, 'error' => 'Patient not found or unauthorized']);
      exit;
    }

    // Build order data from form fields
    $orderData = [
      'product' => trim($_POST['product'] ?? ''),
      'icd10_primary' => trim($_POST['icd10_primary'] ?? ''),
      'icd10_secondary' => trim($_POST['icd10_secondary'] ?? ''),
      'wound_type' => trim($_POST['wound_type'] ?? ''),
      'wound_stage' => trim($_POST['wound_stage'] ?? ''),
      'wound_location' => trim($_POST['wound_location'] ?? ''),
      'wound_laterality' => trim($_POST['wound_laterality'] ?? ''),
      'wound_length_cm' => trim($_POST['wound_length_cm'] ?? ''),
      'wound_width_cm' => trim($_POST['wound_width_cm'] ?? ''),
      'wound_depth_cm' => trim($_POST['wound_depth_cm'] ?? ''),
      'wound_notes' => trim($_POST['wound_notes'] ?? ''),
      'frequency_per_week' => trim($_POST['frequency_per_week'] ?? ''),
      'qty_per_change' => trim($_POST['qty_per_change'] ?? ''),
      'duration_days' => trim($_POST['duration_days'] ?? ''),
      'refills_allowed' => trim($_POST['refills_allowed'] ?? ''),
      'additional_instructions' => trim($_POST['additional_instructions'] ?? ''),
      'cpt' => trim($_POST['cpt'] ?? ''),
      'last_eval_date' => trim($_POST['last_eval_date'] ?? date('Y-m-d')),
      'start_date' => trim($_POST['start_date'] ?? date('Y-m-d'))
    ];

    // Validate minimum required fields
    if (empty($orderData['icd10_primary'])) {
      echo json_encode(['ok' => false, 'error' => 'Primary diagnosis (ICD-10) is required to generate visit note']);
      exit;
    }

    if (empty($orderData['wound_location'])) {
      echo json_encode(['ok' => false, 'error' => 'Wound location is required to generate visit note']);
      exit;
    }

    // Initialize AI service
    $aiService = new AIService();

    // Generate visit note
    $result = $aiService->generateVisitNote($orderData, $patient, [
      'name' => $physician['first_name'] . ' ' . $physician['last_name'],
      'npi' => $physician['npi'],
      'practice' => $physician['practice_name']
    ]);

    if (isset($result['error'])) {
      echo json_encode(['ok' => false, 'error' => $result['error']]);
      exit;
    }

    echo json_encode([
      'ok' => true,
      'note' => $result['note'],
      'patient_name' => $patient['first_name'] . ' ' . $patient['last_name'],
      'generated_at' => date('Y-m-d H:i:s')
    ]);
    exit;

  } catch (Exception $e) {
    error_log('[AI Visit Note Form] Error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
    exit;
  }
}

echo json_encode(['ok' => false, 'error' => 'Invalid request method']);
