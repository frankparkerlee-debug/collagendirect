<?php
// admin/auth.php
declare(strict_types=1);
require __DIR__ . '/db.php';

function current_admin() {
  // ADMIN PORTAL ACCESS RULES (/admin):
  // 1. Super Admin (parker@collagendirect.health) - from users table with role='superadmin'
  // 2. Employees - from admin_users table with role='employee' or 'admin'
  // 3. Manufacturer - from admin_users table with role='manufacturer'
  //
  // IMPORTANT: practice_admin users are practice managers and should ONLY access /portal
  // They should NEVER access /admin portal - they belong in the physician portal

  // Check if logged in as admin_user (employees, manufacturer)
  if (isset($_SESSION['admin'])) {
    return $_SESSION['admin'];
  }

  // Check if logged in as physician with superadmin role ONLY
  // practice_admin is explicitly EXCLUDED - they belong in the physician portal
  if (isset($_SESSION['user_id'])) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role FROM users WHERE id = ? AND role = 'superadmin'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
      // Return in same format as admin_users
      return [
        'id' => $user['id'],
        'email' => $user['email'],
        'name' => trim($user['first_name'] . ' ' . $user['last_name']),
        'role' => $user['role']
      ];
    }
  }

  return null;
}
function require_admin(): void {
  if (!current_admin()) {
    header('Location: /admin/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin/'));
    exit;
  }
}
