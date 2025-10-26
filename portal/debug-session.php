<?php
// portal/debug-session.php - Show session status
declare(strict_types=1);
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain');

echo "=== SESSION DIAGNOSTIC ===\n\n";

echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "\n\n";

echo "--- Session Data ---\n";
if (!empty($_SESSION)) {
  foreach ($_SESSION as $key => $value) {
    if (is_array($value)) {
      echo "$key: " . json_encode($value, JSON_PRETTY_PRINT) . "\n";
    } else {
      echo "$key: " . var_export($value, true) . "\n";
    }
  }
} else {
  echo "(No session data)\n";
}

echo "\n--- Cookies ---\n";
if (!empty($_COOKIE)) {
  foreach ($_COOKIE as $key => $value) {
    echo "$key: " . substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '') . "\n";
  }
} else {
  echo "(No cookies)\n";
}

echo "\n--- Expected Session Variables ---\n";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "admin: " . (isset($_SESSION['admin']) ? json_encode($_SESSION['admin']) : 'NOT SET') . "\n";
echo "csrf: " . (isset($_SESSION['csrf']) ? 'SET' : 'NOT SET') . "\n";

if (isset($_SESSION['user_id'])) {
  echo "\n--- User Lookup ---\n";
  try {
    require __DIR__ . '/../api/db.php';
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
      echo "Found user in database:\n";
      echo json_encode($user, JSON_PRETTY_PRINT) . "\n";
    } else {
      echo "ERROR: user_id is set but user not found in database!\n";
    }
  } catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
  }
}
