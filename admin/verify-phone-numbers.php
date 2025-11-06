<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Phone Number Verification ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Check patient CD-20251029-F892
    $stmt = $pdo->prepare("
        SELECT id, mrn, first_name, last_name, phone, cell_phone
        FROM patients
        WHERE mrn = ?
    ");
    $stmt->execute(['CD-20251029-F892']);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        echo "Patient: {$patient['first_name']} {$patient['last_name']} ({$patient['mrn']})\n";
        echo "  Phone: {$patient['phone']}\n";
        echo "  Cell: {$patient['cell_phone']}\n\n";

        // Validate format
        $phoneValid = preg_match('/^\+1\d{10}$/', $patient['phone']);
        $cellValid = $patient['cell_phone'] ? preg_match('/^\+1\d{10}$/', $patient['cell_phone']) : true;

        echo "  Phone format: " . ($phoneValid ? "✓ Valid E.164" : "✗ Invalid") . "\n";
        echo "  Cell format: " . ($cellValid ? "✓ Valid E.164" : "✗ Invalid") . "\n\n";
    } else {
        echo "Patient CD-20251029-F892 not found\n\n";
    }

    // Summary of all phone numbers
    echo "Database Summary:\n";
    echo "----------------------------------------\n";
    $summary = $pdo->query("
        SELECT
            COUNT(*) as total_patients,
            COUNT(phone) as has_phone,
            COUNT(cell_phone) as has_cell,
            COUNT(CASE WHEN phone ~ '^\+1\d{10}$' THEN 1 END) as valid_phone,
            COUNT(CASE WHEN cell_phone ~ '^\+1\d{10}$' THEN 1 END) as valid_cell,
            COUNT(CASE WHEN phone LIKE '+1 +1%' OR cell_phone LIKE '+1 +1%' THEN 1 END) as malformed
        FROM patients
    ")->fetch(PDO::FETCH_ASSOC);

    echo "Total patients: {$summary['total_patients']}\n";
    echo "Patients with phone: {$summary['has_phone']}\n";
    echo "Patients with cell: {$summary['has_cell']}\n";
    echo "Valid E.164 phone numbers: {$summary['valid_phone']}\n";
    echo "Valid E.164 cell numbers: {$summary['valid_cell']}\n";
    echo "Malformed (double prefix): {$summary['malformed']}\n";

    echo "\n✓ Verification complete!\n";

} catch (Throwable $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Verification Complete ===\n";
