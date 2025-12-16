<?php
/**
 * API: Get Payout Adjustment History
 *
 * Returns adjustment history for a specific payout
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../admin/db.php';
require_once __DIR__ . '/../../admin/auth.php';

// Check admin authentication
if (!isset($admin) || !$admin) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$payoutId = (int)($_GET['payout_id'] ?? 0);

if (!$payoutId) {
  http_response_code(400);
  echo json_encode(['error' => 'Payout ID required']);
  exit;
}

try {
  // Check if payout_adjustments table exists
  $tableExists = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'payout_adjustments'
    )
  ")->fetchColumn();

  if (!$tableExists) {
    echo json_encode(['adjustments' => []]);
    exit;
  }

  // Fetch adjustments with adjuster names
  // adjusted_by can be either admin_users.id (integer) or users.id (UUID string)
  $stmt = $pdo->prepare("
    SELECT pa.*,
      COALESCE(au.name, CONCAT(u.first_name, ' ', u.last_name)) as adjusted_by_name,
      TO_CHAR(pa.created_at, 'Mon DD, YYYY at HH12:MI AM') as created_at
    FROM payout_adjustments pa
    LEFT JOIN admin_users au ON pa.adjusted_by ~ '^[0-9]+$' AND au.id = pa.adjusted_by::integer
    LEFT JOIN users u ON pa.adjusted_by !~ '^[0-9]+$' AND u.id = pa.adjusted_by
    WHERE pa.payout_id = ?
    ORDER BY pa.created_at DESC
  ");
  $stmt->execute([$payoutId]);
  $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['adjustments' => $adjustments]);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
