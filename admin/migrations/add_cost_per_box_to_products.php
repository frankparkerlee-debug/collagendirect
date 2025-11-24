<?php
/**
 * Migration: Add cost_per_box column to products table
 * This enables cost and profit tracking in revenue reports
 */

require_once __DIR__ . '/../../api/db.php';

echo "=== Adding cost_per_box to products table ===\n\n";

try {
  // Check if column already exists
  $checkStmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'products'
    AND column_name = 'cost_per_box'
  ");

  if ($checkStmt->rowCount() > 0) {
    echo "✓ Column cost_per_box already exists in products table\n";
    exit(0);
  }

  // Add the column
  echo "Adding cost_per_box column...\n";
  $pdo->exec("
    ALTER TABLE products
    ADD COLUMN cost_per_box DECIMAL(10,2) DEFAULT 0.00
  ");

  echo "✓ Successfully added cost_per_box column\n\n";

  // Show current products for reference
  echo "Current products:\n";
  echo str_repeat("-", 80) . "\n";

  $stmt = $pdo->query("
    SELECT id, name, pieces_per_box, price_wholesale, cost_per_box
    FROM products
    ORDER BY name
  ");

  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf(
      "%-40s | Pieces/Box: %2d | Wholesale: $%6.2f | Cost: $%6.2f\n",
      $row['name'],
      $row['pieces_per_box'],
      $row['price_wholesale'],
      $row['cost_per_box']
    );
  }

  echo "\n";
  echo "Migration complete! You can now update cost_per_box values in the products table.\n";
  echo "Example: UPDATE products SET cost_per_box = 75.00 WHERE name = 'Product Name';\n";

} catch (PDOException $e) {
  echo "✗ Migration failed: " . $e->getMessage() . "\n";
  exit(1);
}
