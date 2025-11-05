<?php
/**
 * Authentication helper for API endpoints
 * Verifies user session and returns user data
 */

/**
 * Verify user authentication from session
 *
 * @return array|false User data array or false if not authenticated
 */
function verifyAuth() {
  // Session should already be started by db.php
  if (empty($_SESSION['user_id'])) {
    return false;
  }

  $userId = $_SESSION['user_id'];

  // Get PDO from global scope (set by db.php)
  global $pdo;

  try {
    $stmt = $pdo->prepare("SELECT id, email, role, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
      return false;
    }

    return $user;
  } catch (PDOException $e) {
    error_log("Auth verification error: " . $e->getMessage());
    return false;
  }
}
