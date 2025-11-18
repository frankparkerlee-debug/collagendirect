<?php
/**
 * Migration: Add order_groups table for multi-product orders
 *
 * This migration creates the order_groups table to support grouping multiple
 * products into a single order (e.g., treating one wound with multiple products).
 *
 * Benefits:
 * - Single visit note and baseline photo per wound treatment
 * - Better UX: one order group instead of multiple separate orders
 * - Easier invoicing and fulfillment tracking
 * - Backward compatible: existing single-product orders remain unchanged
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Order Groups Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // Step 1: Create order_groups table
  echo "Step 1: Creating order_groups table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS order_groups (
      id VARCHAR(64) PRIMARY KEY,
      user_id VARCHAR(64) NOT NULL,
      patient_id VARCHAR(64) NOT NULL,

      -- Shared metadata across all products in this order
      visit_note_path VARCHAR(255),
      visit_note_mime VARCHAR(100),
      baseline_wound_photo_path VARCHAR(255),
      baseline_wound_photo_mime VARCHAR(100),

      -- Wound details (shared across products)
      wound_location VARCHAR(120),
      wound_laterality VARCHAR(30),
      wound_type VARCHAR(50),
      wound_stage VARCHAR(20),
      wound_length_cm DECIMAL(6,2),
      wound_width_cm DECIMAL(6,2),
      wound_depth_cm DECIMAL(6,2),
      wound_notes TEXT,

      -- Clinical details
      icd10_primary VARCHAR(10),
      icd10_secondary VARCHAR(10),
      last_eval_date DATE,
      start_date DATE,

      -- Shipping (shared across all products in group)
      shipping_name VARCHAR(255),
      shipping_address VARCHAR(255),
      shipping_city VARCHAR(120),
      shipping_state VARCHAR(10),
      shipping_zip VARCHAR(15),
      shipping_phone VARCHAR(50),

      -- Insurance (shared)
      insurer_name VARCHAR(255),
      member_id VARCHAR(100),
      group_id VARCHAR(100),
      payer_phone VARCHAR(50),
      prior_auth VARCHAR(100),
      payment_type VARCHAR(20) DEFAULT 'insurance',

      -- Status tracking
      status VARCHAR(40) DEFAULT 'submitted',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

      -- E-signature (applies to entire group)
      sign_name VARCHAR(255),
      sign_title VARCHAR(255),
      signed_at TIMESTAMP,
      signed_ip VARCHAR(45),

      -- Additional instructions for entire order
      additional_instructions TEXT,

      -- Foreign keys
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
      FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT
    )
  ");
  echo "  ✓ order_groups table created\n\n";

  // Step 2: Add indexes for performance
  echo "Step 2: Adding indexes...\n";
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_groups_user ON order_groups(user_id)");
  echo "  ✓ Index on user_id created\n";

  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_groups_patient ON order_groups(patient_id)");
  echo "  ✓ Index on patient_id created\n";

  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_groups_created ON order_groups(created_at DESC)");
  echo "  ✓ Index on created_at created\n";

  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_groups_status ON order_groups(status)");
  echo "  ✓ Index on status created\n\n";

  // Step 3: Add order_group_id column to orders table
  echo "Step 3: Adding order_group_id to orders table...\n";
  $pdo->exec("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS order_group_id VARCHAR(64) DEFAULT NULL
  ");
  echo "  ✓ order_group_id column added\n\n";

  // Step 4: Add foreign key constraint
  echo "Step 4: Adding foreign key constraint...\n";
  $pdo->exec("
    DO $$
    BEGIN
      IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_orders_group'
      ) THEN
        ALTER TABLE orders
        ADD CONSTRAINT fk_orders_group
        FOREIGN KEY (order_group_id)
        REFERENCES order_groups(id)
        ON DELETE CASCADE;
      END IF;
    END $$;
  ");
  echo "  ✓ Foreign key constraint added\n\n";

  // Step 5: Add index on order_group_id for fast lookups
  echo "Step 5: Adding index on order_group_id...\n";
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_group ON orders(order_group_id)");
  echo "  ✓ Index created\n\n";

  // Step 6: Add column comments for documentation
  echo "Step 6: Adding column comments...\n";
  $pdo->exec("
    COMMENT ON TABLE order_groups IS 'Groups multiple products into a single order for treating one wound with multiple products';
  ");
  $pdo->exec("
    COMMENT ON COLUMN order_groups.visit_note_path IS 'Visit note applies to entire order group (all products)';
  ");
  $pdo->exec("
    COMMENT ON COLUMN order_groups.baseline_wound_photo_path IS 'Baseline photo for the wound being treated with all products in this group';
  ");
  $pdo->exec("
    COMMENT ON COLUMN orders.order_group_id IS 'NULL for single-product orders, set for multi-product grouped orders';
  ");
  echo "  ✓ Comments added\n\n";

  $pdo->commit();

  echo "=== Migration Complete ===\n";
  echo "✓ order_groups table created with all indexes\n";
  echo "✓ orders.order_group_id column added\n";
  echo "✓ Foreign key constraints established\n";
  echo "✓ System is backward compatible:\n";
  echo "  - Existing orders remain ungrouped (order_group_id = NULL)\n";
  echo "  - New single-product orders work as before\n";
  echo "  - Multi-product orders now group properly\n\n";

  echo "Next Steps:\n";
  echo "1. Update api/portal/orders.create.php to create order groups\n";
  echo "2. Update portal order list UI to display grouped orders\n";
  echo "3. Update admin order management to show groups\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed:\n";
  echo "  Error: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}
