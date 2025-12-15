<?php
/**
 * CLI-safe database connection for migrations
 * Does not start sessions or send headers
 */

declare(strict_types=1);

// Load environment from .env if available
$envFile = __DIR__ . '/../../../.env';
if (file_exists($envFile)) {
  $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos($line, '#') === 0) continue;
    if (strpos($line, '=') === false) continue;
    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value, " \t\n\r\0\x0B\"'");
    if (!getenv($key)) {
      putenv("$key=$value");
    }
  }
}

// Database connection parameters
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_PORT = getenv('DB_PORT') ?: '5432';
$DB_NAME = getenv('DB_NAME') ?: 'collagendirect';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: '';

try {
  $pdo = new PDO(
    "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false
    ]
  );
} catch (PDOException $e) {
  echo "Database connection failed: " . $e->getMessage() . "\n";
  exit(1);
}
