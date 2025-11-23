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

  // Detect column names dynamically
  $ppCols = $pdo->query("
    SELECT column_name FROM information_schema.columns
    WHERE table_name = 'practice_physicians'
  ")->fetchAll(PDO::FETCH_COLUMN);

  // Determine which columns exist
  $adminCol = in_array('practice_admin_id', $ppCols) ? 'practice_admin_id' :
              (in_array('practice_manager_id', $ppCols) ? 'practice_manager_id' : 'practice_user_id');
  $hasPhysicianName = in_array('physician_name', $ppCols);
  $npiCol = in_array('npi', $ppCols) ? 'npi' : null;
  $licenseCol = in_array('license_number', $ppCols) ? 'license_number' :
                (in_array('license', $ppCols) ? 'license' : null);
  $signatureCol = in_array('signature_text', $ppCols) ? 'signature_text' : null;

  // Build SELECT query based on available columns
  if ($hasPhysicianName) {
    $selectCols = "id, physician_name";
    if ($npiCol) $selectCols .= ", $npiCol as npi";
    if ($licenseCol) $selectCols .= ", $licenseCol as license_number";
    if ($signatureCol) $selectCols .= ", $signatureCol as signature_text";

    $stmt = $pdo->prepare("
      SELECT $selectCols
      FROM practice_physicians
      WHERE $adminCol = ? AND is_active = TRUE
      ORDER BY physician_name ASC
    ");
  } else {
    // Table has separate first_name and last_name columns
    $firstNameCol = in_array('first_name', $ppCols) ? 'first_name' : 'physician_first_name';
    $lastNameCol = in_array('last_name', $ppCols) ? 'last_name' : 'physician_last_name';

    $selectCols = "id, CONCAT($firstNameCol, ' ', $lastNameCol) as physician_name";
    if ($npiCol) $selectCols .= ", $npiCol as npi";
    if ($licenseCol) $selectCols .= ", $licenseCol as license_number";
    if ($signatureCol) $selectCols .= ", $signatureCol as signature_text";

    $stmt = $pdo->prepare("
      SELECT $selectCols
      FROM practice_physicians
      WHERE $adminCol = ? AND is_active = TRUE
      ORDER BY $firstNameCol ASC, $lastNameCol ASC
    ");
  }

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
    'details' => $e->getMessage(),
    'line' => $e->getLine(),
    'file' => basename($e->getFile()),
    'userId' => $userId ?? 'unknown'
  ]);
}
