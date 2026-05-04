<?php
/**
 * Bulk Approve Pending Distributors
 *
 * Run as superadmin: /admin/bulk-approve-pending-distributors.php
 * Approves all pending sales reps and sets a default 15% commission rate.
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
require_once __DIR__ . '/../api/lib/email_sender.php';

$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';
$sendEmails = isset($_GET['send_emails']) && $_GET['send_emails'] === '1';
$defaultRate = isset($_GET['rate']) ? floatval($_GET['rate']) / 100 : 0.15;

header('Content-Type: text/plain; charset=utf-8');

echo "=== Bulk Approve Pending Distributors ===\n";
echo "Dry run: " . ($dryRun ? 'YES (no changes)' : 'NO (will commit)') . "\n";
echo "Send approval emails: " . ($sendEmails ? 'YES' : 'NO') . "\n";
echo "Default commission rate: " . ($defaultRate * 100) . "%\n\n";

// Find all pending reps
$stmt = $pdo->prepare("
    SELECT sr.id as rep_id, sr.user_id, sr.application_date, sr.company_name,
           u.email, u.first_name, u.last_name
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    WHERE sr.status = 'pending'
    ORDER BY sr.application_date ASC
");
$stmt->execute();
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pending)) {
    echo "No pending distributors found.\n";
    exit;
}

echo "Found " . count($pending) . " pending distributor(s):\n";
foreach ($pending as $rep) {
    echo "  - {$rep['first_name']} {$rep['last_name']} <{$rep['email']}> (applied {$rep['application_date']})\n";
}
echo "\n";

if ($dryRun) {
    echo "Dry run complete. Add ?dry_run=0 to execute.\n";
    echo "Optional flags: &send_emails=1 to email reps, &rate=15 for custom commission %.\n";
    exit;
}

$approved = 0;
$failed = 0;

foreach ($pending as $rep) {
    try {
        $pdo->beginTransaction();

        // Update status to active
        $pdo->prepare("
            UPDATE sales_reps
            SET status = 'active', approved_date = NOW(), approved_by = NULL, updated_at = NOW()
            WHERE id = ?
        ")->execute([$rep['rep_id']]);

        // Insert default commission rate (skip if one already exists)
        $rateCheck = $pdo->prepare("SELECT COUNT(*) FROM rep_commission_rates WHERE rep_id = ?");
        $rateCheck->execute([$rep['rep_id']]);
        if ((int)$rateCheck->fetchColumn() === 0) {
            // Detect which column name exists (set_by or created_by)
            $colCheck = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'rep_commission_rates' AND column_name IN ('set_by', 'created_by')")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('set_by', $colCheck)) {
                $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, set_by, notes, created_at) VALUES (?, ?, CURRENT_DATE, NULL, 'Bulk approval', NOW())")
                    ->execute([$rep['rep_id'], $defaultRate]);
            } elseif (in_array('created_by', $colCheck)) {
                $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, created_by, notes, created_at) VALUES (?, ?, CURRENT_DATE, NULL, 'Bulk approval', NOW())")
                    ->execute([$rep['rep_id'], $defaultRate]);
            } else {
                $pdo->prepare("INSERT INTO rep_commission_rates (rep_id, rate, effective_date, notes, created_at) VALUES (?, ?, CURRENT_DATE, 'Bulk approval', NOW())")
                    ->execute([$rep['rep_id'], $defaultRate]);
            }
        }

        $pdo->commit();
        echo "[OK] Approved: {$rep['first_name']} {$rep['last_name']} <{$rep['email']}>\n";
        $approved++;

        // Send email if requested
        if ($sendEmails && function_exists('sendDistributorApprovalEmail')) {
            try {
                sendDistributorApprovalEmail($pdo, $rep['rep_id']);
                echo "     -> Approval email sent\n";
            } catch (Throwable $e) {
                echo "     -> Email failed: " . $e->getMessage() . "\n";
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "[FAIL] {$rep['first_name']} {$rep['last_name']}: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n=== Summary ===\n";
echo "Approved: $approved\n";
echo "Failed:   $failed\n";
echo "Total:    " . count($pending) . "\n";
