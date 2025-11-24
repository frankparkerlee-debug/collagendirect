<?php
/**
 * Migration: Add cost_per_box to practice_pricing table
 * Cost varies by practice, just like wholesale pricing does
 *
 * Access via: https://collagendirect.health/admin/migrate-add-cost-to-practice-pricing.php
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../api/db.php';

echo "=== Migration: Add cost_per_box to practice_pricing table ===\n\n";

try {
  // Check if column already exists in practice_pricing
  $checkStmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'practice_pricing'
    AND column_name = 'cost_per_box'
  ");

  if ($checkStmt->rowCount() > 0) {
    echo "✓ Column cost_per_box already exists in practice_pricing table\n";
  } else {
    echo "Adding cost_per_box column to practice_pricing table...\n";
    $pdo->exec("
      ALTER TABLE practice_pricing
      ADD COLUMN cost_per_box DECIMAL(10,2) DEFAULT NULL
    ");
    echo "✓ Successfully added cost_per_box to practice_pricing\n\n";
  }

  // Also add to products table as a fallback default
  $checkStmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'products'
    AND column_name = 'cost_per_box'
  ");

  if ($checkStmt->rowCount() > 0) {
    echo "✓ Column cost_per_box already exists in products table\n";
  } else {
    echo "Adding cost_per_box column to products table (as fallback)...\n";
    $pdo->exec("
      ALTER TABLE products
      ADD COLUMN cost_per_box DECIMAL(10,2) DEFAULT 0.00
    ");
    echo "✓ Successfully added cost_per_box to products\n\n";
  }

  // Show current practice pricing
  echo "Current practice pricing with cost:\n";
  echo str_repeat("-", 120) . "\n";
  printf("%-30s | %-35s | %12s | %12s | %12s\n", "Practice", "Product", "Custom Price", "Cost/Box", "Margin");
  echo str_repeat("-", 120) . "\n";

  $stmt = $pdo->query("
    SELECT
      u.practice_name,
      pr.name AS product_name,
      pp.custom_price,
      COALESCE(pp.cost_per_box, pr.cost_per_box, 0) AS cost_per_box
    FROM practice_pricing pp
    JOIN users u ON u.id = pp.user_id
    JOIN products pr ON pr.id = pp.product_id
    WHERE pp.custom_price IS NOT NULL
    ORDER BY u.practice_name, pr.name
  ");

  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $price = (float)$row['custom_price'];
    $cost = (float)$row['cost_per_box'];
    $margin = $cost > 0 ? (($price - $cost) / $price) * 100 : 0;

    printf(
      "%-30s | %-35s | %12.2f | %12.2f | %11.1f%%\n",
      substr($row['practice_name'], 0, 30),
      substr($row['product_name'], 0, 35),
      $price,
      $cost,
      $margin
    );
  }

  echo str_repeat("-", 120) . "\n\n";
  echo "✓ Migration complete!\n\n";
  echo "Next steps:\n";
  echo "1. Update cost_per_box for each practice in practice_pricing table\n";
  echo "2. Example: UPDATE practice_pricing SET cost_per_box = 75.00 WHERE user_id = 'USER_ID' AND product_id = 'PRODUCT_ID';\n";
  echo "3. Set default cost in products table: UPDATE products SET cost_per_box = 75.00 WHERE id = 'PRODUCT_ID';\n";
  echo "4. Revenue report will use practice-specific cost if available, otherwise fall back to product default\n";

} catch (PDOException $e) {
  echo "✗ Migration failed: " . $e->getMessage() . "\n";
  echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
