<?php
/**
 * Debug script to test billing export and capture full error details
 * Access via: https://collagendirect.health/admin/debug-billing-export.php
 */

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Log errors to output
ini_set('log_errors', 1);
ini_set('error_log', 'php://stdout');

header('Content-Type: text/plain; charset=utf-8');

echo "=== Billing Export Debug ===\n\n";

try {
    require_once __DIR__ . '/../api/db.php';
    echo "✓ Database connection established\n\n";

    // Check if billable_encounters table exists
    echo "Step 1: Checking billable_encounters table...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM billable_encounters");
    $count = $stmt->fetch();
    echo "  Found {$count['cnt']} billable encounters\n\n";

    // Check if patients table has required columns
    echo "Step 2: Checking patients table columns...\n";
    $stmt = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'patients'
        ORDER BY ordinal_position
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  Patients table columns:\n";
    foreach ($columns as $col) {
        echo "    - $col\n";
    }

    $requiredCols = ['sex', 'insurance_company', 'insurance_id', 'group_number'];
    $missingCols = array_diff($requiredCols, $columns);
    if (empty($missingCols)) {
        echo "  ✓ All required billing columns exist\n\n";
    } else {
        echo "  ✗ Missing columns: " . implode(', ', $missingCols) . "\n\n";
    }

    // Try to fetch some sample data
    echo "Step 3: Testing export query...\n";
    $month = date('Y-m');
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));

    $sql = "
        SELECT
          e.encounter_date,
          e.id as encounter_id,
          p.id as patient_id,
          p.first_name,
          p.last_name,
          p.mrn,
          p.dob,
          p.sex,
          p.phone,
          p.email,
          p.address,
          p.city,
          p.state,
          p.zip,
          p.insurance_company,
          p.insurance_id,
          p.group_number,
          e.cpt_code,
          e.modifier,
          e.charge_amount,
          e.assessment,
          e.clinical_note,
          u.first_name as provider_first_name,
          u.last_name as provider_last_name,
          u.npi as provider_npi,
          u.credential_type as provider_credential
        FROM billable_encounters e
        JOIN patients p ON p.id = e.patient_id
        LEFT JOIN users u ON u.id = e.physician_id
        WHERE e.encounter_date >= ? AND e.encounter_date <= ?
          AND e.exported = FALSE
        ORDER BY e.encounter_date DESC
        LIMIT 5
    ";

    echo "  Query date range: $startDate to $endDate\n";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startDate, $endDate . ' 23:59:59']);
    $encounters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "  Found " . count($encounters) . " unexported encounters\n\n";

    if (count($encounters) > 0) {
        echo "Step 4: Sample encounter data:\n";
        $sample = $encounters[0];
        echo "  Encounter ID: {$sample['encounter_id']}\n";
        echo "  Patient: {$sample['first_name']} {$sample['last_name']}\n";
        echo "  Date: {$sample['encounter_date']}\n";
        echo "  CPT Code: {$sample['cpt_code']}\n";
        echo "  Charge: {$sample['charge_amount']}\n";
        echo "  Provider: {$sample['provider_first_name']} {$sample['provider_last_name']}\n";
        echo "  Provider NPI: " . ($sample['provider_npi'] ?: 'NULL') . "\n";
        echo "  Assessment: {$sample['assessment']}\n\n";

        // Test helper functions
        echo "Step 5: Testing helper functions...\n";

        // Test getDiagnosisCodes
        echo "  Testing getDiagnosisCodes()...\n";
        try {
            require_once __DIR__ . '/../portal/index.php';
        } catch (Exception $e) {
            // Portal index.php might redirect, so we need to define the function here
            if (!function_exists('getDiagnosisCodes')) {
                function getDiagnosisCodes(string $assessment, string $clinicalNote): array {
                    $note = strtolower($clinicalNote);
                    $primary = 'L97.929';

                    if (strpos($note, 'diabetic') !== false) {
                        if (strpos($note, 'foot') !== false) {
                            $primary = 'E11.621';
                        } else {
                            $primary = 'E11.622';
                        }
                    } else if (strpos($note, 'pressure') !== false) {
                        $primary = 'L89.90';
                    }

                    $secondary = null;
                    if (($assessment === 'concern' || $assessment === 'urgent') &&
                        strpos($note, 'infection') !== false) {
                        $secondary = 'L03.90';
                    }

                    return ['primary' => $primary, 'secondary' => $secondary];
                }
            }

            if (!function_exists('formatPhone')) {
                function formatPhone(?string $phone): string {
                    if (empty($phone)) return '';
                    $phone = preg_replace('/[^0-9]/', '', $phone);
                    if (strlen($phone) === 10) {
                        return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
                    }
                    return $phone;
                }
            }
        }

        $diagCodes = getDiagnosisCodes($sample['assessment'], $sample['clinical_note'] ?? '');
        echo "    Primary: {$diagCodes['primary']}\n";
        echo "    Secondary: " . ($diagCodes['secondary'] ?: 'NULL') . "\n";

        // Test formatPhone
        echo "  Testing formatPhone()...\n";
        $formattedPhone = formatPhone($sample['phone']);
        echo "    Raw: {$sample['phone']}\n";
        echo "    Formatted: $formattedPhone\n\n";

        echo "Step 6: Simulating CSV generation...\n";
        ob_start();
        $testOutput = fopen('php://temp', 'w');

        // Test CSV header
        fputcsv($testOutput, [
            'Service Date', 'Patient Last Name', 'Patient First Name', 'Patient DOB',
            'Patient Sex', 'MRN', 'Patient Phone', 'Patient Email'
        ]);

        // Test CSV row
        fputcsv($testOutput, [
            date('m/d/Y', strtotime($sample['encounter_date'])),
            $sample['last_name'],
            $sample['first_name'],
            date('m/d/Y', strtotime($sample['dob'])),
            strtoupper($sample['sex'] ?? 'U'),
            $sample['mrn'] ?: 'TEMP-' . $sample['patient_id'],
            formatPhone($sample['phone']),
            $sample['email'] ?: ''
        ]);

        rewind($testOutput);
        $csvContent = stream_get_contents($testOutput);
        fclose($testOutput);
        ob_end_clean();

        echo "  CSV Sample:\n";
        echo str_replace("\n", "\n  ", trim($csvContent)) . "\n\n";

        echo "✓ All tests passed!\n\n";
    } else {
        echo "⚠️  No unexported encounters found for current month\n";
        echo "  This may be why the export appears to fail\n\n";
    }

    echo "=== Debug Complete ===\n\n";
    echo "If you still see errors when exporting, please copy the full error message.\n";

} catch (Throwable $e) {
    echo "\n✗ ERROR CAUGHT:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}
