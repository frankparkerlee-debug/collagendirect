<?php
// migrate-superadmin.php - Update existing practice_admin users to superadmin role
declare(strict_types=1);
require __DIR__ . '/api/db.php';

echo "Updating user roles to superadmin...\n\n";

try {
  // Update sparkingmatt and parker to superadmin role
  $stmt = $pdo->prepare("UPDATE users SET role = 'superadmin' WHERE email IN (?, ?)");
  $stmt->execute(['sparkingmatt@gmail.com', 'parker@senecawest.com']);
  $updated = $stmt->rowCount();

  echo "✓ Updated $updated users to superadmin role\n";

  // Show the updated users
  $stmt = $pdo->query("SELECT id, email, first_name, last_name, role FROM users WHERE email IN ('sparkingmatt@gmail.com', 'parker@senecawest.com')");
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "\nCurrent superadmin users:\n";
  foreach ($users as $user) {
    echo sprintf("  - %s %s (%s) - Role: %s\n",
      $user['first_name'],
      $user['last_name'],
      $user['email'],
      $user['role']
    );
  }

  echo "\n✓ Migration complete!\n";
  echo "\nThese users now have access to:\n";
  echo "  - Practice Admin (manage orders & physicians)\n";
  echo "  - Platform Admin (manage practices & system)\n";

} catch (Exception $e) {
  echo "✗ Error: " . $e->getMessage() . "\n";
  exit(1);
}
