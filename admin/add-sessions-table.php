<?php
/**
 * Migration: sessions table for the Postgres-backed session handler
 * (api/lib/pg_session_handler.php). Additive; must exist before the
 * auto_prepend session handler goes live. Safe to run repeatedly.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

$pdo->exec("
  CREATE TABLE IF NOT EXISTS sessions (
    id         VARCHAR(128) PRIMARY KEY,
    data       TEXT NOT NULL DEFAULT '',
    expires    BIGINT NOT NULL,
    updated_at TIMESTAMP DEFAULT NOW()
  )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires)");

echo "OK: sessions table ready.\n";
