<?php
/**
 * Deactivate "AlgiHeal Calcium Alginate" products (without hyphen in name)
 * These were added separately and duplicate the products from the product matrix.
 * Run once via admin panel, then this file can be removed.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$admin = current_admin();
if (!$admin || ($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    die('Superadmin access required');
}

header('Content-Type: text/html; charset=utf-8');

echo '<h2>Deactivate AlgiHeal Calcium Alginate (without hyphen)</h2>';

// Find products named exactly "AlgiHeal Calcium Alginate" (the ones added via add-algiheal-products.php)
// These have SKUs like AH-ALG-2X2, AH-ALG-4X4
$findStmt = $pdo->prepare("
    SELECT id, name, size, sku, active
    FROM products
    WHERE name = 'AlgiHeal Calcium Alginate'
    ORDER BY id
");
$findStmt->execute();
$products = $findStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    echo '<p>No products found with name exactly "AlgiHeal Calcium Alginate". Nothing to deactivate.</p>';
    echo '<p><a href="/admin/products.php">Back to Products</a></p>';
    exit;
}

echo '<h3>Products found:</h3><ul>';
foreach ($products as $p) {
    $status = $p['active'] ? 'ACTIVE' : 'already inactive';
    echo "<li>{$p['name']} {$p['size']} (SKU: {$p['sku']}, ID: {$p['id']}) — $status</li>";
}
echo '</ul>';

// Deactivate them
$deactivateStmt = $pdo->prepare("
    UPDATE products SET active = FALSE WHERE name = 'AlgiHeal Calcium Alginate'
");
$deactivateStmt->execute();
$affected = $deactivateStmt->rowCount();

echo "<p><strong>Deactivated $affected product(s).</strong></p>";
echo '<p>These products will no longer appear in product selectors.</p>';
echo '<p><a href="/admin/products.php">Back to Products</a></p>';
