<?php
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Current Products in Database ===\n\n";

// Check if products table exists and has data
try {
    $count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "Total products in database: $count\n\n";

    if ($count > 0) {
        echo "Active products:\n";
        $active = $pdo->query("SELECT COUNT(*) FROM products WHERE active = TRUE")->fetchColumn();
        echo "- Active: $active\n";
        echo "- Inactive: " . ($count - $active) . "\n\n";

        echo "Product List:\n";
        echo str_repeat("-", 80) . "\n";

        $stmt = $pdo->query("
            SELECT id, name, size, sku, category, hcpcs_code, price_admin, active
            FROM products
            ORDER BY name, size
        ");

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $p) {
            $status = $p['active'] ? '✓' : '✗';
            echo "$status ID: {$p['id']}\n";
            echo "  Name: {$p['name']}\n";
            echo "  Size: {$p['size']}\n";
            echo "  SKU: {$p['sku']}\n";
            echo "  Category: " . ($p['category'] ?: 'N/A') . "\n";
            echo "  HCPCS: " . ($p['hcpcs_code'] ?: 'N/A') . "\n";
            echo "  Price: \$" . ($p['price_admin'] ?: '0.00') . "\n";
            echo "\n";
        }
    } else {
        echo "⚠ No products found in database!\n";
        echo "\nYou need to run the product catalog update script:\n";
        echo "https://collagendirect.onrender.com/admin/update-product-catalog-2025.php\n";
    }

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "\nThe products table may not exist or have schema issues.\n";
}

echo "\n=== Check Complete ===\n";
