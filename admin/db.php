<?php
// /public/admin/db.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* Database credentials */
$DB_HOST = 'localhost';
$DB_NAME = 'frxnaisp_collagendirect';
$DB_USER = 'frxnaisp_collagendirect';
$DB_PASS = 'YEW!ad10jeo';

try {
  $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
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
