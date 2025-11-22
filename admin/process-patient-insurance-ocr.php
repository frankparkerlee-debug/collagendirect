<?php
/**
 * Manually process insurance OCR for a specific patient
 * Usage: /admin/process-patient-insurance-ocr.php?patient_id=xxxxx
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

$patientId = $_GET['patient_id'] ?? null;

if (!$patientId) {
  echo "<h1>Error</h1><p>Please provide a patient_id parameter</p>";
  exit;
}

// Get patient insurance card path
$stmt = $pdo->prepare("SELECT id, first_name, last_name, ins_card_path, ins_card_mime FROM patients WHERE id = ?");
$stmt->execute([$patientId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
  echo "<h1>Error</h1><p>Patient not found: $patientId</p>";
  exit;
}

if (empty($patient['ins_card_path'])) {
  echo "<h1>Error</h1><p>Patient {$patient['first_name']} {$patient['last_name']} has no insurance card uploaded</p>";
  exit;
}

echo "<h1>Processing Insurance OCR</h1>";
echo "<p><strong>Patient:</strong> {$patient['first_name']} {$patient['last_name']}</p>";
echo "<p><strong>Insurance Card Path:</strong> {$patient['ins_card_path']}</p>";

// Try different path resolutions
$possiblePaths = [
  '/opt/render/project/src' . $patient['ins_card_path'],
  __DIR__ . '/..' . $patient['ins_card_path'],
  '/var/www/html' . $patient['ins_card_path']
];

$actualPath = null;
foreach ($possiblePaths as $path) {
  if (file_exists($path)) {
    $actualPath = $path;
    echo "<p style='color: green;'><strong>✓ Found file at:</strong> $path</p>";
    break;
  }
}

if (!$actualPath) {
  echo "<p style='color: red;'><strong>✗ File not found</strong></p>";
  echo "<p>Tried paths:</p><ul>";
  foreach ($possiblePaths as $path) {
    echo "<li>$path</li>";
  }
  echo "</ul>";
  exit;
}

// Process with OCR
require_once __DIR__ . '/../api/insurance-ocr.php';
$ocr = new InsuranceOCR();

if (!$ocr->isEnabled()) {
  echo "<p style='color: red;'><strong>✗ OCR is disabled</strong></p>";
  echo "<p>Set INSURANCE_OCR_ENABLED=1 environment variable to enable</p>";
  exit;
}

echo "<h2>Processing with OCR...</h2>";
$insuranceData = $ocr->processInsuranceCard($actualPath);

if (!$insuranceData) {
  echo "<p style='color: red;'><strong>✗ OCR failed or returned no data</strong></p>";
  exit;
}

echo "<p style='color: green;'><strong>✓ OCR succeeded!</strong></p>";
echo "<h2>Extracted Data</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Value</th></tr>";
echo "<tr><td>Provider</td><td>" . htmlspecialchars($insuranceData['provider'] ?? '—') . "</td></tr>";
echo "<tr><td>Member ID</td><td>" . htmlspecialchars($insuranceData['member_id'] ?? '—') . "</td></tr>";
echo "<tr><td>Group ID</td><td>" . htmlspecialchars($insuranceData['group_id'] ?? '—') . "</td></tr>";
echo "<tr><td>Payer Phone</td><td>" . htmlspecialchars($insuranceData['payer_phone'] ?? '—') . "</td></tr>";
echo "<tr><td>Plan Type</td><td>" . htmlspecialchars($insuranceData['plan_type'] ?? '—') . "</td></tr>";
echo "<tr><td>Confidence</td><td><strong>" . round(($insuranceData['confidence'] ?? 0) * 100) . "%</strong></td></tr>";
echo "</table>";

// Save to database
echo "<h2>Saving to Database...</h2>";
$saved = $ocr->saveToPatient($pdo, $patientId, $insuranceData);

if ($saved) {
  echo "<p style='color: green;'><strong>✓ Successfully saved to database!</strong></p>";

  // Show updated patient data
  $stmt = $pdo->prepare("SELECT insurance_provider, insurance_member_id, insurance_group_id, insurance_payer_phone FROM patients WHERE id = ?");
  $stmt->execute([$patientId]);
  $updated = $stmt->fetch(PDO::FETCH_ASSOC);

  echo "<h2>Updated Patient Record</h2>";
  echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
  echo "<tr><th>Field</th><th>Value</th></tr>";
  echo "<tr><td>Insurance Provider</td><td>" . htmlspecialchars($updated['insurance_provider'] ?? '—') . "</td></tr>";
  echo "<tr><td>Member ID</td><td>" . htmlspecialchars($updated['insurance_member_id'] ?? '—') . "</td></tr>";
  echo "<tr><td>Group ID</td><td>" . htmlspecialchars($updated['insurance_group_id'] ?? '—') . "</td></tr>";
  echo "<tr><td>Payer Phone</td><td>" . htmlspecialchars($updated['insurance_payer_phone'] ?? '—') . "</td></tr>";
  echo "</table>";

  echo "<p><a href='/portal/index.php?page=patient-detail&id=$patientId'>View Patient Profile</a></p>";
} else {
  echo "<p style='color: red;'><strong>✗ Failed to save to database</strong></p>";
}
