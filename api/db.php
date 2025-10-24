<?php
// public/api/db.php
declare(strict_types=1);

// --- Security headers (basic) ---
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Content-Type-Options: nosniff');

// --- Session (for auth endpoints that include this file) ---
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() === PHP_SESSION_NONE) {
  // Configure session for 30 days persistence
  // Use longer lifetime to prevent frequent session expiration
  ini_set('session.gc_maxlifetime', (string)(60*60*24*30)); // 30 days
  ini_set('session.cookie_lifetime', (string)(60*60*24*30)); // 30 days
  ini_set('session.gc_probability', '1');
  ini_set('session.gc_divisor', '100');

  session_set_cookie_params([
    'lifetime' => 60*60*24*30, // 30 days
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();

  // Regenerate session ID periodically to prevent fixation attacks
  // But only if session is older than 1 hour
  if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
  } elseif (time() - $_SESSION['last_regeneration'] > 3600) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
  }
}

// --- Helpers used by other endpoints ---
function json_out(int $code, $data=null){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  if ($data !== null) echo json_encode($data);
  exit;
}
function require_csrf(){
  if ($_SERVER['REQUEST_METHOD'] === 'GET') return;
  $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $hdr)){
    json_out(419, ['error' => 'CSRF token invalid']);
  }
}
function uid(): string {
  return rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=');
}

// --- DB connection (PDO PostgreSQL) ---
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'collagen_db';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '5432';

try {
  $pdo = new PDO(
    "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};options='--client_encoding=UTF8'",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Throwable $e) {
  // When db.php is included, we don't want to echo. But if opened directly, show JSON.
  if (basename($_SERVER['SCRIPT_NAME']) === 'db.php') {
    json_out(500, ['error' => 'DB connection failed', 'detail' => $e->getMessage()]);
  }
  // If included, just stop.
  http_response_code(500);
  exit;
}

// Self-test if visited directly in browser
if (basename($_SERVER['SCRIPT_NAME']) === 'db.php') {
  json_out(200, ['success' => true, 'message' => 'Database connected OK']);
}
