<?php
// Suppress all errors from being displayed (log them instead)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
  $error = error_get_last();
  if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']]);
    exit;
  }
});

// Start output buffering to catch any stray output
ob_start();

try {
  require_once __DIR__.'/../db.php';
} catch (Throwable $e) {
  while (ob_get_level()) ob_end_clean();
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
  exit;
}

// Clear any output that might have been generated (warnings, notices, etc.)
ob_end_clean();
ob_start();

header('Content-Type: application/json');

// Check if $pdo exists
if (!isset($pdo)) {
  echo json_encode(['ok' => false, 'error' => 'Database connection not available']);
  exit;
}

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

    // Log for debugging
    error_log("Reply request - Patient ID: $patientId, Reply length: " . strlen($replyMessage));

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

    // Check if tracking columns exist
    $hasAdminReadTracking = false;
    $hasProviderReadTracking = false;
    try {
      $cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'patients' AND column_name IN ('admin_response_read_at', 'provider_comment_read_at')")->fetchAll(PDO::FETCH_COLUMN);
      $hasAdminReadTracking = in_array('admin_response_read_at', $cols);
      $hasProviderReadTracking = in_array('provider_comment_read_at', $cols);
    } catch (Throwable $e) {
      error_log("Could not check for tracking columns: " . $e->getMessage());
    }

    // Get current comment to append to it (for conversation thread)
    $currentComment = '';
    $patientExists = false;
    try {
      $stmt = $pdo->prepare("SELECT status_comment FROM patients WHERE id = ?");
      $stmt->execute([$patientId]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Patient not found']);
        exit;
      }

      $patientExists = true;
      $currentComment = $row['status_comment'] ?? '';
    } catch (Throwable $e) {
      error_log("Error fetching patient: " . $e->getMessage());
      echo json_encode(['ok' => false, 'error' => 'Failed to fetch patient: ' . $e->getMessage()]);
      exit;
    }

    // Build conversation thread by appending new message
    $timestamp = date('Y-m-d H:i:s');
    $separator = "\n\n---\n\n";
    $newMessage = "[" . $timestamp . "] Manufacturer:\n" . $replyMessage;

    if (!empty($currentComment)) {
      // Append to existing conversation
      $fullComment = $currentComment . $separator . $newMessage;
    } else {
      // First message
      $fullComment = $newMessage;
    }

    // Update the patient with the conversation thread
    // Reset provider_comment_read_at so physician sees the red dot notification
    try {
      // Build SQL based on available columns
      $sql = "UPDATE patients SET status_comment = ?, status_updated_at = NOW()";
      $params = [$fullComment];

      if ($hasAdminReadTracking) {
        $sql .= ", admin_response_read_at = NOW()";
      }

      if ($hasProviderReadTracking) {
        $sql .= ", provider_comment_read_at = NULL";
      }

      $sql .= " WHERE id = ?";
      $params[] = $patientId;

      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);

      // Log success for debugging
      error_log("Successfully updated patient $patientId with conversation thread. SQL: $sql");
    } catch (Throwable $e) {
      error_log("Error updating patient comment: " . $e->getMessage() . " SQL: $sql");
      echo json_encode(['ok' => false, 'error' => 'Failed to update patient record: ' . $e->getMessage()]);
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
