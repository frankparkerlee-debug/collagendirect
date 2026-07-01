<?php
/**
 * Migration: "actual pieces shipped" editor support.
 *  - orders.actual_pieces  : override the ordered/frequency-based piece count with
 *                            the real shipped count (calculate_order_revenue honors it).
 *  - order_piece_audit      : audit trail for those corrections (admin, before/after).
 * Both additive; NULL actual_pieces means "unchanged" for every existing order.
 */
require_once __DIR__ . '/db.php';

$pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS actual_pieces INTEGER");

$pdo->exec("
  CREATE TABLE IF NOT EXISTS order_piece_audit (
    id SERIAL PRIMARY KEY,
    order_id VARCHAR(64) NOT NULL,
    changed_by VARCHAR(64),
    old_pieces INTEGER, new_pieces INTEGER,
    old_revenue NUMERIC(12,2), new_revenue NUMERIC(12,2),
    old_amount_due NUMERIC(12,2), new_amount_due NUMERIC(12,2),
    reason TEXT,
    created_at TIMESTAMP DEFAULT NOW()
  )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_piece_audit_order ON order_piece_audit(order_id)");

echo "OK: orders.actual_pieces + order_piece_audit ready.\n";
