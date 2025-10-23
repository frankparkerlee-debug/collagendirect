<?php
// public/api/register.php
declare(strict_types=1);
require __DIR__ . '/db.php';
require_csrf();

try {
  $data = json_decode(file_get_contents('php://input'), true) ?? [];
} catch (Throwable $e) {
  json_out(400, ['error' => 'Invalid JSON']);
}

$required = ['email','password','practiceName','firstName','lastName','npi','license','licenseState','licenseExpiry','accountType','agreeMSA','agreeBAA','signName','signTitle','signDate'];
foreach ($required as $k) {
  if (!isset($data[$k]) || $data[$k] === '') json_out(400, ['error' => "Missing field: $k"]);
}

$email = strtolower(trim((string)$data['email']));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(400, ['error'=>'Invalid email']);

if (strlen((string)$data['password']) < 8) json_out(400, ['error'=>'Password must be at least 8 characters']);

$acct = $data['accountType'];
if (!in_array($acct, ['referral','wholesale','hybrid'], true)) json_out(400, ['error'=>'Invalid account type']);

$npi = preg_replace('/\D/','',(string)$data['npi']);
if (strlen($npi) !== 10) json_out(400, ['error'=>'NPI must be 10 digits']);

if (empty($data['agreeMSA']) || empty($data['agreeBAA'])) json_out(400, ['error'=>'Agreements must be accepted']);

$hash = password_hash((string)$data['password'], PASSWORD_DEFAULT);
$id = uid();

try {
  // unique email check (gives nicer 409 than raw SQL error)
  $chk = $pdo->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
  $chk->execute([$email]);
  if ($chk->fetch()) json_out(409, ['error' => 'Email already registered']);

  $stmt = $pdo->prepare("
    INSERT INTO users(
      id,email,password_hash,first_name,last_name,account_type,practice_name,phone,
      npi,license,license_state,license_expiry,
      dme_number,dme_state,dme_expiry,
      agree_msa,agree_baa,sign_name,sign_title,sign_date,status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ");

  $stmt->execute([
    $id, $email, $hash,
    trim((string)$data['firstName']), trim((string)$data['lastName']),
    $acct, trim((string)$data['practiceName']), $data['phone'] ?? null,
    $npi, trim((string)$data['license']), (string)$data['licenseState'], (string)$data['licenseExpiry'],
    $data['dmeNumber'] ?? null, $data['dmeState'] ?? null, $data['dmeExpiry'] ?? null,
    !empty($data['agreeMSA']) ? 1 : 0, !empty($data['agreeBAA']) ? 1 : 0,
    trim((string)$data['signName']), trim((string)$data['signTitle']), (string)$data['signDate'],
    'active'
  ]);

  json_out(201, ['ok' => true]);
} catch (Throwable $e) {
  // uncomment during troubleshooting:
  // json_out(500, ['error'=>'Server error', 'detail'=>$e->getMessage()]);
  json_out(500, ['error' => 'Server error']);
}
