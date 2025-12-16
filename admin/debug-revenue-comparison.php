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
        p.name as product_name,
        p.id as product_id,
        p.pieces_per_box,
        p.price_wholesale,
        p.price_admin,
        p.product_price,
        p.medicare_allowable,
        COALESCE(o.billed_by, p.billed_by) as billed_by,
        pr.name as practice_name,
        pr.id as practice_id
    FROM orders o
    JOIN products p ON o.product_id = p.id
    LEFT JOIN practices pr ON o.practice_id = pr.id
    WHERE o.status IN ('completed', 'shipped', 'pending', 'approved')
    ORDER BY o.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Load reimbursement rates (Dashboard method)
$rates = [];
$rateRows = $pdo->query("SELECT product_id, rate FROM reimbursement_rates WHERE effective_date <= CURRENT_DATE ORDER BY effective_date DESC")->fetchAll();
foreach ($rateRows as $r) {
    if (!isset($rates[$r['product_id']])) {
        $rates[$r['product_id']] = (float)$r['rate'];
    }
}

// Load practice custom pricing
$practicePricing = [];
$ppRows = $pdo->query("SELECT practice_id, product_id, custom_price FROM practice_pricing")->fetchAll();
foreach ($ppRows as $pp) {
    $practicePricing[$pp['practice_id']][$pp['product_id']] = (float)$pp['custom_price'];
}

// Load products for revenue_calculator
$productsForCalc = load_products_for_revenue($pdo);
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

    <?php foreach ($orders as $order):
        $productId = $order['product_id'];
        $practiceId = $order['practice_id'];
        $piecesPerBox = (int)$order['pieces_per_box'];
        $totalPieces = (float)$order['total_pieces'];
        $isWholesale = $order['billed_by'] === 'practice_dme';

        // ============ DASHBOARD METHOD ============
        $dashboardRevenue = 0;
        $dashboardExplanation = [];

        if ($isWholesale) {
            $boxes = $piecesPerBox > 0 ? (int)ceil($totalPieces / $piecesPerBox) : 0;
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
            $billablePieces = (int)ceil($totalPieces);
            // Dashboard rate lookup chain
            $cptRate = $rates[$productId]
                ?? (float)$order['price_admin']
                ?? ($piecesPerBox > 0 ? (float)$order['product_price'] / $piecesPerBox : 0);
            $dashboardRevenue = $billablePieces * $cptRate;

            $rateSource = 'fallback (product_price/pieces)';
            if (isset($rates[$productId])) {
                $rateSource = 'reimbursement_rates table';
            } elseif ($order['price_admin']) {
                $rateSource = 'price_admin column';
            }

            $dashboardExplanation = [
                'type' => 'Referral',
                'billable_pieces' => $billablePieces,
                'cpt_rate' => $cptRate,
                'source' => $rateSource,
                'formula' => "{$billablePieces} pieces × \${$cptRate} = \$" . number_format($dashboardRevenue, 2)
            ];
        }

        // ============ REVENUE REPORT METHOD ============
        $reportRevenue = calculate_order_revenue(
            $order['id'],
            $totalPieces,
            $piecesPerBox,
            $productId,
            $practiceId,
            $order['billed_by'],
            $productsForCalc,
            $reimbursementRates,
            $practicePricing
        );

        // Reconstruct explanation for report method
        $reportExplanation = [];
        if ($isWholesale) {
            $boxes = $piecesPerBox > 0 ? (int)ceil($totalPieces / $piecesPerBox) : 0;

            // Check practice_pricing first
            $customPrice = $practicePricing[$practiceId][$productId] ?? null;
            if ($customPrice) {
                $pricePerBox = $customPrice;
                $source = 'practice_pricing (custom price)';
            } elseif (isset($productsForCalc[$productId])) {
                $prod = $productsForCalc[$productId];
                if ($prod['product_price'] && $prod['pieces_per_box']) {
                    $pricePerBox = (float)$prod['product_price'] * (int)$prod['pieces_per_box'];
                    $source = 'product_price × pieces_per_box';
                } else {
                    $pricePerBox = (float)$prod['price_wholesale'];
                    $source = 'price_wholesale';
                }
            } else {
                $pricePerBox = 0;
                $source = 'unknown';
            }

            $reportExplanation = [
                'type' => 'Wholesale',
                'boxes' => $boxes,
                'price_per_box' => $pricePerBox,
                'source' => $source,
                'formula' => "{$boxes} boxes × \${$pricePerBox} = \$" . number_format($reportRevenue, 2)
            ];
        } else {
            $billablePieces = (int)ceil($totalPieces);
            $cptRate = $reimbursementRates[$productId]
                ?? ($piecesPerBox > 0 ? (float)($productsForCalc[$productId]['product_price'] ?? 0) / $piecesPerBox : 0);

            $rateSource = isset($reimbursementRates[$productId]) ? 'medicare_allowable' : 'product_price/pieces';

            $reportExplanation = [
                'type' => 'Referral',
                'billable_pieces' => $billablePieces,
                'cpt_rate' => $cptRate,
                'source' => $rateSource,
                'formula' => "{$billablePieces} pieces × \${$cptRate} = \$" . number_format($reportRevenue, 2)
            ];
        }

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
                    <?= $order['total_pieces'] ?> pieces |
                    Status: <?= ucfirst($order['status']) ?> |
                    <span style="padding: 0.125rem 0.5rem; background: <?= $isWholesale ? '#fef3c7' : '#dbeafe' ?>; border-radius: 4px; font-size: 0.75rem;">
                        <?= $isWholesale ? 'Wholesale' : 'Referral' ?>
                    </span>
                </p>
            </div>
            <?php if ($hasDiscrepancy): ?>
                <span style="background: #fee2e2; color: #991b1b; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.875rem; font-weight: 600;">
                    ⚠️ Discrepancy: $<?= number_format(abs($difference), 2) ?>
                </span>
            <?php else: ?>
                <span style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.875rem;">
                    ✓ Match
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
                        <p><strong>CPT Rate:</strong> $<?= number_format($dashboardExplanation['cpt_rate'], 2) ?></p>
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
                    <p><strong>Type:</strong> <?= $reportExplanation['type'] ?></p>
                    <?php if ($isWholesale): ?>
                        <p><strong>Boxes:</strong> <?= $reportExplanation['boxes'] ?></p>
                        <p><strong>Price/Box:</strong> $<?= number_format($reportExplanation['price_per_box'], 2) ?></p>
                    <?php else: ?>
                        <p><strong>Billable Pieces:</strong> <?= $reportExplanation['billable_pieces'] ?></p>
                        <p><strong>CPT Rate:</strong> $<?= number_format($reportExplanation['cpt_rate'], 2) ?></p>
                    <?php endif; ?>
                    <p><strong>Rate Source:</strong> <?= $reportExplanation['source'] ?></p>
                    <p style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #bbf7d0;">
                        <strong>Formula:</strong> <?= $reportExplanation['formula'] ?>
                    </p>
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
                    <tr><td style="padding: 0.25rem; border-bottom: 1px solid #ddd;"><strong>reimbursement_rate (from table):</strong></td><td><?= isset($rates[$productId]) ? '$' . number_format($rates[$productId], 2) : 'N/A' ?></td></tr>
                    <tr><td style="padding: 0.25rem;"><strong>practice_custom_price:</strong></td><td><?= isset($practicePricing[$practiceId][$productId]) ? '$' . number_format($practicePricing[$practiceId][$productId], 2) : 'N/A' ?></td></tr>
                </table>
            </div>
        </details>
    </div>

    <?php endforeach; ?>

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
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">reimbursement_rates → price_admin → product_price/pieces</td>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">medicare_allowable → product_price/pieces</td>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);"><strong>Wholesale Price</strong></td>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">price_wholesale OR product_price</td>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">practice_custom_price → product_price×pieces → price_wholesale</td>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);"><strong>Practice Custom Pricing</strong></td>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">❌ Not checked</td>
                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">✅ Checked first for wholesale</td>
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
