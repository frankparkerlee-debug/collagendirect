<?php
// Debug endpoint to see raw AI response
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

session_start();

// Check authentication
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo "ERROR: Not authenticated\n";
  exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/ai_service.php';

// Get patient ID
$patientId = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : '';

if (empty($patientId)) {
  echo "ERROR: Patient ID required. Usage: ?patient_id=xxx\n";
  exit;
}

$userId = $_SESSION['user_id'];

// Fetch patient data
$stmt = $pdo->prepare("SELECT p.* FROM patients p WHERE p.id = ? AND p.user_id = ?");
$stmt->execute([$patientId, $userId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
  echo "ERROR: Patient not found or access denied\n";
  exit;
}

echo "=== PATIENT DATA ===\n";
echo "Name: {$patient['first_name']} {$patient['last_name']}\n";
echo "DOB: {$patient['dob']}\n";
echo "Insurance: {$patient['insurance_provider']}\n";
echo "ID Card: " . (!empty($patient['id_card_path']) ? 'Uploaded' : 'Missing') . "\n";
echo "Insurance Card: " . (!empty($patient['ins_card_path']) ? 'Uploaded' : 'Missing') . "\n";
echo "Notes: " . (!empty($patient['notes_text']) ? substr($patient['notes_text'], 0, 100) . '...' : 'Missing') . "\n";
echo "\n";

// Prepare documents array
$documents = [];

if (!empty($patient['id_card_path'])) {
  $documents[] = [
    'type' => 'Photo ID',
    'filename' => basename($patient['id_card_path']),
    'path' => $patient['id_card_path'],
    'mime' => isset($patient['id_card_mime']) ? $patient['id_card_mime'] : 'unknown',
    'extracted_text' => ''
  ];
}

if (!empty($patient['ins_card_path'])) {
  $documents[] = [
    'type' => 'Insurance Card',
    'filename' => basename($patient['ins_card_path']),
    'path' => $patient['ins_card_path'],
    'mime' => isset($patient['ins_card_mime']) ? $patient['ins_card_mime'] : 'unknown',
    'extracted_text' => ''
  ];
}

if (!empty($patient['notes_path'])) {
  $patient['notes_text'] = '';
  if (file_exists($patient['notes_path'])) {
    $patient['notes_text'] = @file_get_contents($patient['notes_path']) ?: '';
  }

  $documents[] = [
    'type' => 'Clinical Notes',
    'filename' => basename($patient['notes_path']),
    'path' => $patient['notes_path'],
    'mime' => isset($patient['notes_mime']) ? $patient['notes_mime'] : 'unknown',
    'extracted_text' => isset($patient['notes_text']) ? $patient['notes_text'] : ''
  ];
}

echo "=== CALLING AI SERVICE ===\n";
$aiService = new AIService();

try {
  // Use reflection to access private method for debugging
  $reflection = new ReflectionClass($aiService);
  $method = $reflection->getMethod('buildApprovalScorePrompt');
  $method->setAccessible(true);
  $prompt = $method->invoke($aiService, $patient, $documents);

  echo "Prompt length: " . strlen($prompt) . " characters\n";
  echo "\n=== FIRST 500 CHARS OF PROMPT ===\n";
  echo substr($prompt, 0, 500) . "...\n\n";

  // Call the API
  $apiMethod = $reflection->getMethod('callClaudeAPI');
  $apiMethod->setAccessible(true);

  echo "=== CALLING CLAUDE API ===\n";
  $response = $apiMethod->invoke($aiService, $prompt, 3072);

  echo "Response length: " . strlen($response) . " characters\n\n";
  echo "=== RAW CLAUDE RESPONSE ===\n";
  echo $response . "\n\n";

  // Try to parse it
  echo "=== ATTEMPTING JSON PARSE ===\n";

  $jsonText = $response;

  // Remove markdown code fences if present
  if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
    echo "Found JSON markdown fence\n";
    $jsonText = $matches[1];
  } elseif (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
    echo "Found generic markdown fence\n";
    $jsonText = $matches[1];
  } else {
    echo "No markdown fences found, using raw response\n";
  }

  $result = json_decode(trim($jsonText), true);

  if ($result) {
    echo "✓ JSON parsed successfully!\n\n";
    echo "=== PARSED RESULT ===\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
  } else {
    echo "✗ JSON parse FAILED\n";
    echo "JSON error: " . json_last_error_msg() . "\n";
    echo "Attempted to parse:\n";
    echo substr(trim($jsonText), 0, 500) . "...\n";
  }

} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  echo "Trace: " . $e->getTraceAsString() . "\n";
}
