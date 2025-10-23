<?php
// admin/logout.php
declare(strict_types=1);
require __DIR__ . '/db.php';

// Handle both GET and POST for logout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Verify CSRF for POST requests (from forms)
  if (!empty($_POST['csrf']) && $_POST['csrf'] === ($_SESSION['csrf'] ?? '')) {
    $_SESSION['admin'] = null;
    $_SESSION['user_id'] = null; // Also clear physician session if exists
  } else {
    // CSRF failed but still logout to be safe
    $_SESSION['admin'] = null;
    $_SESSION['user_id'] = null;
  }
} else {
  // GET request - allow logout without CSRF for convenience
  $_SESSION['admin'] = null;
  $_SESSION['user_id'] = null;
}

// Clear entire session
session_destroy();
header('Location: /admin/login.php');
exit;
