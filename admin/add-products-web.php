<?php
/**
 * Web-accessible Product Catalog Installer
 *
 * Visit this page in your browser to add all products to the database.
 * For security, this should be deleted after use or protected with authentication.
 */

require __DIR__ . '/../api/db.php';

// Simple password protection (change this!)
$INSTALL_PASSWORD = 'add-products-2025';

// Check password
if (!isset($_GET['password']) || $_GET['password'] !== $INSTALL_PASSWORD) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head><title>Product Installer - Authentication Required</title></head>
<body style="font-family: system-ui; max-width: 600px; margin: 50px auto; padding: 20px;">
    <h1>🔒 Authentication Required</h1>
    <p>This page adds the complete product catalog to your database.</p>
    <form method="GET">
        <label>Password: <input type="password" name="password" required></label>
        <button type="submit">Install Products</button>
    </form>
</body>
</html>';
    exit;
}

// Start output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Product Catalog Installer</title>
    <style>
        body { font-family: system-ui; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #47c6be; padding-bottom: 10px; }
        .success { color: #0a5f56; background: #eefaf8; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .product { padding: 8px; border-left: 3px solid #47c6be; margin: 5px 0; background: #f9f9f9; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #47c6be; color: white; }
        .category-header { background: #e0f2f1; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Product Catalog Installer</h1>

<?php

try {
    // Check current product count
    $currentCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "<div class='success'>✓ Database connected successfully</div>";
    echo "<p>Current products in database: <strong>$currentCount</strong></p>";

    // Define all products
    $allProducts = [
        // Matrix Products
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
        ],
        // Powder Products
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
        ],
        // Antimicrobial Products
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

    echo "<h2>Installing Products...</h2>";

    foreach ($allProducts as $product) {
        // Check if product exists
        $exists = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $exists->execute([$product['sku']]);
        $isUpdate = $exists->fetch() !== false;

        // Execute upsert
        $stmt->execute($product);

        $status = $isUpdate ? 'Updated' : 'Added';
        $price = number_format($product['price_admin'], 2);

        echo "<div class='product'>✓ <strong>$status:</strong> {$product['name']} ({$product['size']}) - HCPCS {$product['hcpcs_code']} - \${$price}</div>";

        if ($isUpdate) {
            $updated++;
        } else {
            $added++;
        }
    }

    echo "<div class='success'>";
    echo "<h3>✅ Installation Complete!</h3>";
    echo "<p>Products added: <strong>$added</strong></p>";
    echo "<p>Products updated: <strong>$updated</strong></p>";
    echo "</div>";

    // Show final product list
    echo "<h2>Current Product Catalog</h2>";

    $products = $pdo->query("
        SELECT id, sku, name, category, size, hcpcs_code, price_admin, active
        FROM products
        WHERE active = TRUE
        ORDER BY category, size
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>ID</th><th>Product Name</th><th>Size</th><th>Category</th><th>HCPCS</th><th>Price</th></tr>";

    $lastCategory = '';
    foreach ($products as $p) {
        if ($p['category'] !== $lastCategory) {
            echo "<tr class='category-header'><td colspan='6'>" . strtoupper($p['category']) . "</td></tr>";
            $lastCategory = $p['category'];
        }
        $price = number_format($p['price_admin'], 2);
        echo "<tr>";
        echo "<td>{$p['id']}</td>";
        echo "<td>{$p['name']}</td>";
        echo "<td>{$p['size']}</td>";
        echo "<td>{$p['category']}</td>";
        echo "<td>{$p['hcpcs_code']}</td>";
        echo "<td>\${$price}</td>";
        echo "</tr>";
    }

    echo "</table>";

    $totalCount = count($products);
    echo "<div class='success'>";
    echo "<h3>Total Active Products: $totalCount</h3>";
    echo "<p>✅ Users can now see all products when creating orders!</p>";
    echo "<p><strong>⚠️ Security Note:</strong> You should delete this file (admin/add-products-web.php) after use or move it to a secure location.</p>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Database Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

?>
    </div>
</body>
</html>
