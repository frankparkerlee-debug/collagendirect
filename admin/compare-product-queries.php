<?php
/**
 * Compare Product Queries - Referral vs Wholesale
 *
 * This diagnostic compares the products returned by:
 * 1. Referral ordering (portal/index.php - action=products)
 * 2. Wholesale ordering (portal/wholesale-new.php)
 * 3. Practice pricing page (admin/practice-pricing.php)
 *
 * Goal: Ensure ALL three use the SAME product catalog
 */

require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== PRODUCT QUERY COMPARISON ===\n";
echo "Comparing product catalogs used across Referral, Wholesale, and Practice Pricing\n\n";

// Test user ID (use a real practice for accurate results)
$testUserId = null;
$userStmt = $pdo->query("SELECT id, email FROM users WHERE role IN ('physician', 'practice_admin') LIMIT 1");
$testUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if ($testUser) {
  $testUserId = $testUser['id'];
  echo "Using test user: {$testUser['email']} (ID: {$testUserId})\n\n";
} else {
  echo "⚠️  No physician/practice_admin users found. Using NULL user_id.\n\n";
}

// ========================================
// QUERY 1: Referral Ordering (portal/index.php action=products)
// ========================================
echo "1. REFERRAL ORDERING QUERY (portal/index.php action=products)\n";
echo str_repeat("-", 80) . "\n";

try {
  $colCheck = $pdo->query("
    SELECT column_name FROM information_schema.columns
    WHERE table_name = 'products' AND column_name IN ('hcpcs_code', 'cpt_code', 'price_wholesale', 'pieces_per_box', 'category', 'can_be_primary', 'can_be_secondary', 'can_be_additional')
  ")->fetchAll(PDO::FETCH_COLUMN);

  $hcpcsCol = in_array('hcpcs_code', $colCheck) ? 'hcpcs_code' : 'cpt_code';
  $categoryCol = in_array('category', $colCheck) ? ', category' : '';
  $wholesalePriceCol = in_array('price_wholesale', $colCheck) ? ', price_wholesale' : '';
  $piecesPerBoxCol = in_array('pieces_per_box', $colCheck) ? ', pieces_per_box' : '';

  // Check for practice pricing
  $hasPracticePricing = false;
  try {
    $practiceCheckCol = $pdo->query("
      SELECT column_name FROM information_schema.columns
      WHERE table_name = 'practice_pricing'
    ")->fetchAll(PDO::FETCH_COLUMN);
    $hasPracticePricing = count($practiceCheckCol) > 0;
  } catch (Exception $e) {
    $hasPracticePricing = false;
  }

  if ($hasPracticePricing && $testUserId) {
    $sql = "SELECT p.id, p.name, p.size, p.size AS uom, p.price_admin AS price, p.{$hcpcsCol} AS hcpcs_code{$categoryCol}{$wholesalePriceCol}{$piecesPerBoxCol},
                   COALESCE(pp.custom_price, p.price_wholesale) AS effective_wholesale_price,
                   pp.discount_percentage
            FROM products p
            LEFT JOIN practice_pricing pp ON pp.product_id = p.id AND pp.user_id = ?
            WHERE p.active=TRUE
            ORDER BY p.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$testUserId]);
    $referralProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $sql = "SELECT id, name, size, size AS uom, price_admin AS price, {$hcpcsCol} AS hcpcs_code{$categoryCol}{$wholesalePriceCol}{$piecesPerBoxCol}
            FROM products WHERE active=TRUE ORDER BY name ASC";
    $referralProducts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  echo "Products returned: " . count($referralProducts) . "\n";
  echo "Query filters: active=TRUE\n";
  echo "Deprecated filter: NO\n";
  echo "Deduplication: NO\n";
  echo "\n";

} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n\n";
  $referralProducts = [];
}

// ========================================
// QUERY 2: Wholesale Ordering (portal/wholesale-new.php)
// ========================================
echo "2. WHOLESALE ORDERING QUERY (portal/wholesale-new.php)\n";
echo str_repeat("-", 80) . "\n";

try {
  if ($testUserId) {
    $stmt = $pdo->prepare("
      SELECT DISTINCT ON (
        CASE
          WHEN p.hcpcs_code IS NOT NULL AND p.hcpcs_code != '' THEN p.hcpcs_code || '|' || LOWER(TRIM(COALESCE(p.size, '')))
          ELSE 'NO_HCPCS|' || LOWER(TRIM(p.name)) || '|' || LOWER(TRIM(COALESCE(p.size, '')))
        END
      )
        p.*,
        pp.custom_price,
        pp.discount_percentage,
        CASE
          WHEN pp.custom_price IS NOT NULL AND pp.custom_price > 0 THEN pp.custom_price
          WHEN pp.discount_percentage IS NOT NULL AND pp.discount_percentage > 0 THEN
            (p.price_wholesale / p.pieces_per_box) * (1 - pp.discount_percentage / 100)
          ELSE (p.price_wholesale / p.pieces_per_box)
        END as effective_price_per_piece
      FROM products p
      LEFT JOIN practice_pricing pp ON pp.product_id = p.id AND pp.user_id = ?
      WHERE p.active = true
        AND (p.name NOT ILIKE '%deprecated%' OR p.name IS NULL)
        AND (p.category NOT ILIKE '%deprecated%' OR p.category IS NULL)
      ORDER BY
        CASE
          WHEN p.hcpcs_code IS NOT NULL AND p.hcpcs_code != '' THEN p.hcpcs_code || '|' || LOWER(TRIM(COALESCE(p.size, '')))
          ELSE 'NO_HCPCS|' || LOWER(TRIM(p.name)) || '|' || LOWER(TRIM(COALESCE(p.size, '')))
        END,
        CASE WHEN p.hcpcs_code IS NOT NULL AND p.hcpcs_code != '' THEN 0 ELSE 1 END,
        CASE WHEN p.price_wholesale > 0 THEN 0 ELSE 1 END,
        LENGTH(p.name) DESC,
        p.id ASC
    ");
    $stmt->execute([$testUserId]);
    $wholesaleProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $stmt = $pdo->query("
      SELECT DISTINCT ON (
        CASE
          WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN hcpcs_code || '|' || LOWER(TRIM(COALESCE(size, '')))
          ELSE 'NO_HCPCS|' || LOWER(TRIM(name)) || '|' || LOWER(TRIM(COALESCE(size, '')))
        END
      )
        *
      FROM products
      WHERE active = true
        AND (name NOT ILIKE '%deprecated%' OR name IS NULL)
        AND (category NOT ILIKE '%deprecated%' OR category IS NULL)
      ORDER BY
        CASE
          WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN hcpcs_code || '|' || LOWER(TRIM(COALESCE(size, '')))
          ELSE 'NO_HCPCS|' || LOWER(TRIM(name)) || '|' || LOWER(TRIM(COALESCE(size, '')))
        END,
        id ASC
    ");
    $wholesaleProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  echo "Products returned: " . count($wholesaleProducts) . "\n";
  echo "Query filters: active=true, NOT deprecated (name/category)\n";
  echo "Deprecated filter: YES (excludes %deprecated%)\n";
  echo "Deduplication: YES (DISTINCT ON HCPCS+size or name+size)\n";
  echo "\n";

} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n\n";
  $wholesaleProducts = [];
}

// ========================================
// QUERY 3: Practice Pricing (admin/practice-pricing.php)
// ========================================
echo "3. PRACTICE PRICING QUERY (admin/practice-pricing.php)\n";
echo str_repeat("-", 80) . "\n";

try {
  $stmt = $pdo->query("
    SELECT DISTINCT ON (
      CASE
        WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN hcpcs_code || '|' || LOWER(TRIM(COALESCE(size, '')))
        ELSE 'NO_HCPCS|' || LOWER(TRIM(name)) || '|' || LOWER(TRIM(COALESCE(size, '')))
      END
    )
      id, name, size, price_wholesale, pieces_per_box, category, hcpcs_code
    FROM products
    WHERE active = TRUE
      AND (name NOT ILIKE '%deprecated%' OR name IS NULL)
      AND (category NOT ILIKE '%deprecated%' OR category IS NULL)
    ORDER BY
      CASE
        WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN hcpcs_code || '|' || LOWER(TRIM(COALESCE(size, '')))
        ELSE 'NO_HCPCS|' || LOWER(TRIM(name)) || '|' || LOWER(TRIM(COALESCE(size, '')))
      END,
      CASE WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN 0 ELSE 1 END,
      CASE WHEN price_wholesale > 0 THEN 0 ELSE 1 END,
      LENGTH(name) DESC,
      id ASC
  ");
  $practicePricingProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Products returned: " . count($practicePricingProducts) . "\n";
  echo "Query filters: active=TRUE, NOT deprecated (name/category)\n";
  echo "Deprecated filter: YES (excludes %deprecated%)\n";
  echo "Deduplication: YES (DISTINCT ON HCPCS+size or name+size)\n";
  echo "\n";

} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n\n";
  $practicePricingProducts = [];
}

// ========================================
// COMPARISON ANALYSIS
// ========================================
echo "\n" . str_repeat("=", 80) . "\n";
echo "COMPARISON ANALYSIS\n";
echo str_repeat("=", 80) . "\n\n";

// Extract product IDs and names for comparison
$referralIds = array_column($referralProducts, 'id');
$wholesaleIds = array_column($wholesaleProducts, 'id');
$pricingIds = array_column($practicePricingProducts, 'id');

echo "Product Count Summary:\n";
echo "  Referral ordering:   " . count($referralIds) . " products\n";
echo "  Wholesale ordering:  " . count($wholesaleIds) . " products\n";
echo "  Practice pricing:    " . count($pricingIds) . " products\n\n";

// Find products ONLY in referral (not in wholesale/pricing)
$onlyInReferral = array_diff($referralIds, $wholesaleIds, $pricingIds);
if (!empty($onlyInReferral)) {
  echo "⚠️  PRODUCTS ONLY IN REFERRAL ORDERING (" . count($onlyInReferral) . "):\n";
  foreach ($onlyInReferral as $id) {
    $product = array_values(array_filter($referralProducts, fn($p) => $p['id'] == $id))[0] ?? null;
    if ($product) {
      echo "  - ID: {$id} | {$product['name']} | Size: {$product['size']}\n";
    }
  }
  echo "\n";
} else {
  echo "✓ No products exclusive to referral ordering\n\n";
}

// Find products ONLY in wholesale (not in referral)
$onlyInWholesale = array_diff($wholesaleIds, $referralIds);
if (!empty($onlyInWholesale)) {
  echo "⚠️  PRODUCTS ONLY IN WHOLESALE ORDERING (" . count($onlyInWholesale) . "):\n";
  foreach ($onlyInWholesale as $id) {
    $product = array_values(array_filter($wholesaleProducts, fn($p) => $p['id'] == $id))[0] ?? null;
    if ($product) {
      echo "  - ID: {$id} | {$product['name']} | Size: {$product['size']}\n";
    }
  }
  echo "\n";
} else {
  echo "✓ No products exclusive to wholesale ordering\n\n";
}

// Find products ONLY in practice pricing (not in referral)
$onlyInPricing = array_diff($pricingIds, $referralIds);
if (!empty($onlyInPricing)) {
  echo "⚠️  PRODUCTS ONLY IN PRACTICE PRICING (" . count($onlyInPricing) . "):\n";
  foreach ($onlyInPricing as $id) {
    $product = array_values(array_filter($practicePricingProducts, fn($p) => $p['id'] == $id))[0] ?? null;
    if ($product) {
      echo "  - ID: {$id} | {$product['name']} | Size: {$product['size']}\n";
    }
  }
  echo "\n";
} else {
  echo "✓ No products exclusive to practice pricing\n\n";
}

// Check if ALL products match
$wholesaleMatchesReferral = (count(array_diff($wholesaleIds, $referralIds)) === 0 && count(array_diff($referralIds, $wholesaleIds)) === 0);
$pricingMatchesReferral = (count(array_diff($pricingIds, $referralIds)) === 0 && count(array_diff($referralIds, $pricingIds)) === 0);

echo str_repeat("=", 80) . "\n";
echo "FINAL VERDICT\n";
echo str_repeat("=", 80) . "\n\n";

if (count($referralIds) === count($wholesaleIds) && count($wholesaleIds) === count($pricingIds) && $wholesaleMatchesReferral && $pricingMatchesReferral) {
  echo "✅ SUCCESS: All three queries return IDENTICAL product catalogs!\n";
  echo "   - All " . count($referralIds) . " products are available in referral, wholesale, and pricing\n";
  echo "   - No discrepancies found\n\n";
} else {
  echo "❌ MISMATCH DETECTED: Product catalogs are NOT identical!\n\n";

  echo "ISSUE: Different filtering logic across queries:\n";
  echo "  1. Referral ordering: Uses active=TRUE only (NO deprecated filter, NO deduplication)\n";
  echo "  2. Wholesale ordering: Uses active=true + deprecated filter + deduplication\n";
  echo "  3. Practice pricing: Uses active=TRUE + deprecated filter + deduplication\n\n";

  echo "RECOMMENDATION:\n";
  echo "  Apply SAME filters to all three queries:\n";
  echo "  - active = TRUE\n";
  echo "  - Exclude deprecated products (name/category NOT ILIKE '%deprecated%')\n";
  echo "  - Apply DISTINCT ON deduplication (by HCPCS+size or name+size)\n\n";

  echo "This will ensure physicians see IDENTICAL product lists in:\n";
  echo "  - Referral patient orders\n";
  echo "  - Wholesale bulk orders\n";
  echo "  - Practice pricing configuration\n\n";
}

// Show sample products from each query
echo str_repeat("=", 80) . "\n";
echo "SAMPLE PRODUCTS (first 5 from each query)\n";
echo str_repeat("=", 80) . "\n\n";

echo "Referral Ordering:\n";
foreach (array_slice($referralProducts, 0, 5) as $i => $p) {
  echo "  " . ($i+1) . ". ID: {$p['id']} | {$p['name']} | {$p['size']}\n";
}

echo "\nWholesale Ordering:\n";
foreach (array_slice($wholesaleProducts, 0, 5) as $i => $p) {
  echo "  " . ($i+1) . ". ID: {$p['id']} | {$p['name']} | {$p['size']}\n";
}

echo "\nPractice Pricing:\n";
foreach (array_slice($practicePricingProducts, 0, 5) as $i => $p) {
  echo "  " . ($i+1) . ". ID: {$p['id']} | {$p['name']} | {$p['size']}\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "End of diagnostic\n";
