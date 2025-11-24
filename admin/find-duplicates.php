#!/usr/bin/env php
<?php
// Direct database query without headers
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'collagen_db';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '5432';

try {
  $pdo = new PDO(
    "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}",
    $DB_USER,
    $DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  echo "=== FINDING DUPLICATE PRODUCTS ===\n\n";

  // Find duplicates by SKU
  $stmt = $pdo->query("
    SELECT sku, COUNT(*) as count
    FROM products
    WHERE active = TRUE
    GROUP BY sku
    HAVING COUNT(*) > 1
    ORDER BY COUNT(*) DESC, sku
  ");

  $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($duplicates)) {
    echo "No duplicate reference numbers found!\n";
    exit(0);
  }

  echo "Found " . count($duplicates) . " duplicate reference number(s):\n";
  echo str_repeat("=", 120) . "\n\n";

  $to_delete = [];

  // Get details for each duplicate
  foreach ($duplicates as $dup) {
    $detailStmt = $pdo->prepare("
      SELECT id, name, size, hcpcs_code, price_wholesale, pieces_per_box, created_at
      FROM products
      WHERE sku = ? AND active = TRUE
      ORDER BY
        CASE WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN 0 ELSE 1 END,
        CASE WHEN size IS NOT NULL AND size != '' AND size != '-' THEN 0 ELSE 1 END,
        created_at DESC
    ");
    $detailStmt->execute([$dup['sku']]);
    $products = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Reference Number: {$dup['sku']} ({$dup['count']} products)\n";
    echo str_repeat("-", 120) . "\n";

    $keep_id = null;
    foreach ($products as $idx => $p) {
      $has_hcpcs = !empty($p['hcpcs_code']);
      $has_size = !empty($p['size']) && $p['size'] !== '-';
      $is_complete = $has_hcpcs && $has_size;

      $marker = '';
      if ($idx === 0) {
        $marker = '✓ KEEP';
        $keep_id = $p['id'];
      } else {
        $marker = '✗ DELETE';
        $to_delete[] = $p['id'];
      }

      printf("  %s | ID: %-4d | %-50s | Size: %-8s | HCPCS: %-8s | %s | Created: %s\n",
        str_pad($marker, 10),
        $p['id'],
        substr($p['name'], 0, 50),
        $p['size'] ?? 'NULL',
        $p['hcpcs_code'] ?? 'NULL',
        $is_complete ? 'COMPLETE  ' : 'INCOMPLETE',
        substr($p['created_at'], 0, 10)
      );
    }

    echo "\n";
  }

  // Also find products missing key info (not duplicates)
  echo str_repeat("=", 120) . "\n";
  echo "PRODUCTS MISSING KEY INFO (not duplicates):\n";
  echo str_repeat("=", 120) . "\n\n";

  $incompleteStmt = $pdo->query("
    SELECT id, sku, name, size, hcpcs_code
    FROM products
    WHERE active = TRUE
      AND (
        hcpcs_code IS NULL OR hcpcs_code = '' OR
        size IS NULL OR size = '' OR size = '-'
      )
      AND sku NOT IN (
        SELECT sku FROM products WHERE active = TRUE GROUP BY sku HAVING COUNT(*) > 1
      )
    ORDER BY name
  ");

  $incomplete = $incompleteStmt->fetchAll(PDO::FETCH_ASSOC);

  if (!empty($incomplete)) {
    foreach ($incomplete as $p) {
      $missing = [];
      if (empty($p['hcpcs_code'])) $missing[] = 'HCPCS';
      if (empty($p['size']) || $p['size'] === '-') $missing[] = 'SIZE';

      printf("  ID: %-4d | %-50s | Missing: %s\n",
        $p['id'],
        substr($p['name'], 0, 50),
        implode(', ', $missing)
      );

      $to_delete[] = $p['id'];
    }
    echo "\n";
  } else {
    echo "  None found\n\n";
  }

  // Summary
  echo str_repeat("=", 120) . "\n";
  echo "SUMMARY:\n";
  echo str_repeat("=", 120) . "\n\n";

  $totalActive = $pdo->query("SELECT COUNT(*) FROM products WHERE active = TRUE")->fetchColumn();

  echo "Total active products: $totalActive\n";
  echo "Products to deactivate: " . count($to_delete) . "\n";
  echo "Products remaining: " . ($totalActive - count($to_delete)) . "\n\n";

  if (!empty($to_delete)) {
    echo "SQL TO DEACTIVATE:\n";
    echo "UPDATE products SET active = FALSE WHERE id IN (" . implode(', ', $to_delete) . ");\n\n";

    // Ask for confirmation
    echo "Do you want to execute this deactivation? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if (strtolower($line) === 'yes') {
      $pdo->exec("UPDATE products SET active = FALSE WHERE id IN (" . implode(', ', $to_delete) . ")");
      echo "\n✓ Deactivated " . count($to_delete) . " products\n";
    } else {
      echo "\nCancelled. No changes made.\n";
    }
  }

} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  exit(1);
}
