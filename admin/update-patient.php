<?php
// /admin/update-patient.php - Update patient information
declare(strict_types=1);

require_once __DIR__ . '/db.php';
$auth = __DIR__ . '/auth.php';
if (is_file($auth)) require_once $auth;
if (function_exists('require_admin')) require_admin();

header('Content-Type: application/json');

try {
  // Verify admin session
  if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
  }

  $adminId = $_SESSION['admin_id'];
  $adminRole = $_SESSION['admin_role'] ?? 'employee';

  // Get patient ID
  $patientId = $_POST['patient_id'] ?? '';
  if (!$patientId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Patient ID required']);
    exit;
  }

  // Get form data
  $firstName = trim($_POST['first_name'] ?? '');
  $lastName = trim($_POST['last_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $dob = trim($_POST['dob'] ?? '');
  $state = trim($_POST['state'] ?? 'pending');

  // Insurance information
  $insuranceProvider = trim($_POST['insurance_provider'] ?? '');
  $insuranceMemberId = trim($_POST['insurance_member_id'] ?? '');
  $insuranceGroupId = trim($_POST['insurance_group_id'] ?? '');
  $insurancePayerPhone = trim($_POST['insurance_payer_phone'] ?? '');

  // Validate required fields
  if (!$firstName || !$lastName) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'First and last name are required']);
    exit;
  }

  // Check if patient exists and admin has permission
  $checkSql = "SELECT id, user_id FROM patients WHERE id = ?";
  $checkStmt = $pdo->prepare($checkSql);
  $checkStmt->execute([$patientId]);
  $patient = $checkStmt->fetch(PDO::FETCH_ASSOC);

  if (!$patient) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
    exit;
  }

  // Role-based access control
  if ($adminRole !== 'superadmin' && $adminRole !== 'manufacturer') {
    // Employees can only edit patients from their assigned physicians
    $permissionSql = "SELECT 1 FROM admin_physicians WHERE admin_id = ? AND physician_user_id = ?";
    $permStmt = $pdo->prepare($permissionSql);
    $permStmt->execute([$adminId, $patient['user_id']]);

    if (!$permStmt->fetch()) {
      http_response_code(403);
      echo json_encode(['ok' => false, 'error' => 'No permission to edit this patient']);
      exit;
    }
  }

  // Update patient
  $updateSql = "
    UPDATE patients
    SET
      first_name = ?,
      last_name = ?,
      email = ?,
      phone = ?,
      dob = ?,
      state = ?,
      insurance_provider = ?,
      insurance_member_id = ?,
      insurance_group_id = ?,
      insurance_payer_phone = ?,
      updated_at = NOW()
    WHERE id = ?
  ";

  $updateStmt = $pdo->prepare($updateSql);
  $updateStmt->execute([
    $firstName,
    $lastName,
    $email ?: null,
    $phone ?: null,
    $dob ?: null,
    $state,
    $insuranceProvider ?: null,
    $insuranceMemberId ?: null,
    $insuranceGroupId ?: null,
    $insurancePayerPhone ?: null,
    $patientId
  ]);

  echo json_encode([
    'ok' => true,
    'message' => 'Patient updated successfully'
  ]);

} catch (PDOException $e) {
  error_log("[update-patient] Database error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
  error_log("[update-patient] Error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error']);
}
