<?php
/**
 * Migration: Add practice_pricing table for custom wholesale pricing
 *
 * Allows admins to give specific practices discounted/favorable pricing
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== ADDING PRACTICE_PRICING TABLE ===\n\n";

try {
  global $pdo;

  // Check if table already exists
  $stmt = $pdo->query("
    SELECT table_name
    FROM information_schema.tables
    WHERE table_name='practice_pricing'
  ");

  if ($stmt->rowCount() > 0) {
    echo "✓ Table 'practice_pricing' already exists.\n\n";
    exit(0);
  }

  // Create the table
  echo "Creating 'practice_pricing' table...\n";
  $pdo->exec("
    CREATE TABLE practice_pricing (
      id SERIAL PRIMARY KEY,
      user_id VARCHAR(64) NOT NULL,
      product_id INT NOT NULL,
      custom_price DECIMAL(10, 2) NOT NULL,
      discount_percentage DECIMAL(5, 2) DEFAULT NULL,
      notes TEXT DEFAULT NULL,
      created_at TIMESTAMP DEFAULT NOW(),
      updated_at TIMESTAMP DEFAULT NOW(),
      created_by VARCHAR(64) DEFAULT NULL,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
      UNIQUE(user_id, product_id)
    )
  ");

  echo "✓ Table created successfully.\n\n";

  // Create indexes
  echo "Creating indexes...\n";
  $pdo->exec("CREATE INDEX idx_practice_pricing_user ON practice_pricing(user_id)");
  $pdo->exec("CREATE INDEX idx_practice_pricing_product ON practice_pricing(product_id)");
  echo "✓ Indexes created.\n\n";

  echo "=== MIGRATION COMPLETE ===\n\n";
  echo "Practice-specific pricing is now available.\n";
  echo "\nUsage:\n";
  echo "- Set custom pricing for specific practices in admin panel\n";
  echo "- Prices will override default wholesale pricing\n";
  echo "- Track discount percentages for reporting\n";
  echo "- Notes field for admin reference\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
