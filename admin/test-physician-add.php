<?php
// Test script to debug physician add functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../api/db.php';

echo "=== Testing Physician Add Functionality ===\n\n";

// Check practice_physicians table structure
echo "1. Checking practice_physicians table columns:\n";
$ppCols = $pdo->query("
  SELECT column_name FROM information_schema.columns
  WHERE table_name = 'practice_physicians'
  ORDER BY ordinal_position
")->fetchAll(PDO::FETCH_COLUMN);

echo "   Columns found: " . implode(', ', $ppCols) . "\n\n";

// Test column detection logic
echo "2. Testing column detection:\n";
$adminCol = in_array('practice_admin_id', $ppCols) ? 'practice_admin_id' : 'practice_manager_id';
$physicianIdCol = in_array('physician_id', $ppCols) ? 'physician_id' : 'physician_npi';
$firstNameCol = in_array('first_name', $ppCols) ? 'first_name' : 'physician_first_name';
$lastNameCol = in_array('last_name', $ppCols) ? 'last_name' : 'physician_last_name';
$emailCol = in_array('email', $ppCols) ? 'email' : 'physician_email';
$licenseCol = in_array('license', $ppCols) ? 'license' : 'physician_license';
$licenseStateCol = in_array('license_state', $ppCols) ? 'license_state' : 'physician_license_state';
$licenseExpiryCol = in_array('license_expiry', $ppCols) ? 'license_expiry' : 'physician_license_expiry';

echo "   Admin column: $adminCol (exists: " . (in_array($adminCol, $ppCols) ? 'YES' : 'NO') . ")\n";
echo "   Physician ID column: $physicianIdCol (exists: " . (in_array($physicianIdCol, $ppCols) ? 'YES' : 'NO') . ")\n";
echo "   First name column: $firstNameCol (exists: " . (in_array($firstNameCol, $ppCols) ? 'YES' : 'NO') . ")\n";
echo "   Last name column: $lastNameCol (exists: " . (in_array($lastNameCol, $ppCols) ? 'YES' : 'NO') . ")\n";
echo "   Email column: $emailCol (exists: " . (in_array($emailCol, $ppCols) ? 'YES' : 'NO') . ")\n";
echo "   License column: $licenseCol (exists: " . (in_array($licenseCol, $ppCols) ? 'YES' : 'NO') . ")\n";
echo "   License state column: $licenseStateCol (exists: " . (in_array($licenseStateCol, $ppCols) ? 'YES' : 'NO') . ")\n";
echo "   License expiry column: $licenseExpiryCol (exists: " . (in_array($licenseExpiryCol, $ppCols) ? 'YES' : 'NO') . ")\n\n";

// Build test INSERT
echo "3. Building test INSERT statement:\n";
$columns = [$adminCol, $physicianIdCol, $firstNameCol, $lastNameCol, $emailCol];
$placeholders = ['?', '?', '?', '?', '?'];

if (in_array($licenseCol, $ppCols)) {
  $columns[] = $licenseCol;
  $placeholders[] = '?';
}
if (in_array($licenseStateCol, $ppCols)) {
  $columns[] = $licenseStateCol;
  $placeholders[] = '?';
}
if (in_array($licenseExpiryCol, $ppCols)) {
  $columns[] = $licenseExpiryCol;
  $placeholders[] = '?';
}

$sql = "INSERT INTO practice_physicians (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
echo "   SQL: $sql\n\n";

echo "=== Test Complete ===\n";
