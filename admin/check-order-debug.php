<?php
/**
 * Debug script to check why order c57e7444586f266b261ca3c304eb25b2 isn't showing
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

$orderId = 'c57e7444586f266b261ca3c304eb25b2';

echo "<h2>Order Debug Info</h2>\n";
echo "<pre>\n";

// 1. Check if order exists
echo "=== Order Exists Check ===\n";
$stmt = $pdo->prepare("SELECT id, status, review_status, created_at, user_id, patient_id FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "❌ Order NOT FOUND in database!\n";
    exit;
}

echo "✅ Order found:\n";
print_r($order);
echo "\n";

// 2. Check patient exists
echo "=== Patient Check ===\n";
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM patients WHERE id = ?");
$stmt->execute([$order['patient_id']]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if ($patient) {
    echo "✅ Patient: {$patient['first_name']} {$patient['last_name']}\n";
} else {
    echo "❌ Patient NOT FOUND!\n";
}
echo "\n";

// 3. Check user exists
echo "=== Physician Check ===\n";
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
$stmt->execute([$order['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user) {
    echo "✅ Physician: {$user['first_name']} {$user['last_name']} ({$user['email']})\n";
} else {
    echo "❌ Physician NOT FOUND!\n";
}
echo "\n";

// 4. Test the WHERE clause filters
echo "=== WHERE Clause Tests ===\n";

// Draft filter
$isDraft = $order['review_status'] === 'draft';
$passesDraftFilter = ($order['review_status'] === null || $order['review_status'] !== 'draft');
echo "review_status: " . ($order['review_status'] ?? 'NULL') . "\n";
echo "Is draft? " . ($isDraft ? 'YES' : 'NO') . "\n";
echo "Passes draft filter? " . ($passesDraftFilter ? '✅ YES' : '❌ NO') . "\n\n";

// 5. Run the actual query from orders.php
echo "=== Actual Query Test ===\n";
$where = [];
$params = [];

// Same filters as orders.php
$where[] = "(o.review_status IS NULL OR o.review_status != 'draft')";

$whereClause = 'WHERE ' . implode(' AND ', $where);

$sql = "
  SELECT o.id, o.status, o.review_status, o.created_at,
         p.first_name, p.last_name
  FROM orders o
  LEFT JOIN patients p ON p.id=o.patient_id
  $whereClause
  AND o.id = :order_id
  ORDER BY o.created_at DESC
";

echo "SQL: $sql\n\n";
echo "Params: " . json_encode(array_merge($params, ['order_id' => $orderId])) . "\n\n";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, ['order_id' => $orderId]));
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "✅ Order FOUND by query:\n";
    print_r($result);
} else {
    echo "❌ Order NOT FOUND by query!\n";
}
echo "\n";

// 6. Count total orders returned by main query
echo "=== Total Orders in Main Query ===\n";
$stmt = $pdo->prepare("
  SELECT COUNT(*) as count
  FROM orders o
  LEFT JOIN patients p ON p.id=o.patient_id
  WHERE (o.review_status IS NULL OR o.review_status != 'draft')
");
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total orders shown on /admin/orders.php: {$count['count']}\n";

echo "</pre>\n";
