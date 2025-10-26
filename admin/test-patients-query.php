<?php
// admin/test-patients-query.php - Test the exact query used in patients.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

header('Content-Type: text/plain');

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$adminId = $admin['id'] ?? '';

echo "=== PATIENTS QUERY TEST ===\n\n";
echo "Admin Role: $adminRole\n";
echo "Admin ID: $adminId\n\n";

$where = "1=1";
$params = [];

// Role-based access control (same as patients.php)
if ($adminRole === 'superadmin' || $adminRole === 'manufacturer') {
  echo "Filter: Superadmin/Manufacturer - showing ALL patients\n\n";
} else {
  echo "Filter: Employee - showing only assigned physicians' patients\n\n";
  $where .= " AND EXISTS (SELECT 1 FROM admin_physicians ap WHERE ap.admin_id = :admin_id AND ap.physician_user_id = p.user_id)";
  $params['admin_id'] = $adminId;
}

$sql = "
  SELECT
    p.id, p.user_id, p.first_name, p.last_name, p.email, p.phone, p.dob,
    p.status, p.created_at,
    u.first_name AS phys_first, u.last_name AS phys_last, u.practice_name,
    COUNT(DISTINCT o.id) AS order_count,
    MAX(o.created_at) AS last_order_date
  FROM patients p
  LEFT JOIN users u ON u.id = p.user_id
  LEFT JOIN orders o ON o.patient_id = p.id AND o.status NOT IN ('rejected','cancelled')
  WHERE $where
  GROUP BY p.id, p.user_id, p.first_name, p.last_name, p.email, p.phone, p.dob,
           p.status, p.created_at, u.first_name, u.last_name, u.practice_name
  ORDER BY p.created_at DESC
";

echo "SQL Query:\n$sql\n\n";
echo "Params: " . json_encode($params) . "\n\n";

try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();

  echo "Found " . count($rows) . " patients\n\n";

  if (count($rows) > 0) {
    echo "--- First 5 Patients ---\n";
    foreach (array_slice($rows, 0, 5) as $row) {
      echo sprintf("ID: %s | Name: %s %s | Physician: %s %s | Status: %s | Orders: %d\n",
        $row['id'],
        $row['first_name'],
        $row['last_name'],
        $row['phys_first'] ?? 'N/A',
        $row['phys_last'] ?? 'N/A',
        $row['status'] ?? 'NULL',
        $row['order_count']
      );
    }
  }

} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
