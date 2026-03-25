<?php
/**
 * Add IVR (Insurance Verification Record) upload columns to orders table
 * Run once via admin panel, then this file can be removed.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$admin = current_admin();
if (!$admin || ($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    die('Superadmin access required');
}

header('Content-Type: text/html; charset=utf-8');

echo '<h2>Add IVR Upload Columns to Orders Table</h2>';

$columns = [
    'ivr_path' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS ivr_path VARCHAR(255)",
    'ivr_name' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS ivr_name VARCHAR(255)",
    'ivr_mime' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS ivr_mime VARCHAR(100)",
];

foreach ($columns as $col => $sql) {
    try {
        // Check if column already exists
        $check = $pdo->prepare("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_name = 'orders' AND column_name = ?
        ");
        $check->execute([$col]);

        if ($check->fetch()) {
            echo "<p>Column <code>$col</code> already exists. Skipping.</p>";
        } else {
            $pdo->exec($sql);
            echo "<p>Added column <code>$col</code> to orders table.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red'>Error adding column <code>$col</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Also ensure the billed_by column allows 'healkit' value (it's VARCHAR, so just document it)
echo '<p>Reminder: The <code>billed_by</code> column is VARCHAR and now accepts: NULL (referral), "practice_dme" (wholesale), "healkit" (HealKit orders).</p>';

echo '<h3>Done</h3>';
echo '<p><a href="/admin/orders.php">Back to Orders</a></p>';
