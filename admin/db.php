<?php
// /public/admin/db.php
declare(strict_types=1);

// Configure session for 7 days persistence
if (session_status() !== PHP_SESSION_ACTIVE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

  ini_set('session.gc_maxlifetime', (string)(60*60*24*7)); // 7 days
  ini_set('session.cookie_lifetime', (string)(60*60*24*7)); // 7 days

  session_set_cookie_params([
    'lifetime' => 60*60*24*7, // 7 days
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

/* Database credentials - Use environment variables for production */
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'collagen_db';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '5432';

try {
  $dsn = "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};options='--client_encoding=UTF8'";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo "db_connect_failed";
  exit;
}

/* ===== Helpers (guarded to prevent redeclare fatals) ===== */
if (!function_exists('e')) {
  function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

if (!function_exists('csrf_field')) {
  function csrf_field(): string {
    $t = $_SESSION['csrf'] ?? '';
    return '<input type="hidden" name="csrf" value="'.e($t).'">';
  }
}

if (!function_exists('verify_csrf')) {
  function verify_csrf(): void {
    if (empty($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
      http_response_code(400); echo "bad_csrf"; exit;
    }
  }
}
