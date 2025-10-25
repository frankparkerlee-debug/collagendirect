<?php
// public/api/login.php
declare(strict_types=1);
require __DIR__ . '/db.php';
require_csrf();

try {
  $data = json_decode(file_get_contents('php://input'), true) ?? [];
} catch (Throwable $e) {
  json_out(400, ['error'=>'Invalid JSON']);
}

$email = strtolower(trim((string)($data['email'] ?? '')));
$pass  = (string)($data['password'] ?? '');
$remember = !empty($data['remember']);

if (!$email || !$pass) json_out(400, ['error'=>'Email and password required']);

// Try to find user in users table first (physicians, practice_admin, superadmin)
$stmt = $pdo->prepare("SELECT id, password_hash, first_name, last_name, email, role FROM users WHERE email=? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

$isAdminUser = false;
$adminUser = null;

// If not found in users table, check admin_users table (employees, manufacturer)
if (!$user) {
  $stmt = $pdo->prepare("SELECT id, password_hash, name, email, role FROM admin_users WHERE email=? LIMIT 1");
  $stmt->execute([$email]);
  $adminUser = $stmt->fetch();
  if ($adminUser) {
    $isAdminUser = true;
  }
}

// Verify credentials
if ($isAdminUser) {
  if (!password_verify($pass, (string)$adminUser['password_hash'])) {
    json_out(401, ['error'=>'Invalid credentials']);
  }
} else {
  if (!$user || !password_verify($pass, (string)$user['password_hash'])) {
    json_out(401, ['error'=>'Invalid credentials']);
  }
}

// Rotate session id for security
session_regenerate_id(true);

// Handle admin_users (employees, manufacturer)
if ($isAdminUser) {
  // Admin users go to /admin portal
  $_SESSION['admin'] = [
    'id' => $adminUser['id'],
    'email' => $adminUser['email'],
    'name' => $adminUser['name'],
    'role' => $adminUser['role']
  ];

  // Always set persistent cookie
  $params = session_get_cookie_params();
  setcookie(session_name(), session_id(), [
    'expires'  => time() + 60*60*24*7, // 7 days
    'path'     => $params['path'],
    'domain'   => $params['domain'],
    'secure'   => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite'] ?? 'Lax'
  ]);

  json_out(200, ['ok'=>true, 'redirect'=>'/admin/', 'user'=>[
    'id'=>$adminUser['id'],
    'email'=>$adminUser['email'],
    'name'=>$adminUser['name'],
    'role'=>$adminUser['role']
  ]]);
}

// Handle users table (physicians, practice_admin, superadmin)
$_SESSION['user_id'] = $user['id'];

// Set admin session ONLY for superadmin (CollagenDirect business users)
// practice_admin is for practice managers and should NOT access /admin
$userRole = $user['role'] ?? 'physician';
if ($userRole === 'superadmin') {
  $_SESSION['admin'] = [
    'id' => $user['id'],
    'email' => $user['email'],
    'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
    'role' => $userRole
  ];
}

// Always set persistent cookie (7 days) - session config handles this
// The session cookie params are already configured in db.php for 7 days
$params = session_get_cookie_params();
setcookie(session_name(), session_id(), [
  'expires'  => time() + 60*60*24*7, // 7 days
  'path'     => $params['path'],
  'domain'   => $params['domain'],
  'secure'   => $params['secure'],
  'httponly' => $params['httponly'],
  'samesite' => $params['samesite'] ?? 'Lax'
]);

// Determine redirect based on role
$redirectUrl = '/portal/';
if ($userRole === 'superadmin') {
  // Superadmins can access both portal and admin
  // Default to portal (they can navigate to admin if needed)
  $redirectUrl = '/portal/';
}

json_out(200, ['ok'=>true, 'redirect'=>$redirectUrl, 'user'=>[
  'id'=>$user['id'],
  'email'=>$user['email'],
  'name'=>trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')),
  'role'=>$userRole
]]);
