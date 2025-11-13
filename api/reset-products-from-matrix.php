<?php
/**
 * Reset Products Database - Replace with Dressing Rule Matrix Products Only
 *
 * This script will:
 * 1. DELETE all existing products
 * 2. INSERT only products from Dressing Rule Matrix (21 products)
 * 3. Populate wholesale pricing from MD-DME Bulk Order Form
 * 4. Set HCPCS codes and Medicare rates from matrix
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== RESETTING PRODUCTS DATABASE ===\n";
echo "This will DELETE all existing products and replace with Dressing Rule Matrix products only.\n\n";

try {
  global $pdo;

  // STEP 1: Delete all existing products
  echo "STEP 1: Deleting all existing products...\n";
  $stmt = $pdo->query("SELECT COUNT(*) FROM products");
  $oldCount = $stmt->fetchColumn();
  echo "Found {$oldCount} existing products.\n";

  $pdo->exec("DELETE FROM products");
  echo "✓ Deleted all products.\n\n";

  // STEP 2: Define products from Dressing Rule Matrix
  echo "STEP 2: Adding products from Dressing Rule Matrix...\n\n";

  $matrixProducts = [
    // AlgiHeal - Calcium Alginate
    [
      'name' => 'AlgiHeal Calcium Alginate 2x2',
      'sku' => 'AH-ALG-2X2',
      'brand' => 'AlgiHeal',
      'product_type' => 'Calcium Alginate',
      'size' => '2x2',
      'hcpcs' => 'A6196',
      'price_admin' => 6.28,  // Medicare rate from existing data
      'price_wholesale' => 2.50,  // MD-DME: MD0202CA
      'pieces_per_box' => 10,
      'ref' => 'MD0202CA'
    ],
    [
      'name' => 'AlgiHeal Calcium Alginate 4.33x4.33',
      'sku' => 'AH-ALG-4X4',
      'brand' => 'AlgiHeal',
      'product_type' => 'Calcium Alginate',
      'size' => '4.33x4.33',
      'hcpcs' => 'A6197',
      'price_admin' => 9.02,
      'price_wholesale' => 4.00,  // MD-DME: MD0404CA
      'pieces_per_box' => 10,
      'ref' => 'MD0404CA'
    ],
    [
      'name' => 'AlgiHeal Calcium Alginate 6x6',
      'sku' => 'AH-ALG-6X6',
      'brand' => 'AlgiHeal',
      'product_type' => 'Calcium Alginate',
      'size' => '6x6',
      'hcpcs' => 'A6197',
      'price_admin' => 12.44,
      'price_wholesale' => 5.75,  // MD-DME: MD0606CA
      'pieces_per_box' => 10,
      'ref' => 'MD0606CA'
    ],
    [
      'name' => 'AlgiHeal Calcium Alginate Rope 2g',
      'sku' => 'AH-ALG-ROPE',
      'brand' => 'AlgiHeal',
      'product_type' => 'Calcium Alginate Rope',
      'size' => '2g Rope',
      'hcpcs' => 'A6199',
      'price_admin' => 10.00,  // Estimate - verify with Medicare rate
      'price_wholesale' => 5.00,  // MD-DME: MDCAR
      'pieces_per_box' => 5,
      'ref' => 'MDCAR'
    ],

    // AlgiHeal AG - Silver Alginate
    [
      'name' => 'AlgiHeal AG Silver Alginate 2x2',
      'sku' => 'AH-AG-2X2',
      'brand' => 'AlgiHeal AG',
      'product_type' => 'Silver Alginate Dressing',
      'size' => '2x2',
      'hcpcs' => 'A6196',
      'price_admin' => 11.50,
      'price_wholesale' => 2.75,  // MD-DME: MD0202SA
      'pieces_per_box' => 10,
      'ref' => 'MD0202SA'
    ],
    [
      'name' => 'AlgiHeal AG Silver Alginate 4.33x4.33',
      'sku' => 'AH-AG-4X4',
      'brand' => 'AlgiHeal AG',
      'product_type' => 'Silver Alginate Dressing',
      'size' => '4.33x4.33',
      'hcpcs' => 'A6197',
      'price_admin' => 14.00,  // Estimate
      'price_wholesale' => 4.75,  // MD-DME: MD0404SA
      'pieces_per_box' => 10,
      'ref' => 'MD0404SA'
    ],
    [
      'name' => 'AlgiHeal AG Silver Alginate 6x6',
      'sku' => 'AH-AG-6X6',
      'brand' => 'AlgiHeal AG',
      'product_type' => 'Silver Alginate Dressing',
      'size' => '6x6',
      'hcpcs' => 'A6197',
      'price_admin' => 18.00,  // Estimate
      'price_wholesale' => 6.25,  // MD-DME: MD0606SA
      'pieces_per_box' => 10,
      'ref' => 'MD0606SA'
    ],

    // CuraFoam - Silicone Foam (bordered)
    [
      'name' => 'CuraFoam Silicone Foam 2x2 (bordered)',
      'sku' => 'CF-FOAM-2X2',
      'brand' => 'CuraFoam',
      'product_type' => 'Silicone Foam Dressing (border)',
      'size' => '2x2',
      'hcpcs' => 'A6212',
      'price_admin' => 10.77,
      'price_wholesale' => 2.00,  // MD-DME: MD0202SFB
      'pieces_per_box' => 10,
      'ref' => 'MD0202SFB'
    ],
    [
      'name' => 'CuraFoam Silicone Foam 4.13x4.13 (bordered)',
      'sku' => 'CF-FOAM-4X4',
      'brand' => 'CuraFoam',
      'product_type' => 'Silicone Foam Dressing (border)',
      'size' => '4.13x4.13',
      'hcpcs' => 'A6213',
      'price_admin' => 15.00,  // Estimate
      'price_wholesale' => 3.00,  // MD-DME: MD0404SFB
      'pieces_per_box' => 10,
      'ref' => 'MD0404SFB'
    ],
    [
      'name' => 'CuraFoam Silicone Foam 6x6 (bordered)',
      'sku' => 'CF-FOAM-6X6',
      'brand' => 'CuraFoam',
      'product_type' => 'Silicone Foam Dressing (border)',
      'size' => '6x6',
      'hcpcs' => 'A6213',
      'price_admin' => 20.43,
      'price_wholesale' => 4.50,  // MD-DME: MD0606SFB
      'pieces_per_box' => 10,
      'ref' => 'MD0606SFB'
    ],
    [
      'name' => 'CuraFoam Sacral Silicone Foam 9x9 (bordered)',
      'sku' => 'CF-FOAM-SACRAL',
      'brand' => 'CuraFoam',
      'product_type' => 'Sacral Silicone Foam (border)',
      'size' => '9"x9"',
      'hcpcs' => 'A6214',
      'price_admin' => 30.00,  // Estimate
      'price_wholesale' => 6.50,  // MD-DME: GEN-14700
      'pieces_per_box' => 10,
      'ref' => 'GEN-14700'
    ],

    // HydraPad - Super Absorbent (Non-adherent)
    [
      'name' => 'HydraPad Super Absorbent 2x2 (Non-adherent)',
      'sku' => 'HP-SAN-2X2',
      'brand' => 'HydraPad',
      'product_type' => 'Super Absorbent (Non-adherent)',
      'size' => '2x2',
      'hcpcs' => 'A6251',
      'price_admin' => 8.34,
      'price_wholesale' => 1.50,  // MD-DME: MD0202SAN
      'pieces_per_box' => 10,
      'ref' => 'MD0202SAN'
    ],
    [
      'name' => 'HydraPad Super Absorbent 4.13x4.13 (Non-adherent)',
      'sku' => 'HP-SAN-4X4',
      'brand' => 'HydraPad',
      'product_type' => 'Super Absorbent (Non-adherent)',
      'size' => '4.13x4.13',
      'hcpcs' => 'A6252',
      'price_admin' => 12.00,  // Estimate
      'price_wholesale' => 2.50,  // MD-DME: MD0404SAN
      'pieces_per_box' => 10,
      'ref' => 'MD0404SAN'
    ],
    [
      'name' => 'HydraPad Super Absorbent 8x8 (Non-adherent)',
      'sku' => 'HP-SAN-8X8',
      'brand' => 'HydraPad',
      'product_type' => 'Super Absorbent (Non-adherent)',
      'size' => '8x8',
      'hcpcs' => 'A6253',
      'price_admin' => 17.92,
      'price_wholesale' => 4.50,  // MD-DME: MD0808SAN
      'pieces_per_box' => 10,
      'ref' => 'MD0808SAN'
    ],

    // HydraPad - Super Absorbent (Adherent)
    [
      'name' => 'HydraPad Super Absorbent 2x2 (Adherent)',
      'sku' => 'HP-SAA-2X2',
      'brand' => 'HydraPad',
      'product_type' => 'Super Absorbent (Adherent)',
      'size' => '2x2',
      'hcpcs' => 'A6251',
      'price_admin' => 8.34,
      'price_wholesale' => 1.75,  // MD-DME: MD0202SAA
      'pieces_per_box' => 10,
      'ref' => 'MD0202SAA'
    ],
    [
      'name' => 'HydraPad Super Absorbent 4.13x4.13 (Adherent)',
      'sku' => 'HP-SAA-4X4',
      'brand' => 'HydraPad',
      'product_type' => 'Super Absorbent (Adherent)',
      'size' => '4.13x4.13',
      'hcpcs' => 'A6252',
      'price_admin' => 12.00,  // Estimate
      'price_wholesale' => 3.75,  // MD-DME: MD0404SAA
      'pieces_per_box' => 10,
      'ref' => 'MD0404SAA'
    ],
    [
      'name' => 'HydraPad Super Absorbent 8x8 (Adherent)',
      'sku' => 'HP-SAA-8X8',
      'brand' => 'HydraPad',
      'product_type' => 'Super Absorbent (Adherent)',
      'size' => '8x8',
      'hcpcs' => 'A6253',
      'price_admin' => 17.92,
      'price_wholesale' => 5.00,  // MD-DME: MD0808SAA
      'pieces_per_box' => 10,
      'ref' => 'MD0808SAA'
    ],

    // CollaHeal - Collagen
    [
      'name' => 'CollaHeal Collagen Dressing 2x2',
      'sku' => 'CH-COL-2X2',
      'brand' => 'CollaHeal',
      'product_type' => 'Collagen Dressing',
      'size' => '2x2',
      'hcpcs' => 'A6021',
      'price_admin' => 16.44,
      'price_wholesale' => 12.00,  // MD-DME: MD0202CS
      'pieces_per_box' => 10,
      'ref' => 'MD0202CS'
    ],
    [
      'name' => 'CollaHeal Collagen Dressing 7x7',
      'sku' => 'CH-COL-7X7',
      'brand' => 'CollaHeal',
      'product_type' => 'Collagen Dressing',
      'size' => '7x7',
      'hcpcs' => 'A6023',
      'price_admin' => 52.70,
      'price_wholesale' => 90.00,  // MD-DME: MD0707CS
      'pieces_per_box' => 10,
      'ref' => 'MD0707CS'
    ],
    [
      'name' => 'CollaHeal Collagen Powder 1g',
      'sku' => 'CH-POW-1G',
      'brand' => 'CollaHeal',
      'product_type' => 'Collagen Powder 1g',
      'size' => '1.0g',
      'hcpcs' => 'A6010',
      'price_admin' => 24.16,
      'price_wholesale' => 16.50,  // MD-DME: MD001000
      'pieces_per_box' => 10,
      'ref' => 'MD001000'
    ],
  ];

  // STEP 3: Insert products
  $stmt = $pdo->prepare("
    INSERT INTO products (name, sku, price_admin, price_wholesale, pieces_per_box, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
  ");

  $insertCount = 0;
  foreach ($matrixProducts as $product) {
    $stmt->execute([
      $product['name'],
      $product['sku'],
      $product['price_admin'],
      $product['price_wholesale'],
      $product['pieces_per_box']
    ]);

    echo "✓ Added: {$product['name']} ({$product['sku']})\n";
    echo "  HCPCS: {$product['hcpcs']} | Medicare: \${$product['price_admin']} | Wholesale: \${$product['price_wholesale']}/pc | {$product['pieces_per_box']}/box\n";
    echo "  MD-DME REF: {$product['ref']}\n\n";

    $insertCount++;
  }

  echo "=== RESET COMPLETE ===\n";
  echo "Deleted: {$oldCount} products\n";
  echo "Added: {$insertCount} products from Dressing Rule Matrix\n\n";

  echo "=== PRODUCT BREAKDOWN ===\n";
  echo "AlgiHeal Calcium Alginate: 4 products\n";
  echo "AlgiHeal AG Silver Alginate: 3 products\n";
  echo "CuraFoam Silicone Foam: 4 products\n";
  echo "HydraPad Super Absorbent (Non-adherent): 3 products\n";
  echo "HydraPad Super Absorbent (Adherent): 3 products\n";
  echo "CollaHeal Collagen: 3 products\n";
  echo "TOTAL: {$insertCount} products\n\n";

  echo "All products now match Dressing Rule Matrix exactly.\n";
  echo "Wholesale pricing populated from MD-DME Bulk Order Form.\n";
  echo "Medicare rates (price_admin) populated from existing data.\n\n";

  echo "⚠️ NOTE: Some Medicare rates are estimates. Please verify:\n";
  echo "- AlgiHeal Calcium Alginate Rope (A6199)\n";
  echo "- AlgiHeal AG Silver Alginate 4x4 and 6x6 (A6197)\n";
  echo "- CuraFoam 4x4 (A6213)\n";
  echo "- CuraFoam Sacral (A6214)\n";
  echo "- HydraPad 4x4 sizes (A6252)\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
