<?php
// Quick script to upload test files for a patient
declare(strict_types=1);

require __DIR__ . '/api/db.php';

// Get patient ID from command line or use default
$patientId = $argv[1] ?? '37a48e443174cee3ee4e454d4c83bb04';

// Check patient exists
$stmt = $pdo->prepare("SELECT id, first_name, last_name, user_id FROM patients WHERE id = ?");
$stmt->execute([$patientId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    echo "❌ Patient not found: {$patientId}\n";
    exit(1);
}

echo "Found patient: {$patient['first_name']} {$patient['last_name']}\n\n";

// Create upload directories if they don't exist
$uploadRoot = __DIR__ . '/uploads';
$dirs = [
    'ids' => $uploadRoot . '/ids',
    'insurance' => $uploadRoot . '/insurance',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✓ Created directory: {$dir}\n";
    }
}

// Create test ID card file
$idCardContent = "DRIVER LICENSE\n\n";
$idCardContent .= "Name: {$patient['first_name']} {$patient['last_name']}\n";
$idCardContent .= "State: TX\n";
$idCardContent .= "License #: D12345678\n";
$idCardContent .= "DOB: 01/15/1980\n";
$idCardContent .= "\nThis is a test ID card for development purposes.\n";

$idCardFilename = 'test-id-' . date('Ymd-His') . '-' . substr($patientId, 0, 6) . '.txt';
$idCardPath = $dirs['ids'] . '/' . $idCardFilename;
file_put_contents($idCardPath, $idCardContent);

// Update patient with ID card path
$pdo->prepare("UPDATE patients SET id_card_path = ?, id_card_mime = ?, updated_at = NOW() WHERE id = ?")
    ->execute(['/uploads/ids/' . $idCardFilename, 'text/plain', $patientId]);

echo "✓ Created ID card: {$idCardPath}\n";

// Create test insurance card file
$insCardContent = "INSURANCE CARD\n\n";
$insCardContent .= "Member: {$patient['first_name']} {$patient['last_name']}\n";
$insCardContent .= "Insurance: Blue Cross Blue Shield\n";
$insCardContent .= "Member ID: ABC123456789\n";
$insCardContent .= "Group #: GRP9876\n";
$insCardContent .= "Plan: PPO Gold\n";
$insCardContent .= "\nThis is a test insurance card for development purposes.\n";

$insCardFilename = 'test-insurance-' . date('Ymd-His') . '-' . substr($patientId, 0, 6) . '.txt';
$insCardPath = $dirs['insurance'] . '/' . $insCardFilename;
file_put_contents($insCardPath, $insCardContent);

// Update patient with insurance card path
$pdo->prepare("UPDATE patients SET ins_card_path = ?, ins_card_mime = ?, updated_at = NOW() WHERE id = ?")
    ->execute(['/uploads/insurance/' . $insCardFilename, 'text/plain', $patientId]);

echo "✓ Created insurance card: {$insCardPath}\n";

// Verify patient now has all files
$stmt = $pdo->prepare("SELECT id_card_path, ins_card_path, aob_path FROM patients WHERE id = ?");
$stmt->execute([$patientId]);
$files = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\n";
echo "Patient File Status:\n";
echo "====================\n";
echo "ID Card:       " . ($files['id_card_path'] ? "✓ {$files['id_card_path']}" : "✗ Missing") . "\n";
echo "Insurance:     " . ($files['ins_card_path'] ? "✓ {$files['ins_card_path']}" : "✗ Missing") . "\n";
echo "AOB:           " . ($files['aob_path'] ? "✓ {$files['aob_path']}" : "✗ Missing") . "\n";

if ($files['id_card_path'] && $files['ins_card_path'] && $files['aob_path']) {
    echo "\n✅ Patient is ready for insurance orders!\n";
} else {
    echo "\n⚠️  Patient still missing some files\n";
}

echo "\nYou can now create an order for this patient.\n";
