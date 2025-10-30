<?php
/**
 * Debug script to test patients query
 */

require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';

header('Content-Type: text/plain');

echo "=== Testing Admin Patients Query ===\n\n";

echo "1. Checking session...\n";
echo "   Admin ID: " . ($_SESSION['admin_id'] ?? 'NOT SET') . "\n";
echo "   Admin Role: " . ($_SESSION['admin_role'] ?? 'NOT SET') . "\n\n";

$adminId = $_SESSION['admin_id'] ?? null;
$adminRole = $_SESSION['admin_role'] ?? null;

if (!$adminId) {
  echo "ERROR: Not logged in\n";
  exit;
}

echo "2. Simple patient count query...\n";
try {
  $count = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
  echo "   Total patients in database: $count\n\n";
} catch (Exception $e) {
  echo "   ERROR: " . $e->getMessage() . "\n\n";
}

echo "3. Testing basic query with joins...\n";
try {
  $sql = "
    SELECT
      p.id, p.first_name, p.last_name,
      u.first_name AS phys_first, u.last_name AS phys_last,
      COUNT(DISTINCT o.id) AS order_count
    FROM patients p
    LEFT JOIN users u ON u.id = p.user_id
    LEFT JOIN orders o ON o.patient_id = p.id
    WHERE 1=1
    GROUP BY p.id, p.first_name, p.last_name, u.first_name, u.last_name
    LIMIT 10
  ";

  $stmt = $pdo->query($sql);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "   Found " . count($rows) . " patients\n";
  if (count($rows) > 0) {
    echo "   Sample patient: " . $rows[0]['first_name'] . " " . $rows[0]['last_name'] . "\n";
    echo "   Physician: " . ($rows[0]['phys_first'] ?? 'NULL') . " " . ($rows[0]['phys_last'] ?? 'NULL') . "\n";
  }
  echo "\n";
} catch (Exception $e) {
  echo "   ERROR: " . $e->getMessage() . "\n\n";
}

echo "=== Test Complete ===\n";
