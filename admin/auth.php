<?php
// admin/auth.php
declare(strict_types=1);
require __DIR__ . '/db.php';

function current_admin() {
  // Check if logged in as admin_user
  if (isset($_SESSION['admin'])) {
    return $_SESSION['admin'];
  }

  // Check if logged in as physician with practice_admin role
  if (isset($_SESSION['user_id'])) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role FROM users WHERE id = ? AND role = 'practice_admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
      // Return in same format as admin_users
      return [
        'id' => $user['id'],
        'email' => $user['email'],
        'name' => trim($user['first_name'] . ' ' . $user['last_name']),
        'role' => 'practice_admin'
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
