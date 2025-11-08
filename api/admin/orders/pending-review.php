<?php
// api/admin/orders/pending-review.php
declare(strict_types=1);
require __DIR__ . '/../../db.php';
require_csrf();

// Require superadmin authentication
if (!isset($_SESSION['user_id'])) {
    json_out(401, ['error' => 'Unauthorized']);
}

// Check if user is superadmin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'superadmin') {
    json_out(403, ['error' => 'Superadmin access required']);
}

try {
    // Get all orders awaiting review
    // Note: Using review_status to filter pending orders (set by orders.create.php)
    $stmt = $pdo->query("
        SELECT
            o.id,
            o.created_at,
            o.updated_at,
            o.status,
            o.review_status,
            o.product,
            o.is_complete,
            o.missing_fields,
            o.payment_type as payment_method,
            o.delivery_mode as delivery_location,
            p.first_name as patient_first_name,
            p.last_name as patient_last_name,
            p.dob as patient_dob,
            u.first_name as physician_first_name,
            u.last_name as physician_last_name,
            u.practice_name,
            u.npi as has_dme_license
        FROM orders o
        JOIN patients p ON o.patient_id = p.id
        JOIN users u ON o.user_id = u.id
        WHERE o.review_status IN ('pending_admin_review', 'under_review')
           OR (o.status IN ('submitted', 'under_review') AND o.review_status IS NULL)
        ORDER BY o.created_at DESC
    ");

    $orders = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Parse missing_fields array
        $missingFields = [];
        if (!empty($row['missing_fields'])) {
            $arrayStr = trim($row['missing_fields'], '{}');
            if ($arrayStr) {
                $missingFields = explode(',', $arrayStr);
            }
        }
        $row['missing_fields'] = $missingFields;

        // Convert boolean
        $row['is_complete'] = (bool)$row['is_complete'];
        $row['has_dme_license'] = (bool)$row['has_dme_license'];

        $orders[] = $row;
    }

    json_out(200, [
        'orders' => $orders,
        'count' => count($orders)
    ]);

} catch (Exception $e) {
    error_log("Pending review error: " . $e->getMessage());
    json_out(500, [
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
