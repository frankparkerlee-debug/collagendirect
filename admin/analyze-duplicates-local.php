#!/usr/bin/env php
<?php
/**
 * Identify duplicate products by reference number - Local execution
 */
require_once __DIR__ . '/../api/db.php';

echo "=== IDENTIFYING DUPLICATE PRODUCTS ===\n\n";

// Get all products grouped by reference number
$stmt = $pdo->query("
  SELECT
    id,
    sku AS reference_number,
    name,
    size,
    hcpcs_code,
    price_wholesale,
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
  ORDER BY sku, created_at
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

// Find incomplete products
$incomplete = array_filter($products, function($p) {
  return $p['completeness'] !== 'COMPLETE';
});

echo "SUMMARY:\n";
echo "Total active products: " . count($products) . "\n";
echo "Duplicate reference numbers: " . count($duplicates) . "\n";
echo "Products missing key info: " . count($incomplete) . "\n\n";

// Show duplicates
if (!empty($duplicates)) {
  echo str_repeat("=", 120) . "\n";
  echo "DUPLICATE PRODUCTS (same reference number):\n";
  echo str_repeat("=", 120) . "\n\n";

  foreach ($duplicates as $ref => $group) {
    echo "Reference Number: $ref (" . count($group) . " products)\n";
    echo str_repeat("-", 120) . "\n";

    foreach ($group as $p) {
      printf("  ID: %-4s | %-50s | Size: %-8s | HCPCS: %-8s | Status: %-15s | Created: %s\n",
        $p['id'],
        substr($p['name'], 0, 50),
        $p['size'] ?? 'NULL',
        $p['hcpcs_code'] ?? 'NULL',
        $p['completeness'],
        substr($p['created_at'], 0, 10)
      );
    }

    // Recommend which to keep
    $complete = array_filter($group, fn($p) => $p['completeness'] === 'COMPLETE');
    $incomplete_in_group = array_filter($group, fn($p) => $p['completeness'] !== 'COMPLETE');

    if (!empty($complete) && !empty($incomplete_in_group)) {
      echo "\n  RECOMMENDATION: Keep complete product(s), DELETE incomplete:\n";
      foreach ($complete as $p) {
        echo "    ✓ KEEP ID {$p['id']}: {$p['name']}\n";
      }
      foreach ($incomplete_in_group as $p) {
        echo "    ✗ DELETE ID {$p['id']}: {$p['name']} (missing: " .
             ($p['completeness'] === 'MISSING_HCPCS' ? 'HCPCS' : 'SIZE') . ")\n";
      }
    } elseif (count($group) > 1) {
      // All complete or all incomplete - keep newest
      usort($group, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
      echo "\n  RECOMMENDATION: Keep newest, DELETE older:\n";
      echo "    ✓ KEEP ID {$group[0]['id']}: {$group[0]['name']} (newest)\n";
      for ($i = 1; $i < count($group); $i++) {
        echo "    ✗ DELETE ID {$group[$i]['id']}: {$group[$i]['name']} (older)\n";
      }
    }

    echo "\n\n";
  }
}

// Show incomplete products (non-duplicates)
$incomplete_non_dup = array_filter($incomplete, function($p) use ($grouped) {
  $ref = $p['reference_number'];
  return count($grouped[$ref]) === 1;
});

if (!empty($incomplete_non_dup)) {
  echo str_repeat("=", 120) . "\n";
  echo "INCOMPLETE PRODUCTS (not duplicates, but missing key info):\n";
  echo str_repeat("=", 120) . "\n\n";

  foreach ($incomplete_non_dup as $p) {
    printf("ID: %-4s | %-50s | Size: %-8s | HCPCS: %-8s | Ref: %s\n",
      $p['id'],
      substr($p['name'], 0, 50),
      $p['size'] ?? 'NULL',
      $p['hcpcs_code'] ?? 'NULL',
      $p['reference_number']
    );
  }

  echo "\n  RECOMMENDATION: Review these products - they may need HCPCS/size data or should be deleted\n\n";
}

// Generate DELETE statements
echo str_repeat("=", 120) . "\n";
echo "SUGGESTED DELETIONS (SET TO INACTIVE):\n";
echo str_repeat("=", 120) . "\n\n";

$to_delete = [];

// Add incomplete products from duplicate groups
foreach ($duplicates as $ref => $group) {
  $complete = array_filter($group, fn($p) => $p['completeness'] === 'COMPLETE');
  $incomplete_in_group = array_filter($group, fn($p) => $p['completeness'] !== 'COMPLETE');

  if (!empty($complete) && !empty($incomplete_in_group)) {
    foreach ($incomplete_in_group as $p) {
      $to_delete[] = $p['id'];
    }
  } elseif (count($group) > 1) {
    // Keep newest
    usort($group, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    for ($i = 1; $i < count($group); $i++) {
      $to_delete[] = $group[$i]['id'];
    }
  }
}

if (!empty($to_delete)) {
  echo "-- SQL to deactivate duplicates:\n";
  echo "UPDATE products SET active = FALSE WHERE id IN (" . implode(', ', $to_delete) . ");\n\n";
  echo "Products to deactivate: " . count($to_delete) . "\n";
  echo "Products remaining: " . (count($products) - count($to_delete)) . "\n\n";
}
