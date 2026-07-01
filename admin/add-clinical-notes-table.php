<?php
/**
 * Migration: clinical_notes — guided Medical-Necessity documentation (Phase 1 of the
 * MD DME clinical core). Deterministic notes generated from patient/wound/order data;
 * no AI. Schema is template-agnostic: `structured` (JSONB) snapshots the input fields
 * for whichever template was used, `body` holds the rendered/edited note text.
 * All additive.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

$pdo->exec("
  CREATE TABLE IF NOT EXISTS clinical_notes (
    id              VARCHAR(64) PRIMARY KEY,
    patient_id      VARCHAR(64) NOT NULL,
    user_id         VARCHAR(64) NOT NULL,          -- owning practice/physician account
    physician_name  VARCHAR(255),                  -- rendering / signing provider
    order_group_id  VARCHAR(64),                   -- optional link to a referral order group
    wound_index     INTEGER,                       -- optional specific wound in wounds_data
    note_type       VARCHAR(50)  DEFAULT 'wound_care',
    template_key    VARCHAR(80),                   -- which template produced it
    structured      JSONB,                         -- snapshot of the note's input fields
    body            TEXT,                          -- rendered note (editable before finalizing)
    status          VARCHAR(20)  DEFAULT 'draft',  -- draft | final
    signed_by       VARCHAR(255),
    signed_at       TIMESTAMP,
    created_by      VARCHAR(64),
    created_at      TIMESTAMP DEFAULT NOW(),
    updated_at      TIMESTAMP DEFAULT NOW()
  )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_clinical_notes_patient ON clinical_notes(patient_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_clinical_notes_user ON clinical_notes(user_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_clinical_notes_group ON clinical_notes(order_group_id)");

echo "OK: clinical_notes table ready.\n";
