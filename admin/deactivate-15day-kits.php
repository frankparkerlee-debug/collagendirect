<?php
/**
 * Deactivate 15-Day Product Kits
 * Discontinued as of 11/6/2025
 */

declare(strict_types=1);
require __DIR__ . '/../api/db.php';

echo "Deactivating 15-day product kits (discontinued 11/6/2025)...\n\n";

try {
  $stmt = $pdo->prepare("
    UPDATE products
    SET active = FALSE, updated_at = NOW()
    WHERE sku IN ('KIT-COL-15', 'KIT-ALG-15', 'KIT-AG-15')
    RETURNING id, name, sku
  ");
  $stmt->execute();
  $deactivated = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($deactivated)) {
    echo "⚠ No products found with those SKUs\n";
    echo "Checking for products with '15-Day' in name...\n\n";

    $stmt2 = $pdo->prepare("
      UPDATE products
      SET active = FALSE, updated_at = NOW()
      WHERE name LIKE '%15-Day%' AND active = TRUE
      RETURNING id, name, sku
    ");
    $stmt2->execute();
    $deactivated = $stmt2->fetchAll(PDO::FETCH_ASSOC);
  }

  if (empty($deactivated)) {
    echo "ℹ No 15-day products found to deactivate (may already be inactive)\n";
  } else {
    echo "✅ Deactivated " . count($deactivated) . " products:\n";
    foreach ($deactivated as $p) {
      echo "  - {$p['name']} ({$p['sku']})\n";
    }
  }

} catch (PDOException $e) {
  echo "❌ Failed: " . $e->getMessage() . "\n";
  exit(1);
}
