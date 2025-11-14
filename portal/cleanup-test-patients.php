<?php
/**
 * Cleanup: Delete test patients older than Randy Dittmar
 * Also deletes associated orders and any related data
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST PATIENT CLEANUP ===\n\n";

$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

if ($dryRun) {
  echo "DRY RUN MODE - No data will be deleted\n";
  echo "Remove ?dry_run=1 from URL to actually delete\n\n";
}

try {
  global $pdo;

  // Start transaction
  $pdo->beginTransaction();

  // 1. Find Randy Dittmar's creation date
  echo "1. Finding Randy Dittmar's creation date...\n";
  $stmt = $pdo->prepare("
    SELECT id, first_name, last_name, mrn, created_at
    FROM patients
    WHERE first_name ILIKE '%Randy%' AND last_name ILIKE '%Dittmar%'
    ORDER BY created_at DESC
    LIMIT 1
  ");
  $stmt->execute();
  $randyDittmar = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$randyDittmar) {
    echo "   ❌ Randy Dittmar not found in database\n";
    $pdo->rollBack();
    exit(1);
  }

  $cutoffDate = $randyDittmar['created_at'];
  echo "   Found: {$randyDittmar['first_name']} {$randyDittmar['last_name']} (MRN: {$randyDittmar['mrn']})\n";
  echo "   Created: $cutoffDate\n";
  echo "   Will delete all patients created BEFORE this date\n\n";

  // 2. Find patients created before Randy Dittmar
  echo "2. Finding patients created before Randy Dittmar...\n";
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
    echo "✓ No patients found before Randy Dittmar. Nothing to delete.\n";
    $pdo->rollBack();
    exit(0);
  }

  // Show all patients that will be deleted
  echo "3. Patients to be deleted:\n";
  foreach ($patientsToDelete as $p) {
    echo "   - MRN: " . ($p['mrn'] ?? 'N/A') . " | ";
    echo ($p['first_name'] ?? '') . " " . ($p['last_name'] ?? '') . " | ";
    echo "Created: " . date('Y-m-d H:i:s', strtotime($p['created_at'])) . "\n";
  }
  echo "\n";

  // Get patient IDs
  $patientIds = array_column($patientsToDelete, 'id');

  // 3. Find and count related orders
  echo "4. Checking for related orders...\n";
  $placeholders = str_repeat('?,', count($patientIds) - 1) . '?';
  $orderStmt = $pdo->prepare("
    SELECT COUNT(*) as cnt
    FROM orders
    WHERE patient_id IN ($placeholders)
  ");
  $orderStmt->execute($patientIds);
  $orderCount = $orderStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
  echo "   Found $orderCount order(s) associated with these patients\n\n";

  // 4. Find and count related wound photos
  echo "5. Checking for related wound photos...\n";
  $photoStmt = $pdo->prepare("
    SELECT COUNT(*) as cnt
    FROM wound_photos
    WHERE patient_id IN ($placeholders)
  ");
  $photoStmt->execute($patientIds);
  $photoCount = $photoStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
  echo "   Found $photoCount wound photo(s) associated with these patients\n\n";

  // 5. Find and count related billable encounters
  echo "6. Checking for related billable encounters...\n";
  $encounterStmt = $pdo->prepare("
    SELECT COUNT(*) as cnt
    FROM billable_encounters
    WHERE patient_id IN ($placeholders)
  ");
  $encounterStmt->execute($patientIds);
  $encounterCount = $encounterStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
  echo "   Found $encounterCount billable encounter(s) associated with these patients\n\n";

  if ($dryRun) {
    echo "=== DRY RUN SUMMARY ===\n";
    echo "Would delete:\n";
    echo "  - $patientCount patient record(s)\n";
    echo "  - $orderCount order record(s)\n";
    echo "  - $encounterCount billable encounter record(s)\n";
    echo "  - $photoCount wound photo record(s)\n\n";
    echo "To actually perform deletion, visit this URL without ?dry_run=1\n";
    $pdo->rollBack();
    exit(0);
  }

  // Delete related orders first (foreign key constraints)
  if ($orderCount > 0) {
    echo "7. Deleting $orderCount related order(s)...\n";
    $deleteOrders = $pdo->prepare("
      DELETE FROM orders
      WHERE patient_id IN ($placeholders)
    ");
    $deleteOrders->execute($patientIds);
    echo "   ✓ Orders deleted\n\n";
  } else {
    echo "7. No orders to delete\n\n";
  }

  // Delete billable encounters that reference wound_photos from these patients
  echo "8. Deleting billable encounters that reference related wound photos...\n";
  $deleteEncountersByPhoto = $pdo->prepare("
    DELETE FROM billable_encounters
    WHERE wound_photo_id IN (
      SELECT id FROM wound_photos WHERE patient_id IN ($placeholders)
    )
  ");
  $deleteEncountersByPhoto->execute($patientIds);
  $deletedByPhoto = $deleteEncountersByPhoto->rowCount();
  echo "   ✓ Deleted $deletedByPhoto billable encounter(s) by wound photo reference\n\n";

  // Delete remaining billable encounters by patient_id
  if ($encounterCount > 0) {
    echo "9. Deleting remaining billable encounter(s) by patient ID...\n";
    $deleteEncounters = $pdo->prepare("
      DELETE FROM billable_encounters
      WHERE patient_id IN ($placeholders)
    ");
    $deleteEncounters->execute($patientIds);
    $deletedByPatient = $deleteEncounters->rowCount();
    echo "   ✓ Deleted $deletedByPatient billable encounter(s) by patient ID\n\n";
  } else {
    echo "9. No remaining billable encounters to delete\n\n";
  }

  // Delete related wound photos (now safe, no FK references)
  if ($photoCount > 0) {
    echo "10. Deleting $photoCount related wound photo(s)...\n";
    $deletePhotos = $pdo->prepare("
      DELETE FROM wound_photos
      WHERE patient_id IN ($placeholders)
    ");
    $deletePhotos->execute($patientIds);
    echo "   ✓ Wound photos deleted\n\n";
  } else {
    echo "10. No wound photos to delete\n\n";
  }

  // Delete the patients
  echo "11. Deleting $patientCount patient record(s)...\n";
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
  echo "  ✓ $encounterCount billable encounter(s)\n";
  echo "  ✓ $photoCount wound photo(s)\n\n";
  echo "All patients created before Randy Dittmar (" . date('Y-m-d H:i:s', strtotime($cutoffDate)) . ") have been removed.\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
