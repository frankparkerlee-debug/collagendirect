<?php
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/db.php';

$stmt = $pdo->query("
  SELECT id, name, size, category, hcpcs_code, price_wholesale, pieces_per_box, active
  FROM products
  WHERE name ILIKE '%deprecated%' OR category ILIKE '%deprecated%'
  ORDER BY active DESC, name ASC
");
$deprecatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->query("
  SELECT id, name, size, category, hcpcs_code, active, COUNT(*) OVER (PARTITION BY LOWER(TRIM(name)), LOWER(TRIM(size))) as duplicate_count
  FROM products
  WHERE active = TRUE
    AND name NOT ILIKE '%deprecated%'
    AND (category NOT ILIKE '%deprecated%' OR category IS NULL)
  ORDER BY duplicate_count DESC, name ASC, size ASC
");
$allProducts = $stmt2->fetchAll(PDO::FETCH_ASSOC);
$duplicates = array_filter($allProducts, function($p) {
  return $p['duplicate_count'] > 1;
});
?>
<!DOCTYPE html>
<html>
<head>
  <title>Deprecated & Duplicate Products</title>
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; padding: 2rem; max-width: 1400px; margin: 0 auto; background: #f5f5f5; }
    h1 { color: #1a1a1a; margin-bottom: 0.5rem; }
    h2 { color: #333; margin-top: 2rem; margin-bottom: 1rem; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem; }
    .summary { display: flex; gap: 1rem; margin-bottom: 2rem; }
    .stat-card { flex: 1; background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .stat-card h3 { margin: 0 0 0.5rem 0; font-size: 0.875rem; color: #666; text-transform: uppercase; }
    .stat-card .number { font-size: 2rem; font-weight: 700; color: #1a1a1a; }
    .stat-card.warning .number { color: #f39c12; }
    .stat-card.danger .number { color: #e74c3c; }
    table { width: 100%; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    thead { background: #f8f9fa; }
    th { padding: 1rem; text-align: left; font-weight: 600; color: #666; font-size: 0.875rem; text-transform: uppercase; border-bottom: 2px solid #dee2e6; }
    td { padding: 1rem; border-bottom: 1px solid #f0f0f0; }
    tr:last-child td { border-bottom: none; }
    .inactive { opacity: 0.6; }
    .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
    .badge-danger { background: #fee; color: #e74c3c; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-success { background: #d4edda; color: #155724; }
    .badge-secondary { background: #e9ecef; color: #6c757d; }
    .product-id { font-family: monospace; font-size: 0.75rem; color: #999; }
    .actions { display: flex; gap: 0.5rem; }
    .btn { padding: 0.375rem 0.75rem; border-radius: 4px; border: none; cursor: pointer; font-size: 0.75rem; text-decoration: none; display: inline-block; }
    .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.7rem; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-primary { background: #3498db; color: white; }
    .highlight-duplicate { background: #fff3cd !important; }
    .empty-state { text-align: center; padding: 3rem; color: #999; }
  </style>
</head>
<body>
  <h1>Deprecated & Duplicate Products Report</h1>
  <p style="color: #666; margin-bottom: 2rem;">Review products that are marked as deprecated or have duplicates in the system.</p>

  <div class="summary">
    <div class="stat-card danger">
      <h3>Deprecated Products</h3>
      <div class="number"><?= count($deprecatedProducts) ?></div>
    </div>
    <div class="stat-card warning">
      <h3>Active Duplicates</h3>
      <div class="number"><?= count($duplicates) ?></div>
    </div>
    <div class="stat-card">
      <h3>Total Active Products</h3>
      <div class="number"><?= count($allProducts) ?></div>
    </div>
  </div>

  <h2>🚫 Deprecated Products (<?= count($deprecatedProducts) ?>)</h2>
  <?php if (empty($deprecatedProducts)): ?>
    <div class="empty-state">
      <p><strong>No deprecated products found.</strong></p>
    </div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Status</th>
          <th>Product Name</th>
          <th>Size</th>
          <th>Category</th>
          <th>HCPCS</th>
          <th>Price</th>
          <th>Product ID</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($deprecatedProducts as $p): ?>
          <tr class="<?= !$p['active'] ? 'inactive' : '' ?>">
            <td>
              <span class="badge <?= $p['active'] ? 'badge-warning' : 'badge-secondary' ?>">
                <?= $p['active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
            <td><?= htmlspecialchars($p['size'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($p['category'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($p['hcpcs_code'] ?? 'N/A') ?></td>
            <td>$<?= number_format($p['price_wholesale'] ?? 0, 2) ?></td>
            <td class="product-id"><?= substr($p['id'], 0, 16) ?>...</td>
            <td>
              <?php if ($p['active']): ?>
                <form method="POST" action="/admin/products.php" style="display: inline;">
                  <input type="hidden" name="action" value="deactivate">
                  <input type="hidden" name="product_id" value="<?= htmlspecialchars($p['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Deactivate this deprecated product?')">
                    Deactivate
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2>⚠️ Active Duplicate Products (<?= count($duplicates) ?>)</h2>
  <?php if (empty($duplicates)): ?>
    <div class="empty-state">
      <p><strong>No duplicate products found.</strong></p>
      <p>All products have unique name + size combinations.</p>
    </div>
  <?php else: ?>
    <p style="color: #856404; background: #fff3cd; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
      <strong>Note:</strong> These products share the same name and size. Consider keeping only one version.
    </p>
    <table>
      <thead>
        <tr>
          <th>Duplicates</th>
          <th>Product Name</th>
          <th>Size</th>
          <th>Category</th>
          <th>HCPCS</th>
          <th>Price</th>
          <th>Product ID</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($duplicates as $p): ?>
          <tr class="highlight-duplicate">
            <td>
              <span class="badge badge-warning"><?= $p['duplicate_count'] ?>x</span>
            </td>
            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
            <td><?= htmlspecialchars($p['size'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($p['category'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($p['hcpcs_code'] ?? 'N/A') ?></td>
            <td>$<?= number_format($p['price_wholesale'] ?? 0, 2) ?></td>
            <td class="product-id"><?= substr($p['id'], 0, 16) ?>...</td>
            <td>
              <div class="actions">
                <a href="/admin/find-duplicate-products.php" class="btn btn-sm btn-primary">
                  Review All
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div style="margin-top: 2rem; padding: 1rem; background: #e7f3ff; border-left: 4px solid #3498db; border-radius: 4px;">
    <p style="margin: 0;"><strong>💡 Recommendation:</strong></p>
    <ul style="margin: 0.5rem 0 0 0; padding-left: 1.5rem;">
      <li>Deactivate all deprecated products to clean up the product list</li>
      <li>For duplicates, visit <a href="/admin/find-duplicate-products.php">/admin/find-duplicate-products.php</a> to review and remove extras</li>
      <li>Keep products with complete information (HCPCS codes, correct pricing, etc.)</li>
    </ul>
  </div>
</body>
</html>
