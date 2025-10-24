#!/usr/bin/env php
<?php
/**
 * Add Complete Product Catalog
 *
 * This script adds all products from the public website to the database.
 * It uses UPSERT (INSERT ... ON CONFLICT) to safely add or update products.
 */

require __DIR__ . '/api/db.php';

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "Adding Complete Product Catalog to Database\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // Check current product count
    $currentCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "Current products in database: $currentCount\n\n";

    // Matrix Products
    echo "Adding Matrix Products...\n";
    $matrixProducts = [
        [
            'sku' => 'COLL-MTX-2X2',
            'name' => 'Collagen Matrix 2×2',
            'description' => 'Sheet matrix for DFU/VLU & pressure ulcers. Absorbent collagen matrix that supports wound healing.',
            'price_admin' => 45.00,
            'price_wholesale' => 30.00,
            'category' => 'matrix',
            'size' => '2×2 in',
            'hcpcs_code' => 'A6196',
            'cpt_code' => '97597'
        ],
        [
            'sku' => 'COLL-MTX-3X3',
            'name' => 'Collagen Matrix 3×3',
            'description' => 'Absorbent matrix supporting epithelialization. Medium-sized collagen sheet for moderate wound coverage.',
            'price_admin' => 75.00,
            'price_wholesale' => 50.00,
            'category' => 'matrix',
            'size' => '3×3 in',
            'hcpcs_code' => 'A6197',
            'cpt_code' => '97597'
        ],
        [
            'sku' => 'COLL-MTX-4X4',
            'name' => 'Collagen Matrix 4×4',
            'description' => 'Larger coverage for exuding wounds. High-absorbency collagen matrix for moderate to heavy exudate.',
            'price_admin' => 95.00,
            'price_wholesale' => 65.00,
            'category' => 'matrix',
            'size' => '4×4 in',
            'hcpcs_code' => 'A6197',
            'cpt_code' => '97597'
        ]
    ];

    // Powder Products
    echo "Adding Powder Products...\n";
    $powderProducts = [
        [
            'sku' => 'COLL-PWD-1G',
            'name' => 'Collagen Powder 1 g',
            'description' => 'Maintains moist wound environment for granulation. Micronized collagen powder for small to medium wounds.',
            'price_admin' => 55.00,
            'price_wholesale' => 38.00,
            'category' => 'powder',
            'size' => '1 g',
            'hcpcs_code' => 'A6010',
            'cpt_code' => '97597'
        ],
        [
            'sku' => 'COLL-PWD-3G',
            'name' => 'Collagen Powder 3 g',
            'description' => 'Higher volume for large or tunneling wounds. Particulate collagen for deep or irregularly shaped wounds.',
            'price_admin' => 125.00,
            'price_wholesale' => 85.00,
            'category' => 'powder',
            'size' => '3 g',
            'hcpcs_code' => 'A6010',
            'cpt_code' => '97597'
        ]
    ];

    // Antimicrobial Products
    echo "Adding Antimicrobial Products...\n";
    $antimicrobialProducts = [
        [
            'sku' => 'COLL-AG-2X2',
            'name' => 'Antimicrobial Collagen 2×2',
            'description' => 'Silver-infused collagen for bioburden management. Silver-composite collagen sheet for infected or at-risk wounds.',
            'price_admin' => 85.00,
            'price_wholesale' => 58.00,
            'category' => 'antimicrobial',
            'size' => '2×2 in',
            'hcpcs_code' => 'A6196',
            'cpt_code' => '97597'
        ],
        [
            'sku' => 'COLL-AG-4X4',
            'name' => 'Antimicrobial Collagen 4×4',
            'description' => 'Larger silver-collagen composite for infected/exuding wounds. Broad-spectrum antimicrobial protection with collagen matrix.',
            'price_admin' => 135.00,
            'price_wholesale' => 92.00,
            'category' => 'antimicrobial',
            'size' => '4×4 in',
            'hcpcs_code' => 'A6197',
            'cpt_code' => '97597'
        ],
        [
            'sku' => 'COLL-AG-PWD-1G',
            'name' => 'Antimicrobial Collagen Powder 1 g',
            'description' => 'Conforms to irregular topography; assists with bioburden. Silver-infused collagen powder for undermined or tunneling wounds.',
            'price_admin' => 95.00,
            'price_wholesale' => 65.00,
            'category' => 'antimicrobial',
            'size' => '1 g',
            'hcpcs_code' => 'A6010',
            'cpt_code' => '97597'
        ]
    ];

    // Combine all products
    $allProducts = array_merge($matrixProducts, $powderProducts, $antimicrobialProducts);

    // Prepare INSERT statement with ON CONFLICT
    $stmt = $pdo->prepare("
        INSERT INTO products (
            sku, name, description, price_admin, price_wholesale,
            category, size, hcpcs_code, cpt_code, active, created_at
        ) VALUES (
            :sku, :name, :description, :price_admin, :price_wholesale,
            :category, :size, :hcpcs_code, :cpt_code, TRUE, CURRENT_TIMESTAMP
        )
        ON CONFLICT (sku) DO UPDATE SET
            name = EXCLUDED.name,
            description = EXCLUDED.description,
            price_admin = EXCLUDED.price_admin,
            price_wholesale = EXCLUDED.price_wholesale,
            category = EXCLUDED.category,
            size = EXCLUDED.size,
            hcpcs_code = EXCLUDED.hcpcs_code,
            cpt_code = EXCLUDED.cpt_code,
            active = EXCLUDED.active
    ");

    $added = 0;
    $updated = 0;

    foreach ($allProducts as $product) {
        // Check if product exists
        $exists = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $exists->execute([$product['sku']]);
        $isUpdate = $exists->fetch() !== false;

        // Execute upsert
        $stmt->execute($product);

        if ($isUpdate) {
            echo "  ✓ Updated: {$product['name']} ({$product['size']})\n";
            $updated++;
        } else {
            echo "  ✓ Added: {$product['name']} ({$product['size']})\n";
            $added++;
        }
    }

    echo "\n" . str_repeat("-", 60) . "\n";
    echo "Products added: $added\n";
    echo "Products updated: $updated\n";

    // Show final product list
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Current Product Catalog:\n";
    echo str_repeat("=", 60) . "\n\n";

    $products = $pdo->query("
        SELECT id, sku, name, category, size, hcpcs_code, price_admin, active
        FROM products
        WHERE active = TRUE
        ORDER BY category, size
    ")->fetchAll(PDO::FETCH_ASSOC);

    $lastCategory = '';
    foreach ($products as $p) {
        if ($p['category'] !== $lastCategory) {
            echo "\n" . strtoupper($p['category']) . ":\n";
            $lastCategory = $p['category'];
        }
        $price = number_format($p['price_admin'], 2);
        echo sprintf("  [%d] %-35s %8s  HCPCS: %-6s  $%s\n",
            $p['id'],
            $p['name'],
            $p['size'],
            $p['hcpcs_code'],
            $price
        );
    }

    $totalCount = count($products);
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Total active products: $totalCount\n";
    echo str_repeat("=", 60) . "\n\n";

    echo "✅ Product catalog successfully updated!\n";
    echo "Users can now see all products when creating orders.\n\n";

} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
