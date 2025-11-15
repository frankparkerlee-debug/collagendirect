<?php
/**
 * Migration: Update User Roles and Permissions
 *
 * Changes:
 * 1. Set only parker@collagendirect.health as superadmin
 * 2. Update other superadmins to appropriate roles
 * 3. Clean up role structure
 */
declare(strict_types=1);
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== User Roles Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Find current superadmins
  echo "1. Finding current superadmins...\n";
  $currentSuperadmins = $pdo->query("
    SELECT id, email, first_name, last_name, role
    FROM users
    WHERE role = 'superadmin'
  ")->fetchAll(PDO::FETCH_ASSOC);

  echo "   Found " . count($currentSuperadmins) . " superadmin(s):\n";
  foreach ($currentSuperadmins as $admin) {
    echo "   - {$admin['email']}\n";
  }

  // 2. Update all superadmins except parker@collagendirect.health to practice_admin
  echo "\n2. Updating superadmins (keeping only parker@collagendirect.health)...\n";
  $stmt = $pdo->prepare("
    UPDATE users
    SET role = 'practice_admin',
        user_type = 'practice_admin',
        can_manage_physicians = TRUE,
        updated_at = NOW()
    WHERE role = 'superadmin'
    AND email != 'parker@collagendirect.health'
  ");
  $stmt->execute();
  $updated = $stmt->rowCount();
  echo "   ✓ Updated $updated user(s) from superadmin to practice_admin\n";

  // 3. Ensure parker@collagendirect.health is superadmin
  echo "\n3. Ensuring parker@collagendirect.health is superadmin...\n";
  $stmt = $pdo->prepare("
    UPDATE users
    SET role = 'superadmin',
        user_type = 'superadmin',
        status = 'active',
        updated_at = NOW()
    WHERE email = 'parker@collagendirect.health'
  ");
  $stmt->execute();

  // Check if parker exists
  $parkerCheck = $pdo->query("
    SELECT id, email, role
    FROM users
    WHERE email = 'parker@collagendirect.health'
  ")->fetch(PDO::FETCH_ASSOC);

  if ($parkerCheck) {
    echo "   ✓ parker@collagendirect.health confirmed as superadmin\n";
  } else {
    echo "   ⚠️  Warning: parker@collagendirect.health not found in users table\n";
    echo "   You may need to create this user manually\n";
  }

  // 4. Show final superadmin list
  echo "\n4. Final superadmin list:\n";
  $finalSuperadmins = $pdo->query("
    SELECT email, first_name, last_name, role, status
    FROM users
    WHERE role = 'superadmin'
  ")->fetchAll(PDO::FETCH_ASSOC);

  if (count($finalSuperadmins) > 0) {
    foreach ($finalSuperadmins as $admin) {
      echo "   ✓ {$admin['email']} - {$admin['first_name']} {$admin['last_name']} (Status: {$admin['status']})\n";
    }
  } else {
    echo "   ⚠️  No superadmins found!\n";
  }

  $pdo->commit();

  echo "\n✅ Migration completed successfully!\n";
  echo "\nSummary:\n";
  echo "- Only parker@collagendirect.health has superadmin role\n";
  echo "- Other former superadmins converted to practice_admin\n";

} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "\n❌ Migration failed!\n";
  echo "Error: " . $e->getMessage() . "\n";
  echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
  http_response_code(500);
}
