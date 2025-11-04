<?php
/**
 * Migration: Add order lifecycle fields to orders table
 * This enables AI-assisted order editing and admin approval workflow
 */

require_once __DIR__ . '/../api/db.php';

echo "=== Adding Order Lifecycle Fields ===\n\n";

try {
  // Check if orders table exists
  $stmt = $pdo->query("
    SELECT COUNT(*) as cnt
    FROM information_schema.tables
    WHERE table_name = 'orders'
  ");

  if ((int)$stmt->fetchColumn() === 0) {
    echo "✗ Orders table does not exist. Cannot proceed.\n";
    exit(1);
  }

  echo "✓ Orders table found\n\n";

  // Add review_status column
  echo "Adding review_status column...\n";
  try {
    $pdo->exec("
      ALTER TABLE orders
      ADD COLUMN IF NOT EXISTS review_status VARCHAR(50) DEFAULT 'draft'
    ");
    echo "✓ review_status column added\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "  Column already exists, skipping\n";
    } else {
      throw $e;
    }
  }

  // Add ai_suggestions column
  echo "Adding ai_suggestions column...\n";
  try {
    $pdo->exec("
      ALTER TABLE orders
      ADD COLUMN IF NOT EXISTS ai_suggestions JSONB
    ");
    echo "✓ ai_suggestions column added\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "  Column already exists, skipping\n";
    } else {
      throw $e;
    }
  }

  // Add ai_suggestions_accepted column
  echo "Adding ai_suggestions_accepted column...\n";
  try {
    $pdo->exec("
      ALTER TABLE orders
      ADD COLUMN IF NOT EXISTS ai_suggestions_accepted BOOLEAN DEFAULT FALSE
    ");
    echo "✓ ai_suggestions_accepted column added\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "  Column already exists, skipping\n";
    } else {
      throw $e;
    }
  }

  // Add ai_suggestions_accepted_at column
  echo "Adding ai_suggestions_accepted_at column...\n";
  try {
    $pdo->exec("
      ALTER TABLE orders
      ADD COLUMN IF NOT EXISTS ai_suggestions_accepted_at TIMESTAMP
    ");
    echo "✓ ai_suggestions_accepted_at column added\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "  Column already exists, skipping\n";
    } else {
      throw $e;
    }
  }

  // Add locked_at column
  echo "Adding locked_at column...\n";
  try {
    $pdo->exec("
      ALTER TABLE orders
      ADD COLUMN IF NOT EXISTS locked_at TIMESTAMP
    ");
    echo "✓ locked_at column added\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "  Column already exists, skipping\n";
    } else {
      throw $e;
    }
  }

  // Add locked_by column
  echo "Adding locked_by column...\n";
  try {
    $pdo->exec("
      ALTER TABLE orders
      ADD COLUMN IF NOT EXISTS locked_by VARCHAR(32)
    ");
    echo "✓ locked_by column added\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "  Column already exists, skipping\n";
    } else {
      throw $e;
    }
  }

  // Add reviewed_by column
  echo "Adding reviewed_by column...\n";
  try {
    $pdo->exec("
      ALTER TABLE orders
      ADD COLUMN IF NOT EXISTS reviewed_by VARCHAR(32)
    ");
    echo "✓ reviewed_by column added\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "  Column already exists, skipping\n";
    } else {
      throw $e;
    }
  }

  // Add reviewed_at column
  echo "Adding reviewed_at column...\n";
  try {
    $pdo->exec("
      ALTER TABLE orders
      ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP
    ");
    echo "✓ reviewed_at column added\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "  Column already exists, skipping\n";
    } else {
      throw $e;
    }
  }

  // Add review_notes column
  echo "Adding review_notes column...\n";
  try {
    $pdo->exec("
      ALTER TABLE orders
      ADD COLUMN IF NOT EXISTS review_notes TEXT
    ");
    echo "✓ review_notes column added\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "  Column already exists, skipping\n";
    } else {
      throw $e;
    }
  }

  // Add index on review_status for faster queries
  echo "\nAdding indexes...\n";
  try {
    $pdo->exec("
      CREATE INDEX IF NOT EXISTS idx_orders_review_status
      ON orders(review_status)
    ");
    echo "✓ Index on review_status created\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "  Index already exists, skipping\n";
    } else {
      throw $e;
    }
  }

  // Update existing orders to have proper review_status
  echo "\nUpdating existing orders...\n";
  $updated = $pdo->exec("
    UPDATE orders
    SET review_status = 'approved'
    WHERE review_status = 'draft'
      AND created_at < NOW() - INTERVAL '1 day'
  ");
  echo "✓ Updated $updated existing orders to 'approved' status\n";

  echo "\n=== Migration Complete ===\n";
  echo "All order lifecycle fields have been added successfully.\n";

} catch (Exception $e) {
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
