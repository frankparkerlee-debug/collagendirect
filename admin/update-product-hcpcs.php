<?php
require __DIR__ . '/../api/db.php';
header('Content-Type: text/plain');

echo "=== Updating Product HCPCS Codes and Reimbursements ===\n\n";

// Correct product data based on Dressing Reimbursements.docx
$products = [
    ['name' => 'Collagen Powder', 'size' => '1g', 'cpt_code' => 'Q4164', 'reimbursement' => 99.18],
    ['name' => 'Collagen Gel', 'size' => '2.5oz', 'cpt_code' => 'Q4114', 'reimbursement' => 248.79],
    ['name' => 'Collagen Gel', 'size' => '1oz', 'cpt_code' => 'Q4114', 'reimbursement' => 99.52],
    ['name' => 'Collagen Sheet', 'size' => '2x2', 'cpt_code' => 'Q4164', 'reimbursement' => 99.18],
    ['name' => 'Collagen Sheet', 'size' => '4x4', 'cpt_code' => 'Q4164', 'reimbursement' => 396.72],
];

foreach ($products as $prod) {
    $stmt = $pdo->prepare("
        UPDATE products 
        SET cpt_code = ?, 
            reimbursement_amount = ?
        WHERE name = ? AND size = ?
    ");
    
    $stmt->execute([
        $prod['cpt_code'],
        $prod['reimbursement'],
        $prod['name'],
        $prod['size']
    ]);
    
    $updated = $stmt->rowCount();
    
    if ($updated > 0) {
        echo "✓ Updated {$prod['name']} {$prod['size']}: HCPCS {$prod['cpt_code']}, Reimbursement \${$prod['reimbursement']}\n";
    } else {
        echo "✗ No product found for {$prod['name']} {$prod['size']}\n";
    }
}

// Add reimbursement_amount column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS reimbursement_amount DECIMAL(10,2)");
    echo "\n✓ Ensured reimbursement_amount column exists\n";
} catch (Exception $e) {
    echo "\nNote: " . $e->getMessage() . "\n";
}

echo "\n=== Update Complete ===\n";
echo "Note: Reimbursement amounts are for admin/revenue calculation only.\n";
echo "They should NOT be shown to portal users (physicians).\n";
