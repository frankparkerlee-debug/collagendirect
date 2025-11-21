<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== CALCIUM ALGINATE PRODUCTS DEBUG ===\n\n";

// Check all calcium products
$stmt = $pdo->query("
  SELECT
    id,
    name,
    size,
    hcpcs_code,
    price_wholesale,
    pieces_per_box,
    active
  FROM products
  WHERE name ILIKE '%calcium%'
  ORDER BY name, size
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total calcium products in database: " . count($products) . "\n\n";

$withHCPCS = 0;
$withoutHCPCS = 0;

foreach ($products as $p) {
  $hasHCPCS = !empty($p['hcpcs_code']);
  if ($hasHCPCS) $withHCPCS++;
  else $withoutHCPCS++;

  echo ($p['active'] ? '✓' : '✗') . " " . $p['name'] . "\n";
  echo "    Size: " . ($p['size'] ?? 'NULL') . "\n";
  echo "    HCPCS: " . ($p['hcpcs_code'] ?? 'NULL') . "\n";
  echo "    Price: $" . ($p['price_wholesale'] ?? '0') . "\n";
  echo "    ID: " . $p['id'] . "\n\n";
}

echo "\n=== SUMMARY ===\n";
echo "Products with HCPCS code: $withHCPCS\n";
echo "Products without HCPCS code: $withoutHCPCS\n\n";

// Now test the deduplication query
echo "=== AFTER DEDUPLICATION (current logic) ===\n\n";

$stmt2 = $pdo->query("
  SELECT DISTINCT ON (COALESCE(hcpcs_code, ''), LOWER(TRIM(COALESCE(size, ''))))
    id, name, size, hcpcs_code, price_wholesale
  FROM products
  WHERE active = TRUE
    AND (name NOT ILIKE '%deprecated%' OR name IS NULL)
    AND (category NOT ILIKE '%deprecated%' OR category IS NULL)
    AND name ILIKE '%calcium%'
  ORDER BY
    COALESCE(hcpcs_code, ''),
    LOWER(TRIM(COALESCE(size, ''))),
    CASE WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN 0 ELSE 1 END,
    CASE WHEN price_wholesale > 0 THEN 0 ELSE 1 END,
    LENGTH(name) DESC,
    id ASC
");

$deduped = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Visible after deduplication: " . count($deduped) . "\n\n";

foreach ($deduped as $p) {
  echo "✓ " . $p['name'] . " (" . ($p['hcpcs_code'] ?? 'no HCPCS') . ") - Size: " . ($p['size'] ?? 'N/A') . "\n";
}
