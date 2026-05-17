<?php
/**
 * Backfill users.status='active' for sales reps whose sales_reps.status='active'
 * but whose users.status was never updated from the default 'pending'.
 *
 * Symptom this fixes: rep is approved (sales_reps.status='active') but admin
 * user lists still show them as "pending" because they read users.status.
 *
 * Superadmin-only. Dry-run by default.
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$admin = current_admin();
if (!$admin || ($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    echo "Access denied. Superadmin only.";
    exit;
}

require_once __DIR__ . '/db.php';

$dryRun = !isset($_GET['dry_run']) || $_GET['dry_run'] !== '0';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Backfill users.status for active sales reps ===\n";
echo "Dry run: " . ($dryRun ? 'YES (no changes)' : 'NO (will commit)') . "\n\n";

// Find users whose sales_reps row is active but users.status isn't
$stmt = $pdo->query("
    SELECT u.id, u.email, u.status AS user_status, sr.status AS rep_status, sr.id AS rep_id
    FROM users u
    JOIN sales_reps sr ON sr.user_id = u.id
    WHERE sr.status = 'active' AND COALESCE(u.status, '') <> 'active'
    ORDER BY u.email
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "Nothing to fix — all approved reps already have users.status='active'.\n";
    exit;
}

echo "Found " . count($rows) . " rep(s) with mismatched user/rep status:\n";
foreach ($rows as $r) {
    echo "  - {$r['email']}  users.status='{$r['user_status']}'  sales_reps.status='{$r['rep_status']}'\n";
}
echo "\n";

if ($dryRun) {
    echo "Dry run complete. Add ?dry_run=0 to execute.\n";
    exit;
}

$fixed = 0;
foreach ($rows as $r) {
    try {
        $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?")
            ->execute([$r['id']]);
        echo "[OK] Updated {$r['email']}\n";
        $fixed++;
    } catch (Throwable $e) {
        echo "[FAIL] {$r['email']}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Summary ===\n";
echo "Fixed: $fixed / " . count($rows) . "\n";
