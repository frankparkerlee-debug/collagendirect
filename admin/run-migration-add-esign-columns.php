<?php
/**
 * Add E-Signature Columns Migration
 * Adds the missing e-signature and ID card columns
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Add E-Signature Columns Migration ===\n\n";

try {
  $pdo->beginTransaction();

  echo "Step 1: Adding missing columns...\n\n";

  // Add e-signature columns
  echo "  Adding e_sign_user_id...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS e_sign_user_id VARCHAR(64)");

  echo "  Adding e_sign_name...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS e_sign_name VARCHAR(255)");

  echo "  Adding e_sign_title...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS e_sign_title VARCHAR(255)");

  echo "  Adding e_sign_at...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS e_sign_at TIMESTAMP");

  echo "  Adding e_sign_ip...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS e_sign_ip VARCHAR(45)");

  // Add ID card columns (to match naming convention)
  echo "  Adding id_card_path...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS id_card_path VARCHAR(255)");

  echo "  Adding id_card_mime...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS id_card_mime VARCHAR(100)");

  echo "\n";

  echo "Step 2: Migrating data from old columns...\n\n";

  // Copy sign_name to e_sign_name if it exists
  echo "  Copying sign_name to e_sign_name...\n";
  $pdo->exec("UPDATE orders SET e_sign_name = sign_name WHERE sign_name IS NOT NULL AND e_sign_name IS NULL");

  echo "  Copying sign_title to e_sign_title...\n";
  $pdo->exec("UPDATE orders SET e_sign_title = sign_title WHERE sign_title IS NOT NULL AND e_sign_title IS NULL");

  echo "  Copying signed_at to e_sign_at...\n";
  $pdo->exec("UPDATE orders SET e_sign_at = signed_at WHERE signed_at IS NOT NULL AND e_sign_at IS NULL");

  echo "  Copying patient_id_path to id_card_path...\n";
  $pdo->exec("UPDATE orders SET id_card_path = patient_id_path WHERE patient_id_path IS NOT NULL AND id_card_path IS NULL");

  echo "  Copying patient_id_mime to id_card_mime...\n";
  $pdo->exec("UPDATE orders SET id_card_mime = patient_id_mime WHERE patient_id_mime IS NOT NULL AND id_card_mime IS NULL");

  echo "\n";

  $pdo->commit();

  echo "=== Migration Complete ===\n";
  echo "✓ Added 7 missing columns\n";
  echo "✓ Migrated existing data to new columns\n";
  echo "✓ Order creation should now work correctly\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed:\n";
  echo "  Error: " . $e->getMessage() . "\n";
  exit(1);
}
