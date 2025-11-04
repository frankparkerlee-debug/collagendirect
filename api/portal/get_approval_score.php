<?php
/**
 * API endpoint to retrieve stored AI approval score feedback
 * Returns the most recent approval score for a patient
 */

header('Content-Type: application/json');
session_start();

try {
  require_once __DIR__ . '/../db.php';
} catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => 'Failed to load database: ' . $e->getMessage()]);
  exit;
}

// Check authentication
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = $_SESSION['user_id'];
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'physician';

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
  // Check if table exists
  $tableCheck = $pdo->query("
    SELECT COUNT(*) as cnt
    FROM information_schema.tables
    WHERE table_name = 'patient_approval_scores'
  ");
  $tableExists = (int)$tableCheck->fetchColumn() > 0;

  if (!$tableExists) {
    echo json_encode(['ok' => true, 'has_score' => false]);
    exit;
  }

  // Verify patient access
  if ($userRole === 'superadmin') {
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
  } else {
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
    $stmt->execute([$patientId, $userId]);
  }

  if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found or access denied']);
    exit;
  }

  // Get most recent approval score for this patient
  $scoreStmt = $pdo->prepare("
    SELECT
      score,
      score_numeric,
      summary,
      missing_items,
      complete_items,
      recommendations,
      concerns,
      document_analysis,
      created_at
    FROM patient_approval_scores
    WHERE patient_id = ?
    ORDER BY created_at DESC
    LIMIT 1
  ");
  $scoreStmt->execute([$patientId]);
  $score = $scoreStmt->fetch(PDO::FETCH_ASSOC);

  if (!$score) {
    echo json_encode(['ok' => true, 'has_score' => false]);
    exit;
  }

  // Decode JSON fields
  $score['missing_items'] = json_decode($score['missing_items'], true) ?: [];
  $score['complete_items'] = json_decode($score['complete_items'], true) ?: [];
  $score['recommendations'] = json_decode($score['recommendations'], true) ?: [];
  $score['concerns'] = json_decode($score['concerns'], true) ?: [];
  $score['document_analysis'] = json_decode($score['document_analysis'], true);

  echo json_encode([
    'ok' => true,
    'has_score' => true,
    'score' => $score['score'],
    'score_numeric' => $score['score_numeric'],
    'summary' => $score['summary'],
    'missing_items' => $score['missing_items'],
    'complete_items' => $score['complete_items'],
    'recommendations' => $score['recommendations'],
    'concerns' => $score['concerns'],
    'document_analysis' => $score['document_analysis'],
    'created_at' => $score['created_at']
  ]);

} catch (Exception $e) {
  error_log("Get approval score error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Failed to retrieve approval score']);
}
