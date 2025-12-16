<?php
/**
 * Web-based runner for wholesale billing columns migration
 *
 * Access: /admin/run-add-wholesale-columns.php
 * Requires superadmin role
 */

declare(strict_types=1);
require_once __DIR__ . '/_header.php';

// Only superadmin can run migrations
if (($admin['role'] ?? '') !== 'superadmin') {
    echo '<div class="card p-6"><p class="text-red-600">Access denied. Superadmin required.</p></div>';
    require_once __DIR__ . '/_footer.php';
    exit;
}

$migrationRun = false;
$output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    verify_csrf();

    // Capture output
    ob_start();
    try {
        include __DIR__ . '/migrations/add_wholesale_billing_columns.php';
        $migrationRun = true;
    } catch (Throwable $e) {
        echo "\nError: " . $e->getMessage() . "\n";
    }
    $output = ob_get_clean();
}
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="/admin/billing-wholesale.php" class="text-sm text-gray-500 hover:text-gray-700">
            &larr; Back to Wholesale Billing
        </a>
        <h1 class="text-2xl font-bold mt-4">Add Wholesale Billing Columns</h1>
        <p class="text-gray-600 mt-2">
            This migration adds the database columns required for wholesale billing functionality.
        </p>
    </div>

    <?php if ($migrationRun): ?>
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4 text-green-600">Migration Output</h2>
            <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm font-mono whitespace-pre-wrap"><?= htmlspecialchars($output) ?></pre>
        </div>

        <div class="flex gap-4">
            <a href="/admin/billing-wholesale.php" class="btn btn-primary">
                View Wholesale Billing &rarr;
            </a>
            <a href="/admin/run-add-wholesale-columns.php" class="btn btn-secondary">
                Run Again
            </a>
        </div>
    <?php else: ?>
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">What this migration does:</h2>
            <ul class="list-disc list-inside space-y-2 text-gray-700">
                <li>Adds <code class="bg-gray-100 px-1 rounded">due_date</code> column to orders table</li>
                <li>Adds <code class="bg-gray-100 px-1 rounded">default_payment_terms</code> column to users table</li>
                <li>Adds <code class="bg-gray-100 px-1 rounded">credit_limit</code> and <code class="bg-gray-100 px-1 rounded">collection_flag</code> to users table</li>
                <li>Adds billing contact fields to users table</li>
                <li>Adds invoice lifecycle columns to orders table</li>
                <li>Creates <code class="bg-gray-100 px-1 rounded">wholesale_payments</code> table if not exists</li>
                <li>Sets default payment terms (net30) for wholesale practices</li>
                <li>Sets default due_date (30 days from order) for wholesale orders</li>
            </ul>
        </div>

        <div class="card p-6 bg-amber-50 border-amber-200 mb-6">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <h3 class="font-semibold text-amber-800">Before Running</h3>
                    <p class="text-amber-700 mt-1">
                        This migration is safe to run multiple times. It uses IF NOT EXISTS for all column additions
                        and only updates records that need updating.
                    </p>
                </div>
            </div>
        </div>

        <form method="POST">
            <?= csrf_field() ?>
            <button type="submit" name="run_migration" value="1" class="btn btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Run Migration
            </button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
