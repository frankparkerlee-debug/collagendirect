<?php
require_once __DIR__ . '/../api/db.php';

$order_id = $_GET['id'] ?? '42d80f6d665589006563be48119b4420';

// Get order with all fields
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Data: <?= htmlspecialchars(substr($order_id, 0, 8)) ?></title>
    <style>
        body { font-family: monospace; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .highlight { background-color: #ffffcc; }
    </style>
</head>
<body>
    <h1>Order Data</h1>
    <h2>ID: <?= htmlspecialchars($order_id) ?></h2>

    <h3>Key Fields:</h3>
    <table>
        <tr>
            <th>Field</th>
            <th>Value</th>
        </tr>
        <tr class="highlight">
            <td>order_group_id</td>
            <td><?= htmlspecialchars($order['order_group_id'] ?? 'NULL') ?></td>
        </tr>
        <tr class="highlight">
            <td>product</td>
            <td><?= htmlspecialchars($order['product'] ?? '') ?></td>
        </tr>
        <tr class="highlight">
            <td>product_type</td>
            <td><?= htmlspecialchars($order['product_type'] ?? 'NULL') ?></td>
        </tr>
        <tr class="highlight">
            <td>wound_index</td>
            <td><?= htmlspecialchars($order['wound_index'] ?? 'NULL') ?></td>
        </tr>
        <tr>
            <td>wounds_data (JSON)</td>
            <td><pre><?= htmlspecialchars($order['wounds_data'] ?? 'NULL') ?></pre></td>
        </tr>
        <tr>
            <td>created_at</td>
            <td><?= htmlspecialchars($order['created_at'] ?? '') ?></td>
        </tr>
    </table>

    <?php if (!empty($order['order_group_id'])): ?>
        <h3>Related Orders in Group:</h3>
        <?php
        $stmt = $pdo->prepare("SELECT id, product, product_type, wound_index FROM orders WHERE order_group_id = ? ORDER BY wound_index, product_type");
        $stmt->execute([$order['order_group_id']]);
        $related = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <table>
            <tr>
                <th>Order ID</th>
                <th>Product</th>
                <th>Product Type</th>
                <th>Wound Index</th>
            </tr>
            <?php foreach ($related as $r): ?>
            <tr>
                <td><?= htmlspecialchars(substr($r['id'], 0, 8)) ?>...</td>
                <td><?= htmlspecialchars($r['product']) ?></td>
                <td><?= htmlspecialchars($r['product_type'] ?? 'NULL') ?></td>
                <td><?= htmlspecialchars($r['wound_index'] ?? 'NULL') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p><strong>No order_group_id - checking if this might be a group parent...</strong></p>
        <?php
        $stmt = $pdo->prepare("SELECT id, product, product_type, wound_index FROM orders WHERE order_group_id = ?");
        $stmt->execute([$order_id]);
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($children) > 0):
        ?>
            <h3>Child Orders:</h3>
            <table>
                <tr>
                    <th>Order ID</th>
                    <th>Product</th>
                    <th>Product Type</th>
                    <th>Wound Index</th>
                </tr>
                <?php foreach ($children as $c): ?>
                <tr>
                    <td><?= htmlspecialchars(substr($c['id'], 0, 8)) ?>...</td>
                    <td><?= htmlspecialchars($c['product']) ?></td>
                    <td><?= htmlspecialchars($c['product_type'] ?? 'NULL') ?></td>
                    <td><?= htmlspecialchars($c['wound_index'] ?? 'NULL') ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>No child orders found. This appears to be a single-product order.</p>
        <?php endif; ?>
    <?php endif; ?>

    <h3>All Fields:</h3>
    <table>
        <tr>
            <th>Field</th>
            <th>Value</th>
        </tr>
        <?php foreach ($order as $key => $value): ?>
        <tr>
            <td><?= htmlspecialchars($key) ?></td>
            <td><?= htmlspecialchars($value ?? 'NULL') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
