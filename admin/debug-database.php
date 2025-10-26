<?php
// admin/debug-database.php - Diagnostic page to check database contents
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

header('Content-Type: text/plain');

echo "=== DATABASE DIAGNOSTIC ===\n\n";

try {
  // Check patients
  $patientCount = $pdo->query("SELECT COUNT(*) as cnt FROM patients")->fetch();
  echo "Total Patients: " . $patientCount['cnt'] . "\n\n";

  if ($patientCount['cnt'] > 0) {
    echo "--- Sample Patients (first 5) ---\n";
    $patients = $pdo->query("SELECT id, user_id, first_name, last_name, email, created_at FROM patients ORDER BY created_at DESC LIMIT 5")->fetchAll();
    foreach ($patients as $p) {
      echo sprintf("ID: %s | Name: %s %s | Email: %s | User ID: %s | Created: %s\n",
        $p['id'], $p['first_name'], $p['last_name'], $p['email'], $p['user_id'], $p['created_at']);
    }
    echo "\n";
  }

  // Check orders
  $orderCount = $pdo->query("SELECT COUNT(*) as cnt FROM orders")->fetch();
  echo "Total Orders: " . $orderCount['cnt'] . "\n\n";

  if ($orderCount['cnt'] > 0) {
    echo "--- Sample Orders (first 5) ---\n";
    $orders = $pdo->query("SELECT id, user_id, patient_id, status, product, created_at FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();
    foreach ($orders as $o) {
      echo sprintf("ID: %s | Patient ID: %s | User ID: %s | Status: %s | Product: %s | Created: %s\n",
        $o['id'], $o['patient_id'], $o['user_id'], $o['status'], $o['product'], $o['created_at']);
    }
    echo "\n";
  }

  // Check order statuses
  echo "--- Order Status Breakdown ---\n";
  $statuses = $pdo->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status ORDER BY cnt DESC")->fetchAll();
  foreach ($statuses as $s) {
    echo sprintf("%s: %d\n", $s['status'], $s['cnt']);
  }
  echo "\n";

  // Check users (physicians)
  $userCount = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE role IN ('physician', 'practice_admin', 'superadmin')")->fetch();
  echo "Total Physicians/Practice Admins: " . $userCount['cnt'] . "\n\n";

  if ($userCount['cnt'] > 0) {
    echo "--- Physicians/Practice Admins ---\n";
    $users = $pdo->query("SELECT id, email, first_name, last_name, role FROM users WHERE role IN ('physician', 'practice_admin', 'superadmin') ORDER BY created_at DESC")->fetchAll();
    foreach ($users as $u) {
      echo sprintf("ID: %s | Email: %s | Name: %s %s | Role: %s\n",
        $u['id'], $u['email'], $u['first_name'], $u['last_name'], $u['role']);
    }
    echo "\n";
  }

  // Check admin_users
  $adminCount = $pdo->query("SELECT COUNT(*) as cnt FROM admin_users")->fetch();
  echo "Total Admin Users: " . $adminCount['cnt'] . "\n\n";

  if ($adminCount['cnt'] > 0) {
    echo "--- Admin Users ---\n";
    $admins = $pdo->query("SELECT id, email, name, role FROM admin_users ORDER BY created_at DESC")->fetchAll();
    foreach ($admins as $a) {
      echo sprintf("ID: %s | Email: %s | Name: %s | Role: %s\n",
        $a['id'], $a['email'], $a['name'], $a['role']);
    }
    echo "\n";
  }

  // Check current session
  echo "--- Current Session ---\n";
  $admin = current_admin();
  echo "Admin Role: " . ($admin['role'] ?? 'NONE') . "\n";
  echo "Admin ID: " . ($admin['id'] ?? 'NONE') . "\n";
  echo "Admin Email: " . ($admin['email'] ?? 'NONE') . "\n";
  echo "Admin Name: " . ($admin['name'] ?? 'NONE') . "\n";

} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
