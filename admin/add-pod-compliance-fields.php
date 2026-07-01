<?php
/**
 * Migration: Medicare-compliant Proof of Delivery fields on delivery_confirmations.
 * Captures drawn signature + typed name, designee, quantity confirmation, patient-attested
 * receipt date, attestation version, and the rendered POD document URL. All additive.
 */
require_once __DIR__ . '/db.php';
$pdo->exec("ALTER TABLE delivery_confirmations
  ADD COLUMN IF NOT EXISTS pod_signature_image TEXT,
  ADD COLUMN IF NOT EXISTS pod_signature_typed VARCHAR(255),
  ADD COLUMN IF NOT EXISTS pod_signed_by VARCHAR(20),
  ADD COLUMN IF NOT EXISTS pod_designee_name VARCHAR(255),
  ADD COLUMN IF NOT EXISTS pod_designee_relationship VARCHAR(120),
  ADD COLUMN IF NOT EXISTS pod_quantity_confirmed INTEGER,
  ADD COLUMN IF NOT EXISTS pod_quantity_correct BOOLEAN,
  ADD COLUMN IF NOT EXISTS pod_date_received DATE,
  ADD COLUMN IF NOT EXISTS pod_signed_at TIMESTAMP,
  ADD COLUMN IF NOT EXISTS pod_document_path VARCHAR(255),
  ADD COLUMN IF NOT EXISTS pod_attestation_version VARCHAR(20)");
echo "OK: POD compliance fields ready.\n";
