<?php
/**
 * API: Get active physicians for practice admin
 * Returns list of physicians from practice_physicians table
 */

header('Content-Type: application/json');
session_start();

try {
  require_once __DIR__ . '/../db.php';
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Failed to load database connection']);
  exit;
}

// Check authentication
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = $_SESSION['user_id'];

try {
  // Check if table exists
  $tableExists = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'practice_physicians'
    )
  ")->fetchColumn();

  if (!$tableExists) {
    echo json_encode(['ok' => true, 'physicians' => []]);
    exit;
  }

  // Fetch active physicians for this practice
  $stmt = $pdo->prepare("
    SELECT id, physician_name, npi, license_number, signature_text
    FROM practice_physicians
    WHERE practice_user_id = ? AND is_active = TRUE
    ORDER BY physician_name ASC
  ");

  $stmt->execute([$userId]);
  $physicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'ok' => true,
    'physicians' => $physicians
  ]);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Failed to fetch physicians',
    'details' => $e->getMessage()
  ]);
}
