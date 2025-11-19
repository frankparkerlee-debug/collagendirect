<?php
/**
 * Backfill patient insurance data from existing orders
 *
 * For patients without insurance data, copy it from their most recent order
 * This ensures old orders show insurance info in PDFs
 */

require_once __DIR__ . '/../api/db.php';

echo "<h1>Backfilling Patient Insurance Data</h1>\n";
echo "<p>Copying insurance information from orders to patient records...</p>\n";

try {
    // Find patients without insurance data who have orders with insurance data
    $stmt = $pdo->query("
        SELECT DISTINCT
            p.id as patient_id,
            p.first_name,
            p.last_name,
            o.insurer_name,
            o.member_id,
            o.group_id,
            o.payer_phone
        FROM patients p
        INNER JOIN orders o ON o.patient_id = p.id
        WHERE (p.insurance_provider IS NULL OR p.insurance_provider = '')
          AND o.insurer_name IS NOT NULL
          AND o.insurer_name != ''
        ORDER BY p.id, o.created_at DESC
    ");

    $patients_to_update = [];
    $seen_patients = [];

    // Group by patient, keeping only the first (most recent) order per patient
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $patient_id = $row['patient_id'];

        if (!isset($seen_patients[$patient_id])) {
            $patients_to_update[] = $row;
            $seen_patients[$patient_id] = true;
        }
    }

    echo "<p>Found <strong>" . count($patients_to_update) . "</strong> patients to update</p>\n";

    if (count($patients_to_update) === 0) {
        echo "<p>✓ No patients need updating. All patient records already have insurance data.</p>\n";
        exit;
    }

    echo "<h2>Updating Patient Records</h2>\n";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Patient</th><th>Insurance Provider</th><th>Member ID</th><th>Group ID</th><th>Status</th></tr>\n";

    $updated_count = 0;
    $failed_count = 0;

    foreach ($patients_to_update as $patient) {
        try {
            $pdo->prepare("
                UPDATE patients
                SET insurance_provider = ?,
                    insurance_member_id = ?,
                    insurance_group_id = ?,
                    insurance_payer_phone = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $patient['insurer_name'],
                $patient['member_id'],
                $patient['group_id'],
                $patient['payer_phone'],
                $patient['patient_id']
            ]);

            echo "<tr>";
            echo "<td>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($patient['insurer_name'] ?? '—') . "</td>";
            echo "<td>" . htmlspecialchars($patient['member_id'] ?? '—') . "</td>";
            echo "<td>" . htmlspecialchars($patient['group_id'] ?? '—') . "</td>";
            echo "<td style='color: green;'>✓ Updated</td>";
            echo "</tr>\n";

            $updated_count++;

        } catch (PDOException $e) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</td>";
            echo "<td colspan='3'>" . htmlspecialchars($e->getMessage()) . "</td>";
            echo "<td style='color: red;'>✗ Failed</td>";
            echo "</tr>\n";

            $failed_count++;
        }
    }

    echo "</table>\n";

    echo "<h2>Summary</h2>\n";
    echo "<ul>\n";
    echo "<li>Patients updated: <strong>$updated_count</strong></li>";
    echo "<li>Failed: <strong>$failed_count</strong></li>";
    echo "</ul>\n";

    if ($updated_count > 0) {
        echo "<p style='color: green; font-weight: bold;'>✓ Backfill completed successfully!</p>\n";
        echo "<p>All existing orders will now show insurance information in their PDFs.</p>\n";
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    exit(1);
}
