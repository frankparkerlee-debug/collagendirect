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

$isAdminUser = false;
$adminUser = null;
$user = null;

// IMPORTANT: Check admin_users table FIRST for employees/manufacturer
// This ensures users with INTEGER ids (admin_users) are not confused with
// UUID ids from the users table if someone exists in both tables
$stmt = $pdo->prepare("SELECT id, password_hash, name, email, role, has_rep_view FROM admin_users WHERE email=? LIMIT 1");
$stmt->execute([$email]);
$adminUser = $stmt->fetch();
if ($adminUser) {
  $isAdminUser = true;
}

// If not found in admin_users table, check users table (physicians, practice_admin, superadmin, sales_rep)
if (!$isAdminUser) {
  $stmt = $pdo->prepare("SELECT id, password_hash, first_name, last_name, email, role FROM users WHERE email=? LIMIT 1");
  $stmt->execute([$email]);
  $user = $stmt->fetch();
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
    'role' => $adminUser['role'],
    'has_rep_view' => !empty($adminUser['has_rep_view'])
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

  // Employee sales reps with rep view go to employee-rep portal
  $redirect = '/admin/';
  if (!empty($adminUser['has_rep_view'])) {
    $redirect = '/admin/employee-rep/';
  }

  json_out(200, ['ok'=>true, 'redirect'=>$redirect, 'user'=>[
    'id'=>$adminUser['id'],
    'email'=>$adminUser['email'],
    'name'=>$adminUser['name'],
    'role'=>$adminUser['role'],
    'has_rep_view'=>!empty($adminUser['has_rep_view'])
  ]]);
}

// Handle users table (physicians, practice_admin, superadmin, sales_rep)
$_SESSION['user_id'] = $user['id'];

// Check if user is a sales rep (active or pending)
$userRole = $user['role'] ?? 'physician';
$isSalesRep = false;
$isPendingRep = false;
$repId = null;

$repStmt = $pdo->prepare("SELECT id, status FROM sales_reps WHERE user_id = ?");
$repStmt->execute([$user['id']]);
$repRecord = $repStmt->fetch();
if ($repRecord) {
  if ($repRecord['status'] === 'active') {
    $isSalesRep = true;
    $repId = $repRecord['id'];
    $userRole = 'sales_rep';
  } elseif ($repRecord['status'] === 'pending') {
    $isPendingRep = true;
  } elseif ($repRecord['status'] === 'suspended') {
    json_out(403, ['error' => 'Your sales rep account has been suspended. Please contact support.']);
  }
}

// If pending rep, show pending message
if ($isPendingRep) {
  json_out(403, [
    'error' => 'pending_approval',
    'message' => 'Your application is under review. You will receive an email once your account is approved.'
  ]);
}

// Set admin session for superadmin or sales_rep
// practice_admin is for practice managers and should NOT access /admin
if ($userRole === 'superadmin' || $isSalesRep) {
  $_SESSION['admin'] = [
    'id' => $user['id'],
    'email' => $user['email'],
    'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
    'role' => $userRole
  ];
  if ($isSalesRep) {
    $_SESSION['admin']['rep_id'] = $repId;
  }
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
  // Superadmins can access both portal and admin - default to /admin
  $redirectUrl = '/admin/';
} elseif ($isSalesRep) {
  // Sales reps go to their dedicated portal
  $redirectUrl = '/admin/rep/';
}

json_out(200, ['ok'=>true, 'redirect'=>$redirectUrl, 'user'=>[
  'id'=>$user['id'],
  'email'=>$user['email'],
  'name'=>trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')),
  'role'=>$userRole
]]);
