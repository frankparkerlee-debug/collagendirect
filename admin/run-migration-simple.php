<?php
/**
 * Simple Order Groups Migration - No Foreign Key
 * Creates table and column without foreign key constraint
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Simple Order Groups Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // Step 1: Create order_groups table
  echo "Step 1: Creating order_groups table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS order_groups (
      id VARCHAR(64) PRIMARY KEY,
      user_id VARCHAR(64) NOT NULL,
      patient_id VARCHAR(64) NOT NULL,
      visit_note_path VARCHAR(255),
      visit_note_mime VARCHAR(100),
      baseline_wound_photo_path VARCHAR(255),
      baseline_wound_photo_mime VARCHAR(100),
      wound_location VARCHAR(120),
      wound_laterality VARCHAR(30),
      wound_type VARCHAR(50),
      wound_stage VARCHAR(20),
      wound_length_cm DECIMAL(6,2),
      wound_width_cm DECIMAL(6,2),
      wound_depth_cm DECIMAL(6,2),
      wound_notes TEXT,
      icd10_primary VARCHAR(10),
      icd10_secondary VARCHAR(10),
      last_eval_date DATE,
      start_date DATE,
      shipping_name VARCHAR(255),
      shipping_address VARCHAR(255),
      shipping_city VARCHAR(120),
      shipping_state VARCHAR(10),
      shipping_zip VARCHAR(15),
      shipping_phone VARCHAR(50),
      insurer_name VARCHAR(255),
      member_id VARCHAR(100),
      group_id VARCHAR(100),
      payer_phone VARCHAR(50),
      prior_auth VARCHAR(100),
      payment_type VARCHAR(20) DEFAULT 'insurance',
      status VARCHAR(40) DEFAULT 'submitted',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      sign_name VARCHAR(255),
      sign_title VARCHAR(255),
      signed_at TIMESTAMP,
      signed_ip VARCHAR(45),
      additional_instructions TEXT,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
      FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT
    )
  ");
  echo "  ✓ order_groups table created\n\n";

  // Step 2: Add indexes
  echo "Step 2: Adding indexes...\n";
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_groups_user ON order_groups(user_id)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_groups_patient ON order_groups(patient_id)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_groups_created ON order_groups(created_at DESC)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_groups_status ON order_groups(status)");
  echo "  ✓ Indexes created\n\n";

  // Step 3: Add order_group_id column to orders
  echo "Step 3: Adding order_group_id column...\n";
  $pdo->exec("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS order_group_id VARCHAR(64) DEFAULT NULL
  ");
  echo "  ✓ Column added\n\n";

  // Step 4: Clear any orphaned values
  echo "Step 4: Clearing orphaned order_group_id values...\n";
  $pdo->exec("UPDATE orders SET order_group_id = NULL WHERE order_group_id IS NOT NULL");
  echo "  ✓ Values cleared\n\n";

  $pdo->commit();

  echo "=== Migration Complete ===\n";
  echo "✓ Ready to add foreign key constraint\n";
  echo "✓ Run fix-order-groups-migration.php next\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed:\n";
  echo "  Error: " . $e->getMessage() . "\n";
  exit(1);
}
