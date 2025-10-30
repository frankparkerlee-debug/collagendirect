<?php
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/auth.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['admin_id'])) {
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$adminId = (int)$_SESSION['admin_id'];
$adminRole = $_SESSION['admin_role'] ?? '';
$action = $_POST['action'] ?? '';

try {
  // Mark provider response as read by admin
  if ($action === 'mark_response_read') {
    $patientId = (int)($_POST['patient_id'] ?? 0);

    if (!$patientId) {
      echo json_encode(['ok' => false, 'error' => 'Invalid patient ID']);
      exit;
    }

    try {
      $pdo->prepare("
        UPDATE patients
        SET admin_response_read_at = NOW()
        WHERE id = ?
      ")->execute([$patientId]);

      echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
      // Column might not exist yet - ignore error
      error_log("Could not update admin_response_read_at: " . $e->getMessage());
      echo json_encode(['ok' => true]); // Still return success
    }
    exit;
  }

  // Send reply from admin to provider
  if ($action === 'send_reply_to_provider') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $replyMessage = trim($_POST['reply_message'] ?? '');

    if (!$patientId) {
      echo json_encode(['ok' => false, 'error' => 'Invalid patient ID']);
      exit;
    }

    if (!$replyMessage) {
      echo json_encode(['ok' => false, 'error' => 'Reply message cannot be empty']);
      exit;
    }

    // Only superadmin and manufacturer can send replies
    if ($adminRole !== 'superadmin' && $adminRole !== 'manufacturer') {
      echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
      exit;
    }

    // Update the patient with the new comment (overwriting previous manufacturer comment)
    // and mark the admin response as read
    $pdo->prepare("
      UPDATE patients
      SET status_comment = ?,
          status_updated_at = NOW(),
          admin_response_read_at = NOW()
      WHERE id = ?
    ")->execute([$replyMessage, $patientId]);

    // Get patient and provider info for notification
    $stmt = $pdo->prepare("
      SELECT p.id, p.user_id, p.first_name, p.last_name, u.email as provider_email
      FROM patients p
      JOIN users u ON u.id = p.user_id
      WHERE p.id = ?
    ");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    // TODO: Send email notification to provider about the new reply
    // Can use SendGrid here similar to other notification emails

    echo json_encode(['ok' => true]);
    exit;
  }

  echo json_encode(['ok' => false, 'error' => 'Invalid action']);

} catch (Exception $e) {
  error_log("Admin patients API error: " . $e->getMessage());
  echo json_encode(['ok' => false, 'error' => 'Server error']);
}
