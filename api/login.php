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

$stmt = $pdo->prepare("SELECT id, password_hash, first_name, last_name, email FROM users WHERE email=? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($pass, (string)$user['password_hash'])) {
  json_out(401, ['error'=>'Invalid credentials']);
}

// rotate session id
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];

// extend cookie if remember == true (7 days)
if ($remember) {
  $params = session_get_cookie_params();
  setcookie(session_name(), session_id(), [
    'expires'  => time() + 60*60*24*7,
    'path'     => $params['path'],
    'domain'   => $params['domain'],
    'secure'   => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite'] ?? 'Lax'
  ]);
}

json_out(200, ['ok'=>true, 'user'=>[
  'id'=>$user['id'],
  'email'=>$user['email'],
  'name'=>trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? ''))
]]);
