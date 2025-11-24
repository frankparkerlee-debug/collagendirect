<?php
/**
 * Migration: Add cost_per_box column to products table
 * This enables cost and profit tracking in revenue reports
 *
 * Access via: https://collagendirect.health/admin/migrate-add-cost-per-box.php
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../api/db.php';

echo "=== Migration: Add cost_per_box to products table ===\n\n";

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
    echo "\nNothing to do. Migration already applied.\n";
    exit(0);
  }

  // Add the column
  echo "Adding cost_per_box column to products table...\n";
  $pdo->exec("
    ALTER TABLE products
    ADD COLUMN cost_per_box DECIMAL(10,2) DEFAULT 0.00
  ");

  echo "✓ Successfully added cost_per_box column\n\n";

  // Show current products for reference
  echo "Current products (cost defaults to 0.00):\n";
  echo str_repeat("-", 100) . "\n";
  printf("%-50s | %10s | %12s | %12s\n", "Product Name", "Pieces/Box", "Wholesale $", "Cost $");
  echo str_repeat("-", 100) . "\n";

  $stmt = $pdo->query("
    SELECT id, name, pieces_per_box, price_wholesale, cost_per_box
    FROM products
    ORDER BY name
  ");

  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf(
      "%-50s | %10d | %12.2f | %12.2f\n",
      substr($row['name'], 0, 50),
      $row['pieces_per_box'],
      $row['price_wholesale'],
      $row['cost_per_box']
    );
  }

  echo str_repeat("-", 100) . "\n\n";
  echo "✓ Migration complete!\n\n";
  echo "Next steps:\n";
  echo "1. Update cost_per_box values for each product\n";
  echo "2. Example SQL: UPDATE products SET cost_per_box = 75.00 WHERE name = 'Product Name';\n";
  echo "3. Revenue reports will now show cost and profit calculations\n";

} catch (PDOException $e) {
  echo "✗ Migration failed: " . $e->getMessage() . "\n";
  echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
