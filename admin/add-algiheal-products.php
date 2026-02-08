<?php
/**
 * Add AlgiHeal Calcium Alginate products to the catalog
 * Run once, then delete this file.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$admin = current_admin();
if (!$admin || ($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    die('Superadmin access required');
}

header('Content-Type: text/html; charset=utf-8');

$results = [];
$errors = [];

$products = [
    [
        'name' => 'AlgiHeal Calcium Alginate',
        'size' => '2x2 in',
        'sku' => 'AH-ALG-2X2',
        'category' => 'Alginate',
        'hcpcs_code' => 'A6196',
        'price_admin' => 6.28,
        'price_wholesale' => 6.28,
        'pieces_per_box' => 10,
        'active' => true,
    ],
    [
        'name' => 'AlgiHeal Calcium Alginate',
        'size' => '4x4 in',
        'sku' => 'AH-ALG-4X4',
        'category' => 'Alginate',
        'hcpcs_code' => 'A6197',
        'price_admin' => 9.02,
        'price_wholesale' => 9.02,
        'pieces_per_box' => 10,
        'active' => true,
    ],
];

foreach ($products as $product) {
    try {
        // Check if product already exists by SKU
        $check = $pdo->prepare("SELECT id, name, size, active FROM products WHERE sku = ?");
        $check->execute([$product['sku']]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update to make sure it's active and has correct pricing
            $pdo->prepare("
                UPDATE products
                SET name = ?, size = ?, category = ?, hcpcs_code = ?,
                    price_admin = ?, price_wholesale = ?, pieces_per_box = ?,
                    active = TRUE, is_active = TRUE, updated_at = NOW()
                WHERE sku = ?
            ")->execute([
                $product['name'], $product['size'], $product['category'], $product['hcpcs_code'],
                $product['price_admin'], $product['price_wholesale'], $product['pieces_per_box'],
                $product['sku']
            ]);
            $results[] = "Updated: {$product['name']} {$product['size']} (SKU: {$product['sku']}) - was " . ($existing['active'] ? 'active' : 'inactive');
        } else {
            // Try inserting with all fields, fall back if some columns don't exist
            try {
                $pdo->prepare("
                    INSERT INTO products (name, size, sku, category, hcpcs_code, price_admin, price_wholesale, pieces_per_box, active, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, TRUE, NOW(), NOW())
                ")->execute([
                    $product['name'], $product['size'], $product['sku'], $product['category'],
                    $product['hcpcs_code'], $product['price_admin'], $product['price_wholesale'], $product['pieces_per_box']
                ]);
            } catch (PDOException $colErr) {
                // Try simpler insert without is_active column
                $pdo->prepare("
                    INSERT INTO products (name, size, sku, category, hcpcs_code, price_admin, price_wholesale, pieces_per_box, active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, NOW(), NOW())
                ")->execute([
                    $product['name'], $product['size'], $product['sku'], $product['category'],
                    $product['hcpcs_code'], $product['price_admin'], $product['price_wholesale'], $product['pieces_per_box']
                ]);
            }
            $results[] = "Added: {$product['name']} {$product['size']} (SKU: {$product['sku']})";
        }
    } catch (Throwable $e) {
        $errors[] = "{$product['name']} {$product['size']}: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add AlgiHeal Products</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { color: #059669; background: #d1fae5; padding: 10px; border-radius: 6px; margin: 5px 0; }
        .error { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 6px; margin: 5px 0; }
        .done { color: #d97706; background: #fef3c7; padding: 10px; border-radius: 6px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Add AlgiHeal Products</h1>
    <?php foreach ($results as $r): ?>
        <div class="success"><?= htmlspecialchars($r) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <?php if (empty($errors)): ?>
        <div class="done"><strong>Done!</strong> Delete this file after confirming products appear in Practice Pricing.</div>
    <?php endif; ?>
    <p><a href="/admin/practice-pricing.php">Check Practice Pricing</a></p>
</body>
</html>
