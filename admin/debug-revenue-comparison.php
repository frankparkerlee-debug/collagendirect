<?php
/**
 * Debug Tool: Revenue Calculation Comparison
 *
 * Shows how revenue is calculated for real orders using both:
 * 1. Dashboard method (inline calculation in admin/index.php)
 * 2. Revenue Report method (calculate_order_revenue() from revenue_calculator.php)
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/lib/revenue_calculator.php';

require_admin();

// Get sample orders (mix of referral and wholesale)
$orders = $pdo->query("
    SELECT
        o.id,
        o.status,
        o.total_pieces,
        o.created_at,
        o.frequency_per_week,
        o.duration_days,
        o.qty_per_change,
        o.refills_allowed,
        o.cpt_code,
        o.hcpcs_code,
        o.wounds_data,
        COALESCE(o.billed_by, p.billed_by) as billed_by,
        p.name as product_name,
        p.id as product_id,
        p.pieces_per_box,
        p.price_wholesale,
        p.price_admin,
        p.product_price,
        p.medicare_allowable,
        p.cost_per_box,
        p.hcpcs_code as product_hcpcs,
        u.practice_name,
        u.id as user_id,
        u.account_type,
        pp.custom_price as practice_custom_price
    FROM orders o
    JOIN products p ON o.product_id = p.id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN practice_pricing pp ON pp.practice_id = u.id AND pp.product_id = p.id
    WHERE o.status IN ('completed', 'shipped', 'pending', 'approved')
    ORDER BY o.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Load reimbursement rates for dashboard method (keyed by CPT/HCPCS)
$dashboardRates = [];
try {
    $rateRows = $pdo->query("
        SELECT DISTINCT hcpcs_code, medicare_allowable
        FROM products
        WHERE hcpcs_code IS NOT NULL AND hcpcs_code != '' AND medicare_allowable > 0
    ")->fetchAll();
    foreach ($rateRows as $r) {
        $dashboardRates[$r['hcpcs_code']] = (float)$r['medicare_allowable'];
    }
} catch (Exception $e) {
    // Ignore
}

// Also try reimbursement_rates table if it exists
try {
    $rateRows = $pdo->query("SELECT product_id, rate FROM reimbursement_rates WHERE effective_date <= CURRENT_DATE ORDER BY effective_date DESC")->fetchAll();
    foreach ($rateRows as $r) {
        if (!isset($dashboardRates[$r['product_id']])) {
            $dashboardRates['product_' . $r['product_id']] = (float)$r['rate'];
        }
    }
} catch (Exception $e) {
    // Table might not exist
}

// Load rates for revenue_calculator method
$reimbursementRates = load_reimbursement_rates($pdo);
?>

<div style="max-width: 1400px; margin: 0 auto; padding: 2rem;">
    <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
        Revenue Calculation Comparison
    </h1>
    <p style="color: var(--muted); margin-bottom: 2rem;">
        Comparing Dashboard method vs Revenue Report method for real orders
    </p>

    <!-- Legend -->
    <div style="background: #f8f9fa; border: 1px solid var(--border); border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
        <h3 style="font-weight: 600; margin-bottom: 1rem;">Calculation Methods</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <div>
                <h4 style="color: #2563eb; font-weight: 600;">Dashboard Method (admin/index.php)</h4>
                <ul style="font-size: 0.875rem; color: var(--ink-light); line-height: 1.75; margin-left: 1rem;">
                    <li><strong>Referral:</strong> billable_pieces × (reimbursement_rates → price_admin → product_price/pieces)</li>
                    <li><strong>Wholesale:</strong> boxes × (price_wholesale OR product_price)</li>
                    <li>Does NOT check practice_pricing table</li>
                </ul>
            </div>
            <div>
                <h4 style="color: #16a34a; font-weight: 600;">Revenue Report Method (revenue_calculator.php)</h4>
                <ul style="font-size: 0.875rem; color: var(--ink-light); line-height: 1.75; margin-left: 1rem;">
                    <li><strong>Referral:</strong> billable_pieces × (medicare_allowable → product_price/pieces)</li>
                    <li><strong>Wholesale:</strong> boxes × (practice_custom_price → product_price×pieces → price_wholesale)</li>
                    <li>DOES check practice_pricing table first for wholesale</li>
                </ul>
            </div>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 1.5rem;">
            <p style="color: #92400e;">No orders found matching the criteria (completed, shipped, pending, or approved status).</p>
        </div>
    <?php else: ?>

    <?php foreach ($orders as $order):
        $productId = $order['product_id'];
        $piecesPerBox = max(1, (int)$order['pieces_per_box']);
        $totalPieces = (float)$order['total_pieces'];
        $billedBy = $order['billed_by'] ?? 'collagen_direct';
        $accountType = $order['account_type'] ?? '';
        $isWholesale = ($billedBy === 'practice_dme' || in_array($accountType, ['wholesale', 'dme_wholesale']));

        // ============ DASHBOARD METHOD ============
        $dashboardRevenue = 0;
        $dashboardExplanation = [];

        if ($isWholesale) {
            $boxes = max(1, (int)($order['qty_per_change'] ?? 1));
            $pricePerBox = (float)($order['price_wholesale'] ?: $order['product_price']);
            $dashboardRevenue = $boxes * $pricePerBox;
            $dashboardExplanation = [
                'type' => 'Wholesale',
                'boxes' => $boxes,
                'price_per_box' => $pricePerBox,
                'source' => $order['price_wholesale'] ? 'price_wholesale' : 'product_price',
                'formula' => "{$boxes} boxes × \${$pricePerBox} = \$" . number_format($dashboardRevenue, 2)
            ];
        } else {
            // Calculate total pieces from order data
            $fpw = (int)($order['frequency_per_week'] ?? 0);
            $qty = max(1, (int)($order['qty_per_change'] ?? 1));
            $days = (int)($order['duration_days'] ?? 0);
            $refills = max(0, (int)($order['refills_allowed'] ?? 0));

            if ($fpw === 0) $fpw = 1;
            if ($days === 0) $days = 30;

            $weeks = $days / 7.0;
            $calcPieces = $weeks * $fpw * $qty * (1 + $refills);
            $billablePieces = (int)ceil($calcPieces);

            // Dashboard rate lookup chain
            $cpt = $order['cpt_code'] ?? $order['hcpcs_code'] ?? $order['product_hcpcs'] ?? '';
            $cptRate = 0;
            $rateSource = 'none';

            if ($cpt && isset($dashboardRates[$cpt])) {
                $cptRate = $dashboardRates[$cpt];
                $rateSource = "medicare_allowable (HCPCS: {$cpt})";
            } elseif (!empty($order['price_admin']) && (float)$order['price_admin'] > 0) {
                $cptRate = (float)$order['price_admin'];
                $rateSource = 'price_admin';
            } elseif ($piecesPerBox > 0 && (float)$order['product_price'] > 0) {
                $cptRate = (float)$order['product_price'] / $piecesPerBox;
                $rateSource = 'product_price / pieces_per_box';
            }

            $dashboardRevenue = $billablePieces * $cptRate;

            $dashboardExplanation = [
                'type' => 'Referral',
                'billable_pieces' => $billablePieces,
                'cpt_rate' => $cptRate,
                'source' => $rateSource,
                'formula' => "{$billablePieces} pieces × \$" . number_format($cptRate, 4) . " = \$" . number_format($dashboardRevenue, 2),
                'calc_details' => "({$days}d / 7) × {$fpw}fpw × {$qty}qty × (1 + {$refills}refills) = " . number_format($calcPieces, 2) . " pieces"
            ];
        }

        // ============ REVENUE REPORT METHOD ============
        $reportResult = calculate_order_revenue($order, $reimbursementRates, true);
        $reportRevenue = $reportResult['revenue'];
        $reportSteps = $reportResult['calculation_steps'];

        $difference = $dashboardRevenue - $reportRevenue;
        $hasDiscrepancy = abs($difference) > 0.01;
    ?>

    <div style="background: white; border: 1px solid <?= $hasDiscrepancy ? '#ef4444' : 'var(--border)' ?>; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; <?= $hasDiscrepancy ? 'box-shadow: 0 0 0 3px rgba(239,68,68,0.1);' : '' ?>">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
            <div>
                <h3 style="font-weight: 600; margin-bottom: 0.25rem;">
                    Order #<?= $order['id'] ?> - <?= e($order['product_name']) ?>
                </h3>
                <p style="font-size: 0.875rem; color: var(--muted);">
                    <?= e($order['practice_name'] ?? 'No Practice') ?> |
                    <?= $order['total_pieces'] ?> total pieces |
                    Status: <?= ucfirst($order['status']) ?> |
                    <span style="padding: 0.125rem 0.5rem; background: <?= $isWholesale ? '#fef3c7' : '#dbeafe' ?>; border-radius: 4px; font-size: 0.75rem;">
                        <?= $isWholesale ? 'Wholesale' : 'Referral' ?>
                    </span>
                </p>
            </div>
            <?php if ($hasDiscrepancy): ?>
                <span style="background: #fee2e2; color: #991b1b; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.875rem; font-weight: 600;">
                    Discrepancy: $<?= number_format(abs($difference), 2) ?>
                </span>
            <?php else: ?>
                <span style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.875rem;">
                    Match
                </span>
            <?php endif; ?>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- Dashboard Method -->
            <div style="background: #eff6ff; border-radius: 6px; padding: 1rem;">
                <h4 style="color: #2563eb; font-weight: 600; margin-bottom: 0.75rem;">Dashboard Method</h4>
                <div style="font-size: 0.875rem; color: var(--ink-light);">
                    <p><strong>Type:</strong> <?= $dashboardExplanation['type'] ?></p>
                    <?php if ($isWholesale): ?>
                        <p><strong>Boxes:</strong> <?= $dashboardExplanation['boxes'] ?></p>
                        <p><strong>Price/Box:</strong> $<?= number_format($dashboardExplanation['price_per_box'], 2) ?></p>
                    <?php else: ?>
                        <p><strong>Billable Pieces:</strong> <?= $dashboardExplanation['billable_pieces'] ?></p>
                        <p><strong>CPT Rate:</strong> $<?= number_format($dashboardExplanation['cpt_rate'], 4) ?></p>
                        <?php if (!empty($dashboardExplanation['calc_details'])): ?>
                            <p style="font-size: 0.75rem; color: #6b7280;"><strong>Calc:</strong> <?= $dashboardExplanation['calc_details'] ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p><strong>Rate Source:</strong> <?= $dashboardExplanation['source'] ?></p>
                    <p style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #bfdbfe;">
                        <strong>Formula:</strong> <?= $dashboardExplanation['formula'] ?>
                    </p>
                </div>
                <p style="font-size: 1.25rem; font-weight: 700; color: #2563eb; margin-top: 0.75rem;">
                    $<?= number_format($dashboardRevenue, 2) ?>
                </p>
            </div>

            <!-- Revenue Report Method -->
            <div style="background: #f0fdf4; border-radius: 6px; padding: 1rem;">
                <h4 style="color: #16a34a; font-weight: 600; margin-bottom: 0.75rem;">Revenue Report Method</h4>
                <div style="font-size: 0.875rem; color: var(--ink-light);">
                    <?php foreach ($reportSteps as $step): ?>
                        <p><?= e($step) ?></p>
                    <?php endforeach; ?>
                    <?php if (empty($reportSteps)): ?>
                        <p>No calculation steps available</p>
                    <?php endif; ?>
                </div>
                <p style="font-size: 1.25rem; font-weight: 700; color: #16a34a; margin-top: 0.75rem;">
                    $<?= number_format($reportRevenue, 2) ?>
                </p>
            </div>
        </div>

        <!-- Raw Data -->
        <details style="margin-top: 1rem;">
            <summary style="cursor: pointer; font-size: 0.875rem; color: var(--muted);">View Raw Product Data</summary>
            <div style="margin-top: 0.5rem; background: #f8f9fa; padding: 0.75rem; border-radius: 4px; font-size: 0.75rem; font-family: monospace; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>pieces_per_box:</strong></td><td><?= $order['pieces_per_box'] ?></td></tr>
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>price_wholesale:</strong></td><td>$<?= number_format((float)$order['price_wholesale'], 2) ?></td></tr>
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>price_admin:</strong></td><td>$<?= number_format((float)$order['price_admin'], 2) ?></td></tr>
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>product_price:</strong></td><td>$<?= number_format((float)$order['product_price'], 2) ?></td></tr>
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>medicare_allowable:</strong></td><td>$<?= number_format((float)$order['medicare_allowable'], 2) ?></td></tr>
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>HCPCS code:</strong></td><td><?= e($order['product_hcpcs'] ?? $order['hcpcs_code'] ?? 'N/A') ?></td></tr>
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>frequency_per_week:</strong></td><td><?= $order['frequency_per_week'] ?? 'N/A' ?></td></tr>
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>duration_days:</strong></td><td><?= $order['duration_days'] ?? 'N/A' ?></td></tr>
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>qty_per_change:</strong></td><td><?= $order['qty_per_change'] ?? 'N/A' ?></td></tr>
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>refills_allowed:</strong></td><td><?= $order['refills_allowed'] ?? 'N/A' ?></td></tr>
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>billed_by:</strong></td><td><?= e($order['billed_by'] ?? 'N/A') ?></td></tr>
                    <tr><td style="padding: 0.25rem;"><strong>practice_custom_price:</strong></td><td><?= $order['practice_custom_price'] ? '$' . number_format((float)$order['practice_custom_price'], 2) : 'N/A' ?></td></tr>
                </table>
            </div>
        </details>
    </div>

    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Summary -->
    <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 1.5rem; margin-top: 2rem;">
        <h3 style="font-weight: 600; margin-bottom: 1rem;">Key Differences Summary</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border);">Aspect</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border);">Dashboard</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border);">Revenue Report</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);"><strong>Referral CPT Rate</strong></td>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">medicare_allowable (by HCPCS) → price_admin → product_price/pieces</td>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">medicare_allowable (by HCPCS) → product_price/pieces</td>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);"><strong>Wholesale Price</strong></td>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">price_wholesale OR product_price</td>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">practice_custom_price → product_price×pieces → price_wholesale</td>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);"><strong>Practice Custom Pricing</strong></td>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">Not checked</td>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">Checked first for wholesale</td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 1.5rem; padding: 1rem; background: #fef3c7; border-radius: 6px;">
            <strong style="color: #92400e;">Recommendation:</strong>
            <p style="color: #92400e; margin-top: 0.5rem; font-size: 0.875rem;">
                For consistency, consider updating the dashboard to use <code>calculate_order_revenue()</code> from
                <code>revenue_calculator.php</code> instead of inline calculations. This ensures both reports
                use the same logic and reduces maintenance burden.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
