<?php
/**
 * Database Setup & Product Installer
 *
 * This script creates all necessary tables and adds products.
 * Run this ONCE on a fresh database.
 */

// Simple password
$PASSWORD = 'setup-db-2025';

if (!isset($_GET['password']) || $_GET['password'] !== $PASSWORD) {
    die('<!DOCTYPE html><html><head><title>Setup Required</title></head><body style="font-family:system-ui;max-width:600px;margin:50px auto;padding:20px;"><h1>🔒 Password Required</h1><form method="GET"><input type="password" name="password" placeholder="Enter setup password" required> <button type="submit">Run Setup</button></form></body></html>');
}

// Get database credentials from environment
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'collagen_db';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '5432';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body{font-family:system-ui;max-width:900px;margin:30px auto;padding:20px;background:#f5f5f5}
        .box{background:white;padding:30px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);margin:20px 0}
        h1{color:#333;border-bottom:3px solid #47c6be;padding-bottom:10px}
        .success{color:#0a5f56;background:#eefal8;padding:12px;border-radius:4px;margin:10px 0;border-left:4px solid #47c6be}
        .error{color:#d32f2f;background:#ffebee;padding:12px;border-radius:4px;margin:10px 0;border-left:4px solid #d32f2f}
        .info{background:#e3f2fd;padding:12px;border-radius:4px;margin:10px 0;border-left:4px solid #2196f3}
        .step{padding:15px;background:#f9f9f9;border-left:3px solid #47c6be;margin:10px 0}
        table{width:100%;border-collapse:collapse;margin:15px 0}
        th,td{padding:10px;text-align:left;border-bottom:1px solid #ddd}
        th{background:#47c6be;color:white}
        code{background:#f5f5f5;padding:2px 6px;border-radius:3px;font-family:monospace}
    </style>
</head>
<body>
    <div class="box">
        <h1>🗄️ Database Setup & Product Installer</h1>

<?php

try {
    // Connect to database
    echo "<div class='step'>Step 1: Connecting to database...</div>";

    $pdo = new PDO(
        "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "<div class='success'>✓ Connected to database: <code>{$DB_NAME}</code> on <code>{$DB_HOST}</code></div>";

    // Check if products table exists
    echo "<div class='step'>Step 2: Checking for products table...</div>";

    $tableCheck = $pdo->query("
        SELECT EXISTS (
            SELECT FROM pg_tables
            WHERE schemaname = 'public'
            AND tablename = 'products'
        )
    ")->fetchColumn();

    if (!$tableCheck) {
        echo "<div class='info'>⚠️ Products table does not exist. Creating it now...</div>";

        // Create products table
        $pdo->exec("
            CREATE TABLE products (
                id SERIAL PRIMARY KEY,
                sku VARCHAR(100) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                price_admin DECIMAL(10,2),
                price_wholesale DECIMAL(10,2),
                category VARCHAR(100),
                size VARCHAR(50),
                hcpcs_code VARCHAR(20),
                cpt_code VARCHAR(20),
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        echo "<div class='success'>✓ Products table created</div>";

        // Create indexes
        $pdo->exec("CREATE INDEX prod_sku ON products(sku)");
        $pdo->exec("CREATE INDEX prod_active ON products(active)");

        echo "<div class='success'>✓ Indexes created (prod_sku, prod_active)</div>";
    } else {
        echo "<div class='success'>✓ Products table already exists</div>";
    }

    // Check current products
    $currentCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "<div class='info'>Current products in database: <strong>{$currentCount}</strong></div>";

    // Define products
    echo "<div class='step'>Step 3: Adding products to catalog...</div>";

    $products = [
        // Matrix
        ['COLL-MTX-2X2', 'Collagen Matrix 2×2', 'Sheet matrix for DFU/VLU & pressure ulcers', 45.00, 30.00, 'matrix', '2×2 in', 'A6196', '97597'],
        ['COLL-MTX-3X3', 'Collagen Matrix 3×3', 'Absorbent matrix supporting epithelialization', 75.00, 50.00, 'matrix', '3×3 in', 'A6197', '97597'],
        ['COLL-MTX-4X4', 'Collagen Matrix 4×4', 'Larger coverage for exuding wounds', 95.00, 65.00, 'matrix', '4×4 in', 'A6197', '97597'],
        // Powder
        ['COLL-PWD-1G', 'Collagen Powder 1 g', 'Maintains moist wound environment for granulation', 55.00, 38.00, 'powder', '1 g', 'A6010', '97597'],
        ['COLL-PWD-3G', 'Collagen Powder 3 g', 'Higher volume for large or tunneling wounds', 125.00, 85.00, 'powder', '3 g', 'A6010', '97597'],
        // Antimicrobial
        ['COLL-AG-2X2', 'Antimicrobial Collagen 2×2', 'Silver-infused collagen for bioburden management', 85.00, 58.00, 'antimicrobial', '2×2 in', 'A6196', '97597'],
        ['COLL-AG-4X4', 'Antimicrobial Collagen 4×4', 'Larger silver-collagen composite for infected wounds', 135.00, 92.00, 'antimicrobial', '4×4 in', 'A6197', '97597'],
        ['COLL-AG-PWD-1G', 'Antimicrobial Collagen Powder 1 g', 'Silver-infused powder for tunneling wounds', 95.00, 65.00, 'antimicrobial', '1 g', 'A6010', '97597'],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO products (sku, name, description, price_admin, price_wholesale, category, size, hcpcs_code, cpt_code, active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
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

    foreach ($products as $p) {
        $exists = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $exists->execute([$p[0]]);
        $isUpdate = $exists->fetch() !== false;

        $stmt->execute($p);

        if ($isUpdate) {
            $updated++;
            echo "<div style='padding:5px;background:#fff9e6;margin:3px 0'>↻ Updated: <strong>{$p[1]}</strong> ({$p[6]}) - \${$p[3]}</div>";
        } else {
            $added++;
            echo "<div style='padding:5px;background:#eefaf8;margin:3px 0'>✓ Added: <strong>{$p[1]}</strong> ({$p[6]}) - \${$p[3]}</div>";
        }
    }

    echo "<div class='success'><strong>Products added:</strong> {$added} | <strong>Products updated:</strong> {$updated}</div>";

    // Show final catalog
    echo "<div class='step'>Step 4: Verifying product catalog...</div>";

    $allProducts = $pdo->query("
        SELECT id, sku, name, category, size, hcpcs_code, price_admin
        FROM products
        WHERE active = TRUE
        ORDER BY category, size
    ")->fetchAll();

    echo "<table>";
    echo "<tr><th>ID</th><th>SKU</th><th>Product Name</th><th>Size</th><th>Category</th><th>HCPCS</th><th>Price</th></tr>";

    $lastCat = '';
    foreach ($allProducts as $p) {
        if ($p['category'] !== $lastCat) {
            echo "<tr style='background:#e0f2f1'><td colspan='7'><strong>" . strtoupper($p['category']) . "</strong></td></tr>";
            $lastCat = $p['category'];
        }
        echo "<tr>";
        echo "<td>{$p['id']}</td>";
        echo "<td><code>{$p['sku']}</code></td>";
        echo "<td>{$p['name']}</td>";
        echo "<td>{$p['size']}</td>";
        echo "<td>{$p['category']}</td>";
        echo "<td>{$p['hcpcs_code']}</td>";
        echo "<td>\$" . number_format($p['price_admin'], 2) . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    $total = count($allProducts);
    echo "<div class='success'>";
    echo "<h2>✅ Setup Complete!</h2>";
    echo "<p><strong>Total active products:</strong> {$total}</p>";
    echo "<p>✓ Database tables created<br>";
    echo "✓ All products added to catalog<br>";
    echo "✓ Users can now see products when creating orders</p>";
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>⚠️ Security Note</h3>";
    echo "<p>For security, you should delete this file after setup:</p>";
    echo "<code>rm admin/setup-database.php</code>";
    echo "<p>Or move it to a secure location outside the web root.</p>";
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>Next Steps</h3>";
    echo "<p>1. Test order creation in the provider portal<br>";
    echo "2. Verify all {$total} products appear in the dropdown<br>";
    echo "3. Delete or secure this setup file</p>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Database Error</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Database:</strong> {$DB_NAME}@{$DB_HOST}:{$DB_PORT}</p>";
    echo "</div>";
    exit(1);
}

?>
    </div>
</body>
</html>
