<?php
/**
 * Test File Upload Flow
 * This script tests the complete upload flow without going through the portal
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

echo "<pre>";
echo "=== Testing File Upload Flow ===\n\n";

// Get a test patient
$patient = $pdo->query("SELECT id, user_id, first_name, last_name FROM patients ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
  echo "ERROR: No patients found in database\n";
  exit;
}

echo "Test Patient: {$patient['first_name']} {$patient['last_name']}\n";
echo "Patient ID: {$patient['id']}\n";
echo "User ID: {$patient['user_id']}\n\n";

// Test: Check if we can read the patient
echo "--- Step 1: Read Patient Record ---\n";
$stmt = $pdo->prepare("SELECT id, user_id, notes_path, notes_mime FROM patients WHERE id = ?");
$stmt->execute([$patient['id']]);
$before = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Before: notes_path = " . ($before['notes_path'] ?: 'NULL') . "\n";
echo "Before: notes_mime = " . ($before['notes_mime'] ?: 'NULL') . "\n\n";

// Test: Try to update notes_path
echo "--- Step 2: Test UPDATE Query ---\n";
$testPath = '/uploads/notes/test-' . date('YmdHis') . '.txt';
$testMime = 'text/plain';

$updateStmt = $pdo->prepare("UPDATE patients SET notes_path = ?, notes_mime = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
$updateStmt->execute([$testPath, $testMime, $patient['id'], $patient['user_id']]);
$rowCount = $updateStmt->rowCount();

echo "UPDATE executed with:\n";
echo "  notes_path = $testPath\n";
echo "  notes_mime = $testMime\n";
echo "  id = {$patient['id']}\n";
echo "  user_id = {$patient['user_id']}\n";
echo "Rows affected: $rowCount\n\n";

if ($rowCount === 0) {
  echo "⚠️  UPDATE FAILED - No rows affected!\n";
  echo "This means the WHERE clause didn't match any records.\n\n";

  // Debug: Check without user_id constraint
  echo "--- Debugging: Check without user_id ---\n";
  $debugStmt = $pdo->prepare("SELECT id, user_id FROM patients WHERE id = ?");
  $debugStmt->execute([$patient['id']]);
  $debugData = $debugStmt->fetch(PDO::FETCH_ASSOC);

  if ($debugData) {
    echo "Patient found with:\n";
    echo "  id = {$debugData['id']}\n";
    echo "  user_id = {$debugData['user_id']}\n";
    echo "  Expected user_id = {$patient['user_id']}\n";
    if ($debugData['user_id'] !== $patient['user_id']) {
      echo "⚠️  USER_ID MISMATCH!\n";
    }
  } else {
    echo "Patient not found at all!\n";
  }
} else {
  echo "✓ UPDATE succeeded\n\n";

  // Verify the update
  echo "--- Step 3: Verify Update ---\n";
  $stmt = $pdo->prepare("SELECT id, notes_path, notes_mime FROM patients WHERE id = ?");
  $stmt->execute([$patient['id']]);
  $after = $stmt->fetch(PDO::FETCH_ASSOC);
  echo "After: notes_path = " . ($after['notes_path'] ?: 'NULL') . "\n";
  echo "After: notes_mime = " . ($after['notes_mime'] ?: 'NULL') . "\n\n";

  if ($after['notes_path'] === $testPath) {
    echo "✓ Value persisted correctly!\n";
  } else {
    echo "✗ Value didn't persist! Database shows: " . $after['notes_path'] . "\n";
  }

  // Clean up test data
  echo "\n--- Cleanup ---\n";
  $pdo->prepare("UPDATE patients SET notes_path = ?, notes_mime = ? WHERE id = ?")
      ->execute([$before['notes_path'], $before['notes_mime'], $patient['id']]);
  echo "Restored original values\n";
}

echo "\n=== Test Complete ===\n";
echo "</pre>";
