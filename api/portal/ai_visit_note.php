<?php
// /api/portal/ai_visit_note.php — AI Visit Note Generator for Physicians
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

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Generate comprehensive visit note for a patient order
 */
if ($action === 'generate_visit_note') {
  $patientId = trim($_POST['patient_id'] ?? '');

  if (empty($patientId)) {
    echo json_encode(['ok' => false, 'error' => 'Patient ID is required']);
    exit;
  }

  try {
    // Get patient data - verify physician owns this patient
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ? AND user_id = ?");
    $stmt->execute([$patientId, $userId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
      echo json_encode(['ok' => false, 'error' => 'Patient not found or unauthorized']);
      exit;
    }

    // Get most recent order for this patient
    $stmt = $pdo->prepare("
      SELECT o.*, p.name as product_name, p.hcpcs_code, p.cpt_code
      FROM orders o
      LEFT JOIN products p ON p.id = o.product_id
      WHERE o.patient_id = ? AND o.user_id = ?
      ORDER BY o.created_at DESC
      LIMIT 1
    ");
    $stmt->execute([$patientId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
      echo json_encode(['ok' => false, 'error' => 'No orders found for this patient. Please create an order first.']);
      exit;
    }

    // Merge product data if available
    if (!empty($order['product_name'])) {
      $order['product'] = $order['product_name'];
    }
    if (empty($order['cpt']) && !empty($order['cpt_code'])) {
      $order['cpt'] = $order['cpt_code'];
    }

    // Initialize AI service
    $aiService = new AIService();

    // Generate visit note
    $result = $aiService->generateVisitNote($order, $patient, [
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
    error_log('[AI Visit Note] Error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
    exit;
  }
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
