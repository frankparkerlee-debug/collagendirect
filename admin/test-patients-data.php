<?php
// Test script to check patients and orders data
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
if (function_exists('require_admin')) require_admin();

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Patients & Orders Data Diagnostic</h1>";
echo "<style>body { font-family: Arial; padding: 20px; } table { border-collapse: collapse; margin: 20px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background: #f4f4f4; }</style>";

// Get current admin
$admin = current_admin();
echo "<h2>Current Admin</h2>";
echo "<table>";
echo "<tr><th>ID</th><td>" . ($admin['id'] ?? 'NONE') . "</td></tr>";
echo "<tr><th>Email</th><td>" . ($admin['email'] ?? 'NONE') . "</td></tr>";
echo "<tr><th>Role</th><td>" . ($admin['role'] ?? 'NONE') . "</td></tr>";
echo "</table>";

// Check total patients
echo "<h2>Patients Count</h2>";
try {
  $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM patients");
  $result = $stmt->fetch();
  echo "<p>Total patients in database: <strong>" . ($result['cnt'] ?? 0) . "</strong></p>";

  if ($result['cnt'] > 0) {
    echo "<h3>Sample Patients (first 5)</h3>";
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, user_id, state, created_at FROM patients ORDER BY created_at DESC LIMIT 5");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>User ID</th><th>State/Status</th><th>Created</th></tr></thead><tbody>";
    foreach ($patients as $p) {
      echo "<tr>";
      echo "<td>" . htmlspecialchars($p['id']) . "</td>";
      echo "<td>" . htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) . "</td>";
      echo "<td>" . htmlspecialchars($p['email'] ?? '-') . "</td>";
      echo "<td>" . htmlspecialchars($p['user_id'] ?? '-') . "</td>";
      echo "<td>" . htmlspecialchars($p['state'] ?? '-') . "</td>";
      echo "<td>" . htmlspecialchars($p['created_at'] ?? '-') . "</td>";
      echo "</tr>";
    }
    echo "</tbody></table>";
  }
} catch (Exception $e) {
  echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check total orders
echo "<h2>Orders Count</h2>";
try {
  $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders");
  $result = $stmt->fetch();
  echo "<p>Total orders in database: <strong>" . ($result['cnt'] ?? 0) . "</strong></p>";

  $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status NOT IN ('rejected', 'cancelled')");
  $result = $stmt->fetch();
  echo "<p>Active orders (not rejected/cancelled): <strong>" . ($result['cnt'] ?? 0) . "</strong></p>";

  if ($result['cnt'] > 0) {
    echo "<h3>Sample Orders (first 5)</h3>";
    $stmt = $pdo->query("SELECT o.id, o.patient_id, o.user_id, o.product, o.status, o.created_at, p.first_name, p.last_name FROM orders o LEFT JOIN patients p ON p.id = o.patient_id WHERE o.status NOT IN ('rejected', 'cancelled') ORDER BY o.created_at DESC LIMIT 5");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table><thead><tr><th>Order ID</th><th>Patient</th><th>Product</th><th>Status</th><th>Created</th></tr></thead><tbody>";
    foreach ($orders as $o) {
      echo "<tr>";
      echo "<td>#" . htmlspecialchars($o['id']) . "</td>";
      echo "<td>" . htmlspecialchars(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) . " (ID: " . htmlspecialchars($o['patient_id']) . ")</td>";
      echo "<td>" . htmlspecialchars($o['product'] ?? '-') . "</td>";
      echo "<td>" . htmlspecialchars($o['status'] ?? '-') . "</td>";
      echo "<td>" . htmlspecialchars($o['created_at'] ?? '-') . "</td>";
      echo "</tr>";
    }
    echo "</tbody></table>";
  }
} catch (Exception $e) {
  echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check users table
echo "<h2>Users (Physicians) Count</h2>";
try {
  $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE role IN ('physician', 'practice_admin')");
  $result = $stmt->fetch();
  echo "<p>Total physicians/practice admins: <strong>" . ($result['cnt'] ?? 0) . "</strong></p>";

  if ($result['cnt'] > 0) {
    echo "<h3>Sample Physicians (first 5)</h3>";
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, role, practice_name FROM users WHERE role IN ('physician', 'practice_admin') ORDER BY created_at DESC LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Practice</th></tr></thead><tbody>";
    foreach ($users as $u) {
      echo "<tr>";
      echo "<td>" . htmlspecialchars($u['id']) . "</td>";
      echo "<td>" . htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) . "</td>";
      echo "<td>" . htmlspecialchars($u['email'] ?? '-') . "</td>";
      echo "<td>" . htmlspecialchars($u['role'] ?? '-') . "</td>";
      echo "<td>" . htmlspecialchars($u['practice_name'] ?? '-') . "</td>";
      echo "</tr>";
    }
    echo "</tbody></table>";
  }
} catch (Exception $e) {
  echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='/admin/patients.php'>← Back to Patients</a> | <a href='/admin/billing.php'>← Back to Billing</a></p>";
