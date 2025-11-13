<?php
/**
 * Product Validation and Pricing Update Script
 *
 * Purpose:
 * 1. Cross-reference database products with Dressing Rule Matrix
 * 2. Match products to MD-DME wholesale pricing
 * 3. Identify products to exclude from primary dressing (not on matrix)
 * 4. Populate price_wholesale and pieces_per_box
 */

require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Product Validation and Pricing Update ===\n\n";

try {
  global $pdo;

  // STEP 1: Dressing Rule Matrix Products (validation list)
  $matrixProducts = [
    // AlgiHeal - Calcium Alginate
    ['brand' => 'AlgiHeal', 'product' => 'Calcium Alginate', 'size' => '2x2', 'hcpcs' => 'A6196'],
    ['brand' => 'AlgiHeal', 'product' => 'Calcium Alginate', 'size' => '4.33x4.33', 'hcpcs' => 'A6197'],
    ['brand' => 'AlgiHeal', 'product' => 'Calcium Alginate', 'size' => '6x6', 'hcpcs' => 'A6197'],
    ['brand' => 'AlgiHeal', 'product' => 'Calcium Alginate Rope', 'size' => '2g Rope', 'hcpcs' => 'A6199'],

    // AlgiHeal AG - Silver Alginate
    ['brand' => 'AlgiHeal AG', 'product' => 'Silver Alginate Dressing', 'size' => '2x2', 'hcpcs' => 'A6196'],
    ['brand' => 'AlgiHeal AG', 'product' => 'Silver Alginate Dressing', 'size' => '4.33x4.33', 'hcpcs' => 'A6197'],
    ['brand' => 'AlgiHeal AG', 'product' => 'Silver Alginate Dressing', 'size' => '6x6', 'hcpcs' => 'A6197'],

    // CuraFoam - Silicone Foam
    ['brand' => 'CuraFoam', 'product' => 'Silicone Foam Dressing (border)', 'size' => '2x2', 'hcpcs' => 'A6212'],
    ['brand' => 'CuraFoam', 'product' => 'Silicone Foam Dressing (border)', 'size' => '4.13x4.13', 'hcpcs' => 'A6213'],
    ['brand' => 'CuraFoam', 'product' => 'Silicone Foam Dressing (border)', 'size' => '6x6', 'hcpcs' => 'A6213'],
    ['brand' => 'CuraFoam', 'product' => 'Sacral Silicone Foam (border)', 'size' => '9"x9"', 'hcpcs' => 'A6214'],

    // HydraPad - Super Absorbent
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Non-adherent)', 'size' => '2x2', 'hcpcs' => 'A6251'],
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Non-adherent)', 'size' => '4.13x4.13', 'hcpcs' => 'A6252'],
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Non-adherent)', 'size' => '8x8', 'hcpcs' => 'A6253'],
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Adherent)', 'size' => '2x2', 'hcpcs' => 'A6251'],
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Adherent)', 'size' => '4.13x4.13', 'hcpcs' => 'A6252'],
    ['brand' => 'HydraPad', 'product' => 'Super Absorbent (Adherent)', 'size' => '8x8', 'hcpcs' => 'A6253'],

    // CollaHeal - Collagen
    ['brand' => 'CollaHeal', 'product' => 'Collagen Dressing', 'size' => '2x2', 'hcpcs' => 'A6021'],
    ['brand' => 'CollaHeal', 'product' => 'Collagen Dressing', 'size' => '7x7', 'hcpcs' => 'A6023'],
    ['brand' => 'CollaHeal', 'product' => 'Collagen Powder 1g', 'size' => '1.0g', 'hcpcs' => 'A6010'],
  ];

  // STEP 2: MD-DME Wholesale Pricing
  $wholesalePricing = [
    // AlgiHeal - Calcium Alginate
    ['ref' => 'MD0202CA', 'match' => 'alginate|calcium.*2x2|2"x2"', 'price' => 2.50, 'pieces' => 10],
    ['ref' => 'MD0404CA', 'match' => 'alginate|calcium.*4\.33x4\.33|4\.33"x4\.33"|4x4', 'price' => 4.00, 'pieces' => 10],
    ['ref' => 'MD0606CA', 'match' => 'alginate|calcium.*6x6|6"x6"', 'price' => 5.75, 'pieces' => 10],
    ['ref' => 'MDCAR', 'match' => 'alginate.*rope|calcium.*rope', 'price' => 5.00, 'pieces' => 5],

    // AlgiHeal AG - Silver Alginate
    ['ref' => 'MD0202SA', 'match' => 'silver.*alginate.*2x2|silver.*2"x2"', 'price' => 2.75, 'pieces' => 10],
    ['ref' => 'MD0404SA', 'match' => 'silver.*alginate.*4\.33x4\.33|silver.*4x4', 'price' => 4.75, 'pieces' => 10],
    ['ref' => 'MD0606SA', 'match' => 'silver.*alginate.*6x6|silver.*6"x6"', 'price' => 6.25, 'pieces' => 10],

    // CuraFoam - Silicone Foam
    ['ref' => 'MD0202SFB', 'match' => 'foam.*2x2|foam.*2"x2"', 'price' => 2.00, 'pieces' => 10],
    ['ref' => 'MD0404SFB', 'match' => 'foam.*4\.13x4\.13|foam.*4x4', 'price' => 3.00, 'pieces' => 10],
    ['ref' => 'MD0606SFB', 'match' => 'foam.*6x6|foam.*6"x6"', 'price' => 4.50, 'pieces' => 10],
    ['ref' => 'GEN-14700', 'match' => 'sacral|foam.*9x9|foam.*9"x9"', 'price' => 6.50, 'pieces' => 10],

    // HydraPad - Super Absorbent
    ['ref' => 'MD0202SAN', 'match' => 'absorbent.*non.*2x2|absorbent.*non.*2"x2"', 'price' => 1.50, 'pieces' => 10],
    ['ref' => 'MD0404SAN', 'match' => 'absorbent.*non.*4\.13x4\.13|absorbent.*non.*4x4', 'price' => 2.50, 'pieces' => 10],
    ['ref' => 'MD0808SAN', 'match' => 'absorbent.*non.*8x8|absorbent.*non.*8"x8"', 'price' => 4.50, 'pieces' => 10],
    ['ref' => 'MD0202SAA', 'match' => 'absorbent.*adherent.*2x2|absorbent.*adherent.*2"x2"', 'price' => 1.75, 'pieces' => 10],
    ['ref' => 'MD0404SAA', 'match' => 'absorbent.*adherent.*4\.13x4\.13|absorbent.*adherent.*4x4', 'price' => 3.75, 'pieces' => 10],
    ['ref' => 'MD0808SAA', 'match' => 'absorbent.*adherent.*8x8|absorbent.*adherent.*8"x8"', 'price' => 5.00, 'pieces' => 10],

    // CollaHeal - Collagen
    ['ref' => 'MD0202CS', 'match' => 'collagen.*2x2|collagen.*pad.*2"x2"', 'price' => 12.00, 'pieces' => 10],
    ['ref' => 'MD0707CS', 'match' => 'collagen.*7x7|collagen.*pad.*7"x7"', 'price' => 90.00, 'pieces' => 10],
    ['ref' => 'MD001000', 'match' => 'collagen.*powder|collagen.*particle|collagen.*1g', 'price' => 16.50, 'pieces' => 10],

    // Other Products
    ['ref' => 'MD08WCS', 'match' => 'wound.*cleanser|cleanser.*spray', 'price' => 4.00, 'pieces' => 6],
    ['ref' => 'GEN-15410', 'match' => 'bordered.*gauze.*4x4|gauze.*border.*4"x4"', 'price' => 0.60, 'pieces' => 25],
    ['ref' => 'GEN-15610', 'match' => 'bordered.*gauze.*6x6|gauze.*border.*6"x6"', 'price' => 1.23, 'pieces' => 25],
    ['ref' => 'GEN-15810', 'match' => 'bordered.*gauze.*8x8|gauze.*border.*8"x8"', 'price' => 2.65, 'pieces' => 10],

    // NPWT
    ['ref' => 'AR-0001', 'match' => 'tubing.*set|disposable.*tubing', 'price' => 35.00, 'pieces' => 1],
    ['ref' => 'NP-MDK', 'match' => 'medium.*dressing.*kit|dressing.*kit.*medium', 'price' => 27.50, 'pieces' => 1],
    ['ref' => 'NP-100V', 'match' => '100.*ml.*canister|versa.*canister', 'price' => 11.50, 'pieces' => 1],
    ['ref' => 'NP-250N', 'match' => '250.*ml.*canister|nisus.*canister', 'price' => 13.50, 'pieces' => 1],
  ];

  // STEP 3: Get all products from database
  $stmt = $pdo->query("
    SELECT id, name, sku, price_admin, price_wholesale, pieces_per_box
    FROM products
    ORDER BY name
  ");
  $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Found " . count($dbProducts) . " products in database\n\n";

  // STEP 4: Match products and generate reports
  $matchedProducts = [];
  $unmatchedProducts = [];
  $notOnMatrix = [];
  $updateCount = 0;

  foreach ($dbProducts as $product) {
    $productName = strtolower($product['name']);
    $productSKU = strtolower($product['sku'] ?? '');

    // Check if on Dressing Rule Matrix
    $onMatrix = false;
    foreach ($matrixProducts as $matrixItem) {
      // More flexible matching - check brand AND (product type OR size)
      $brand = strtolower($matrixItem['brand']);
      $productType = strtolower($matrixItem['product']);
      $size = str_replace(['"', '.', ' '], '', strtolower($matrixItem['size']));

      // Normalize product name/SKU
      $normalizedName = str_replace(['"', '.', ' '], '', $productName);
      $normalizedSKU = str_replace(['"', '.', ' ', '-'], '', $productSKU);

      // Check if brand matches
      $brandMatch = (strpos($productName, $brand) !== false || strpos($productSKU, $brand) !== false);

      // Check if size matches (flexible - handle variations like 2x2, 2"x2", 2 x 2)
      $sizeMatch = (strpos($normalizedName, $size) !== false || strpos($normalizedSKU, $size) !== false);

      // Check if product type keywords match
      $productKeywords = [];
      if (strpos($productType, 'alginate') !== false) $productKeywords[] = 'alginate|alg';
      if (strpos($productType, 'silver') !== false) $productKeywords[] = 'silver|ag';
      if (strpos($productType, 'collagen') !== false) $productKeywords[] = 'collagen|col';
      if (strpos($productType, 'foam') !== false) $productKeywords[] = 'foam';
      if (strpos($productType, 'absorbent') !== false) $productKeywords[] = 'absorbent|sa';
      if (strpos($productType, 'powder') !== false || strpos($productType, 'particle') !== false) $productKeywords[] = 'powder|particle';
      if (strpos($productType, 'rope') !== false) $productKeywords[] = 'rope';

      $productMatch = false;
      foreach ($productKeywords as $keyword) {
        if (preg_match('/' . $keyword . '/i', $productName) || preg_match('/' . $keyword . '/i', $productSKU)) {
          $productMatch = true;
          break;
        }
      }

      // Match if brand AND size AND product type all match
      if ($brandMatch && $sizeMatch && $productMatch) {
        $onMatrix = true;
        break;
      }
    }

    // Try to match to wholesale pricing
    $wholesaleMatch = null;
    foreach ($wholesalePricing as $wholesale) {
      if (preg_match('/' . $wholesale['match'] . '/i', $productName) ||
          preg_match('/' . $wholesale['match'] . '/i', $productSKU)) {
        $wholesaleMatch = $wholesale;
        break;
      }
    }

    // Store results
    if ($wholesaleMatch) {
      $matchedProducts[] = [
        'db' => $product,
        'wholesale' => $wholesaleMatch,
        'on_matrix' => $onMatrix
      ];

      // Update database
      $pdo->prepare("
        UPDATE products
        SET price_wholesale = ?, pieces_per_box = ?
        WHERE id = ?
      ")->execute([
        $wholesaleMatch['price'],
        $wholesaleMatch['pieces'],
        $product['id']
      ]);

      $updateCount++;
    } else {
      $unmatchedProducts[] = [
        'db' => $product,
        'on_matrix' => $onMatrix
      ];
    }

    if (!$onMatrix) {
      $notOnMatrix[] = $product;
    }
  }

  // STEP 5: Generate Report
  echo "=== MATCHED PRODUCTS (Wholesale Pricing Updated) ===\n\n";
  foreach ($matchedProducts as $match) {
    $status = $match['on_matrix'] ? '✓ ON MATRIX' : '⚠ NOT ON MATRIX';
    echo "{$status} - {$match['db']['name']}\n";
    echo "  SKU: {$match['db']['sku']}\n";
    echo "  Wholesale: \${$match['wholesale']['price']} per piece\n";
    echo "  Pieces per box: {$match['wholesale']['pieces']}\n";
    echo "  Medicare rate: \${$match['db']['price_admin']}\n";
    echo "  REF: {$match['wholesale']['ref']}\n\n";
  }

  echo "=== UNMATCHED PRODUCTS (No Wholesale Pricing) ===\n\n";
  foreach ($unmatchedProducts as $unmatch) {
    $status = $unmatch['on_matrix'] ? '✓ ON MATRIX' : '⚠ NOT ON MATRIX';
    echo "{$status} - {$unmatch['db']['name']}\n";
    echo "  SKU: {$unmatch['db']['sku']}\n";
    echo "  Medicare rate: \${$unmatch['db']['price_admin']}\n\n";
  }

  echo "=== PRODUCTS NOT ON DRESSING RULE MATRIX ===\n";
  echo "*** These should be REMOVED from primary dressing selection ***\n\n";
  foreach ($notOnMatrix as $product) {
    echo "⚠ {$product['name']} (ID: {$product['id']}, SKU: {$product['sku']})\n";
  }

  echo "\n=== SUMMARY ===\n";
  echo "Total products: " . count($dbProducts) . "\n";
  echo "Matched with wholesale pricing: " . count($matchedProducts) . "\n";
  echo "On Dressing Rule Matrix: " . (count($dbProducts) - count($notOnMatrix)) . "\n";
  echo "NOT on Matrix (remove from primary): " . count($notOnMatrix) . "\n";
  echo "Updated with wholesale pricing: {$updateCount}\n";

  echo "\n=== NEXT STEPS ===\n";
  echo "1. Review products NOT on matrix and update system to exclude them\n";
  echo "2. Review unmatched products and add wholesale pricing manually if needed\n";
  echo "3. Verify all pricing looks correct\n";
  echo "4. Update order creation logic to use price_wholesale when billed_by='practice_dme'\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
