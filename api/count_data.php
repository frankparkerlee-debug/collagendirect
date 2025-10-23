<?php
// Count patients and orders for all users
require __DIR__ . '/db.php';

try {
    // Get all users with their counts
    $users = $pdo->query("
        SELECT
            u.id,
            u.email,
            u.first_name,
            u.last_name,
            (SELECT COUNT(*) FROM patients WHERE user_id = u.id) as patient_count,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count
        FROM users u
        ORDER BY u.email
    ")->fetchAll(PDO::FETCH_ASSOC);

    json_out(200, [
        'success' => true,
        'users' => $users
    ]);

} catch (PDOException $e) {
    json_out(500, ['error' => 'Database error: ' . $e->getMessage()]);
}
