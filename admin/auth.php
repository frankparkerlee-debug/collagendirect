<?php
// admin/auth.php
declare(strict_types=1);
require __DIR__ . '/db.php';

function current_admin() {
  // ADMIN PORTAL ACCESS RULES (/admin):
  // 1. Super Admin (parker@collagendirect.health) - from users table with role='superadmin'
  // 2. Employees - from admin_users table with role='employee' or 'admin'
  // 3. Manufacturer - from admin_users table with role='manufacturer'
  // 4. Sales Rep - from users table with active sales_reps profile
  //
  // IMPORTANT: practice_admin users are practice managers and should ONLY access /portal
  // They should NEVER access /admin portal - they belong in the physician portal

  // Check if logged in as admin_user (employees, manufacturer)
  if (isset($_SESSION['admin'])) {
    return $_SESSION['admin'];
  }

  // Check if logged in as user with admin access (superadmin or sales_rep)
  if (isset($_SESSION['user_id'])) {
    global $pdo;

    // Check for superadmin first
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role FROM users WHERE id = ? AND role = 'superadmin'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
      return [
        'id' => $user['id'],
        'email' => $user['email'],
        'name' => trim($user['first_name'] . ' ' . $user['last_name']),
        'role' => $user['role']
      ];
    }

    // Check for active sales rep
    $stmt = $pdo->prepare("
      SELECT u.id, u.email, u.first_name, u.last_name, sr.id as rep_id, sr.status as rep_status
      FROM users u
      JOIN sales_reps sr ON sr.user_id = u.id
      WHERE u.id = ? AND sr.status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $rep = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($rep) {
      return [
        'id' => $rep['id'],
        'email' => $rep['email'],
        'name' => trim($rep['first_name'] . ' ' . $rep['last_name']),
        'role' => 'sales_rep',
        'rep_id' => $rep['rep_id']
      ];
    }
  }

  return null;
}

function current_sales_rep() {
  // Get current user's sales rep profile if they are a sales rep
  $admin = current_admin();
  if (!$admin || $admin['role'] !== 'sales_rep') {
    return null;
  }

  global $pdo;
  $stmt = $pdo->prepare("
    SELECT sr.*, u.email, u.first_name, u.last_name
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    WHERE sr.id = ?
  ");
  $stmt->execute([$admin['rep_id']]);
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

function require_admin(): void {
  if (!current_admin()) {
    header('Location: /admin/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin/'));
    exit;
  }
}

function require_sales_rep(): void {
  $admin = current_admin();
  if (!$admin || $admin['role'] !== 'sales_rep') {
    header('Location: /admin/login.php');
    exit;
  }
}

function is_sales_rep(): bool {
  $admin = current_admin();
  return $admin && $admin['role'] === 'sales_rep';
}

// Permission check: Returns true if user CAN access (is NOT a sales rep, or is explicitly allowed)
function deny_sales_rep(): void {
  if (is_sales_rep()) {
    header('Location: /admin/rep/');
    exit;
  }
}
