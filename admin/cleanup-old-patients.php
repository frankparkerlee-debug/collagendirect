<?php
/**
 * Cleanup: Delete all patients created before November 1, 2025
 * Also deletes associated orders and any related data
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== PATIENT CLEANUP (Before 11/1/2025) ===\n\n";

$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

if ($dryRun) {
  echo "DRY RUN MODE - No data will be deleted\n";
  echo "Remove ?dry_run=1 from URL to actually delete\n\n";
}

try {
  global $pdo;

  // Start transaction
  $pdo->beginTransaction();

  // 1. Find patients created before 11/1/2025
  echo "1. Finding patients created before November 1, 2025...\n";
  $cutoffDate = '2025-11-01 00:00:00';

  $stmt = $pdo->prepare("
    SELECT id, first_name, last_name, mrn, created_at, user_id
    FROM patients
    WHERE created_at < ?
    ORDER BY created_at ASC
  ");
  $stmt->execute([$cutoffDate]);
  $patientsToDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $patientCount = count($patientsToDelete);
  echo "   Found $patientCount patient(s) to delete\n\n";

  if ($patientCount === 0) {
    echo "✓ No patients found before 11/1/2025. Nothing to delete.\n";
    $pdo->rollBack();
    exit(0);
  }

  // Show sample of patients that will be deleted
  echo "2. Sample of patients to be deleted:\n";
  $sampleCount = min(10, $patientCount);
  for ($i = 0; $i < $sampleCount; $i++) {
    $p = $patientsToDelete[$i];
    echo "   - MRN: " . ($p['mrn'] ?? 'N/A') . " | ";
    echo ($p['first_name'] ?? '') . " " . ($p['last_name'] ?? '') . " | ";
    echo "Created: " . date('Y-m-d H:i:s', strtotime($p['created_at'])) . "\n";
  }
  if ($patientCount > 10) {
    echo "   ... and " . ($patientCount - 10) . " more\n";
  }
  echo "\n";

  // Get patient IDs
  $patientIds = array_column($patientsToDelete, 'id');

  // 2. Find and count related orders
  echo "3. Checking for related orders...\n";
  $placeholders = str_repeat('?,', count($patientIds) - 1) . '?';
  $orderStmt = $pdo->prepare("
    SELECT COUNT(*) as cnt
    FROM orders
    WHERE patient_id IN ($placeholders)
  ");
  $orderStmt->execute($patientIds);
  $orderCount = $orderStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
  echo "   Found $orderCount order(s) associated with these patients\n\n";

  // 3. Find and count related wound photos
  echo "4. Checking for related wound photos...\n";
  $photoStmt = $pdo->prepare("
    SELECT COUNT(*) as cnt
    FROM wound_photos
    WHERE patient_id IN ($placeholders)
  ");
  $photoStmt->execute($patientIds);
  $photoCount = $photoStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
  echo "   Found $photoCount wound photo(s) associated with these patients\n\n";

  if ($dryRun) {
    echo "=== DRY RUN SUMMARY ===\n";
    echo "Would delete:\n";
    echo "  - $patientCount patient record(s)\n";
    echo "  - $orderCount order record(s)\n";
    echo "  - $photoCount wound photo record(s)\n\n";
    echo "To actually perform deletion, visit this URL without ?dry_run=1\n";
    $pdo->rollBack();
    exit(0);
  }

  // 4. Delete related orders first (foreign key constraints)
  if ($orderCount > 0) {
    echo "5. Deleting $orderCount related order(s)...\n";
    $deleteOrders = $pdo->prepare("
      DELETE FROM orders
      WHERE patient_id IN ($placeholders)
    ");
    $deleteOrders->execute($patientIds);
    echo "   ✓ Orders deleted\n\n";
  } else {
    echo "5. No orders to delete\n\n";
  }

  // 5. Delete related wound photos
  if ($photoCount > 0) {
    echo "6. Deleting $photoCount related wound photo(s)...\n";
    $deletePhotos = $pdo->prepare("
      DELETE FROM wound_photos
      WHERE patient_id IN ($placeholders)
    ");
    $deletePhotos->execute($patientIds);
    echo "   ✓ Wound photos deleted\n\n";
  } else {
    echo "6. No wound photos to delete\n\n";
  }

  // 6. Delete the patients
  echo "7. Deleting $patientCount patient record(s)...\n";
  $deletePatients = $pdo->prepare("
    DELETE FROM patients
    WHERE created_at < ?
  ");
  $deletePatients->execute([$cutoffDate]);
  echo "   ✓ Patients deleted\n\n";

  // Commit transaction
  $pdo->commit();

  echo "=== CLEANUP COMPLETE ===\n\n";
  echo "Successfully deleted:\n";
  echo "  ✓ $patientCount patient(s)\n";
  echo "  ✓ $orderCount order(s)\n";
  echo "  ✓ $photoCount wound photo(s)\n\n";
  echo "All patients created before November 1, 2025 have been removed.\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
