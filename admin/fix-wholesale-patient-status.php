<?php
/**
 * Fix existing wholesale order patients to have state = 'approved'
 * Wholesale patients don't require pre-authorization approval
 */

require_once __DIR__ . '/db.php';

try {
  // Find all patients from wholesale orders that don't have state = 'approved'
  $sql = "
    UPDATE patients p
    SET state = 'approved', updated_at = NOW()
    WHERE p.id IN (
      SELECT DISTINCT o.patient_id
      FROM orders o
      WHERE o.billed_by = 'practice_dme'
        AND o.payment_type = 'wholesale'
    )
    AND (p.state IS NULL OR p.state != 'approved')
  ";

  $result = $pdo->exec($sql);

  echo "✓ Updated {$result} wholesale order patient(s) to 'approved' status\n";
  echo "✓ Wholesale orders can now be approved without patient pre-authorization\n";

} catch (Throwable $e) {
  echo "Error: " . $e->getMessage() . "\n";
  exit(1);
}
