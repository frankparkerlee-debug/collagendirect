<?php
// Simple test endpoint to debug approval score issues
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors as HTML

header('Content-Type: application/json');

$response = ['status' => 'testing'];

try {
  session_start();
  $response['session'] = isset($_SESSION['portal_user_id']) ? 'authenticated' : 'not authenticated';

  require_once __DIR__ . '/../db.php';
  $response['database'] = 'connected';

  require_once __DIR__ . '/../lib/ai_service.php';
  $response['ai_service'] = 'loaded';

  $ai = new AIService();
  $response['ai_instance'] = 'created';

  // Check if API key is set
  $apiKey = getenv('ANTHROPIC_API_KEY');
  $response['api_key'] = !empty($apiKey) ? 'present (length: ' . strlen($apiKey) . ')' : 'MISSING';

  // Try a simple patient query
  $patientId = isset($_GET['patient_id']) ? $_GET['patient_id'] : '';
  if ($patientId) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM patients WHERE id = ? LIMIT 1");
    $stmt->execute(array($patientId));
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['patient'] = $patient ? 'found: ' . $patient['first_name'] . ' ' . $patient['last_name'] : 'not found';
  }

  $response['status'] = 'all_checks_passed';

} catch (Exception $e) {
  $response['error'] = $e->getMessage();
  $response['trace'] = $e->getTraceAsString();
} catch (Throwable $e) {
  $response['fatal_error'] = $e->getMessage();
  $response['trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT);
