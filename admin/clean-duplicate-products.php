<?php
/**
 * Clean up duplicate products to restore correct 29-product catalog
 *
 * Keeps: Products with HCPCS codes (newer versions) + 6 Hydrapad products
 * Removes: Old products without HCPCS codes (duplicates)
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

echo "=== CLEANING DUPLICATE PRODUCTS ===\n\n";

// Products to deactivate (old versions without HCPCS in name)
$productsToDeactivate = [
  // Old Calcium Alginate products (without HCPCS codes in name)
  1,  // Calcium Alginate 2x2
  2,  // Calcium Alginate 4.33x4.33
  3,  // Calcium Alginate 6x6

  // Old Silver Alginate products
  5,  // Silver Alginate 2x2
  6,  // Silver Alginate 4.33x4.33
  7,  // Silver Alginate 6x6

  // Old Silicone Foam products
  8,  // Silicone Foam 2x2
  9,  // Silicone Foam 4.13x4.13
  10, // Silicone Foam 6x6

  // Old Super Absorb products (duplicates)
  12, // Super Absorb 2x2 (Non-adherent - MD02022SAN)
  13, // Super Absorb 4.13x4.13 (Non-adherent - MD04045AN)
  14, // Super Absorb 8x8 (Non-adherent - MD08088SAN)
  15, // Super Absorb 2x2 (Adherent - MD02022SAA)
  16, // Super Absorb 4.13x4.13 (Adherent - MD04045AA)
  17, // Super Absorb 8x8 (Adherent - MD08088SAA)

  // Old Collagen products
  18, // Collagen Drx 2x2
  19, // Collagen Drx 7x7
];

echo "Products to deactivate: " . count($productsToDeactivate) . "\n\n";

// Start transaction
$pdo->beginTransaction();

try {
  // Get product names before deactivating
  $placeholders = implode(',', array_fill(0, count($productsToDeactivate), '?'));
  $stmt = $pdo->prepare("
    SELECT id, name, sku, hcpcs_code, active
    FROM products
    WHERE id IN ($placeholders)
    ORDER BY id
  ");
  $stmt->execute($productsToDeactivate);
  $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "PRODUCTS BEING DEACTIVATED:\n";
  echo str_repeat("-", 100) . "\n";
  foreach ($products as $p) {
    $status = $p['active'] ? 'ACTIVE' : 'INACTIVE';
    echo sprintf("ID: %-3d | %-40s | %-12s | HCPCS: %-8s | %s\n",
      $p['id'],
      substr($p['name'], 0, 40),
      $p['sku'] ?? 'N/A',
      $p['hcpcs_code'] ?? 'N/A',
      $status
    );
  }
  echo str_repeat("-", 100) . "\n\n";

  // Deactivate the products
  $stmt = $pdo->prepare("
    UPDATE products
    SET active = FALSE
    WHERE id IN ($placeholders)
  ");
  $stmt->execute($productsToDeactivate);

  $affectedRows = $stmt->rowCount();

  // Get final counts
  $totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
  $activeProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE active = TRUE")->fetchColumn();

  echo "✓ Deactivated {$affectedRows} duplicate products\n";
  echo "✓ Total products in database: {$totalProducts}\n";
  echo "✓ Active products: {$activeProducts}\n\n";

  if ($activeProducts == 29) {
    echo "✓ SUCCESS: Product catalog restored to 29 active products\n\n";
  } else {
    echo "⚠ WARNING: Expected 29 active products, got {$activeProducts}\n\n";
  }

  // Show remaining active products by category
  echo str_repeat("=", 100) . "\n";
  echo "REMAINING ACTIVE PRODUCTS (should be 29):\n";
  echo str_repeat("=", 100) . "\n";

  $activeList = $pdo->query("
    SELECT id, name, sku, hcpcs_code, category
    FROM products
    WHERE active = TRUE
    ORDER BY category, name
  ")->fetchAll(PDO::FETCH_ASSOC);

  $currentCategory = '';
  foreach ($activeList as $p) {
    $cat = $p['category'] ?? 'Uncategorized';
    if ($cat !== $currentCategory) {
      echo "\n" . strtoupper($cat) . ":\n";
      $currentCategory = $cat;
    }
    echo sprintf("  %-3d | %-50s | %-12s | %s\n",
      $p['id'],
      $p['name'],
      $p['sku'] ?? 'N/A',
      $p['hcpcs_code'] ?? 'N/A'
    );
  }

  $pdo->commit();

  echo "\n\n✓ DATABASE CLEANUP COMPLETE\n";
  echo "You can now access https://collagendirect.health/admin/products.php to manage products\n";

} catch (Exception $e) {
  $pdo->rollBack();
  echo "\n✗ ERROR: " . $e->getMessage() . "\n";
  exit(1);
}
