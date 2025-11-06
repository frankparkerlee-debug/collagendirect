<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../api/lib/twilio_sms.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Fixing Phone Numbers ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Find patients with malformed phone numbers (double +1, spaces, etc.)
    $stmt = $pdo->query("
        SELECT id, mrn, first_name, last_name, phone, cell_phone
        FROM patients
        WHERE phone LIKE '+1 +1%'
           OR cell_phone LIKE '+1 +1%'
           OR phone LIKE '+ %'
           OR cell_phone LIKE '+ %'
    ");

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($patients)) {
        echo "✓ No malformed phone numbers found!\n";
        exit;
    }

    echo "Found " . count($patients) . " patients with malformed phone numbers:\n\n";

    foreach ($patients as $patient) {
        echo "Patient: {$patient['first_name']} {$patient['last_name']} (MRN: {$patient['mrn']})\n";
        echo "  Current phone: {$patient['phone']}\n";
        echo "  Current cell: {$patient['cell_phone']}\n";

        // Fix phone
        $fixedPhone = null;
        if ($patient['phone']) {
            $fixedPhone = normalize_phone_number($patient['phone']);
            if ($fixedPhone) {
                echo "  → Fixed phone: {$fixedPhone}\n";
            }
        }

        // Fix cell_phone
        $fixedCell = null;
        if ($patient['cell_phone']) {
            $fixedCell = normalize_phone_number($patient['cell_phone']);
            if ($fixedCell) {
                echo "  → Fixed cell: {$fixedCell}\n";
            }
        }

        // Update database
        $updateStmt = $pdo->prepare("
            UPDATE patients
            SET phone = ?,
                cell_phone = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $updateStmt->execute([
            $fixedPhone ?: $patient['phone'],
            $fixedCell ?: $patient['cell_phone'],
            $patient['id']
        ]);

        echo "  ✓ Updated\n\n";
    }

    echo "\n✓ All phone numbers fixed!\n";

    // Show summary
    echo "\nSummary of patients table:\n";
    echo "----------------------------------------\n";
    $summary = $pdo->query("
        SELECT
            COUNT(*) as total,
            COUNT(CASE WHEN phone ~ '^\+1\d{10}$' THEN 1 END) as valid_phone,
            COUNT(CASE WHEN cell_phone ~ '^\+1\d{10}$' THEN 1 END) as valid_cell
        FROM patients
        WHERE phone IS NOT NULL OR cell_phone IS NOT NULL
    ")->fetch(PDO::FETCH_ASSOC);

    echo "Total patients: {$summary['total']}\n";
    echo "Valid phone numbers: {$summary['valid_phone']}\n";
    echo "Valid cell numbers: {$summary['valid_cell']}\n";

} catch (Throwable $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Cleanup Complete ===\n";
