<?php
/**
 * Analyze product name structure to see if they already combine name + size
 */
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/db.php';

echo "=== ANALYZING PRODUCT NAME STRUCTURE ===\n\n";

$stmt = $pdo->query("
  SELECT id, name, size
  FROM products
  WHERE active = TRUE
  ORDER BY name
  LIMIT 30
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Sample of product names and sizes:\n";
echo str_repeat("=", 120) . "\n";
printf("%-5s | %-60s | %-15s | %s\n", "ID", "Name", "Size", "Analysis");
echo str_repeat("-", 120) . "\n";

$patterns = [
  'has_size_in_name' => 0,
  'separate_size' => 0,
  'missing_size' => 0
];

foreach ($products as $p) {
  $name = $p['name'] ?? '';
  $size = $p['size'] ?? '';

  // Check if size appears in name
  $hasSizeInName = false;
  if ($size && $size !== '-' && stripos($name, $size) !== false) {
    $hasSizeInName = true;
    $patterns['has_size_in_name']++;
    $analysis = "✓ Size in name";
  } elseif ($size && $size !== '-') {
    $patterns['separate_size']++;
    $analysis = "→ Size separate";
  } else {
    $patterns['missing_size']++;
    $analysis = "⚠ No size";
  }

  printf("%-5s | %-60s | %-15s | %s\n",
    $p['id'],
    substr($name, 0, 60),
    substr($size, 0, 15),
    $analysis
  );
}

echo str_repeat("=", 120) . "\n\n";

echo "PATTERN ANALYSIS:\n";
echo "- Products with size IN name: {$patterns['has_size_in_name']}\n";
echo "- Products with size SEPARATE: {$patterns['separate_size']}\n";
echo "- Products missing size: {$patterns['missing_size']}\n\n";

// Check if we can extract base product name by removing size
echo "TESTING BASE NAME EXTRACTION:\n";
echo str_repeat("-", 120) . "\n";

foreach (array_slice($products, 0, 10) as $p) {
  $name = $p['name'];
  $size = $p['size'] ?? '';

  // Try to extract base name by removing size and HCPCS
  $baseName = $name;

  // Remove size pattern (e.g., "2x2", "4.13x4.13")
  $baseName = preg_replace('/\s*\d+\.?\d*\s*[xX×]\s*\d+\.?\d*\s*/', ' ', $baseName);

  // Remove HCPCS code pattern (e.g., "(A6196)")
  $baseName = preg_replace('/\s*\([A-Z]\d{4}\)\s*/', '', $baseName);

  // Clean up extra spaces
  $baseName = trim(preg_replace('/\s+/', ' ', $baseName));

  echo "Original: $name\n";
  echo "Base:     $baseName\n";
  echo "Size:     $size\n";
  echo str_repeat("-", 120) . "\n";
}

echo "\n\nRECOMMENDATION:\n";
if ($patterns['has_size_in_name'] > $patterns['separate_size']) {
  echo "✓ Most products ALREADY have size in the name\n";
  echo "✓ We should KEEP size column separate for filtering/sorting\n";
  echo "✓ Display name = product + size (e.g., 'Calcium Alginate' + '2x2')\n";
} else {
  echo "⚠ Products have size stored separately\n";
  echo "→ Need to decide: combine in display or keep separate?\n";
}
