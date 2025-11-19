<?php
/**
 * Debug OCR processing for John Smith's Medicare card
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/insurance-ocr.php';

echo "<h1>Insurance OCR Debug</h1>\n";

try {
    $insuranceOCR = new InsuranceOCR();

    echo "<h2>OCR Configuration</h2>\n";
    echo "<ul>\n";
    echo "<li>OCR Enabled: " . ($insuranceOCR->isEnabled() ? 'YES' : 'NO') . "</li>\n";
    echo "<li>INSURANCE_OCR_ENABLED env: " . (getenv('INSURANCE_OCR_ENABLED') ?: 'NOT SET') . "</li>\n";
    echo "<li>INSURANCE_OCR_PROVIDER env: " . (getenv('INSURANCE_OCR_PROVIDER') ?: 'NOT SET') . "</li>\n";
    echo "<li>ANTHROPIC_API_KEY env: " . (getenv('ANTHROPIC_API_KEY') ? 'SET (length: ' . strlen(getenv('ANTHROPIC_API_KEY')) . ')' : 'NOT SET') . "</li>\n";
    echo "</ul>\n";

    if (!$insuranceOCR->isEnabled()) {
        echo "<p style='color: red;'>⚠️ OCR is not enabled. Please check environment variables.</p>\n";
        exit;
    }

    // Find John Smith's insurance card
    $stmt = $pdo->query("
        SELECT id, first_name, last_name, ins_card_path
        FROM patients
        WHERE first_name = 'John' AND last_name = 'Smith'
        LIMIT 1
    ");

    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo "<p style='color: red;'>John Smith not found!</p>\n";
        exit;
    }

    echo "<h2>Patient Information</h2>\n";
    echo "<ul>\n";
    echo "<li>ID: " . htmlspecialchars($patient['id']) . "</li>\n";
    echo "<li>Name: " . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</li>\n";
    echo "<li>Insurance Card Path: " . htmlspecialchars($patient['ins_card_path'] ?? 'NONE') . "</li>\n";
    echo "</ul>\n";

    if (empty($patient['ins_card_path'])) {
        echo "<p style='color: red;'>No insurance card path found!</p>\n";
        exit;
    }

    // Try multiple file paths
    $possible_paths = [
        $_SERVER['DOCUMENT_ROOT'] . $patient['ins_card_path'],
        '/opt/render/project/src' . $patient['ins_card_path'],
        __DIR__ . '/..' . $patient['ins_card_path']
    ];

    echo "<h2>File Path Search</h2>\n";
    echo "<ul>\n";

    $imagePath = null;
    foreach ($possible_paths as $path) {
        $exists = file_exists($path);
        echo "<li>" . htmlspecialchars($path) . " - " . ($exists ? '<strong style="color: green;">EXISTS</strong>' : '<span style="color: red;">Not found</span>') . "</li>\n";
        if ($exists && !$imagePath) {
            $imagePath = $path;
        }
    }

    echo "</ul>\n";

    if (!$imagePath) {
        echo "<p style='color: red;'>⚠️ Insurance card file not found at any of the expected locations!</p>\n";

        // List actual contents of uploads/insurance directory
        echo "<h2>Files in /opt/render/project/src/uploads/insurance/</h2>\n";
        if (is_dir('/opt/render/project/src/uploads/insurance')) {
            $files = scandir('/opt/render/project/src/uploads/insurance');
            echo "<ul>\n";
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    echo "<li>" . htmlspecialchars($file) . "</li>\n";
                }
            }
            echo "</ul>\n";
        } else {
            echo "<p style='color: red;'>Directory does not exist!</p>\n";
        }

        exit;
    }

    echo "<p style='color: green;'><strong>✓ Found insurance card at:</strong> " . htmlspecialchars($imagePath) . "</p>\n";

    // Get file info
    $filesize = filesize($imagePath);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $imagePath);
    finfo_close($finfo);

    echo "<h2>File Information</h2>\n";
    echo "<ul>\n";
    echo "<li>Size: " . number_format($filesize) . " bytes (" . round($filesize / 1024, 2) . " KB)</li>\n";
    echo "<li>MIME Type: " . htmlspecialchars($mimeType) . "</li>\n";
    echo "<li>Readable: " . (is_readable($imagePath) ? 'YES' : 'NO') . "</li>\n";
    echo "</ul>\n";

    // Now try OCR
    echo "<h2>Processing with OCR...</h2>\n";
    echo "<p>This may take a few seconds...</p>\n";
    flush();

    $insuranceData = $insuranceOCR->processInsuranceCard($imagePath);

    if ($insuranceData) {
        echo "<p style='color: green; font-weight: bold;'>✓ OCR succeeded!</p>\n";

        echo "<h2>Extracted Data</h2>\n";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Field</th><th>Value</th></tr>\n";
        echo "<tr><td>Provider</td><td>" . htmlspecialchars($insuranceData['provider'] ?? '—') . "</td></tr>\n";
        echo "<tr><td>Member ID</td><td>" . htmlspecialchars($insuranceData['member_id'] ?? '—') . "</td></tr>\n";
        echo "<tr><td>Group ID</td><td>" . htmlspecialchars($insuranceData['group_id'] ?? '—') . "</td></tr>\n";
        echo "<tr><td>Payer Phone</td><td>" . htmlspecialchars($insuranceData['payer_phone'] ?? '—') . "</td></tr>\n";
        echo "<tr><td>Plan Type</td><td>" . htmlspecialchars($insuranceData['plan_type'] ?? '—') . "</td></tr>\n";
        echo "<tr><td>Confidence</td><td><strong>" . round($insuranceData['confidence'] * 100) . "%</strong></td></tr>\n";
        echo "</table>\n";

        echo "<h2>Raw Extracted Text</h2>\n";
        echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>" . htmlspecialchars($insuranceData['raw_text'] ?? 'N/A') . "</pre>\n";

        if ($insuranceData['confidence'] >= 0.5) {
            echo "<p style='color: green;'>✓ Confidence is above 50% threshold - would be saved to database</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠ Confidence is below 50% threshold - would NOT be saved to database</p>\n";
        }
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ OCR failed!</p>\n";
        echo "<p>Check the error logs for details. Common issues:</p>\n";
        echo "<ul>\n";
        echo "<li>ANTHROPIC_API_KEY not set or invalid</li>\n";
        echo "<li>API request failed (network, rate limit, etc.)</li>\n";
        echo "<li>Image format not supported</li>\n";
        echo "<li>File corrupted or not a valid image</li>\n";
        echo "</ul>\n";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}
