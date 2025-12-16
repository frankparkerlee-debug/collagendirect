<?php
/**
 * Migration: Set billed_by for existing wholesale orders
 *
 * This migration updates all existing wholesale orders to have billed_by = 'practice_dme'
 * so they appear correctly in the billing-wholesale.php page.
 *
 * Wholesale orders are identified by:
 * 1. Orders with payment_type = 'wholesale' or 'direct'
 * 2. Orders from users with account_type IN ('wholesale', 'dme_wholesale')
 * 3. Orders that already have billed_by = 'practice_dme' (no change needed)
 *
 * Also sets default values for billing fields if missing.
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
    require_once __DIR__ . '/../db.php';
}

echo "=== Migration: Set billed_by for Existing Wholesale Orders ===\n\n";

try {
    $pdo->beginTransaction();

    // 1. Count orders before migration
    echo "1. Analyzing existing orders...\n";

    $countStmt = $pdo->query("
        SELECT
            COUNT(*) as total_orders,
            SUM(CASE WHEN billed_by = 'practice_dme' THEN 1 ELSE 0 END) as already_set,
            SUM(CASE WHEN billed_by IS NULL OR billed_by = '' THEN 1 ELSE 0 END) as needs_update
        FROM orders
    ");
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
    echo "   Total orders: {$counts['total_orders']}\n";
    echo "   Already set as practice_dme: {$counts['already_set']}\n";
    echo "   Missing billed_by: {$counts['needs_update']}\n\n";

    // 2. Find wholesale orders by payment_type
    echo "2. Finding wholesale orders by payment_type...\n";
    $paymentTypeStmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM orders
        WHERE payment_type IN ('wholesale', 'direct', 'invoice')
        AND (billed_by IS NULL OR billed_by = '')
    ");
    $paymentTypeCount = (int)$paymentTypeStmt->fetch()['count'];
    echo "   Orders with payment_type = wholesale/direct/invoice: {$paymentTypeCount}\n";

    // 3. Find wholesale orders by user account_type
    echo "3. Finding wholesale orders by user account_type...\n";
    $accountTypeStmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE u.account_type IN ('wholesale', 'dme_wholesale')
        AND (o.billed_by IS NULL OR o.billed_by = '')
    ");
    $accountTypeCount = (int)$accountTypeStmt->fetch()['count'];
    echo "   Orders from wholesale/dme_wholesale users: {$accountTypeCount}\n\n";

    // 4. Update orders with payment_type indicating wholesale
    echo "4. Updating orders with wholesale payment_type...\n";
    $updatePaymentType = $pdo->exec("
        UPDATE orders
        SET billed_by = 'practice_dme'
        WHERE payment_type IN ('wholesale', 'direct', 'invoice')
        AND (billed_by IS NULL OR billed_by = '')
    ");
    echo "   Updated {$updatePaymentType} orders\n";

    // 5. Update orders from wholesale account types
    echo "5. Updating orders from wholesale account users...\n";
    $updateAccountType = $pdo->exec("
        UPDATE orders o
        SET billed_by = 'practice_dme'
        FROM users u
        WHERE o.user_id = u.id
        AND u.account_type IN ('wholesale', 'dme_wholesale')
        AND (o.billed_by IS NULL OR o.billed_by = '')
    ");
    echo "   Updated {$updateAccountType} orders\n";

    // 6. Set default billing values for wholesale orders that are missing them
    echo "\n6. Setting default billing values for wholesale orders...\n";

    // Set amount_due from order value if not set
    $updateAmountDue = $pdo->exec("
        UPDATE orders o
        SET amount_due = COALESCE(o.qty_per_change, 1) * COALESCE(o.product_price, 0) *
            COALESCE((SELECT pieces_per_box FROM products WHERE id = o.product_id), 10)
        WHERE o.billed_by = 'practice_dme'
        AND (o.amount_due IS NULL OR o.amount_due = 0)
        AND o.product_price IS NOT NULL
        AND o.product_price > 0
    ");
    echo "   Set amount_due for {$updateAmountDue} orders\n";

    // Set balance_due = amount_due - amount_paid
    $updateBalanceDue = $pdo->exec("
        UPDATE orders
        SET balance_due = COALESCE(amount_due, 0) - COALESCE(amount_paid, 0)
        WHERE billed_by = 'practice_dme'
        AND (balance_due IS NULL OR balance_due != COALESCE(amount_due, 0) - COALESCE(amount_paid, 0))
    ");
    echo "   Updated balance_due for {$updateBalanceDue} orders\n";

    // Set default due_date (30 days from order date) if not set
    $updateDueDate = $pdo->exec("
        UPDATE orders
        SET due_date = DATE(created_at) + INTERVAL '30 days'
        WHERE billed_by = 'practice_dme'
        AND due_date IS NULL
    ");
    echo "   Set due_date for {$updateDueDate} orders\n";

    // Set invoice_status based on payment status
    echo "\n7. Setting invoice_status for wholesale orders...\n";

    // Mark as 'paid' if fully paid
    $updatePaid = $pdo->exec("
        UPDATE orders
        SET invoice_status = 'paid'
        WHERE billed_by = 'practice_dme'
        AND (invoice_status IS NULL OR invoice_status = '')
        AND paid_at IS NOT NULL
    ");
    echo "   Marked {$updatePaid} orders as paid\n";

    // Mark as 'partial' if partially paid
    $updatePartial = $pdo->exec("
        UPDATE orders
        SET invoice_status = 'partial'
        WHERE billed_by = 'practice_dme'
        AND (invoice_status IS NULL OR invoice_status = '')
        AND paid_at IS NULL
        AND COALESCE(amount_paid, 0) > 0
        AND COALESCE(balance_due, amount_due) > 0
    ");
    echo "   Marked {$updatePartial} orders as partial\n";

    // Mark as 'overdue' if past due date and unpaid
    $updateOverdue = $pdo->exec("
        UPDATE orders
        SET invoice_status = 'overdue'
        WHERE billed_by = 'practice_dme'
        AND (invoice_status IS NULL OR invoice_status = '')
        AND paid_at IS NULL
        AND due_date < CURRENT_DATE
        AND COALESCE(balance_due, amount_due) > 0
    ");
    echo "   Marked {$updateOverdue} orders as overdue\n";

    // 8. Final count
    echo "\n8. Final verification...\n";
    $finalCountStmt = $pdo->query("
        SELECT
            COUNT(*) as total_wholesale,
            SUM(CASE WHEN invoice_status = 'paid' THEN 1 ELSE 0 END) as paid,
            SUM(CASE WHEN invoice_status = 'partial' THEN 1 ELSE 0 END) as partial,
            SUM(CASE WHEN invoice_status = 'overdue' THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN invoice_status IS NULL OR invoice_status = '' THEN 1 ELSE 0 END) as pending
        FROM orders
        WHERE billed_by = 'practice_dme'
    ");
    $finalCounts = $finalCountStmt->fetch(PDO::FETCH_ASSOC);
    echo "   Total wholesale orders: {$finalCounts['total_wholesale']}\n";
    echo "   Paid: {$finalCounts['paid']}\n";
    echo "   Partial: {$finalCounts['partial']}\n";
    echo "   Overdue: {$finalCounts['overdue']}\n";
    echo "   Pending/New: {$finalCounts['pending']}\n";

    $pdo->commit();

    echo "\n✓ Migration completed successfully!\n";
    echo "\nWholesale orders should now appear in /admin/billing-wholesale.php\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    throw $e;
}
