<?php
// public/api/register.php - Revamped Registration Handler
declare(strict_types=1);
require __DIR__ . '/db.php';
require_csrf();

try {
  $data = json_decode(file_get_contents('php://input'), true) ?? [];
} catch (Throwable $e) {
  json_out(400, ['error' => 'Invalid JSON']);
}

// Required fields for all user types
$required = ['email', 'password', 'userType', 'agreeMSA', 'agreeBAA', 'signName', 'signTitle', 'signDate'];
foreach ($required as $k) {
  if (!isset($data[$k]) || $data[$k] === '') json_out(400, ['error' => "Missing field: $k"]);
}

$email = strtolower(trim((string)$data['email']));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(400, ['error'=>'Invalid email']);

if (strlen((string)$data['password']) < 8) json_out(400, ['error'=>'Password must be at least 8 characters']);

// Validate user type
$userType = $data['userType'];
if (!in_array($userType, ['practice_admin', 'physician', 'dme_hybrid', 'dme_wholesale'], true)) {
  json_out(400, ['error'=>'Invalid user type']);
}

// Validation based on user type
if ($userType === 'practice_admin') {
  // Practice Manager: requires practice info + physician credentials
  $requiredFields = ['practiceName', 'address', 'city', 'state', 'zip', 'phone', 'firstName', 'lastName', 'npi', 'license', 'licenseState', 'licenseExpiry'];
  foreach ($requiredFields as $k) {
    if (empty($data[$k])) json_out(400, ['error' => "Missing field for Practice Manager: $k"]);
  }
} elseif ($userType === 'physician') {
  // Physician: requires physician credentials + practice manager link
  $requiredFields = ['firstName', 'lastName', 'npi', 'license', 'licenseState', 'licenseExpiry', 'practiceManagerEmail'];
  foreach ($requiredFields as $k) {
    if (empty($data[$k])) json_out(400, ['error' => "Missing field for Physician: $k"]);
  }
} elseif ($userType === 'dme_hybrid' || $userType === 'dme_wholesale') {
  // DME users: requires practice info + physician credentials + DME license
  $requiredFields = ['practiceName', 'address', 'city', 'state', 'zip', 'phone', 'firstName', 'lastName', 'npi', 'license', 'licenseState', 'licenseExpiry', 'dmeNumber', 'dmeState', 'dmeExpiry'];
  foreach ($requiredFields as $k) {
    if (empty($data[$k])) json_out(400, ['error' => "Missing field for DME user: $k"]);
  }
}

// Validate NPI format
$npi = preg_replace('/\D/','', (string)($data['npi'] ?? ''));
if (!empty($npi) && strlen($npi) !== 10) json_out(400, ['error'=>'NPI must be 10 digits']);

if (empty($data['agreeMSA']) || empty($data['agreeBAA'])) json_out(400, ['error'=>'Agreements must be accepted']);

$hash = password_hash((string)$data['password'], PASSWORD_DEFAULT);
$id = uid();

try {
  // Check if email already registered
  $chk = $pdo->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
  $chk->execute([$email]);
  if ($chk->fetch()) json_out(409, ['error' => 'Email already registered']);

  // Set account_type and role based on user type
  $accountType = 'referral';
  $role = 'physician';
  $isReferralOnly = false;
  $hasDmeLicense = false;
  $isHybrid = false;
  $canManagePhysicians = false;
  $parentUserId = null;

  switch ($userType) {
    case 'practice_admin':
      $accountType = 'referral';
      $role = 'practice_admin';
      $isReferralOnly = true;
      $canManagePhysicians = true;
      break;

    case 'physician':
      $accountType = 'referral';
      $role = 'physician';
      $isReferralOnly = true;

      // Try to find practice manager by email
      if (!empty($data['practiceManagerEmail'])) {
        $pmEmail = strtolower(trim($data['practiceManagerEmail']));
        $pmStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND can_manage_physicians = TRUE LIMIT 1");
        $pmStmt->execute([$pmEmail]);
        $pm = $pmStmt->fetch(PDO::FETCH_ASSOC);
        if ($pm) {
          $parentUserId = $pm['id'];
        }
      }
      break;

    case 'dme_hybrid':
      $accountType = 'hybrid';
      $role = 'practice_admin';
      $hasDmeLicense = true;
      $isHybrid = true;
      $canManagePhysicians = true;
      break;

    case 'dme_wholesale':
      $accountType = 'wholesale';
      $role = 'practice_admin';
      $hasDmeLicense = true;
      $canManagePhysicians = true;
      break;
  }

  // Insert main user
  $stmt = $pdo->prepare("
    INSERT INTO users(
      id, email, password_hash, first_name, last_name, account_type, user_type, role,
      practice_name, address, city, state, zip, tax_id, phone,
      npi, license, license_state, license_expiry,
      dme_number, dme_state, dme_expiry,
      agree_msa, agree_baa, sign_name, sign_title, sign_date,
      is_referral_only, has_dme_license, is_hybrid, can_manage_physicians, parent_user_id,
      status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ");

  $stmt->execute([
    $id,
    $email,
    $hash,
    trim((string)($data['firstName'] ?? '')),
    trim((string)($data['lastName'] ?? '')),
    $accountType,
    $userType,
    $role,
    trim((string)($data['practiceName'] ?? '')),
    trim((string)($data['address'] ?? '')),
    trim((string)($data['city'] ?? '')),
    (string)($data['state'] ?? ''),
    trim((string)($data['zip'] ?? '')),
    trim((string)($data['taxId'] ?? '')),
    trim((string)($data['phone'] ?? '')),
    $npi,
    trim((string)($data['license'] ?? '')),
    (string)($data['licenseState'] ?? ''),
    (string)($data['licenseExpiry'] ?? ''),
    trim((string)($data['dmeNumber'] ?? '')),
    (string)($data['dmeState'] ?? ''),
    (string)($data['dmeExpiry'] ?? ''),
    !empty($data['agreeMSA']) ? 1 : 0,
    !empty($data['agreeBAA']) ? 1 : 0,
    trim((string)$data['signName']),
    trim((string)$data['signTitle']),
    (string)$data['signDate'],
    $isReferralOnly ? 1 : 0,
    $hasDmeLicense ? 1 : 0,
    $isHybrid ? 1 : 0,
    $canManagePhysicians ? 1 : 0,
    $parentUserId,
    'active'
  ]);

  // Handle additional physicians for practice_admin
  if ($userType === 'practice_admin' && !empty($data['additionalPhysicians']) && is_array($data['additionalPhysicians'])) {
    $physicianStmt = $pdo->prepare("
      INSERT INTO practice_physicians(
        practice_manager_id, physician_first_name, physician_last_name,
        physician_npi, physician_license, physician_license_state, physician_license_expiry,
        physician_email, physician_phone
      ) VALUES (?,?,?,?,?,?,?,?,?)
    ");

    foreach ($data['additionalPhysicians'] as $physician) {
      if (empty($physician['firstName']) || empty($physician['lastName']) || empty($physician['npi'])) {
        continue; // Skip incomplete entries
      }

      $physicianStmt->execute([
        $id,
        trim((string)$physician['firstName']),
        trim((string)$physician['lastName']),
        preg_replace('/\D/', '', (string)$physician['npi']),
        trim((string)($physician['license'] ?? '')),
        (string)($physician['licenseState'] ?? ''),
        (string)($physician['licenseExpiry'] ?? ''),
        trim((string)($physician['email'] ?? '')),
        trim((string)($physician['phone'] ?? ''))
      ]);
    }
  }

  json_out(201, ['ok' => true, 'message' => 'Registration successful']);

} catch (Throwable $e) {
  // Temporarily enabled for debugging
  json_out(500, ['error'=>'Server error', 'detail'=>$e->getMessage()]);
  // json_out(500, ['error' => 'Server error']);
}
