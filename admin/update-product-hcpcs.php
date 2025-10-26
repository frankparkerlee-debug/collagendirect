<?php
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Updating Product HCPCS Codes and Reimbursements ===\n\n";

// Step 1: Check if reimbursement_amount column exists, add if not
echo "Step 1: Checking for reimbursement_amount column...\n";

// Check if column exists
$checkColumn = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'products'
    AND column_name = 'reimbursement_amount'
");

if ($checkColumn->rowCount() === 0) {
    echo "Column does not exist, adding it...\n";
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN reimbursement_amount DECIMAL(10,2)");
        echo "✓ Column added successfully\n\n";
    } catch (PDOException $e) {
        echo "ERROR adding column: " . $e->getMessage() . "\n\n";
        exit(1);
    }
} else {
    echo "✓ Column already exists\n\n";
}

// Step 2: Update products with correct HCPCS codes and reimbursements
echo "Step 2: Updating product data...\n";

$products = [
    ['name' => 'Collagen Powder', 'size' => '1g', 'cpt_code' => 'Q4164', 'reimbursement' => 99.18],
    ['name' => 'Collagen Gel', 'size' => '2.5oz', 'cpt_code' => 'Q4114', 'reimbursement' => 248.79],
    ['name' => 'Collagen Gel', 'size' => '1oz', 'cpt_code' => 'Q4114', 'reimbursement' => 99.52],
    ['name' => 'Collagen Sheet', 'size' => '2x2', 'cpt_code' => 'Q4164', 'reimbursement' => 99.18],
    ['name' => 'Collagen Sheet', 'size' => '4x4', 'cpt_code' => 'Q4164', 'reimbursement' => 396.72],
];

$updated = 0;
$notFound = [];

foreach ($products as $prod) {
    $stmt = $pdo->prepare("
        UPDATE products
        SET cpt_code = ?,
            reimbursement_amount = ?
        WHERE name = ? AND size = ?
    ");
    $stmt->execute([$prod['cpt_code'], $prod['reimbursement'], $prod['name'], $prod['size']]);

    if ($stmt->rowCount() > 0) {
        echo "✓ Updated: {$prod['name']} ({$prod['size']}) - {$prod['cpt_code']} - \${$prod['reimbursement']}\n";
        $updated++;
    } else {
        echo "✗ Not found: {$prod['name']} ({$prod['size']})\n";
        $notFound[] = $prod;
    }
}

echo "\n=== Summary ===\n";
echo "Updated: $updated products\n";
echo "Not found: " . count($notFound) . " products\n";

if (count($notFound) > 0) {
    echo "\nProducts not found in database:\n";
    foreach ($notFound as $prod) {
        echo "- {$prod['name']} ({$prod['size']})\n";
    }

    echo "\nLet me check what products exist in the database...\n\n";
    $stmt = $pdo->query("SELECT id, name, size, sku, cpt_code FROM products ORDER BY name, size");
    $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Existing products:\n";
    foreach ($existing as $p) {
        echo "- ID: {$p['id']}, Name: '{$p['name']}', Size: '{$p['size']}', SKU: '{$p['sku']}', CPT: '{$p['cpt_code']}'\n";
    }
}

echo "\n✓ Script completed successfully!\n";
