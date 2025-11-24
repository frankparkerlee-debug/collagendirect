#!/usr/bin/env php
<?php
require_once __DIR__ . '/../api/db.php';

try {
  $stmt = $pdo->query("
    SELECT sku, COUNT(*) as count
    FROM products
    WHERE active = TRUE
    GROUP BY sku
    HAVING COUNT(*) > 1
    ORDER BY COUNT(*) DESC, sku
  ");

  $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Duplicate Reference Numbers:\n";
  echo str_repeat("=", 60) . "\n";

  foreach ($duplicates as $dup) {
    echo "{$dup['sku']}: {$dup['count']} products\n";
  }

  echo "\nTotal duplicate groups: " . count($duplicates) . "\n\n";

  // Get details for each duplicate
  foreach ($duplicates as $dup) {
    $detailStmt = $pdo->prepare("
      SELECT id, name, size, hcpcs_code, created_at
      FROM products
      WHERE sku = ? AND active = TRUE
      ORDER BY created_at
    ");
    $detailStmt->execute([$dup['sku']]);
    $products = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nReference: {$dup['sku']}\n";
    echo str_repeat("-", 120) . "\n";
    foreach ($products as $p) {
      printf("ID: %-4d | %-60s | Size: %-8s | HCPCS: %-8s | Created: %s\n",
        $p['id'],
        substr($p['name'], 0, 60),
        $p['size'] ?? 'NULL',
        $p['hcpcs_code'] ?? 'NULL',
        substr($p['created_at'], 0, 10)
      );
    }
  }

} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}
