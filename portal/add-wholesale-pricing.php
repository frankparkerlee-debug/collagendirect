<?php
/**
 * Migration: Add price_wholesale and pieces_per_box columns to products table
 * Also update products with wholesale pricing from MD-DME Bulk Order Form
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Adding Wholesale Pricing to Products Table ===\n\n";

try {
  // Use global $pdo from db.php
  global $pdo;
  $db = $pdo;

  // Check if columns already exist
  $stmt = $db->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name='products' AND column_name IN ('price_wholesale', 'pieces_per_box')
  ");
  $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

  if (!in_array('price_wholesale', $existing)) {
    echo "Adding price_wholesale column...\n";
    $db->exec("ALTER TABLE products ADD COLUMN price_wholesale DECIMAL(10,2)");
    echo "✓ Added price_wholesale column\n";
  } else {
    echo "✓ price_wholesale column already exists\n";
  }

  if (!in_array('pieces_per_box', $existing)) {
    echo "Adding pieces_per_box column...\n";
    $db->exec("ALTER TABLE products ADD COLUMN pieces_per_box INTEGER DEFAULT 10");
    echo "✓ Added pieces_per_box column\n";
  } else {
    echo "✓ pieces_per_box column already exists\n";
  }

  echo "\n=== Updating Products with Wholesale Pricing ===\n\n";

  // Wholesale pricing from MD-DME Bulk Order Form
  $wholesalePricing = [
    // AlgiHeal - Calcium Alginate
    ['ref' => 'MD0202CA', 'name' => 'Calcium Alginate 2"x2"', 'price_wholesale' => 2.50, 'pieces_per_box' => 10, 'price_per_box' => 25.00],
    ['ref' => 'MD0404CA', 'name' => 'Calcium Alginate 4.33"x4.33"', 'price_wholesale' => 4.00, 'pieces_per_box' => 10, 'price_per_box' => 40.00],
    ['ref' => 'MD0606CA', 'name' => 'Calcium Alginate 6"x6"', 'price_wholesale' => 5.75, 'pieces_per_box' => 10, 'price_per_box' => 57.50],
    ['ref' => 'MDCAR', 'name' => 'Calcium Alginate 12" Rope', 'price_wholesale' => 5.00, 'pieces_per_box' => 5, 'price_per_box' => 25.00],

    // AlgiHeal AG - Silver Alginate
    ['ref' => 'MD0202SA', 'name' => 'Silver Alginate 2"x2"', 'price_wholesale' => 2.75, 'pieces_per_box' => 10, 'price_per_box' => 27.50],
    ['ref' => 'MD0404SA', 'name' => 'Silver Alginate 4.33"x4.33"', 'price_wholesale' => 4.75, 'pieces_per_box' => 10, 'price_per_box' => 47.50],
    ['ref' => 'MD0606SA', 'name' => 'Silver Alginate 6"x6"', 'price_wholesale' => 6.25, 'pieces_per_box' => 10, 'price_per_box' => 62.50],

    // CuraFoam - Silicone Foam (bordered)
    ['ref' => 'MD0202SFB', 'name' => 'Silicone Foam(bordered) 2"x2"', 'price_wholesale' => 2.00, 'pieces_per_box' => 10, 'price_per_box' => 20.00],
    ['ref' => 'MD0404SFB', 'name' => 'Silicone Foam(bordered) 4.13"x4.13"', 'price_wholesale' => 3.00, 'pieces_per_box' => 10, 'price_per_box' => 30.00],
    ['ref' => 'MD0606SFB', 'name' => 'Silicone Foam(bordered) 6"x6"', 'price_wholesale' => 4.50, 'pieces_per_box' => 10, 'price_per_box' => 45.00],

    // HydraPad - Super Absorbent non-adherent
    ['ref' => 'MD0202SAN', 'name' => 'Super Absorbent non-adherent 2"x2"', 'price_wholesale' => 1.50, 'pieces_per_box' => 10, 'price_per_box' => 15.00],
    ['ref' => 'MD0404SAN', 'name' => 'Super Absorbent non-adherent 4.13"x4.13"', 'price_wholesale' => 2.50, 'pieces_per_box' => 10, 'price_per_box' => 25.00],
    ['ref' => 'MD0808SAN', 'name' => 'Super Absorbent non-adherent 8"x8"', 'price_wholesale' => 4.50, 'pieces_per_box' => 10, 'price_per_box' => 45.00],

    // HydraPad - Super Absorbent Adherent
    ['ref' => 'MD0202SAA', 'name' => 'Super Absorbent Adherent 2"x2"', 'price_wholesale' => 1.75, 'pieces_per_box' => 10, 'price_per_box' => 17.50],
    ['ref' => 'MD0404SAA', 'name' => 'Super Absorbent Adherent 4.13"x4.13"', 'price_wholesale' => 3.75, 'pieces_per_box' => 10, 'price_per_box' => 37.50],
    ['ref' => 'MD0808SAA', 'name' => 'Super Absorbent Adherent 8"x8"', 'price_wholesale' => 5.00, 'pieces_per_box' => 10, 'price_per_box' => 50.00],

    // CollaHeal - Collagen
    ['ref' => 'MD0202CS', 'name' => 'Collagen Pad 2"x2"', 'price_wholesale' => 12.00, 'pieces_per_box' => 10, 'price_per_box' => 120.00],
    ['ref' => 'MD0707CS', 'name' => 'Collagen Pad 7"x7"', 'price_wholesale' => 90.00, 'pieces_per_box' => 10, 'price_per_box' => 900.00],
    ['ref' => 'MD001000', 'name' => 'Collagen Particles 1g', 'price_wholesale' => 16.50, 'pieces_per_box' => 10, 'price_per_box' => 165.00],

    // Wound Cleanser
    ['ref' => 'MD08WCS', 'name' => 'Wound Cleanser Spray-8oz Btl', 'price_wholesale' => 4.00, 'pieces_per_box' => 6, 'price_per_box' => 24.00],

    // Gauze - Bordered
    ['ref' => 'GEN-15410', 'name' => 'Bordered Gauze 4"x4" (2"x2")', 'price_wholesale' => 0.60, 'pieces_per_box' => 25, 'price_per_box' => 15.00],
    ['ref' => 'GEN-15610', 'name' => 'Bordered Gauze 6"x6" (4.5"x4.5")', 'price_wholesale' => 1.23, 'pieces_per_box' => 25, 'price_per_box' => 30.80],
    ['ref' => 'GEN-15810', 'name' => 'Bordered Gauze 8"x8" (6.5"x6.5")', 'price_wholesale' => 2.65, 'pieces_per_box' => 10, 'price_per_box' => 26.48],

    // Sacral
    ['ref' => 'GEN-14700', 'name' => 'Silicone Foam-Sacral 9"x9"', 'price_wholesale' => 6.50, 'pieces_per_box' => 10, 'price_per_box' => 65.00],

    // NPWT
    ['ref' => 'AR-0001', 'name' => 'Disposable Tubing Set', 'price_wholesale' => 35.00, 'pieces_per_box' => 1, 'price_per_box' => 35.00],
    ['ref' => 'NP-MDK', 'name' => 'Medium Dressing Kit', 'price_wholesale' => 27.50, 'pieces_per_box' => 1, 'price_per_box' => 27.50],
    ['ref' => 'NP-100V', 'name' => '100mL Versa Canister', 'price_wholesale' => 11.50, 'pieces_per_box' => 1, 'price_per_box' => 11.50],
    ['ref' => 'NP-250N', 'name' => '250mL Nisus Canister', 'price_wholesale' => 13.50, 'pieces_per_box' => 1, 'price_per_box' => 13.50],
  ];

  $updateCount = 0;
  $notFoundCount = 0;

  foreach ($wholesalePricing as $item) {
    // Try to find product by name or SKU
    $stmt = $db->prepare("
      SELECT id, name, sku FROM products
      WHERE LOWER(name) LIKE LOWER(?) OR sku = ?
      LIMIT 1
    ");
    $searchName = '%' . str_replace(['"', 'x'], ['%', '%'], $item['name']) . '%';
    $stmt->execute([$searchName, $item['ref']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
      $db->prepare("
        UPDATE products
        SET price_wholesale = ?, pieces_per_box = ?
        WHERE id = ?
      ")->execute([$item['price_wholesale'], $item['pieces_per_box'], $product['id']]);

      echo "✓ Updated {$product['name']} ({$item['ref']}) - \${$item['price_wholesale']}/piece, {$item['pieces_per_box']} per box\n";
      $updateCount++;
    } else {
      echo "⚠ Not found: {$item['name']} ({$item['ref']})\n";
      $notFoundCount++;
    }
  }

  echo "\n=== Migration Complete ===\n";
  echo "Updated: $updateCount products\n";
  echo "Not found: $notFoundCount products\n";
  echo "\nNext steps:\n";
  echo "1. Add HCPCS billing rates to price_admin column (from Dressing Rule Matrix)\n";
  echo "2. Update order pricing logic to use price_wholesale when billed_by='practice_dme'\n";
  echo "3. Update order quantity calculation to use pieces_per_box\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
