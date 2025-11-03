<?php
/**
 * Database connection for CLI scripts (no session, no headers)
 */

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
  echo "ERROR: Database connection failed\n";
  echo "Details: " . $e->getMessage() . "\n";
  exit(1);
}
