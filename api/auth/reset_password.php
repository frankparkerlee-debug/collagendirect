<?php
/**
 * POST /public/api/auth/reset_password.php
 * Body: { "selector": "...", "token": "...", "password": "..." }
 *
 * Verifies selector+token against password_resets, checks expiry (SQL-side) and single-use,
 * updates the user's password securely, and consumes the token.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);

require __DIR__ . '/../db.php';        // provides $pdo
require __DIR__ . '/../lib/env.php';   // if needed later

if (!function_exists('json_out')) {
  function json_out(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
  }
}

// --- Read input
$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true) ?: [];
$selector = isset($in['selector']) ? trim((string)$in['selector']) : '';
$tokenB64 = isset($in['token'])    ? trim((string)$in['token'])    : '';
$newPass  = (string)($in['password'] ?? '');

if ($selector === '' || $tokenB64 === '' || $newPass === '') {
  json_out(400, ['ok'=>false, 'error'=>'Missing required fields']);
}

// --- Password strength: 8+ chars (upper, lower, number, symbol)
$strong = (strlen($newPass) >= 8)
       && preg_match('/[a-z]/', $newPass)
       && preg_match('/[A-Z]/', $newPass)
       && preg_match('/\d/',    $newPass)
       && preg_match('/[^A-Za-z0-9]/', $newPass);
if (!$strong) json_out(400, ['ok'=>false, 'error'=>'Password is not strong enough']);

// --- Base64url decode token from link
function b64url_decode_str(string $s): string {
  $p = strtr($s, '-_', '+/');
  $p .= str_repeat('=', (4 - strlen($p) % 4) % 4);
  $bin = base64_decode($p, true);
  return $bin === false ? '' : $bin;
}
$tokenRaw = b64url_decode_str($tokenB64);
if ($tokenRaw === '') json_out(400, ['ok'=>false, 'error'=>'Invalid token']);

// --- Look up a still-valid token (SQL checks expiry & consumed flags)
$st = $pdo->prepare("
  SELECT id, user_id, email, token_hash
    FROM password_resets
   WHERE selector = ?
     AND consumed_at IS NULL
     AND expires_at > NOW()
   ORDER BY id DESC
   LIMIT 1
");
$st->execute([$selector]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  json_out(400, ['ok'=>false, 'error'=>'This link is invalid or has expired.']);
}

// --- Verify token (constant-time compare)
$calc = hash('sha256', $tokenRaw, true);
if (!hash_equals($row['token_hash'], $calc)) {
  json_out(400, ['ok'=>false, 'error'=>'Invalid token. Please request a new reset email.']);
}

// --- Fetch the user (by id if present; else by email)
$user = null;
if (!empty($row['user_id'])) {
  $u = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
  $u->execute([$row['user_id']]);
  $user = $u->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$user) {
  $u = $pdo->prepare("SELECT id, email FROM users WHERE LOWER(email) = LOWER(?)");
  $u->execute([$row['email']]);
  $user = $u->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$user) {
  // Safeguard: if user vanished, consume token to avoid looping
  $pdo->prepare("UPDATE password_resets SET consumed_at = NOW() WHERE id = ?")->execute([$row['id']]);
  json_out(400, ['ok'=>false, 'error'=>'Account not found. Contact support.']);
}

// --- Hash new password (Argon2id preferred; else Bcrypt)
if (defined('PASSWORD_ARGON2ID')) {
  $hash = password_hash($newPass, PASSWORD_ARGON2ID);
} else {
  $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost'=>12]);
}
if ($hash === false) json_out(500, ['ok'=>false, 'error'=>'Failed to hash password']);

// --- Update password + consume token atomically; invalidate other outstanding tokens
try {
  $pdo->beginTransaction();

  $pdo->prepare("
    UPDATE users
       SET password_hash = ?, password_updated_at = NOW(), updated_at = NOW()
     WHERE id = ?
  ")->execute([$hash, $user['id']]);

  $pdo->prepare("UPDATE password_resets SET consumed_at = NOW() WHERE id = ?")
      ->execute([$row['id']]);

  $pdo->prepare("
    UPDATE password_resets
       SET consumed_at = NOW()
     WHERE email = ?
       AND consumed_at IS NULL
       AND expires_at > NOW()
  ")->execute([$row['email']]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('reset_password tx failed: '.$e->getMessage());
  json_out(500, ['ok'=>false, 'error'=>'Could not update password']);
}

// --- Success
json_out(200, ['ok'=>true]);
