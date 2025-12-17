<?php
// admin/auth.php
declare(strict_types=1);
require __DIR__ . '/db.php';

function current_admin() {
  // ADMIN PORTAL ACCESS RULES (/admin):
  // 1. Super Admin (parker@collagendirect.health) - from users table with role='superadmin'
  // 2. Employees - from admin_users table with role='employee' or 'admin' or 'sales'
  // 3. Manufacturer - from admin_users table with role='manufacturer'
  // 4. Sales Rep - from users table with active sales_reps profile
  // 5. Employee Sales Rep - from admin_users with has_rep_view=true (can access /admin/rep/ portal)
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

// Check if current admin_user has employee rep view enabled
function has_employee_rep_view(): bool {
  $admin = current_admin();
  if (!$admin || !isset($_SESSION['admin'])) {
    return false;
  }
  return !empty($admin['has_rep_view']);
}

// Check if user is an employee sales rep (admin_user with role='sales')
function is_employee_sales_rep(): bool {
  $admin = current_admin();
  return $admin && isset($_SESSION['admin']) && ($admin['role'] ?? '') === 'sales';
}

// Get employee rep's managed distributors
function get_managed_distributors(PDO $pdo, int|string $adminUserId): array {
  $stmt = $pdo->prepare("
    SELECT sr.*, u.email, u.first_name, u.last_name
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    WHERE sr.managed_by_admin_id = ?
    AND sr.status = 'active'
    ORDER BY u.last_name, u.first_name
  ");
  $stmt->execute([$adminUserId]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get employee rep's direct physician accounts
function get_direct_physician_accounts(PDO $pdo, int|string $adminUserId): array {
  $stmt = $pdo->prepare("
    SELECT u.id, u.email, u.first_name, u.last_name, u.practice_name, u.role,
           u.rep_assignment_date
    FROM users u
    WHERE u.employee_rep_id = ?
    AND u.role IN ('physician', 'practice_admin')
    ORDER BY u.last_name, u.first_name
  ");
  $stmt->execute([$adminUserId]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get employee rep commission rate
function get_employee_rep_rate(PDO $pdo, int|string $adminUserId, string $rateType = 'direct'): float {
  $stmt = $pdo->prepare("
    SELECT commission_rate
    FROM employee_rep_commission_rates
    WHERE admin_user_id = ?
    AND rate_type = ?
    AND effective_date <= CURRENT_DATE
    AND (end_date IS NULL OR end_date >= CURRENT_DATE)
    ORDER BY effective_date DESC
    LIMIT 1
  ");
  $stmt->execute([$adminUserId, $rateType]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  return $result ? (float)$result['commission_rate'] : ($rateType === 'direct' ? 0.15 : 0.05);
}

// Get employee rep commission balance
function get_employee_rep_balance(PDO $pdo, int|string $adminUserId): array {
  $stmt = $pdo->prepare("
    SELECT
      COALESCE(SUM(commission_amount), 0) as total_earned,
      COALESCE(SUM(CASE WHEN source_type = 'direct' THEN commission_amount ELSE 0 END), 0) as direct_earned,
      COALESCE(SUM(CASE WHEN source_type = 'distributor_override' THEN commission_amount ELSE 0 END), 0) as override_earned
    FROM employee_rep_ledger
    WHERE admin_user_id = ?
    AND commission_amount > 0
  ");
  $stmt->execute([$adminUserId]);
  return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_earned' => 0, 'direct_earned' => 0, 'override_earned' => 0];
}
