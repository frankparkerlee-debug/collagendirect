<?php
// run-migration.php - Run the superadmin migration
// Delete this file after running!
declare(strict_types=1);
require __DIR__ . '/api/db.php';

header('Content-Type: text/plain');

echo "Running superadmin migration...\n\n";

try {
  // Update sparkingmatt and parker to superadmin role
  $stmt = $pdo->prepare("UPDATE users SET role = 'superadmin' WHERE email IN (?, ?)");
  $stmt->execute(['sparkingmatt@gmail.com', 'parker@senecawest.com']);
  $updated = $stmt->rowCount();

  echo "✓ Updated $updated users to superadmin role\n\n";

  // Show the updated users
  $stmt = $pdo->query("SELECT id, email, first_name, last_name, role FROM users WHERE email IN ('sparkingmatt@gmail.com', 'parker@senecawest.com')");
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Current superadmin users:\n";
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
  echo "\n*** IMPORTANT: Delete this file (run-migration.php) now! ***\n";

} catch (Exception $e) {
  echo "✗ Error: " . $e->getMessage() . "\n";
  http_response_code(500);
}
