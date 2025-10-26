<?php
require __DIR__ . '/../api/db.php';
header('Content-Type: text/plain');

echo "=== Upload Debug for parker@senecawest.com ===\n\n";

// Check session
echo "Session Data:\n";
echo "  user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "  admin_id: " . ($_SESSION['admin_id'] ?? 'NOT SET') . "\n";
echo "  email: " . ($_SESSION['email'] ?? 'NOT SET') . "\n";
echo "  role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n\n";

$userId = $_SESSION['user_id'] ?? null;

if ($userId) {
  // Get user info
  $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  
  echo "User from database:\n";
  print_r($user);
  echo "\n";

  // Get patients owned by this user
  $stmt = $pdo->prepare("SELECT id, first_name, last_name, user_id, created_at FROM patients WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
  $stmt->execute([$userId]);
  $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  echo "Patients owned by this user (user_id = $userId):\n";
  if (empty($patients)) {
    echo "  No patients found!\n\n";
  } else {
    foreach ($patients as $p) {
      echo "  - {$p['first_name']} {$p['last_name']} (ID: {$p['id']}, owner: {$p['user_id']})\n";
    }
    echo "\n";
  }

  // Get ALL patients to check ownership
  $stmt = $pdo->query("SELECT id, first_name, last_name, user_id FROM patients ORDER BY created_at DESC LIMIT 10");
  $allPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  echo "All recent patients (to check ownership):\n";
  foreach ($allPatients as $p) {
    $match = ($p['user_id'] === $userId) ? '✓ MATCH' : '✗ DIFFERENT';
    echo "  - {$p['first_name']} {$p['last_name']} (owner: {$p['user_id']}) $match\n";
  }
}
