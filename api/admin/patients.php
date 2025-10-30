<?php
// Start output buffering to catch any stray output
ob_start();

require_once __DIR__.'/../../db.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Check authentication - session is started in db.php
// Support both admin users (from admin_users table) and superadmin (from users table)
$adminId = null;
$adminRole = null;

if (isset($_SESSION['admin'])) {
  // Admin user (employee, manufacturer) from admin_users table
  $adminId = (int)$_SESSION['admin']['id'];
  $adminRole = $_SESSION['admin']['role'];
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
  // Superadmin from users table
  $adminId = (int)$_SESSION['user_id'];
  $adminRole = $_SESSION['role'];
} else {
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}
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

    // Check if admin_response_read_at column exists
    $hasAdminReadTracking = false;
    try {
      $cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'patients' AND column_name = 'admin_response_read_at'")->fetchAll(PDO::FETCH_COLUMN);
      $hasAdminReadTracking = in_array('admin_response_read_at', $cols);
    } catch (Throwable $e) {
      error_log("Could not check for admin_response_read_at column: " . $e->getMessage());
    }

    // Update the patient with the new comment (overwriting previous manufacturer comment)
    // and mark the admin response as read if the column exists
    try {
      if ($hasAdminReadTracking) {
        $pdo->prepare("
          UPDATE patients
          SET status_comment = ?,
              status_updated_at = NOW(),
              admin_response_read_at = NOW()
          WHERE id = ?
        ")->execute([$replyMessage, $patientId]);
      } else {
        $pdo->prepare("
          UPDATE patients
          SET status_comment = ?,
              status_updated_at = NOW()
          WHERE id = ?
        ")->execute([$replyMessage, $patientId]);
      }
    } catch (Throwable $e) {
      error_log("Error updating patient comment: " . $e->getMessage());
      echo json_encode(['ok' => false, 'error' => 'Failed to update patient record']);
      exit;
    }

    // Get patient and provider info for notification
    try {
      $stmt = $pdo->prepare("
        SELECT p.id, p.user_id, p.first_name, p.last_name, u.email as provider_email
        FROM patients p
        JOIN users u ON u.id = p.user_id
        WHERE p.id = ?
      ");
      $stmt->execute([$patientId]);
      $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      error_log("Error fetching patient info: " . $e->getMessage());
      // Don't fail the request if we can't fetch patient info for notification
    }

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
