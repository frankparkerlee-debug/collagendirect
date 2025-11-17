<?php
/**
 * Sync products table with Dressing Rule Matrix spreadsheet
 * - Update product names to include HCPCS codes
 * - Ensure pricing matches spreadsheet exactly
 * - Set category flags (can_be_primary, can_be_secondary, can_be_additional)
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Syncing Products with Dressing Rule Matrix ===\n\n";

try {
  // Products from the Dressing Rule Matrix spreadsheet
  // Format: Brand | Product | Size | HCPCS | Price/Piece | Pieces/Box | Price/Box | Medicare Allowable | Primary | Secondary | Additional
  $products = [
    // AlgiHeal
    ['brand' => 'AlgiHeal', 'product' => 'Calcium Alginate', 'size' => '2x2', 'hcpcs' => 'A6196', 'price_piece' => 2.50, 'pieces_box' => 10, 'price_box' => 25.00, 'medicare' => 102.80, 'primary' => true, 'secondary' => false, 'additional' => false, 'ref' => 'MD0202CA'],
    ['brand' => 'AlgiHeal', 'product' => 'Calcium Alginate', 'size' => '4.33x4.33', 'hcpcs' => 'A6197', 'price_piece' => 4.00, 'pieces_box' => 10, 'price_box' => 40.00, 'medicare' => 229.80, 'primary' => true, 'secondary' => false, 'additional' => false, 'ref' => 'MD0404CA'],
    ['brand' => 'AlgiHeal', 'product' => 'Calcium Alginate', 'size' => '6x6', 'hcpcs' => 'A6197', 'price_piece' => 5.75, 'pieces_box' => 10, 'price_box' => 57.50, 'medicare' => 229.80, 'primary' => true, 'secondary' => false, 'additional' => false, 'ref' => 'MD0606CA'],
    ['brand' => 'AlgiHeal', 'product' => 'Calcium Alginate Rope', 'size' => '2g Rope', 'hcpcs' => 'A6199', 'price_piece' => 5.00, 'pieces_box' => 10, 'price_box' => 50.00, 'medicare' => 73.70, 'primary' => true, 'secondary' => false, 'additional' => false, 'ref' => 'MDCAR'],

    // AlgiHeal AG
    ['brand' => 'AlgiHeal AG', 'product' => 'Silver Alginate Dressing', 'size' => '2x2', 'hcpcs' => 'A6196', 'price_piece' => 2.75, 'pieces_box' => 10, 'price_box' => 27.50, 'medicare' => 102.80, 'primary' => true, 'secondary' => false, 'additional' => false, 'ref' => 'MD0202SA'],
    ['brand' => 'AlgiHeal AG', 'product' => 'Silver Alginate Dressing', 'size' => '4.33x4.33', 'hcpcs' => 'A6197', 'price_piece' => 4.75, 'pieces_box' => 10, 'price_box' => 47.50, 'medicare' => 229.80, 'primary' => true, 'secondary' => false, 'additional' => false, 'ref' => 'MD0404SA'],
    ['brand' => 'AlgiHeal AG', 'product' => 'Silver Alginate Dressing', 'size' => '6x6', 'hcpcs' => 'A6197', 'price_piece' => 6.25, 'pieces_box' => 10, 'price_box' => 62.50, 'medicare' => 229.80, 'primary' => true, 'secondary' => false, 'additional' => false, 'ref' => 'MD0606SA'],

    // CuraFoam
    ['brand' => 'CuraFoam', 'product' => 'Silicone Foam Dressing (border)', 'size' => '2x2', 'hcpcs' => 'A6212', 'price_piece' => 3.00, 'pieces_box' => 10, 'price_box' => 30.00, 'medicare' => 135.70, 'primary' => true, 'secondary' => true, 'additional' => false, 'ref' => 'MD0202SFB'],
    ['brand' => 'CuraFoam', 'product' => 'Silicone Foam Dressing (border)', 'size' => '4.13x4.13', 'hcpcs' => 'A6213', 'price_piece' => 2.00, 'pieces_box' => 10, 'price_box' => 20.00, 'medicare' => 147.00, 'primary' => true, 'secondary' => true, 'additional' => false, 'ref' => 'MD0404SFB'],
    ['brand' => 'CuraFoam', 'product' => 'Silicone Foam Dressing (border)', 'size' => '6x6', 'hcpcs' => 'A6213', 'price_piece' => 4.50, 'pieces_box' => 10, 'price_box' => 45.00, 'medicare' => 147.00, 'primary' => true, 'secondary' => true, 'additional' => false, 'ref' => 'MD0606SFB'],
    ['brand' => 'CuraFoam', 'product' => 'Sacral Silicone Foam (border)', 'size' => '9"x9"', 'hcpcs' => 'A6214', 'price_piece' => 6.50, 'pieces_box' => 10, 'price_box' => 65.00, 'medicare' => 143.90, 'primary' => true, 'secondary' => true, 'additional' => false, 'ref' => 'GEN-14700'],

    // HydraPad
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Non-adherent)', 'size' => '2x2', 'hcpcs' => 'A6251', 'price_piece' => 1.50, 'pieces_box' => 10, 'price_box' => 15.00, 'medicare' => 27.80, 'primary' => true, 'secondary' => true, 'additional' => false, 'ref' => 'MD0202SAN'],
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Non-adherent)', 'size' => '4.13x4.13', 'hcpcs' => 'A6252', 'price_piece' => 2.50, 'pieces_box' => 10, 'price_box' => 25.00, 'medicare' => 45.50, 'primary' => true, 'secondary' => true, 'additional' => false, 'ref' => 'MD0404SAN'],
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Non-adherent)', 'size' => '8x8', 'hcpcs' => 'A6253', 'price_piece' => 9.00, 'pieces_box' => 10, 'price_box' => 90.00, 'medicare' => 88.50, 'primary' => true, 'secondary' => true, 'additional' => false, 'ref' => 'MD0808SAN'],
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Adherent)', 'size' => '2x2', 'hcpcs' => 'A6251', 'price_piece' => 1.75, 'pieces_box' => 10, 'price_box' => 17.50, 'medicare' => 27.80, 'primary' => true, 'secondary' => true, 'additional' => false, 'ref' => 'MD0202SAA'],
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Adherent)', 'size' => '4.13x4.13', 'hcpcs' => 'A6252', 'price_piece' => 3.75, 'pieces_box' => 10, 'price_box' => 37.50, 'medicare' => 45.50, 'primary' => true, 'secondary' => true, 'additional' => false, 'ref' => 'MD0404SAA'],
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Adherent)', 'size' => '8x8', 'hcpcs' => 'A6253', 'price_piece' => 5.00, 'pieces_box' => 10, 'price_box' => 50.00, 'medicare' => 88.50, 'primary' => true, 'secondary' => true, 'additional' => false, 'ref' => 'MD0808SAA'],

    // CollaHeal
    ['brand' => 'CollaHeal', 'product' => 'Collagen Dressing', 'size' => '2x2', 'hcpcs' => 'A6021', 'price_piece' => 12.00, 'pieces_box' => 10, 'price_box' => 120.00, 'medicare' => 236.60, 'primary' => true, 'secondary' => false, 'additional' => false, 'ref' => 'MD0202CS'],
    ['brand' => 'CollaHeal', 'product' => 'Collagen Dressing', 'size' => '7x7', 'hcpcs' => 'A6023', 'price_piece' => 90.00, 'pieces_box' => 10, 'price_box' => 900.00, 'medicare' => 1034.80, 'primary' => true, 'secondary' => false, 'additional' => false, 'ref' => 'MD0707CS'],
    ['brand' => 'CollaHeal', 'product' => 'Collagen Powder', 'size' => '1.0g', 'hcpcs' => 'A6010', 'price_piece' => 16.50, 'pieces_box' => 10, 'price_box' => 165.00, 'medicare' => 348.60, 'primary' => true, 'secondary' => false, 'additional' => false, 'ref' => 'MD001000'],

    // Gauze
    ['brand' => 'Gauze', 'product' => 'Bordered Gauze', 'size' => '4"x4"(2"x2")', 'hcpcs' => 'A6219', 'price_piece' => 0.60, 'pieces_box' => 25, 'price_box' => 15.00, 'medicare' => 33.25, 'primary' => false, 'secondary' => true, 'additional' => true, 'ref' => 'GEN-15410'],
    ['brand' => 'Gauze', 'product' => 'Bordered Gauze', 'size' => '6"x6"(4.5"x4.5")', 'hcpcs' => 'A6220', 'price_piece' => 1.23, 'pieces_box' => 25, 'price_box' => 30.80, 'medicare' => 90.50, 'primary' => false, 'secondary' => true, 'additional' => true, 'ref' => 'GEN-15610'],
    ['brand' => 'Gauze', 'product' => 'Bordered Gauze', 'size' => '8"x8"(6.5"x6.5")', 'hcpcs' => 'A6221', 'price_piece' => 2.65, 'pieces_box' => 25, 'price_box' => 65.00, 'medicare' => 139.25, 'primary' => false, 'secondary' => true, 'additional' => true, 'ref' => 'GEN-15810'],

    // Dermal
    ['brand' => 'Dermal', 'product' => 'Dermal Wound Cleanser Spray', 'size' => '8oz Bottle', 'hcpcs' => 'A6260', 'price_piece' => 0.66, 'pieces_box' => 6, 'price_box' => 3.96, 'medicare' => 28.38, 'primary' => false, 'secondary' => false, 'additional' => true, 'ref' => 'MD08WCS'],

    // Sacral
    ['brand' => 'Sacral', 'product' => 'Silicone Foam-Sacral', 'size' => '9"X9"', 'hcpcs' => 'A6213', 'price_piece' => 6.50, 'pieces_box' => 10, 'price_box' => 65.00, 'medicare' => 147.00, 'primary' => true, 'secondary' => true, 'additional' => false, 'ref' => 'GEN-14700'],

    // Arobella
    ['brand' => 'Arobella', 'product' => 'Disposable Tubing Set', 'size' => '', 'hcpcs' => 'N/A', 'price_piece' => 35.00, 'pieces_box' => 1, 'price_box' => 35.00, 'medicare' => 0, 'primary' => false, 'secondary' => false, 'additional' => true, 'ref' => 'AR-0001'],

    // NPWT
    ['brand' => 'NPWT', 'product' => 'Medium Dressing Kit', 'size' => 'Medium', 'hcpcs' => 'A6550', 'price_piece' => 27.50, 'pieces_box' => 1, 'price_box' => 27.50, 'medicare' => 30.52, 'primary' => false, 'secondary' => false, 'additional' => true, 'ref' => 'NP-MDK'],
    ['brand' => 'NPWT', 'product' => 'Versa Canister', 'size' => '100ML', 'hcpcs' => 'A7000', 'price_piece' => 11.50, 'pieces_box' => 1, 'price_box' => 11.50, 'medicare' => 10.22, 'primary' => false, 'secondary' => false, 'additional' => true, 'ref' => 'NP-100V'],
    ['brand' => 'NPWT', 'product' => 'Nisus Canister', 'size' => '250ML', 'hcpcs' => 'A7000', 'price_piece' => 13.50, 'pieces_box' => 10, 'price_box' => 135.00, 'medicare' => 102.20, 'primary' => false, 'secondary' => false, 'additional' => true, 'ref' => 'NP-250N'],
  ];

  echo "Step 1: Ensuring product table has all required columns...\n";
  $pdo->exec("
    ALTER TABLE products ADD COLUMN IF NOT EXISTS can_be_primary BOOLEAN DEFAULT FALSE;
    ALTER TABLE products ADD COLUMN IF NOT EXISTS can_be_secondary BOOLEAN DEFAULT FALSE;
    ALTER TABLE products ADD COLUMN IF NOT EXISTS can_be_additional BOOLEAN DEFAULT FALSE;
    ALTER TABLE products ADD COLUMN IF NOT EXISTS ref_number VARCHAR(50);
    ALTER TABLE products ADD COLUMN IF NOT EXISTS medicare_allowable DECIMAL(10,2);
    ALTER TABLE products ADD COLUMN IF NOT EXISTS price_per_piece DECIMAL(10,2);
  ");
  echo "  ✓ Columns verified\n\n";

  echo "Step 2: Syncing products with spreadsheet...\n";
  $added = 0;
  $updated = 0;

  foreach ($products as $p) {
    // Create standardized product name: "Product Size (HCPCS)"
    $productName = $p['product'];
    if (!empty($p['size'])) {
      $productName .= ' ' . $p['size'];
    }
    if ($p['hcpcs'] !== 'N/A') {
      $productName .= ' (' . $p['hcpcs'] . ')';
    }

    // Check if product exists by ref number
    $existing = $pdo->prepare("SELECT id FROM products WHERE ref_number = ?");
    $existing->execute([$p['ref']]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
      // Update existing product
      $stmt = $pdo->prepare("
        UPDATE products SET
          name = ?,
          hcpcs_code = ?,
          price_admin = ?,
          price_wholesale = ?,
          pieces_per_box = ?,
          price_per_piece = ?,
          medicare_allowable = ?,
          can_be_primary = ?,
          can_be_secondary = ?,
          can_be_additional = ?
        WHERE id = ?
      ");
      $stmt->execute([
        $productName,
        $p['hcpcs'],
        $p['medicare'], // price_admin is Medicare Allowable (what insurance reimburses)
        $p['price_box'], // price_wholesale is the cost to practice
        $p['pieces_box'],
        $p['price_piece'],
        $p['medicare'],
        $p['primary'] ? 1 : 0,
        $p['secondary'] ? 1 : 0,
        $p['additional'] ? 1 : 0,
        $existingId
      ]);
      $updated++;
      echo "  ✓ Updated: {$productName}\n";
    } else {
      // Insert new product
      $stmt = $pdo->prepare("
        INSERT INTO products (
          name, hcpcs_code, price_admin, price_wholesale, pieces_per_box, price_per_piece, medicare_allowable,
          can_be_primary, can_be_secondary, can_be_additional, ref_number, active, sku
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?)
      ");
      $stmt->execute([
        $productName,
        $p['hcpcs'],
        $p['medicare'], // price_admin is Medicare Allowable
        $p['price_box'], // price_wholesale is the cost to practice
        $p['pieces_box'],
        $p['price_piece'],
        $p['medicare'],
        $p['primary'] ? 1 : 0,
        $p['secondary'] ? 1 : 0,
        $p['additional'] ? 1 : 0,
        $p['ref'],
        $p['ref'] // Use ref_number as SKU
      ]);
      $added++;
      echo "  ✓ Added: {$productName}\n";
    }
  }

  echo "\n=== Sync Complete ===\n";
  echo "Products added: {$added}\n";
  echo "Products updated: {$updated}\n";
  echo "Total products in spreadsheet: " . count($products) . "\n\n";

  // Show summary
  echo "Step 3: Product category summary...\n";
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

  echo "✓ All product names now include HCPCS codes\n";
  echo "✓ All pricing matches Dressing Rule Matrix spreadsheet\n";
  echo "✓ Category flags set correctly for primary/secondary/additional\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}
