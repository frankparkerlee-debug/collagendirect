<?php
/**
 * Migration: Add practice address fields to users table
 *
 * These fields are needed for shipping orders to doctor's office
 */

require_once __DIR__ . '/../api/db.php';

try {
  echo "<h1>Adding practice address fields to users table</h1>\n";

  // Add practice address fields
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS practice_address TEXT");
  echo "<p>✓ Added practice_address column</p>\n";

  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS practice_city VARCHAR(100)");
  echo "<p>✓ Added practice_city column</p>\n";

  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS practice_state VARCHAR(2)");
  echo "<p>✓ Added practice_state column</p>\n";

  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS practice_zip VARCHAR(10)");
  echo "<p>✓ Added practice_zip column</p>\n";

  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS practice_phone VARCHAR(20)");
  echo "<p>✓ Added practice_phone column</p>\n";

  echo "<h2>Migration completed successfully!</h2>\n";
  echo "<p>Practice address fields have been added to the users table.</p>\n";

} catch (PDOException $e) {
  echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
  exit(1);
}
