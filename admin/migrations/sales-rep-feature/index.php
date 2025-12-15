<?php
/**
 * Sales Rep Feature - Web Migration Runner
 *
 * Access via: https://collagendirect.health/admin/migrations/sales-rep-feature/
 * Requires admin authentication (superadmin only)
 */

declare(strict_types=1);

// Use admin db.php for auth and database connection
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

// Require superadmin
$admin = current_admin();
if (!$admin || $admin['role'] !== 'superadmin') {
  http_response_code(403);
  die('Access denied. Superadmin required.');
}

// CSRF protection for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf']) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    http_response_code(419);
    die('CSRF token invalid');
  }
}

$runMigrations = isset($_POST['run']) && $_POST['run'] === '1';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales Rep Feature - Migrations</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-8">
  <div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Sales Rep Feature - Database Migrations</h1>
      <p class="text-gray-600">Logged in as: <strong><?= htmlspecialchars($admin['email']) ?></strong> (<?= htmlspecialchars($admin['role']) ?>)</p>
    </div>

    <?php if (!$runMigrations): ?>
    <!-- Migration list and confirmation -->
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">Migrations to Run</h2>
      <ol class="list-decimal list-inside space-y-2 text-gray-700 mb-6">
        <li><code class="bg-gray-100 px-2 py-0.5 rounded">001_create_sales_reps.php</code> - Sales rep profile table</li>
        <li><code class="bg-gray-100 px-2 py-0.5 rounded">002_create_rep_commission_rates.php</code> - Commission rate history</li>
        <li><code class="bg-gray-100 px-2 py-0.5 rounded">003_create_rep_signed_documents.php</code> - E-signature records</li>
        <li><code class="bg-gray-100 px-2 py-0.5 rounded">004_create_rep_assignment_requests.php</code> - Clinic assignment workflow</li>
        <li><code class="bg-gray-100 px-2 py-0.5 rounded">005_create_rep_commission_ledger.php</code> - Per-order commission entries</li>
        <li><code class="bg-gray-100 px-2 py-0.5 rounded">006_create_rep_commission_payouts.php</code> - Payout records</li>
        <li><code class="bg-gray-100 px-2 py-0.5 rounded">007_add_rep_columns_to_users.php</code> - Add rep columns to users table</li>
      </ol>

      <form method="POST" onsubmit="return confirm('Are you sure you want to run these migrations? This will modify the database.');">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
        <input type="hidden" name="run" value="1">
        <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white font-semibold px-6 py-3 rounded-lg">
          Run All Migrations
        </button>
      </form>
    </div>

    <?php else: ?>
    <!-- Run migrations and show output -->
    <div class="bg-white rounded-xl shadow-sm border p-6">
      <h2 class="text-lg font-semibold mb-4">Migration Output</h2>
      <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm leading-relaxed"><?php

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

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║       SALES REP FEATURE - DATABASE MIGRATION RUNNER          ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

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

  try {
    // Run migration in isolated scope
    $runMigration = function($path, $pdo) {
      // Re-declare $pdo in the migration's scope
      include $path;
    };

    $runMigration($migrationPath, $pdo);
    $successCount++;
  } catch (Throwable $e) {
    echo "\n✗ Migration threw exception: " . htmlspecialchars($e->getMessage()) . "\n";
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
} else {
  echo "✓ All migrations completed successfully!\n";
}

?></pre>

      <div class="mt-6">
        <a href="/admin/migrations/sales-rep-feature/" class="text-teal-600 hover:underline">← Back to migration list</a>
        <span class="mx-2 text-gray-400">|</span>
        <a href="/admin/" class="text-teal-600 hover:underline">Return to Admin Dashboard</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Verification section -->
    <div class="bg-white rounded-xl shadow-sm border p-6 mt-6">
      <h2 class="text-lg font-semibold mb-4">Verify Tables</h2>
      <?php
      try {
        $tables = [
          'sales_reps',
          'rep_commission_rates',
          'rep_signed_documents',
          'rep_assignment_requests',
          'rep_commission_ledger',
          'rep_commission_payouts'
        ];

        echo '<table class="w-full text-sm">';
        echo '<thead><tr class="text-left border-b"><th class="py-2">Table</th><th class="py-2">Status</th><th class="py-2">Row Count</th></tr></thead>';
        echo '<tbody>';

        foreach ($tables as $table) {
          $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_name = ?");
          $stmt->execute([$table]);
          $exists = (int)$stmt->fetch()['count'] > 0;

          $rowCount = '-';
          if ($exists) {
            try {
              $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
              $rowCount = $countStmt->fetch()['count'];
            } catch (Exception $e) {
              $rowCount = 'Error';
            }
          }

          $statusClass = $exists ? 'text-green-600' : 'text-gray-400';
          $statusText = $exists ? '✓ Exists' : '○ Not created';

          echo "<tr class='border-b'>";
          echo "<td class='py-2 font-mono text-xs'>{$table}</td>";
          echo "<td class='py-2 {$statusClass}'>{$statusText}</td>";
          echo "<td class='py-2'>{$rowCount}</td>";
          echo "</tr>";
        }

        // Check users table columns
        $columns = ['assigned_rep_id', 'rep_assignment_date', 'rep_assigned_by', 'rep_assigned_by_user_id'];
        foreach ($columns as $col) {
          $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.columns WHERE table_name = 'users' AND column_name = ?");
          $stmt->execute([$col]);
          $exists = (int)$stmt->fetch()['count'] > 0;

          $statusClass = $exists ? 'text-green-600' : 'text-gray-400';
          $statusText = $exists ? '✓ Exists' : '○ Not created';

          echo "<tr class='border-b'>";
          echo "<td class='py-2 font-mono text-xs'>users.{$col}</td>";
          echo "<td class='py-2 {$statusClass}'>{$statusText}</td>";
          echo "<td class='py-2'>-</td>";
          echo "</tr>";
        }

        echo '</tbody></table>';
      } catch (Exception $e) {
        echo '<p class="text-red-600">Error checking tables: ' . htmlspecialchars($e->getMessage()) . '</p>';
      }
      ?>
    </div>
  </div>
</body>
</html>
