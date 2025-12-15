<?php
/**
 * Migration: Create a test sales rep account
 *
 * Creates a test user and sales_rep profile for testing the sales rep portal.
 * Run this AFTER running the other migrations (001-007).
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
  require_once __DIR__ . '/db_cli.php';
}

echo "=== Sales Rep Feature: Create Test Sales Rep ===\n\n";

// Test account credentials
$testEmail = 'testrep@collagendirect.health';
$testPassword = 'TestRep2024!';
$testFirstName = 'Test';
$testLastName = 'SalesRep';

try {
  $pdo->beginTransaction();

  // 1. Check if test user already exists
  $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
  $checkStmt->execute([$testEmail]);
  $existingUser = $checkStmt->fetch();

  if ($existingUser) {
    echo "Test user already exists with email: $testEmail\n";
    $userId = $existingUser['id'];

    // Check if sales_rep profile exists
    $repCheck = $pdo->prepare("SELECT id, status FROM sales_reps WHERE user_id = ?");
    $repCheck->execute([$userId]);
    $existingRep = $repCheck->fetch();

    if ($existingRep) {
      echo "Sales rep profile already exists (status: {$existingRep['status']})\n";

      // Ensure it's active
      if ($existingRep['status'] !== 'active') {
        $pdo->prepare("UPDATE sales_reps SET status = 'active', updated_at = NOW() WHERE id = ?")->execute([$existingRep['id']]);
        echo "Updated status to 'active'\n";
      }

      $pdo->commit();
      echo "\n✓ Test sales rep ready!\n";
      echo "\nLogin credentials:\n";
      echo "  Email: $testEmail\n";
      echo "  Password: $testPassword\n";
      echo "  Portal: /admin/rep/\n";
      return;
    }
  } else {
    // 2. Create test user
    echo "1. Creating test user...\n";
    $userId = bin2hex(random_bytes(16));

    $pdo->prepare("
      INSERT INTO users (
        id, email, password_hash, first_name, last_name,
        role, user_type, status,
        created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, 'physician', 'physician', 'active', NOW(), NOW())
    ")->execute([
      $userId,
      $testEmail,
      password_hash($testPassword, PASSWORD_DEFAULT),
      $testFirstName,
      $testLastName
    ]);
    echo "   ✓ Created user: $testEmail\n";
  }

  // 3. Create sales_rep profile
  echo "2. Creating sales_rep profile...\n";
  $repId = bin2hex(random_bytes(16));

  $pdo->prepare("
    INSERT INTO sales_reps (
      id, user_id, company_name, status,
      application_date, approved_date, approved_by,
      how_heard_about_us, notes,
      created_at, updated_at
    ) VALUES (?, ?, 'Test Company', 'active', NOW(), NOW(), NULL, 'Test account', 'Auto-created for testing', NOW(), NOW())
  ")->execute([$repId, $userId]);
  echo "   ✓ Created sales_rep profile (status: active)\n";

  // 4. Set a commission rate
  echo "3. Setting commission rate...\n";
  $pdo->prepare("
    INSERT INTO rep_commission_rates (rep_id, rate, effective_date, set_by, notes, created_at)
    VALUES (?, 0.10, CURRENT_DATE, NULL, 'Default 10% rate', NOW())
  ")->execute([$repId]);
  echo "   ✓ Set commission rate: 10%\n";

  $pdo->commit();

  echo "\n✓ Test sales rep created successfully!\n";
  echo "\n╔═══════════════════════════════════════════════════╗\n";
  echo "║              TEST SALES REP CREDENTIALS           ║\n";
  echo "╠═══════════════════════════════════════════════════╣\n";
  echo "║  Email:    $testEmail      ║\n";
  echo "║  Password: $testPassword                      ║\n";
  echo "║  Portal:   /admin/rep/                            ║\n";
  echo "╚═══════════════════════════════════════════════════╝\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  throw $e;
}
