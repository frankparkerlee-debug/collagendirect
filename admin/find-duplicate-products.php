<?php
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/db.php';

// Get all active products
$stmt = $pdo->query("
  SELECT id, name, size, price_wholesale, pieces_per_box, category, hcpcs_code, active
  FROM products
  WHERE active = TRUE
  ORDER BY name ASC, size ASC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by name + size to find potential duplicates
$grouped = [];
foreach ($products as $p) {
  $key = strtolower(trim($p['name'])) . '|' . strtolower(trim($p['size'] ?? ''));
  if (!isset($grouped[$key])) {
    $grouped[$key] = [];
  }
  $grouped[$key][] = $p;
}

// Find duplicates
$duplicates = array_filter($grouped, function($items) {
  return count($items) > 1;
});

?>
<!DOCTYPE html>
<html>
<head>
  <title>Duplicate Products</title>
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; padding: 2rem; max-width: 1200px; margin: 0 auto; }
    h1 { color: #1a1a1a; margin-bottom: 1rem; }
    .duplicate-group { background: #fff; border: 2px solid #e74c3c; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; }
    .duplicate-group h3 { color: #e74c3c; margin-top: 0; }
    .product-card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 1rem; margin-bottom: 1rem; }
    .product-card:last-child { margin-bottom: 0; }
    .field { margin-bottom: 0.5rem; }
    .field strong { display: inline-block; min-width: 120px; color: #666; }
    .actions { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #dee2e6; }
    .btn { padding: 0.5rem 1rem; border-radius: 4px; border: none; cursor: pointer; font-size: 0.875rem; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-danger:hover { background: #c0392b; }
    .no-duplicates { background: #d4edda; border: 2px solid #28a745; color: #155724; padding: 2rem; border-radius: 8px; text-align: center; }
    .product-id { font-family: monospace; font-size: 0.875rem; color: #666; }
  </style>
</head>
<body>
  <h1>Duplicate Products Report</h1>
  
  <?php if (empty($duplicates)): ?>
    <div class="no-duplicates">
      <h2>No Duplicates Found</h2>
      <p>All active products have unique name + size combinations.</p>
      <p><strong>Total Products:</strong> <?= count($products) ?></p>
    </div>
  <?php else: ?>
    <div style="background: #fff3cd; border: 2px solid #ffc107; color: #856404; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
      <strong>Warning:</strong> Found <?= count($duplicates) ?> product groups with duplicates. 
      Review below and consider deactivating older/incorrect entries.
    </div>
    
    <?php foreach ($duplicates as $key => $items): ?>
      <?php
        list($name, $size) = explode('|', $key);
      ?>
      <div class="duplicate-group">
        <h3><?= htmlspecialchars($name) ?> - <?= htmlspecialchars($size) ?></h3>
        <p><strong>Found <?= count($items) ?> entries:</strong></p>
        
        <?php foreach ($items as $p): ?>
          <div class="product-card">
            <div class="field">
              <strong>Product ID:</strong> 
              <span class="product-id"><?= htmlspecialchars($p['id']) ?></span>
            </div>
            <div class="field">
              <strong>Full Name:</strong> 
              <?= htmlspecialchars($p['name']) ?>
            </div>
            <div class="field">
              <strong>Size:</strong> 
              <?= htmlspecialchars($p['size'] ?? 'N/A') ?>
            </div>
            <div class="field">
              <strong>HCPCS Code:</strong> 
              <?= htmlspecialchars($p['hcpcs_code'] ?? 'N/A') ?>
            </div>
            <div class="field">
              <strong>Price (wholesale):</strong> 
              $<?= number_format($p['price_wholesale'] ?? 0, 2) ?>
            </div>
            <div class="field">
              <strong>Pieces per box:</strong> 
              <?= $p['pieces_per_box'] ?? 0 ?>
            </div>
            <div class="field">
              <strong>Category:</strong> 
              <?= htmlspecialchars($p['category'] ?? 'N/A') ?>
            </div>
            <div class="actions">
              <form method="POST" action="/admin/products.php" style="display: inline;" 
                    onsubmit="return confirm('Are you sure you want to deactivate this product?');">
                <input type="hidden" name="action" value="deactivate">
                <input type="hidden" name="product_id" value="<?= htmlspecialchars($p['id']) ?>">
                <button type="submit" class="btn btn-danger">Deactivate This Entry</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  
  <div style="margin-top: 2rem; padding: 1rem; background: #e9ecef; border-radius: 4px;">
    <p style="margin: 0;"><strong>Recommendation:</strong> Keep only one entry per product name + size combination. 
    Deactivate duplicates that have incorrect pricing, missing HCPCS codes, or are older imports.</p>
  </div>
</body>
</html>
