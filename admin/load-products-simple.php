<?php
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Simple Product Catalog Loader ===\n\n";

// Step 1: Add required columns if they don't exist
echo "Step 1: Adding required columns...\n";

$columns = [
    'category' => "VARCHAR(100)",
    'hcpcs_code' => "VARCHAR(50)",
    'hcpcs_description' => "TEXT",
    'bill_rate_min' => "DECIMAL(10,2)",
    'bill_rate_max' => "DECIMAL(10,2)",
    'reimbursement_amount' => "DECIMAL(10,2)"
];

foreach ($columns as $colName => $colType) {
    try {
        $check = $pdo->query("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_name = 'products'
            AND column_name = '$colName'
        ");

        if ($check->rowCount() === 0) {
            echo "  Adding column: $colName...\n";
            $pdo->exec("ALTER TABLE products ADD COLUMN $colName $colType");
        } else {
            echo "  ✓ Column exists: $colName\n";
        }
    } catch (PDOException $e) {
        echo "  ✗ Error with $colName: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Step 2: Clear existing products
echo "Step 2: Clearing old products...\n";
$pdo->exec("DELETE FROM products");
echo "✓ Product table cleared\n\n";

// Step 3: Insert new products
echo "Step 3: Inserting new products...\n";

$products = [
    ['CollaHeal Collagen Wound Dressing', '2x2 in', 'CH-COL-2X2', 'Collagen Sheet', 'A6021', 'Collagen dressing ≤ 16 sq in', 16.44],
    ['CollaHeal Collagen Wound Dressing', '7x7 in', 'CH-COL-7X7', 'Collagen Sheet', 'A6023', 'Collagen dressing > 48 sq in', 52.70],
    ['CollaHeal Collagen Powder', '1 g', 'CH-POW-1G', 'Collagen Particulate', 'A6010', 'Collagen wound filler, per gram', 24.16],
    ['AlgiHeal Alginate Dressing', '2x2 in', 'AH-ALG-2X2', 'Alginate', 'A6196', 'Alginate dressing ≤ 16 sq in', 6.28],
    ['AlgiHeal Alginate Dressing', '4.33x4.33 in', 'AH-ALG-4X4', 'Alginate', 'A6197', 'Alginate dressing > 16 ≤ 48 sq in', 9.02],
    ['AlgiHeal Alginate Dressing', '6x6 in', 'AH-ALG-6X6', 'Alginate', 'A6198', 'Alginate dressing > 48 ≤ 100 sq in', 12.44],
    ['AlgiHeal AG Silver Alginate', '2x2 in', 'AH-AG-2X2', 'Silver Alginate', 'A6196 + AW', 'Alginate dressing with silver', 11.50],
    ['HydraPad Super Absorbent', '2x2 in', 'HP-SA-2X2', 'Super-Absorbent', 'A6222', 'Hydrocolloid/superabsorbent ≤ 16 sq in', 8.34],
    ['HydraPad Super Absorbent', '8x8 in', 'HP-SA-8X8', 'Super-Absorbent', 'A6224', 'Hydrocolloid/superabsorbent > 48 sq in', 17.92],
    ['CuraFoam Silicone Foam', '2x2 in', 'CF-FOAM-2X2', 'Foam Dressing', 'A6212', 'Foam dressing w/ border ≤ 16 sq in', 10.77],
    ['CuraFoam Silicone Foam', '6x6 in', 'CF-FOAM-6X6', 'Foam Dressing', 'A6215', 'Foam dressing w/ border > 48 sq in', 20.43],
    ['HydraCare Amorphous Hydrogel', '0.9 oz', 'HC-GEL-0.9OZ', 'Hydrogel', 'A6248', 'Hydrogel dressing, wound filler', 6.85],
    ['HydraCare AG Silver Hydrogel', '0.9 oz', 'HC-AG-0.9OZ', 'Silver Hydrogel', 'A6249', 'Hydrogel w/ antimicrobial silver', 12.10],
    ['15-Day Collagen Kit', '15-Day', 'KIT-COL-15', 'Kit', 'A6010 + A6212', 'Collagen filler + foam secondary', 85.00],
    ['30-Day Collagen Kit', '30-Day', 'KIT-COL-30', 'Kit', 'A6010 + A6212', 'Collagen filler + foam secondary', 160.00],
    ['15-Day Alginate Kit', '15-Day', 'KIT-ALG-15', 'Kit', 'A6196 + A6212', 'Alginate primary + foam secondary', 75.00],
    ['15-Day Silver Alginate Kit', '15-Day', 'KIT-AG-15', 'Kit', 'A6196 + AW + A6212', 'Silver alginate + foam secondary', 95.00],
];

$inserted = 0;

foreach ($products as $p) {
    list($name, $size, $sku, $category, $hcpcs, $description, $price) = $p;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO products (
                name, size, sku, category, hcpcs_code, cpt_code,
                hcpcs_description, bill_rate_min, bill_rate_max,
                price_admin, reimbursement_amount, active
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE
            )
        ");

        $stmt->execute([
            $name,
            $size,
            $sku,
            $category,
            $hcpcs,
            null, // cpt_code (deprecated)
            $description,
            $price,
            $price,
            $price,
            $price
        ]);

        echo "✓ $name ($size) - $hcpcs - \$$price\n";
        $inserted++;
    } catch (PDOException $e) {
        echo "✗ Error inserting $name: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Summary ===\n";
echo "Products inserted: $inserted / " . count($products) . "\n";

if ($inserted === count($products)) {
    echo "\n✓ All products loaded successfully!\n";
    echo "\nProducts are now visible in the portal.\n";
} else {
    echo "\n⚠ Some products failed to insert. Check errors above.\n";
}

echo "\n=== Complete ===\n";
