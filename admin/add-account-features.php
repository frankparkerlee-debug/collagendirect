<?php
/**
 * Migration: per-account feature entitlements for the modular MD DME / IWC portal.
 * Adds users.features (JSONB) and seeds existing practice accounts from their
 * current effective access so nothing changes for anyone on day one.
 */
require_once __DIR__ . '/db.php';
$pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS features JSONB");
$pdo->exec("
  UPDATE users SET features = jsonb_build_object(
    'photo_reviews', true,
    'patient_referral', true,
    'healkit', true,
    'wholesale', (COALESCE(has_dme_license,false) OR COALESCE(is_hybrid,false)
                  OR account_type IN ('wholesale','dme_hybrid','both'))
  )
  WHERE features IS NULL AND role IN ('physician','practice_admin')
");
echo "OK: users.features added + seeded.\n";
