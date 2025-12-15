<?php
/**
 * One-time script to delete test rep parker@senecawest.com
 * Run via: php delete_test_rep_parker.php
 * Or visit in browser (must be admin)
 */
declare(strict_types=1);

require_once __DIR__ . '/../../api/db.php';

// If accessed via web, require admin authentication
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../auth.php';
    requireAdmin();
    header('Content-Type: text/plain');
}

$email = 'parker@senecawest.com';

echo "=== Deleting Test Rep: $email ===\n\n";

try {
    // Find the user
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "User $email not found. Already deleted?\n";
        exit(0);
    }

    echo "Found user: {$user['first_name']} {$user['last_name']} ({$user['id']})\n";

    // Find associated sales_rep
    $repStmt = $pdo->prepare("SELECT id, user_id, status FROM sales_reps WHERE user_id = ?");
    $repStmt->execute([$user['id']]);
    $rep = $repStmt->fetch(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    if ($rep) {
        echo "Found sales_rep: {$rep['id']} (status: {$rep['status']})\n\n";

        // Delete in correct order (foreign key constraints)
        echo "Deleting records...\n";

        // Delete signed documents
        $result = $pdo->prepare("DELETE FROM rep_signed_documents WHERE rep_id = ?");
        $result->execute([$rep['id']]);
        echo "  - Deleted {$result->rowCount()} signed documents\n";

        // Delete commission rates
        $result = $pdo->prepare("DELETE FROM rep_commission_rates WHERE rep_id = ?");
        $result->execute([$rep['id']]);
        echo "  - Deleted {$result->rowCount()} commission rates\n";

        // Delete commission ledger entries
        $result = $pdo->prepare("DELETE FROM rep_commission_ledger WHERE rep_id = ?");
        $result->execute([$rep['id']]);
        echo "  - Deleted {$result->rowCount()} ledger entries\n";

        // Delete sales_rep
        $result = $pdo->prepare("DELETE FROM sales_reps WHERE id = ?");
        $result->execute([$rep['id']]);
        echo "  - Deleted sales_rep record\n";
    }

    // Also unassign any clinics that were assigned to this rep
    $result = $pdo->prepare("UPDATE users SET assigned_rep_id = NULL WHERE assigned_rep_id = ?");
    $result->execute([$user['id']]);
    if ($result->rowCount() > 0) {
        echo "  - Unassigned {$result->rowCount()} clinics\n";
    }

    // Delete user
    $result = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $result->execute([$user['id']]);
    echo "  - Deleted user record\n";

    $pdo->commit();

    echo "\n✓ Test rep $email deleted successfully\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
