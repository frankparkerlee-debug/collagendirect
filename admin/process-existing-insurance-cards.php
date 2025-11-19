<?php
/**
 * Process existing insurance card images with OCR
 *
 * For patients who have insurance cards uploaded but no OCR data,
 * this script will retroactively process them with Claude OCR
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/insurance-ocr.php';

echo "<h1>Process Existing Insurance Cards with OCR</h1>\n";

$patient_id = $_GET['patient_id'] ?? null;

try {
    $insuranceOCR = new InsuranceOCR();

    // Show configuration status
    echo "<div style='background: #f0f0f0; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc;'>\n";
    echo "<strong>OCR Configuration:</strong><br>\n";
    echo "• OCR Enabled: " . ($insuranceOCR->isEnabled() ? '<span style="color: green;">YES</span>' : '<span style="color: red;">NO</span>') . "<br>\n";
    echo "• INSURANCE_OCR_ENABLED: " . (getenv('INSURANCE_OCR_ENABLED') ?: '<span style="color: red;">NOT SET</span>') . "<br>\n";
    echo "• INSURANCE_OCR_PROVIDER: " . (getenv('INSURANCE_OCR_PROVIDER') ?: '<span style="color: orange;">NOT SET (using default)</span>') . "<br>\n";
    echo "• ANTHROPIC_API_KEY: " . (getenv('ANTHROPIC_API_KEY') ? '<span style="color: green;">SET (length: ' . strlen(getenv('ANTHROPIC_API_KEY')) . ')</span>' : '<span style="color: red;">NOT SET</span>') . "<br>\n";
    echo "</div>\n";

    if (!$insuranceOCR->isEnabled()) {
        echo "<p style='color: red;'>⚠️ OCR is not enabled!</p>\n";
        echo "<p>Please set the following environment variables:</p>\n";
        echo "<ul>\n";
        echo "<li><code>INSURANCE_OCR_ENABLED=true</code></li>\n";
        echo "<li><code>INSURANCE_OCR_PROVIDER=anthropic</code></li>\n";
        echo "<li><code>ANTHROPIC_API_KEY=your_api_key</code></li>\n";
        echo "</ul>\n";
        exit;
    }

    // Find patients with insurance cards but no OCR processing
    if ($patient_id) {
        // Process specific patient
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, ins_card_path,
                   insurance_ocr_processed, insurance_provider
            FROM patients
            WHERE id = ?
              AND ins_card_path IS NOT NULL
              AND ins_card_path != ''
        ");
        $stmt->execute([$patient_id]);
    } else {
        // Process all unprocessed patients
        $stmt = $pdo->query("
            SELECT id, first_name, last_name, ins_card_path,
                   insurance_ocr_processed, insurance_provider
            FROM patients
            WHERE ins_card_path IS NOT NULL
              AND ins_card_path != ''
              AND (insurance_ocr_processed IS NULL OR insurance_ocr_processed = FALSE)
            ORDER BY created_at DESC
            LIMIT 50
        ");
    }

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($patients) === 0) {
        echo "<p style='color: green;'>✓ No patients need OCR processing!</p>\n";
        if (!$patient_id) {
            echo "<p>All patients with insurance cards have already been processed.</p>\n";
        } else {
            echo "<p>Patient not found or already processed.</p>\n";
        }
        exit;
    }

    echo "<p>Found <strong>" . count($patients) . "</strong> patient(s) with insurance cards to process</p>\n";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr>";
    echo "<th>Patient</th>";
    echo "<th>Insurance Card Path</th>";
    echo "<th>Current Insurance</th>";
    echo "<th>OCR Result</th>";
    echo "<th>Status</th>";
    echo "</tr>\n";

    $processed_count = 0;
    $failed_count = 0;
    $skipped_count = 0;

    foreach ($patients as $patient) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</td>";
        echo "<td style='font-size: 0.8em;'>" . htmlspecialchars($patient['ins_card_path']) . "</td>";
        echo "<td>" . htmlspecialchars($patient['insurance_provider'] ?? '—') . "</td>";

        // Try multiple path locations
        $possible_paths = [
            $_SERVER['DOCUMENT_ROOT'] . $patient['ins_card_path'],
            '/opt/render/project/src' . $patient['ins_card_path'],
            __DIR__ . '/..' . $patient['ins_card_path']
        ];

        $imagePath = null;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $imagePath = $path;
                break;
            }
        }

        if (!$imagePath) {
            echo "<td style='color: red;'>File not found at any path</td>";
            echo "<td style='color: red;'>✗ Skipped</td>";
            $skipped_count++;
            echo "</tr>\n";
            continue;
        }

        // Show which path was found
        echo "<td style='font-size: 0.75em; color: #666;'>Found at: " . htmlspecialchars($imagePath) . "<br>";
        echo "Size: " . number_format(filesize($imagePath)) . " bytes</td>";

        // Process with OCR
        try {
            error_log("[process-existing] Processing: $imagePath (size: " . filesize($imagePath) . " bytes)");
            $insuranceData = $insuranceOCR->processInsuranceCard($imagePath);

            if (!$insuranceData) {
                error_log("[process-existing] OCR returned null for: $imagePath");
                echo "<td style='color: red;'>OCR failed - check error logs</td>";
                echo "<td style='color: red;'>✗ Failed</td>";
                $failed_count++;
                echo "</tr>\n";
                flush();
                continue;
            }

            // Check confidence
            if ($insuranceData['confidence'] < 0.5) {
                echo "<td style='color: orange;'>Low confidence: " . round($insuranceData['confidence'] * 100) . "%</td>";
                echo "<td style='color: orange;'>⚠ Low confidence</td>";
                $skipped_count++;
                echo "</tr>\n";
                continue;
            }

            // Save to patient
            $success = $insuranceOCR->saveToPatient($pdo, $patient['id'], $insuranceData);

            if ($success) {
                echo "<td style='font-size: 0.85em;'>";
                echo "Provider: " . htmlspecialchars($insuranceData['provider'] ?? '—') . "<br>";
                echo "Member ID: " . htmlspecialchars($insuranceData['member_id'] ?? '—') . "<br>";
                echo "Group ID: " . htmlspecialchars($insuranceData['group_id'] ?? '—') . "<br>";
                echo "Confidence: " . round($insuranceData['confidence'] * 100) . "%";
                echo "</td>";
                echo "<td style='color: green;'>✓ Processed</td>";
                $processed_count++;
            } else {
                echo "<td style='color: red;'>Save failed</td>";
                echo "<td style='color: red;'>✗ Failed</td>";
                $failed_count++;
            }

        } catch (Exception $e) {
            error_log("[process-existing] Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            echo "<td style='color: red;'>Exception: " . htmlspecialchars($e->getMessage()) . "</td>";
            echo "<td style='color: red;'>✗ Error</td>";
            $failed_count++;
        }

        echo "</tr>\n";
        flush();
    }

    echo "</table>\n";

    echo "<h2>Summary</h2>\n";
    echo "<ul>\n";
    echo "<li>Successfully processed: <strong style='color: green;'>$processed_count</strong></li>\n";
    echo "<li>Failed: <strong style='color: red;'>$failed_count</strong></li>\n";
    echo "<li>Skipped (low confidence or file not found): <strong style='color: orange;'>$skipped_count</strong></li>\n";
    echo "</ul>\n";

    if ($processed_count > 0) {
        echo "<p style='color: green; font-weight: bold;'>✓ OCR processing completed!</p>\n";
        echo "<p>Insurance information has been extracted and saved to patient records.</p>\n";
        echo "<p>All orders for these patients will now show insurance information in their PDFs.</p>\n";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
