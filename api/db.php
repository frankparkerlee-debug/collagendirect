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
  session_set_cookie_params([
    'lifetime' => 60*60*12,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
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

// --- DB connection (PDO MySQL) ---
$DB_HOST = 'localhost';
$DB_NAME = 'frxnaisp_collagendirect';
$DB_USER = 'frxnaisp_collagendirect';
$DB_PASS = 'YEW!ad10jeo';

try {
  $pdo = new PDO(
    "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
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
