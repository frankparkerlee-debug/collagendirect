<?php
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Product System Diagnostics ===\n\n";

// 1. Check if products table exists
echo "1. Checking products table...\n";
try {
    $tableExists = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_name = 'products'
        )
    ")->fetchColumn();

    if ($tableExists) {
        echo "✓ Products table exists\n\n";
    } else {
        echo "✗ Products table does NOT exist!\n";
        echo "You need to create the products table first.\n\n";
        exit;
    }
} catch (PDOException $e) {
    echo "✗ Error checking table: " . $e->getMessage() . "\n\n";
    exit;
}

// 2. Check table columns
echo "2. Checking table schema...\n";
try {
    $columns = $pdo->query("
        SELECT column_name, data_type, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'products'
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "Columns:\n";
    foreach ($columns as $col) {
        echo "  - {$col['column_name']} ({$col['data_type']}) " .
             ($col['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    echo "\n";

    // Check for required columns
    $columnNames = array_column($columns, 'column_name');
    $required = ['id', 'name', 'size', 'price_admin', 'hcpcs_code', 'active'];
    $missing = array_diff($required, $columnNames);

    if (empty($missing)) {
        echo "✓ All required columns present\n\n";
    } else {
        echo "✗ Missing columns: " . implode(', ', $missing) . "\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Error checking schema: " . $e->getMessage() . "\n\n";
}

// 3. Check product count
echo "3. Checking product count...\n";
try {
    $total = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $active = $pdo->query("SELECT COUNT(*) FROM products WHERE active = TRUE")->fetchColumn();

    echo "Total products: $total\n";
    echo "Active products: $active\n";
    echo "Inactive products: " . ($total - $active) . "\n\n";

    if ($active === 0) {
        echo "⚠ WARNING: No active products found!\n";
        echo "You need to run: /admin/update-product-catalog-2025.php\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Error counting products: " . $e->getMessage() . "\n\n";
}

// 4. Test the products API query
echo "4. Testing products API query...\n";
try {
    $stmt = $pdo->query("
        SELECT id, name, size, size AS uom, price_admin AS price, hcpcs_code AS hcpcs, category
        FROM products
        WHERE active = TRUE
        ORDER BY name ASC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Query executed successfully\n";
    echo "Products returned: " . count($products) . "\n\n";

    if (count($products) > 0) {
        echo "Sample products:\n";
        foreach (array_slice($products, 0, 5) as $p) {
            echo "  - ID: {$p['id']}, Name: {$p['name']}, Size: {$p['size']}, HCPCS: {$p['hcpcs']}, Price: \${$p['price']}\n";
        }
        echo "\n";
    }
} catch (PDOException $e) {
    echo "✗ ERROR running products query!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n\n";

    echo "This is likely the error users are seeing in the portal.\n\n";

    // Try to identify the problematic column
    echo "Testing individual columns...\n";
    $testColumns = [
        'id, name, size',
        'id, name, size, price_admin',
        'id, name, size, price_admin, hcpcs_code',
        'id, name, size, price_admin, hcpcs_code, category'
    ];

    foreach ($testColumns as $cols) {
        try {
            $pdo->query("SELECT $cols FROM products LIMIT 1");
            echo "✓ Columns work: $cols\n";
        } catch (PDOException $e2) {
            echo "✗ Columns fail: $cols\n";
            echo "  Error: " . $e2->getMessage() . "\n";
        }
    }
    echo "\n";
}

// 5. Sample product data
if (isset($products) && count($products) > 0) {
    echo "5. Full product listing:\n";
    echo str_repeat("-", 100) . "\n";
    foreach ($products as $p) {
        echo sprintf(
            "ID: %-3s | %-35s | %-15s | %-12s | $%-6s | %s\n",
            $p['id'],
            $p['name'],
            $p['size'],
            $p['hcpcs'] ?: 'N/A',
            number_format($p['price'], 2),
            $p['category'] ?: 'N/A'
        );
    }
    echo str_repeat("-", 100) . "\n\n";
}

echo "=== Diagnostics Complete ===\n\n";

if (isset($active) && $active === 0) {
    echo "NEXT STEP: Run the product catalog update script:\n";
    echo "https://collagendirect.onrender.com/admin/update-product-catalog-2025.php\n";
} elseif (isset($products) && count($products) > 0) {
    echo "✓ Products are configured correctly and should be visible in the portal.\n";
    echo "\nIf products still don't show:\n";
    echo "1. Clear your browser cache\n";
    echo "2. Check browser console for JavaScript errors\n";
    echo "3. Check Network tab for failed API requests\n";
}
