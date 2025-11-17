<?php
/**
 * Add product category fields to products table
 * Categories: can_be_primary, can_be_secondary, can_be_additional
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Adding Product Category Fields ===\n\n";

try {
  // Step 1: Add category columns
  echo "Step 1: Adding category columns to products table...\n";
  $pdo->exec("
    ALTER TABLE products
    ADD COLUMN IF NOT EXISTS can_be_primary BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS can_be_secondary BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS can_be_additional BOOLEAN DEFAULT FALSE
  ");
  echo "  ✓ Category columns added\n\n";

  // Step 2: Update existing products based on Dressing Rule Matrix
  echo "Step 2: Updating product categories based on Dressing Rule Matrix...\n";

  // Products that can be PRIMARY dressings
  $primaryProducts = [
    'AlgiHeal' => ['Calcium Alginate'],
    'AlgiHeal AG' => ['Silver Alginate Dressing'],
    'CuraFoam' => ['Silicone Foam Dressing', 'Sacral Silicone Foam'],
    'HydraPad' => ['Super Absorbent'],
    'CollaHeal' => ['Collagen'],
    'Sacral' => ['Silicone Foam-Sacral']
  ];

  // Products that can be SECONDARY dressings
  $secondaryProducts = [
    'CuraFoam' => ['Silicone Foam Dressing', 'Sacral Silicone Foam'],
    'HydraPad' => ['Super Absorbent'],
    'Gauze' => ['Bordered Gauze'],
    'Sacral' => ['Silicone Foam-Sacral']
  ];

  // Products that can be ADDITIONAL supplies
  $additionalProducts = [
    'Gauze' => ['Bordered Gauze'],
    'Dermal' => ['Dermal Wound Cleanser'],
    'Arobella' => ['Disposable Tubing Set'],
    'NPWT' => ['Dressing Kit', 'Canister']
  ];

  // Set all products that match primary patterns
  foreach ($primaryProducts as $brand => $keywords) {
    foreach ($keywords as $keyword) {
      $updated = $pdo->exec("
        UPDATE products
        SET can_be_primary = TRUE
        WHERE name ILIKE '%{$keyword}%'
      ");
      if ($updated > 0) {
        echo "  ✓ Marked {$updated} '{$keyword}' product(s) as PRIMARY\n";
      }
    }
  }

  // Set all products that match secondary patterns
  foreach ($secondaryProducts as $brand => $keywords) {
    foreach ($keywords as $keyword) {
      $updated = $pdo->exec("
        UPDATE products
        SET can_be_secondary = TRUE
        WHERE name ILIKE '%{$keyword}%'
      ");
      if ($updated > 0) {
        echo "  ✓ Marked {$updated} '{$keyword}' product(s) as SECONDARY\n";
      }
    }
  }

  // Set all products that match additional patterns
  foreach ($additionalProducts as $brand => $keywords) {
    foreach ($keywords as $keyword) {
      $updated = $pdo->exec("
        UPDATE products
        SET can_be_additional = TRUE
        WHERE name ILIKE '%{$keyword}%' OR name ILIKE '%{$brand}%'
      ");
      if ($updated > 0) {
        echo "  ✓ Marked {$updated} '{$keyword}' product(s) as ADDITIONAL\n";
      }
    }
  }

  // Step 3: Show summary
  echo "\nStep 3: Summary of product categories...\n";
  $summary = $pdo->query("
    SELECT
      COUNT(*) FILTER (WHERE can_be_primary = TRUE) as primary_count,
      COUNT(*) FILTER (WHERE can_be_secondary = TRUE) as secondary_count,
      COUNT(*) FILTER (WHERE can_be_additional = TRUE) as additional_count,
      COUNT(*) as total_count
    FROM products
  ")->fetch();

  echo "  Primary products: {$summary['primary_count']}\n";
  echo "  Secondary products: {$summary['secondary_count']}\n";
  echo "  Additional supplies: {$summary['additional_count']}\n";
  echo "  Total products: {$summary['total_count']}\n\n";

  echo "=== Migration Complete ===\n";
  echo "✓ Product category fields added successfully\n";
  echo "✓ Products categorized based on Dressing Rule Matrix\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}
