<?php
/**
 * Migration: Create order_revisions table
 * This table tracks all changes made to orders for audit purposes
 */

require_once __DIR__ . '/../api/db.php';

echo "=== Creating Order Revisions Table ===\n\n";

try {
  // Check if table already exists
  $stmt = $pdo->query("
    SELECT COUNT(*) as cnt
    FROM information_schema.tables
    WHERE table_name = 'order_revisions'
  ");

  if ((int)$stmt->fetchColumn() > 0) {
    echo "✓ order_revisions table already exists\n";
    exit(0);
  }

  // Create the table
  echo "Creating order_revisions table...\n";
  $pdo->exec("
    CREATE TABLE order_revisions (
      id SERIAL PRIMARY KEY,
      order_id VARCHAR(32) NOT NULL,
      changed_by VARCHAR(32) NOT NULL,
      changed_at TIMESTAMP DEFAULT NOW(),
      changes JSONB NOT NULL,
      reason TEXT,
      ai_suggested BOOLEAN DEFAULT FALSE,
      CONSTRAINT fk_order_revisions_order
        FOREIGN KEY (order_id)
        REFERENCES orders(id)
        ON DELETE CASCADE,
      CONSTRAINT fk_order_revisions_user
        FOREIGN KEY (changed_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
    )
  ");
  echo "✓ order_revisions table created\n\n";

  // Add indexes for common queries
  echo "Adding indexes...\n";

  $pdo->exec("
    CREATE INDEX idx_order_revisions_order_id
    ON order_revisions(order_id)
  ");
  echo "✓ Index on order_id created\n";

  $pdo->exec("
    CREATE INDEX idx_order_revisions_changed_by
    ON order_revisions(changed_by)
  ");
  echo "✓ Index on changed_by created\n";

  $pdo->exec("
    CREATE INDEX idx_order_revisions_changed_at
    ON order_revisions(changed_at DESC)
  ");
  echo "✓ Index on changed_at created\n";

  echo "\n=== Migration Complete ===\n";
  echo "Order revisions table has been created successfully.\n";
  echo "\nTable structure:\n";
  echo "  - id: Auto-incrementing primary key\n";
  echo "  - order_id: Reference to orders table\n";
  echo "  - changed_by: Reference to users table\n";
  echo "  - changed_at: Timestamp of change\n";
  echo "  - changes: JSONB field storing before/after values\n";
  echo "  - reason: Optional text explanation for the change\n";
  echo "  - ai_suggested: Boolean indicating if change came from AI\n";

} catch (Exception $e) {
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
