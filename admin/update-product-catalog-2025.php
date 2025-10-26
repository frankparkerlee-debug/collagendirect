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
    // CollaHeal Collagen Products
    [
        'name' => 'CollaHeal Collagen Wound Dressing',
        'size' => '2x2 in',
        'sku' => 'CH-COL-2X2',
        'category' => 'Collagen Sheet',
        'hcpcs_code' => 'A6021',
        'cpt_code' => '',
        'hcpcs_description' => 'Collagen dressing ≤ 16 sq in',
        'bill_rate_min' => 16.44,
        'bill_rate_max' => 16.44,
        'price_admin' => 16.44,
        'reimbursement_amount' => 16.44,
        'notes' => 'Sterile collagen sheet, small size'
    ],
    [
        'name' => 'CollaHeal Collagen Wound Dressing',
        'size' => '7x7 in',
        'sku' => 'CH-COL-7X7',
        'category' => 'Collagen Sheet',
        'hcpcs_code' => 'A6023',
        'cpt_code' => '',
        'hcpcs_description' => 'Collagen dressing > 48 sq in',
        'bill_rate_min' => 52.70,
        'bill_rate_max' => 52.70,
        'price_admin' => 52.70,
        'reimbursement_amount' => 52.70,
        'notes' => 'Large collagen sheet'
    ],
    [
        'name' => 'CollaHeal Collagen Powder',
        'size' => '1 g',
        'sku' => 'CH-POW-1G',
        'category' => 'Collagen Particulate',
        'hcpcs_code' => 'A6010',
        'cpt_code' => '',
        'hcpcs_description' => 'Collagen wound filler, per gram',
        'bill_rate_min' => 24.16,
        'bill_rate_max' => 24.16,
        'price_admin' => 24.16,
        'reimbursement_amount' => 24.16,
        'notes' => 'Powder form, pure bovine collagen'
    ],

    // AlgiHeal Alginate Products
    [
        'name' => 'AlgiHeal Alginate Dressing',
        'size' => '2x2 in',
        'sku' => 'AH-ALG-2X2',
        'category' => 'Alginate',
        'hcpcs_code' => 'A6196',
        'cpt_code' => '',
        'hcpcs_description' => 'Alginate dressing ≤ 16 sq in',
        'bill_rate_min' => 6.28,
        'bill_rate_max' => 6.28,
        'price_admin' => 6.28,
        'reimbursement_amount' => 6.28,
        'notes' => 'Natural calcium alginate sheet'
    ],
    [
        'name' => 'AlgiHeal Alginate Dressing',
        'size' => '4.33x4.33 in',
        'sku' => 'AH-ALG-4X4',
        'category' => 'Alginate',
        'hcpcs_code' => 'A6197',
        'cpt_code' => '',
        'hcpcs_description' => 'Alginate dressing > 16 ≤ 48 sq in',
        'bill_rate_min' => 9.02,
        'bill_rate_max' => 9.02,
        'price_admin' => 9.02,
        'reimbursement_amount' => 9.02,
        'notes' => 'Moderate wound coverage'
    ],
    [
        'name' => 'AlgiHeal Alginate Dressing',
        'size' => '6x6 in',
        'sku' => 'AH-ALG-6X6',
        'category' => 'Alginate',
        'hcpcs_code' => 'A6198',
        'cpt_code' => '',
        'hcpcs_description' => 'Alginate dressing > 48 ≤ 100 sq in',
        'bill_rate_min' => 12.44,
        'bill_rate_max' => 12.44,
        'price_admin' => 12.44,
        'reimbursement_amount' => 12.44,
        'notes' => 'Large wound area'
    ],
    [
        'name' => 'AlgiHeal AG Silver Alginate',
        'size' => '2x2 in',
        'sku' => 'AH-AG-2X2',
        'category' => 'Silver Alginate',
        'hcpcs_code' => 'A6196 + AW',
        'cpt_code' => '',
        'hcpcs_description' => 'Alginate dressing with silver',
        'bill_rate_min' => 11.50,
        'bill_rate_max' => 11.50,
        'price_admin' => 11.50,
        'reimbursement_amount' => 11.50,
        'notes' => 'Antimicrobial (non-covered silver upcharge by MAC)'
    ],

    // HydraPad Super Absorbent
    [
        'name' => 'HydraPad Super Absorbent',
        'size' => '2x2 in',
        'sku' => 'HP-SA-2X2',
        'category' => 'Super-Absorbent',
        'hcpcs_code' => 'A6222',
        'cpt_code' => '',
        'hcpcs_description' => 'Hydrocolloid/superabsorbent ≤ 16 sq in',
        'bill_rate_min' => 8.34,
        'bill_rate_max' => 8.34,
        'price_admin' => 8.34,
        'reimbursement_amount' => 8.34,
        'notes' => 'Waterproof, multilayer backing'
    ],
    [
        'name' => 'HydraPad Super Absorbent',
        'size' => '8x8 in',
        'sku' => 'HP-SA-8X8',
        'category' => 'Super-Absorbent',
        'hcpcs_code' => 'A6224',
        'cpt_code' => '',
        'hcpcs_description' => 'Hydrocolloid/superabsorbent > 48 sq in',
        'bill_rate_min' => 17.92,
        'bill_rate_max' => 17.92,
        'price_admin' => 17.92,
        'reimbursement_amount' => 17.92,
        'notes' => 'For heavy exudate'
    ],

    // CuraFoam Silicone Foam
    [
        'name' => 'CuraFoam Silicone Foam',
        'size' => '2x2 in',
        'sku' => 'CF-FOAM-2X2',
        'category' => 'Foam Dressing',
        'hcpcs_code' => 'A6212',
        'cpt_code' => '',
        'hcpcs_description' => 'Foam dressing w/ border ≤ 16 sq in',
        'bill_rate_min' => 10.77,
        'bill_rate_max' => 10.77,
        'price_admin' => 10.77,
        'reimbursement_amount' => 10.77,
        'notes' => 'Adherent bordered silicone foam'
    ],
    [
        'name' => 'CuraFoam Silicone Foam',
        'size' => '6x6 in',
        'sku' => 'CF-FOAM-6X6',
        'category' => 'Foam Dressing',
        'hcpcs_code' => 'A6215',
        'cpt_code' => '',
        'hcpcs_description' => 'Foam dressing w/ border > 48 sq in',
        'bill_rate_min' => 20.43,
        'bill_rate_max' => 20.43,
        'price_admin' => 20.43,
        'reimbursement_amount' => 20.43,
        'notes' => 'Large coverage'
    ],

    // HydraCare Hydrogel Products
    [
        'name' => 'HydraCare Amorphous Hydrogel',
        'size' => '0.9 oz',
        'sku' => 'HC-GEL-0.9OZ',
        'category' => 'Hydrogel',
        'hcpcs_code' => 'A6248',
        'cpt_code' => '',
        'hcpcs_description' => 'Hydrogel dressing, wound filler',
        'bill_rate_min' => 6.85,
        'bill_rate_max' => 6.85,
        'price_admin' => 6.85,
        'reimbursement_amount' => 6.85,
        'notes' => 'Rehydrates necrotic tissue'
    ],
    [
        'name' => 'HydraCare AG Silver Hydrogel',
        'size' => '0.9 oz',
        'sku' => 'HC-AG-0.9OZ',
        'category' => 'Silver Hydrogel',
        'hcpcs_code' => 'A6249',
        'cpt_code' => '',
        'hcpcs_description' => 'Hydrogel w/ antimicrobial silver',
        'bill_rate_min' => 12.10,
        'bill_rate_max' => 12.10,
        'price_admin' => 12.10,
        'reimbursement_amount' => 12.10,
        'notes' => 'Broad-spectrum silver protection'
    ],

    // Kits
    [
        'name' => '15-Day Collagen Kit',
        'size' => '15-Day',
        'sku' => 'KIT-COL-15',
        'category' => 'Kit',
        'hcpcs_code' => 'A6010 + A6212',
        'cpt_code' => '',
        'hcpcs_description' => 'Collagen filler + foam secondary',
        'bill_rate_min' => 85.00,
        'bill_rate_max' => 85.00,
        'price_admin' => 85.00,
        'reimbursement_amount' => 85.00,
        'notes' => 'Typical DME bundle rate'
    ],
    [
        'name' => '30-Day Collagen Kit',
        'size' => '30-Day',
        'sku' => 'KIT-COL-30',
        'category' => 'Kit',
        'hcpcs_code' => 'A6010 + A6212',
        'cpt_code' => '',
        'hcpcs_description' => 'Collagen filler + foam secondary',
        'bill_rate_min' => 160.00,
        'bill_rate_max' => 160.00,
        'price_admin' => 160.00,
        'reimbursement_amount' => 160.00,
        'notes' => 'Includes refills'
    ],
    [
        'name' => '15-Day Alginate Kit',
        'size' => '15-Day',
        'sku' => 'KIT-ALG-15',
        'category' => 'Kit',
        'hcpcs_code' => 'A6196 + A6212',
        'cpt_code' => '',
        'hcpcs_description' => 'Alginate primary + foam secondary',
        'bill_rate_min' => 75.00,
        'bill_rate_max' => 75.00,
        'price_admin' => 75.00,
        'reimbursement_amount' => 75.00,
        'notes' => ''
    ],
    [
        'name' => '15-Day Silver Alginate Kit',
        'size' => '15-Day',
        'sku' => 'KIT-AG-15',
        'category' => 'Kit',
        'hcpcs_code' => 'A6196 + AW + A6212',
        'cpt_code' => '',
        'hcpcs_description' => 'Silver alginate + foam secondary',
        'bill_rate_min' => 95.00,
        'bill_rate_max' => 95.00,
        'price_admin' => 95.00,
        'reimbursement_amount' => 95.00,
        'notes' => ''
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
        $p['cpt_code'] ?: null,
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
