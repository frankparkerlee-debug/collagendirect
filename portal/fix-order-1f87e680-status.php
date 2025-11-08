<?php
/**
 * One-time fix for order 1f87e680b3346430d670d76bf1390dc6
 * This order was created before the status fix was deployed
 * It needs status='submitted' and review_status='pending_admin_review'
 */

// Security: Only allow with secret key
$SECRET_KEY = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $SECRET_KEY) {
    http_response_code(403);
    die('Access denied. Provide ?key=SECRET in URL');
}

header('Content-Type: text/plain; charset=utf-8');

echo "Fixing order status for order 1f87e680b3346430d670d76bf1390dc6...\n\n";

// Direct database connection
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'collagen_db';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '5432';

try {
    $pdo = new PDO(
        "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $orderId = '1f87e680b3346430d670d76bf1390dc6';

    // Check current status
    $stmt = $pdo->prepare("SELECT id, status, review_status, created_at FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo "❌ Order not found!\n";
        exit;
    }

    echo "Current status:\n";
    echo "  status: " . ($order['status'] ?? 'NULL') . "\n";
    echo "  review_status: " . ($order['review_status'] ?? 'NULL') . "\n";
    echo "  created_at: " . $order['created_at'] . "\n\n";

    // Fix the status
    $update = $pdo->prepare("
        UPDATE orders
        SET status = 'submitted',
            review_status = 'pending_admin_review',
            updated_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$orderId]);

    echo "✓ Updated order status\n\n";

    // Verify
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "New status:\n";
    echo "  status: " . $order['status'] . "\n";
    echo "  review_status: " . $order['review_status'] . "\n\n";

    echo "✓ Fix completed successfully!\n";
    echo "\nThe order should now be visible in:\n";
    echo "- /admin/orders.php\n";
    echo "- /admin/billing.php\n";
    echo "- /admin/index.php (recent activity)\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
