<?php
/**
 * Set the actual pieces shipped on an order line and recompute its finances.
 *
 * Used by the admin HealKit + Referral order screens. Sets orders.actual_pieces
 * (the override that calculate_order_revenue() now honors), then recomputes the
 * stored snapshot (total_pieces / billable_pieces / boxes_to_ship / expected_revenue,
 * and amount_due if the order was already billed) using CURRENT practice pricing,
 * so every screen and the live revenue report agree. Writes an order_revisions
 * audit row and flags orders that already have a payment or commission.
 */
require_once __DIR__ . '/../../admin/lib/revenue_calculator.php';

function set_order_actual_pieces(PDO $pdo, string $orderId, int $actualPieces, $adminId = null, string $reason = ''): array {
    $adminId = ($adminId === null || $adminId === '') ? null : (string)$adminId;
    if ($actualPieces < 0) return ['ok' => false, 'error' => 'Pieces cannot be negative'];

    // Fetch the order with everything calculate_order_revenue() needs + live practice price
    $stmt = $pdo->prepare("
        SELECT o.id, o.user_id, o.product_id, o.product, o.billed_by, o.total_pieces, o.billable_pieces,
               o.expected_revenue, o.amount_due, o.amount_paid, o.actual_pieces,
               o.frequency_per_week, o.qty_per_change, o.duration_days, o.refills_allowed,
               o.wounds_data, o.product_price,
               pr.pieces_per_box, pr.price_wholesale, pr.cost_per_box, pr.hcpcs_code, pr.cpt_code, pr.medicare_allowable,
               pp.custom_price AS practice_custom_price
        FROM orders o
        LEFT JOIN products pr ON pr.id = o.product_id
        LEFT JOIN LATERAL (
            SELECT ppx.custom_price FROM practice_pricing ppx
            WHERE ppx.product_id = o.product_id
              AND (ppx.user_id = o.user_id OR ppx.user_id IN (
                    SELECT u2.id FROM users u2
                    WHERE u2.practice_name = (SELECT practice_name FROM users WHERE id = o.user_id)
                      AND COALESCE(u2.practice_name,'') <> ''))
            ORDER BY (ppx.user_id = o.user_id) DESC, ppx.updated_at DESC NULLS LAST
            LIMIT 1
        ) pp ON true
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) return ['ok' => false, 'error' => 'Order not found'];

    $oldPieces  = (int)($order['total_pieces'] ?? 0);
    $oldActual  = $order['actual_pieces'];
    $oldRev     = (float)($order['expected_revenue'] ?? 0);
    $oldDue     = (float)($order['amount_due'] ?? 0);
    $paid       = (float)($order['amount_paid'] ?? 0);
    $ppb        = max(1, (int)($order['pieces_per_box'] ?? 10));

    // Recompute revenue with the override applied, using current pricing
    $rates = load_reimbursement_rates($pdo);
    $orderForCalc = $order;
    $orderForCalc['actual_pieces'] = $actualPieces;
    $calc      = calculate_order_revenue($orderForCalc, $rates, false);
    $newRev    = round((float)$calc['revenue'], 2);
    $newBoxes  = (int)$calc['boxes'];
    // If the order was already billed, re-bill to the new revenue; otherwise leave amount_due as-is.
    $newDue    = $oldDue > 0 ? $newRev : $oldDue;

    // Flags (we never silently rewrite collected money / paid commissions)
    $flagPaid = $paid > 0;
    $chk = $pdo->prepare("SELECT COUNT(*) FROM rep_commission_ledger WHERE order_id = ?");
    $chk->execute([$orderId]);
    $flagCommission = (int)$chk->fetchColumn() > 0;

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE orders
            SET actual_pieces = ?, total_pieces = ?, billable_pieces = ?, boxes_to_ship = ?,
                expected_revenue = ?, amount_due = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$actualPieces, $actualPieces, $actualPieces, $newBoxes, $newRev, $newDue, $orderId]);

        // Purpose-built audit (order_revisions requires a users-FK changed_by; admins
        // are in admin_users, so we use a dedicated table with no such constraint).
        $pdo->prepare("
            INSERT INTO order_piece_audit
                (order_id, changed_by, old_pieces, new_pieces, old_revenue, new_revenue, old_amount_due, new_amount_due, reason, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $orderId, $adminId, $oldPieces, $actualPieces, $oldRev, $newRev, $oldDue, $newDue,
            $reason !== '' ? $reason : 'Actual pieces shipped correction',
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    return [
        'ok' => true,
        'old_pieces' => $oldPieces, 'new_pieces' => $actualPieces,
        'old_rev' => $oldRev, 'new_rev' => $newRev,
        'old_due' => $oldDue, 'new_due' => $newDue,
        'flag_paid' => $flagPaid, 'flag_commission' => $flagCommission,
    ];
}
