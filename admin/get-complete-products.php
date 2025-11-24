<?php
/**
 * Get complete products database information
 */
require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
  $stmt = $pdo->query("
    SELECT
      id,
      sku,
      name,
      description,
      price_admin,
      price_wholesale,
      category,
      size,
      hcpcs_code,
      cpt_code,
      pieces_per_box,
      cost_per_box,
      active,
      created_at
    FROM products
    ORDER BY name
  ");

  $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "=== COMPLETE PRODUCTS DATABASE ===\n\n";
  echo "Total Products: " . count($products) . "\n\n";
  echo str_repeat("=", 150) . "\n";

  foreach ($products as $idx => $product) {
    echo "PRODUCT #" . ($idx + 1) . "\n";
    echo str_repeat("-", 150) . "\n";

    foreach ($product as $key => $value) {
      $displayValue = $value ?? 'NULL';
      echo str_pad($key . ':', 25) . $displayValue . "\n";
    }

    echo "\n";
  }

  echo "\n" . str_repeat("=", 150) . "\n";
  echo "KEY FIELDS SUMMARY\n";
  echo str_repeat("=", 150) . "\n";
  printf("%-50s | %-12s | %-15s | %-10s | %-12s | %-12s | %-12s | %-12s\n",
    "Product Name", "SKU", "HCPCS", "Pieces/Box", "Price Admin", "Wholesale", "Cost/Box", "Active"
  );
  echo str_repeat("-", 150) . "\n";

  foreach ($products as $product) {
    printf("%-50s | %-12s | %-15s | %-10s | $%-11.2f | $%-11.2f | $%-11s | %-12s\n",
      substr($product['name'] ?? 'Unknown', 0, 50),
      substr($product['sku'] ?? 'N/A', 0, 12),
      $product['hcpcs_code'] ?? 'N/A',
      $product['pieces_per_box'] ?? 'N/A',
      (float)($product['price_admin'] ?? 0),
      (float)($product['price_wholesale'] ?? 0),
      ($product['cost_per_box'] !== null ? number_format((float)$product['cost_per_box'], 2) : 'NULL'),
      ($product['active'] === 't' || $product['active'] === true) ? 'YES' : 'NO'
    );
  }

  echo "\n\n" . str_repeat("=", 150) . "\n";
  echo "NOTES:\n";
  echo str_repeat("=", 150) . "\n";
  echo "• price_admin: Medicare/insurance reimbursement rate per piece (referral orders)\n";
  echo "• price_wholesale: Price per box for wholesale orders (practice DME)\n";
  echo "• cost_per_box: Our cost to procure/produce each box\n";
  echo "• pieces_per_box: Number of individual pieces in each box\n";
  echo "• HCPCS codes: Used for Medicare/insurance billing\n";
  echo "• Products without HCPCS codes are accessories (e.g., Disposable Tubing Set)\n";

} catch (PDOException $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
