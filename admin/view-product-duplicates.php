<?php
/**
 * View duplicate products by reference number (SKU)
 * This page shows which products are duplicates and recommends which to keep/delete
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$admin = current_admin();

// Restrict access
if ($admin['role'] !== 'superadmin' && $admin['role'] !== 'manufacturer') {
  http_response_code(403);
  die('<h1>403 Forbidden</h1>');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Product Duplicates Analysis</title>
  <style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; }
    h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
    h2 { color: #555; margin-top: 30px; }
    .summary { background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
    .duplicate-group { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 15px 0; }
    .product-row { padding: 8px; margin: 5px 0; border-left: 3px solid #ccc; }
    .keep { border-left-color: #28a745; background: #d4edda; }
    .delete { border-left-color: #dc3545; background: #f8d7da; }
    .incomplete { border-left-color: #ffc107; background: #fff3cd; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background: #007bff; color: white; }
    .badge { padding: 3px 8px; border-radius: 3px; font-size: 0.85em; }
    .badge-success { background: #28a745; color: white; }
    .badge-danger { background: #dc3545; color: white; }
    .badge-warning { background: #ffc107; color: #333; }
    .btn { padding: 10px 20px; margin: 10px 5px 10px 0; border: none; border-radius: 4px; cursor: pointer; }
    .btn-primary { background: #007bff; color: white; }
    .btn-danger { background: #dc3545; color: white; }
    .btn:hover { opacity: 0.8; }
  </style>
</head>
<body>
<div class="container">
  <h1>Product Duplicates Analysis</h1>
  <p><a href="/admin/products.php">← Back to Products</a></p>

<?php

// Get all active products
$stmt = $pdo->query("
  SELECT
    id,
    sku AS reference_number,
    name,
    size,
    hcpcs_code,
    price_wholesale,
    price_referral,
    pieces_per_box,
    active,
    created_at,
    CASE
      WHEN hcpcs_code IS NULL OR TRIM(hcpcs_code) = '' THEN 'MISSING_HCPCS'
      WHEN size IS NULL OR TRIM(size) = '' OR size = '-' THEN 'MISSING_SIZE'
      ELSE 'COMPLETE'
    END AS completeness
  FROM products
  WHERE active = TRUE
  ORDER BY sku, created_at DESC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by reference number
$grouped = [];
foreach ($products as $p) {
  $ref = $p['reference_number'] ?? 'UNKNOWN';
  if (!isset($grouped[$ref])) {
    $grouped[$ref] = [];
  }
  $grouped[$ref][] = $p;
}

// Find duplicates
$duplicates = array_filter($grouped, function($group) {
  return count($group) > 1;
});

// Count incomplete
$incomplete_count = count(array_filter($products, fn($p) => $p['completeness'] !== 'COMPLETE'));

echo '<div class="summary">';
echo '<h2>Summary</h2>';
echo '<strong>Total active products:</strong> ' . count($products) . '<br>';
echo '<strong>Duplicate reference numbers:</strong> ' . count($duplicates) . '<br>';
echo '<strong>Products missing key info:</strong> ' . $incomplete_count . '<br>';
echo '</div>';

// Show duplicates
if (!empty($duplicates)) {
  echo '<h2>Duplicate Products (Same Reference Number)</h2>';
  echo '<p>These products share the same SKU/reference number. We recommend keeping the most complete and newest version.</p>';

  $to_delete_ids = [];

  foreach ($duplicates as $ref => $group) {
    echo '<div class="duplicate-group">';
    echo '<h3>Reference Number: ' . htmlspecialchars($ref) . ' (' . count($group) . ' products)</h3>';

    echo '<table>';
    echo '<tr>';
    echo '<th>Action</th>';
    echo '<th>ID</th>';
    echo '<th>Product Name</th>';
    echo '<th>Size</th>';
    echo '<th>HCPCS</th>';
    echo '<th>Status</th>';
    echo '<th>Price/Box</th>';
    echo '<th>Pieces/Box</th>';
    echo '<th>Created</th>';
    echo '</tr>';

    // Determine which to keep (first = most complete + newest due to ORDER BY)
    foreach ($group as $idx => $p) {
      $is_complete = ($p['completeness'] === 'COMPLETE');
      $action = ($idx === 0) ? 'KEEP' : 'DELETE';
      $row_class = ($idx === 0) ? 'keep' : 'delete';

      if ($idx !== 0) {
        $to_delete_ids[] = $p['id'];
      }

      echo '<tr class="' . $row_class . '">';
      echo '<td><span class="badge badge-' . ($idx === 0 ? 'success' : 'danger') . '">' . $action . '</span></td>';
      echo '<td>' . $p['id'] . '</td>';
      echo '<td>' . htmlspecialchars(substr($p['name'], 0, 60)) . '</td>';
      echo '<td>' . htmlspecialchars($p['size'] ?? 'NULL') . '</td>';
      echo '<td>' . htmlspecialchars($p['hcpcs_code'] ?? 'NULL') . '</td>';
      echo '<td>' . ($is_complete ? '<span class="badge badge-success">Complete</span>' : '<span class="badge badge-warning">Incomplete</span>') . '</td>';
      echo '<td>$' . number_format($p['price_wholesale'], 2) . '</td>';
      echo '<td>' . $p['pieces_per_box'] . '</td>';
      echo '<td>' . substr($p['created_at'], 0, 10) . '</td>';
      echo '</tr>';
    }

    echo '</table>';
    echo '</div>';
  }

  // Show delete button
  if (!empty($to_delete_ids)) {
    echo '<div class="summary">';
    echo '<h2>Recommended Action</h2>';
    echo '<p><strong>Deactivate ' . count($to_delete_ids) . ' duplicate products</strong></p>';
    echo '<p>IDs to deactivate: ' . implode(', ', $to_delete_ids) . '</p>';
    echo '<form method="POST" action="/admin/products.php" onsubmit="return confirm(\'Are you sure you want to deactivate ' . count($to_delete_ids) . ' duplicate products?\');">';
    echo '<input type="hidden" name="action" value="bulk_deactivate">';
    echo '<input type="hidden" name="product_ids" value="' . implode(',', $to_delete_ids) . '">';
    echo '<button type="submit" class="btn btn-danger">Deactivate ' . count($to_delete_ids) . ' Duplicate Products</button>';
    echo '</form>';
    echo '</div>';
  }
} else {
  echo '<div class="summary">';
  echo '<h2>No Duplicates Found</h2>';
  echo '<p>All products have unique reference numbers!</p>';
  echo '</div>';
}

// Show incomplete products (non-duplicates)
$incomplete_non_dup = array_filter($products, function($p) use ($grouped) {
  $ref = $p['reference_number'];
  return count($grouped[$ref]) === 1 && $p['completeness'] !== 'COMPLETE';
});

if (!empty($incomplete_non_dup)) {
  echo '<h2>Incomplete Products (Not Duplicates)</h2>';
  echo '<p>These products are missing HCPCS codes or size information:</p>';

  echo '<table>';
  echo '<tr><th>ID</th><th>Name</th><th>Size</th><th>HCPCS</th><th>Missing</th><th>SKU</th></tr>';

  foreach ($incomplete_non_dup as $p) {
    $missing = [];
    if (empty($p['hcpcs_code'])) $missing[] = 'HCPCS';
    if (empty($p['size']) || $p['size'] === '-') $missing[] = 'SIZE';

    echo '<tr class="incomplete">';
    echo '<td>' . $p['id'] . '</td>';
    echo '<td>' . htmlspecialchars(substr($p['name'], 0, 60)) . '</td>';
    echo '<td>' . htmlspecialchars($p['size'] ?? 'NULL') . '</td>';
    echo '<td>' . htmlspecialchars($p['hcpcs_code'] ?? 'NULL') . '</td>';
    echo '<td><span class="badge badge-warning">' . implode(', ', $missing) . '</span></td>';
    echo '<td>' . htmlspecialchars($p['reference_number']) . '</td>';
    echo '</tr>';
  }

  echo '</table>';
  echo '<p><em>These may need additional data or should be reviewed manually.</em></p>';
}

?>

</div>
</body>
</html>
