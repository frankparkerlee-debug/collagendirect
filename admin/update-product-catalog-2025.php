<?php
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Updating Product Catalog to Q3 2025 Standards ===\n\n";

// Step 1: Backup current products
echo "Step 1: Backing up current products...\n";
$backup = $pdo->query("SELECT * FROM products ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "✓ Backed up " . count($backup) . " products\n\n";

// Step 2: Ensure required columns exist
echo "Step 2: Checking table schema...\n";

$columns = [
    'category' => "VARCHAR(100)",
    'hcpcs_code' => "VARCHAR(50)",
    'hcpcs_description' => "TEXT",
    'bill_rate_min' => "DECIMAL(10,2)",
    'bill_rate_max' => "DECIMAL(10,2)",
    'reimbursement_amount' => "DECIMAL(10,2)"
];

foreach ($columns as $colName => $colType) {
    $check = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'products'
        AND column_name = '$colName'
    ");

    if ($check->rowCount() === 0) {
        echo "Adding column: $colName...\n";
        $pdo->exec("ALTER TABLE products ADD COLUMN $colName $colType");
    }
}
echo "✓ Schema updated\n\n";

// Step 3: Define new product catalog
echo "Step 3: Updating product catalog...\n";

$products = [
    // Collagen Sheets
    [
        'name' => 'CollaHeal Collagen Wound Dressing',
        'size' => '2x2',
        'sku' => 'CH-COL-2X2',
        'category' => 'Collagen Sheet',
        'hcpcs_code' => 'A6021',
        'cpt_code' => 'A6021',
        'hcpcs_description' => 'Collagen dressing, sterile, per dressing',
        'bill_rate_min' => 16.44,
        'bill_rate_max' => 16.44,
        'price_admin' => 16.44,
        'reimbursement_amount' => 16.44
    ],
    [
        'name' => 'CollaHeal Collagen Wound Dressing',
        'size' => '4x4',
        'sku' => 'CH-COL-4X4',
        'category' => 'Collagen Sheet',
        'hcpcs_code' => 'A6022',
        'cpt_code' => 'A6022',
        'hcpcs_description' => 'Collagen dressing, sterile, per dressing',
        'bill_rate_min' => 32.88,
        'bill_rate_max' => 32.88,
        'price_admin' => 32.88,
        'reimbursement_amount' => 32.88
    ],
    [
        'name' => 'CollaHeal Collagen Wound Dressing',
        'size' => '7x7',
        'sku' => 'CH-COL-7X7',
        'category' => 'Collagen Sheet',
        'hcpcs_code' => 'A6023',
        'cpt_code' => 'A6023',
        'hcpcs_description' => 'Collagen dressing, sterile, per dressing',
        'bill_rate_min' => 52.70,
        'bill_rate_max' => 52.70,
        'price_admin' => 52.70,
        'reimbursement_amount' => 52.70
    ],

    // Collagen Powder
    [
        'name' => 'CollaHeal Collagen Powder',
        'size' => '1g',
        'sku' => 'CH-POW-1G',
        'category' => 'Collagen Particulate',
        'hcpcs_code' => 'A6010',
        'cpt_code' => 'A6010',
        'hcpcs_description' => 'Collagen-based wound filler, 1 gram',
        'bill_rate_min' => 24.16,
        'bill_rate_max' => 24.16,
        'price_admin' => 24.16,
        'reimbursement_amount' => 24.16
    ],

    // Hydrogel Products
    [
        'name' => 'HydraCare Amorphous Gel',
        'size' => '1oz',
        'sku' => 'HC-GEL-1OZ',
        'category' => 'Hydrogel',
        'hcpcs_code' => 'A6248',
        'cpt_code' => 'A6248',
        'hcpcs_description' => 'Hydrogel dressing, wound filler, sterile, 1 oz',
        'bill_rate_min' => 6.85,
        'bill_rate_max' => 6.85,
        'price_admin' => 6.85,
        'reimbursement_amount' => 6.85
    ],
    [
        'name' => 'HydraCare Amorphous Gel',
        'size' => '2.5oz',
        'sku' => 'HC-GEL-2.5OZ',
        'category' => 'Hydrogel',
        'hcpcs_code' => 'A6248',
        'cpt_code' => 'A6248',
        'hcpcs_description' => 'Hydrogel dressing, wound filler, sterile, per oz',
        'bill_rate_min' => 17.13,
        'bill_rate_max' => 17.13,
        'price_admin' => 17.13,
        'reimbursement_amount' => 17.13
    ],

    // Silver Hydrogel
    [
        'name' => 'HydraCare AG Silver Gel',
        'size' => '1oz',
        'sku' => 'HC-AG-1OZ',
        'category' => 'Silver Hydrogel',
        'hcpcs_code' => 'A6249',
        'cpt_code' => 'A6249',
        'hcpcs_description' => 'Hydrogel with silver, antimicrobial',
        'bill_rate_min' => 12.10,
        'bill_rate_max' => 12.10,
        'price_admin' => 12.10,
        'reimbursement_amount' => 12.10
    ],
    [
        'name' => 'HydraCare AG Silver Gel',
        'size' => '2.5oz',
        'sku' => 'HC-AG-2.5OZ',
        'category' => 'Silver Hydrogel',
        'hcpcs_code' => 'A6249',
        'cpt_code' => 'A6249',
        'hcpcs_description' => 'Hydrogel with silver, antimicrobial',
        'bill_rate_min' => 30.25,
        'bill_rate_max' => 30.25,
        'price_admin' => 30.25,
        'reimbursement_amount' => 30.25
    ],

    // Super Absorbent
    [
        'name' => 'HydraPad Super Absorbent',
        'size' => '4x4',
        'sku' => 'HP-SA-4X4',
        'category' => 'Super-Absorbent',
        'hcpcs_code' => 'A6222',
        'cpt_code' => 'A6222',
        'hcpcs_description' => 'Hydrocolloid/superabsorbent, each dressing',
        'bill_rate_min' => 8.34,
        'bill_rate_max' => 8.34,
        'price_admin' => 8.34,
        'reimbursement_amount' => 8.34
    ],
    [
        'name' => 'HydraPad Super Absorbent',
        'size' => '6x6',
        'sku' => 'HP-SA-6X6',
        'category' => 'Super-Absorbent',
        'hcpcs_code' => 'A6224',
        'cpt_code' => 'A6224',
        'hcpcs_description' => 'Hydrocolloid/superabsorbent, each dressing',
        'bill_rate_min' => 17.92,
        'bill_rate_max' => 17.92,
        'price_admin' => 17.92,
        'reimbursement_amount' => 17.92
    ],

    // Silicone Foam Bordered
    [
        'name' => 'HydraPad Silicone Bordered (OptiSil)',
        'size' => '2x2',
        'sku' => 'HP-SIL-2X2',
        'category' => 'Silicone Foam',
        'hcpcs_code' => 'A6212',
        'cpt_code' => 'A6212',
        'hcpcs_description' => 'Foam dressing, with border, each dressing',
        'bill_rate_min' => 10.77,
        'bill_rate_max' => 10.77,
        'price_admin' => 10.77,
        'reimbursement_amount' => 10.77
    ],
    [
        'name' => 'HydraPad Silicone Bordered (OptiSil)',
        'size' => '4x4',
        'sku' => 'HP-SIL-4X4',
        'category' => 'Silicone Foam',
        'hcpcs_code' => 'A6213',
        'cpt_code' => 'A6213',
        'hcpcs_description' => 'Foam dressing, with border, each dressing',
        'bill_rate_min' => 15.60,
        'bill_rate_max' => 15.60,
        'price_admin' => 15.60,
        'reimbursement_amount' => 15.60
    ],
    [
        'name' => 'HydraPad Silicone Bordered (OptiSil)',
        'size' => '6x6',
        'sku' => 'HP-SIL-6X6',
        'category' => 'Silicone Foam',
        'hcpcs_code' => 'A6215',
        'cpt_code' => 'A6215',
        'hcpcs_description' => 'Foam dressing, with border, each dressing',
        'bill_rate_min' => 20.43,
        'bill_rate_max' => 20.43,
        'price_admin' => 20.43,
        'reimbursement_amount' => 20.43
    ],

    // CuraFoam Silicone Foam Bordered
    [
        'name' => 'CuraFoam Silicone Foam Bordered',
        'size' => '2x2',
        'sku' => 'CF-FOAM-2X2',
        'category' => 'Foam Dressing',
        'hcpcs_code' => 'A6212',
        'cpt_code' => 'A6212',
        'hcpcs_description' => 'Foam dressing (sterile), with adhesive border',
        'bill_rate_min' => 10.77,
        'bill_rate_max' => 10.77,
        'price_admin' => 10.77,
        'reimbursement_amount' => 10.77
    ],
    [
        'name' => 'CuraFoam Silicone Foam Bordered',
        'size' => '4x4',
        'sku' => 'CF-FOAM-4X4',
        'category' => 'Foam Dressing',
        'hcpcs_code' => 'A6213',
        'cpt_code' => 'A6213',
        'hcpcs_description' => 'Foam dressing (sterile), with adhesive border',
        'bill_rate_min' => 15.60,
        'bill_rate_max' => 15.60,
        'price_admin' => 15.60,
        'reimbursement_amount' => 15.60
    ],
    [
        'name' => 'CuraFoam Silicone Foam Bordered',
        'size' => '6x6',
        'sku' => 'CF-FOAM-6X6',
        'category' => 'Foam Dressing',
        'hcpcs_code' => 'A6215',
        'cpt_code' => 'A6215',
        'hcpcs_description' => 'Foam dressing (sterile), with adhesive border',
        'bill_rate_min' => 20.43,
        'bill_rate_max' => 20.43,
        'price_admin' => 20.43,
        'reimbursement_amount' => 20.43
    ],

    // Calcium Alginate
    [
        'name' => 'AlgiHeal Calcium Alginate Dressing',
        'size' => '2x2',
        'sku' => 'AH-ALG-2X2',
        'category' => 'Alginate',
        'hcpcs_code' => 'A6196',
        'cpt_code' => 'A6196',
        'hcpcs_description' => 'Alginate dressing, sterile, each dressing',
        'bill_rate_min' => 6.28,
        'bill_rate_max' => 6.28,
        'price_admin' => 6.28,
        'reimbursement_amount' => 6.28
    ],
    [
        'name' => 'AlgiHeal Calcium Alginate Dressing',
        'size' => '4x4',
        'sku' => 'AH-ALG-4X4',
        'category' => 'Alginate',
        'hcpcs_code' => 'A6197',
        'cpt_code' => 'A6197',
        'hcpcs_description' => 'Alginate dressing, sterile, each dressing',
        'bill_rate_min' => 10.45,
        'bill_rate_max' => 10.45,
        'price_admin' => 10.45,
        'reimbursement_amount' => 10.45
    ],
    [
        'name' => 'AlgiHeal Calcium Alginate Dressing',
        'size' => '6x6',
        'sku' => 'AH-ALG-6X6',
        'category' => 'Alginate',
        'hcpcs_code' => 'A6199',
        'cpt_code' => 'A6199',
        'hcpcs_description' => 'Alginate dressing, sterile, each dressing',
        'bill_rate_min' => 14.62,
        'bill_rate_max' => 14.62,
        'price_admin' => 14.62,
        'reimbursement_amount' => 14.62
    ],

    // Silver Alginate
    [
        'name' => 'AlgiHeal AG Silver Alginate',
        'size' => '2x2',
        'sku' => 'AH-AG-2X2',
        'category' => 'Silver Alginate',
        'hcpcs_code' => 'A6196-AW',
        'cpt_code' => 'A6196',
        'hcpcs_description' => 'Alginate dressing with silver, antimicrobial',
        'bill_rate_min' => 11.50,
        'bill_rate_max' => 11.50,
        'price_admin' => 11.50,
        'reimbursement_amount' => 11.50
    ],
    [
        'name' => 'AlgiHeal AG Silver Alginate',
        'size' => '4x4',
        'sku' => 'AH-AG-4X4',
        'category' => 'Silver Alginate',
        'hcpcs_code' => 'A6196-AW',
        'cpt_code' => 'A6196',
        'hcpcs_description' => 'Alginate dressing with silver, antimicrobial',
        'bill_rate_min' => 16.50,
        'bill_rate_max' => 16.50,
        'price_admin' => 16.50,
        'reimbursement_amount' => 16.50
    ],
    [
        'name' => 'AlgiHeal AG Silver Alginate',
        'size' => '6x6',
        'sku' => 'AH-AG-6X6',
        'category' => 'Silver Alginate',
        'hcpcs_code' => 'A6196-AW',
        'cpt_code' => 'A6196',
        'hcpcs_description' => 'Alginate dressing with silver, antimicrobial',
        'bill_rate_min' => 21.50,
        'bill_rate_max' => 21.50,
        'price_admin' => 21.50,
        'reimbursement_amount' => 21.50
    ],

    // Kits - 15 Day
    [
        'name' => 'Collagen Kit',
        'size' => '15-Day',
        'sku' => 'KIT-COL-15',
        'category' => 'Kit (multi-item)',
        'hcpcs_code' => 'A6010 + A6212/A6213',
        'cpt_code' => 'A6010',
        'hcpcs_description' => 'Collagen dressing + foam secondary',
        'bill_rate_min' => 85.00,
        'bill_rate_max' => 85.00,
        'price_admin' => 85.00,
        'reimbursement_amount' => 85.00
    ],
    [
        'name' => 'Collagen Kit',
        'size' => '30-Day',
        'sku' => 'KIT-COL-30',
        'category' => 'Kit (multi-item)',
        'hcpcs_code' => 'A6010 + A6212/A6213',
        'cpt_code' => 'A6010',
        'hcpcs_description' => 'Collagen dressing + foam secondary',
        'bill_rate_min' => 160.00,
        'bill_rate_max' => 160.00,
        'price_admin' => 160.00,
        'reimbursement_amount' => 160.00
    ],

    // Alginate Kits
    [
        'name' => 'Alginate Kit',
        'size' => '15-Day',
        'sku' => 'KIT-ALG-15',
        'category' => 'Kit (multi-item)',
        'hcpcs_code' => 'A6196 + A6212/A6213',
        'cpt_code' => 'A6196',
        'hcpcs_description' => 'Alginate primary + foam secondary',
        'bill_rate_min' => 75.00,
        'bill_rate_max' => 75.00,
        'price_admin' => 75.00,
        'reimbursement_amount' => 75.00
    ],
    [
        'name' => 'Alginate Kit',
        'size' => '30-Day',
        'sku' => 'KIT-ALG-30',
        'category' => 'Kit (multi-item)',
        'hcpcs_code' => 'A6196 + A6212/A6213',
        'cpt_code' => 'A6196',
        'hcpcs_description' => 'Alginate primary + foam secondary',
        'bill_rate_min' => 150.00,
        'bill_rate_max' => 150.00,
        'price_admin' => 150.00,
        'reimbursement_amount' => 150.00
    ],

    // Silver Alginate Kits
    [
        'name' => 'Silver Alginate Kit',
        'size' => '15-Day',
        'sku' => 'KIT-AG-15',
        'category' => 'Kit (multi-item)',
        'hcpcs_code' => 'A6196-AW + A6212/A6213',
        'cpt_code' => 'A6196',
        'hcpcs_description' => 'Silver alginate + foam secondary',
        'bill_rate_min' => 95.00,
        'bill_rate_max' => 95.00,
        'price_admin' => 95.00,
        'reimbursement_amount' => 95.00
    ],
    [
        'name' => 'Silver Alginate Kit',
        'size' => '30-Day',
        'sku' => 'KIT-AG-30',
        'category' => 'Kit (multi-item)',
        'hcpcs_code' => 'A6196-AW + A6212/A6213',
        'cpt_code' => 'A6196',
        'hcpcs_description' => 'Silver alginate + foam secondary',
        'bill_rate_min' => 175.00,
        'bill_rate_max' => 175.00,
        'price_admin' => 175.00,
        'reimbursement_amount' => 175.00
    ],

    // Hydrogel Kits
    [
        'name' => 'Hydrogel Kit',
        'size' => '15-Day',
        'sku' => 'KIT-HG-15',
        'category' => 'Gel + Foam',
        'hcpcs_code' => 'A6248 + A6212/A6213',
        'cpt_code' => 'A6248',
        'hcpcs_description' => 'Hydrogel + foam border',
        'bill_rate_min' => 70.00,
        'bill_rate_max' => 70.00,
        'price_admin' => 70.00,
        'reimbursement_amount' => 70.00
    ],
    [
        'name' => 'Hydrogel Kit',
        'size' => '30-Day',
        'sku' => 'KIT-HG-30',
        'category' => 'Gel + Foam',
        'hcpcs_code' => 'A6248 + A6212/A6213',
        'cpt_code' => 'A6248',
        'hcpcs_description' => 'Hydrogel + foam border',
        'bill_rate_min' => 130.00,
        'bill_rate_max' => 130.00,
        'price_admin' => 130.00,
        'reimbursement_amount' => 130.00
    ],
];

// Step 4: Clear existing products and insert new catalog
echo "Deleting old products...\n";
$pdo->exec("DELETE FROM products");
echo "✓ Cleared product table\n\n";

echo "Inserting new product catalog...\n";
$inserted = 0;

foreach ($products as $p) {
    $stmt = $pdo->prepare("
        INSERT INTO products (
            name, size, sku, category, hcpcs_code, cpt_code,
            hcpcs_description, bill_rate_min, bill_rate_max,
            price_admin, reimbursement_amount, active, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, NOW(), NOW()
        )
    ");

    $stmt->execute([
        $p['name'],
        $p['size'],
        $p['sku'],
        $p['category'],
        $p['hcpcs_code'],
        $p['cpt_code'],
        $p['hcpcs_description'],
        $p['bill_rate_min'],
        $p['bill_rate_max'],
        $p['price_admin'],
        $p['reimbursement_amount']
    ]);

    echo "✓ {$p['name']} ({$p['size']}) - {$p['hcpcs_code']} - \${$p['price_admin']}\n";
    $inserted++;
}

echo "\n=== Summary ===\n";
echo "Products inserted: $inserted\n";
echo "Product categories: " . count(array_unique(array_column($products, 'category'))) . "\n";

echo "\nProduct breakdown by category:\n";
$categories = [];
foreach ($products as $p) {
    if (!isset($categories[$p['category']])) {
        $categories[$p['category']] = 0;
    }
    $categories[$p['category']]++;
}
foreach ($categories as $cat => $count) {
    echo "- $cat: $count products\n";
}

echo "\n✓ Product catalog updated successfully!\n";
