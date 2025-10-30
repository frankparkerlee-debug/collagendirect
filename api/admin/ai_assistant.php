<?php
// /api/admin/ai_assistant.php — AI Assistant API for Admin Panel
session_start();
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../lib/ai_service.php';

header('Content-Type: application/json');

// Authentication check
if (isset($_SESSION['admin'])) {
  $adminId = (int)$_SESSION['admin']['id'];
  $adminRole = $_SESSION['admin']['role'];
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
  $adminId = (int)$_SESSION['user_id'];
  $adminRole = $_SESSION['role'];
} else {
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

// Only superadmin and manufacturer can use AI assistant
if ($adminRole !== 'superadmin' && $adminRole !== 'manufacturer') {
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Initialize AI service
$aiService = new AIService();

/**
 * Generate AI response suggestion for manufacturer to send to physician
 */
if ($action === 'generate_response') {
  $patientId = trim($_POST['patient_id'] ?? '');

  if (empty($patientId)) {
    echo json_encode(['ok' => false, 'error' => 'Patient ID is required']);
    exit;
  }

  try {
    // Get patient data
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
      echo json_encode(['ok' => false, 'error' => 'Patient not found']);
      exit;
    }

    // Get most recent order data for this patient
    $stmt = $pdo->prepare("
      SELECT o.*, p.name as product_name
      FROM orders o
      LEFT JOIN products p ON p.id = o.product_id
      WHERE o.patient_id = ?
      ORDER BY o.created_at DESC
      LIMIT 1
    ");
    $stmt->execute([$patientId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
      echo json_encode(['ok' => false, 'error' => 'No orders found for this patient']);
      exit;
    }

    // Parse conversation history
    $conversation = [];
    if (!empty($patient['status_comment'])) {
      $parts = preg_split('/\n\n---\n\n/', $patient['status_comment']);
      foreach ($parts as $part) {
        if (preg_match('/^\[([^\]]+)\]\s+Manufacturer:\n(.+)/s', $part, $match)) {
          $conversation[] = [
            'type' => 'manufacturer',
            'timestamp' => $match[1],
            'message' => trim($match[2])
          ];
        }
      }
    }

    if (!empty($patient['provider_response'])) {
      $conversation[] = [
        'type' => 'provider',
        'timestamp' => $patient['provider_response_at'] ?? '',
        'message' => $patient['provider_response']
      ];
    }

    // Generate AI response
    $result = $aiService->generateResponseMessage($order, $patient, $conversation);

    if (isset($result['error'])) {
      echo json_encode(['ok' => false, 'error' => $result['error']]);
      exit;
    }

    echo json_encode([
      'ok' => true,
      'message' => $result['message'],
      'context' => [
        'patient_name' => $patient['first_name'] . ' ' . $patient['last_name'],
        'product' => $order['product_name'] ?? $order['product']
      ]
    ]);
    exit;

  } catch (Exception $e) {
    error_log('[AI Assistant] Error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
    exit;
  }
}

/**
 * Analyze order for completeness
 */
if ($action === 'analyze_order') {
  $patientId = trim($_POST['patient_id'] ?? $_GET['patient_id'] ?? '');

  if (empty($patientId)) {
    echo json_encode(['ok' => false, 'error' => 'Patient ID is required']);
    exit;
  }

  try {
    // Get patient data
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
      echo json_encode(['ok' => false, 'error' => 'Patient not found']);
      exit;
    }

    // Get most recent order
    $stmt = $pdo->prepare("
      SELECT o.*, p.name as product_name
      FROM orders o
      LEFT JOIN products p ON p.id = o.product_id
      WHERE o.patient_id = ?
      ORDER BY o.created_at DESC
      LIMIT 1
    ");
    $stmt->execute([$patientId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
      echo json_encode(['ok' => false, 'error' => 'No orders found for this patient']);
      exit;
    }

    // Analyze with AI
    $result = $aiService->analyzeOrder($order, $patient);

    if (isset($result['error'])) {
      echo json_encode(['ok' => false, 'error' => $result['error']]);
      exit;
    }

    // Try to parse JSON response from AI
    $analysis = json_decode($result['analysis'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      // If not valid JSON, return as raw text
      $analysis = ['raw' => $result['analysis']];
    }

    echo json_encode([
      'ok' => true,
      'analysis' => $analysis
    ]);
    exit;

  } catch (Exception $e) {
    error_log('[AI Assistant] Error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
    exit;
  }
}

/**
 * Generate medical necessity letter
 */
if ($action === 'generate_med_letter') {
  $patientId = trim($_POST['patient_id'] ?? $_GET['patient_id'] ?? '');

  if (empty($patientId)) {
    echo json_encode(['ok' => false, 'error' => 'Patient ID is required']);
    exit;
  }

  try {
    // Get patient data
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
      echo json_encode(['ok' => false, 'error' => 'Patient not found']);
      exit;
    }

    // Get most recent order
    $stmt = $pdo->prepare("
      SELECT o.*, p.name as product_name
      FROM orders o
      LEFT JOIN products p ON p.id = o.product_id
      WHERE o.patient_id = ?
      ORDER BY o.created_at DESC
      LIMIT 1
    ");
    $stmt->execute([$patientId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
      echo json_encode(['ok' => false, 'error' => 'No orders found for this patient']);
      exit;
    }

    // Generate letter
    $result = $aiService->generateMedicalNecessityLetter($order, $patient);

    if (isset($result['error'])) {
      echo json_encode(['ok' => false, 'error' => $result['error']]);
      exit;
    }

    echo json_encode([
      'ok' => true,
      'letter' => $result['letter']
    ]);
    exit;

  } catch (Exception $e) {
    error_log('[AI Assistant] Error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
    exit;
  }
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
