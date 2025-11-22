<?php
/**
 * Quick fix: Create practice_physicians table if it doesn't exist
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_admin();

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Fix practice_physicians Table</h1>";
echo "<p>Checking and creating practice_physicians table if needed...</p>";

try {
  $pdo->beginTransaction();

  // Check if table exists
  $tableExists = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'practice_physicians'
    )
  ")->fetchColumn();

  if (!$tableExists) {
    echo "<p style='color: orange;'>⚠️ Table does not exist. Creating now...</p>";

    // Create practice_physicians table
    $pdo->exec("
      CREATE TABLE practice_physicians (
        id SERIAL PRIMARY KEY,
        practice_user_id VARCHAR(32) NOT NULL,
        physician_name VARCHAR(255) NOT NULL,
        npi VARCHAR(20),
        license_number VARCHAR(50),
        address TEXT,
        city VARCHAR(100),
        state VARCHAR(50),
        zip VARCHAR(20),
        phone VARCHAR(50),
        signature_text TEXT,
        signature_image_path TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (practice_user_id) REFERENCES users(id) ON DELETE CASCADE
      )
    ");

    echo "<p style='color: green;'>✓ Created practice_physicians table</p>";

    $pdo->exec("
      CREATE INDEX idx_practice_physicians_practice ON practice_physicians(practice_user_id)
    ");

    echo "<p style='color: green;'>✓ Created index on practice_user_id</p>";

    $pdo->exec("
      CREATE INDEX idx_practice_physicians_active ON practice_physicians(practice_user_id, is_active)
    ");

    echo "<p style='color: green;'>✓ Created composite index on practice_user_id and is_active</p>";

  } else {
    echo "<p style='color: green;'>✓ Table practice_physicians already exists</p>";
  }

  // Add physician_id to orders table if it doesn't exist
  $hasPhysicianId = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = 'physician_id'
  ")->fetchColumn();

  if (!$hasPhysicianId) {
    echo "<p style='color: orange;'>⚠️ Adding physician_id column to orders table...</p>";

    $pdo->exec("
      ALTER TABLE orders
      ADD COLUMN physician_id INTEGER,
      ADD CONSTRAINT fk_orders_physician
        FOREIGN KEY (physician_id)
        REFERENCES practice_physicians(id)
        ON DELETE SET NULL
    ");

    $pdo->exec("
      CREATE INDEX idx_orders_physician ON orders(physician_id)
    ");

    echo "<p style='color: green;'>✓ Added physician_id column to orders table</p>";
  } else {
    echo "<p style='color: green;'>✓ Column physician_id already exists in orders table</p>";
  }

  $pdo->commit();

  echo "<hr>";
  echo "<h2 style='color: green;'>✓ Success!</h2>";
  echo "<p>The practice_physicians table is now ready to use.</p>";
  echo "<p><a href='/portal/?page=physicians' style='background: #3b82f6; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block;'>Go to Physician Roster →</a></p>";

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "<p style='color: red; background: #fee; padding: 1rem; border: 1px solid red; border-radius: 6px;'>";
  echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
  echo "</p>";
  echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
