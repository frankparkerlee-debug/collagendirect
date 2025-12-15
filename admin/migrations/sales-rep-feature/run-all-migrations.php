<?php
/**
 * Sales Rep Feature - Run All Migrations
 *
 * Executes all sales rep feature migrations in order.
 * Run this file to set up the complete sales rep schema.
 *
 * Usage: php admin/migrations/sales-rep-feature/run-all-migrations.php
 */

declare(strict_types=1);

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║       SALES REP FEATURE - DATABASE MIGRATION RUNNER          ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$migrations = [
  '001_create_sales_reps.php',
  '002_create_rep_commission_rates.php',
  '003_create_rep_signed_documents.php',
  '004_create_rep_assignment_requests.php',
  '005_create_rep_commission_ledger.php',
  '006_create_rep_commission_payouts.php',
  '007_add_rep_columns_to_users.php',
];

$migrationDir = __DIR__;
$successCount = 0;
$failCount = 0;

foreach ($migrations as $index => $migration) {
  $num = $index + 1;
  $total = count($migrations);

  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
  echo "[$num/$total] Running: $migration\n";
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

  $migrationPath = $migrationDir . '/' . $migration;

  if (!file_exists($migrationPath)) {
    echo "✗ Migration file not found: $migrationPath\n\n";
    $failCount++;
    continue;
  }

  // Capture output from migration
  ob_start();
  $returnCode = 0;

  try {
    // Each migration is self-contained, so we include it
    // They will exit(0) on success or exit(1) on failure
    include $migrationPath;
    $output = ob_get_clean();
    echo $output;
    $successCount++;
  } catch (Throwable $e) {
    $output = ob_get_clean();
    echo $output;
    echo "\n✗ Migration threw exception: " . $e->getMessage() . "\n";
    $failCount++;
  }

  echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "                     MIGRATION SUMMARY                        \n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Total migrations: " . count($migrations) . "\n";
echo "Successful: $successCount\n";
echo "Failed: $failCount\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if ($failCount > 0) {
  echo "⚠️  Some migrations failed. Please review the output above.\n";
  exit(1);
} else {
  echo "✓ All migrations completed successfully!\n\n";
  echo "Next steps:\n";
  echo "1. Verify tables were created: SELECT table_name FROM information_schema.tables WHERE table_name LIKE 'sales_reps%' OR table_name LIKE 'rep_%';\n";
  echo "2. Review the schema documentation at /docs/sales-rep-feature/SCHEMA.md\n";
  echo "3. Proceed with implementing the sales rep portal UI\n";
  exit(0);
}
