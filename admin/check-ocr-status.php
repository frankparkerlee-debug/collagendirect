<?php
/**
 * Check Insurance OCR Status and Configuration
 */
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/insurance-ocr.php';
header('Content-Type: text/plain');

echo "=== INSURANCE OCR DIAGNOSTIC ===\n\n";

// Check environment variables
echo "1. ENVIRONMENT VARIABLES:\n";
echo "   INSURANCE_OCR_ENABLED: " . (getenv('INSURANCE_OCR_ENABLED') ?: 'NOT SET') . "\n";
echo "   INSURANCE_OCR_PROVIDER: " . (getenv('INSURANCE_OCR_PROVIDER') ?: 'NOT SET (defaults to anthropic)') . "\n";
echo "   ANTHROPIC_API_KEY: " . (getenv('ANTHROPIC_API_KEY') ? 'SET (length: ' . strlen(getenv('ANTHROPIC_API_KEY')) . ' chars)' : 'NOT SET') . "\n\n";

// Check OCR object
echo "2. OCR OBJECT STATUS:\n";
try {
    $insuranceOCR = new InsuranceOCR();
    echo "   InsuranceOCR instantiated: YES\n";
    echo "   isEnabled(): " . ($insuranceOCR->isEnabled() ? 'YES' : 'NO') . "\n\n";

    if (!$insuranceOCR->isEnabled()) {
        echo "   ⚠️  OCR IS DISABLED\n";
        echo "   To enable, set environment variable: INSURANCE_OCR_ENABLED=true\n\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Check database columns
echo "3. DATABASE SCHEMA:\n";
try {
    $stmt = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'patients'
        AND column_name IN (
            'insurance_provider',
            'insurance_member_id',
            'insurance_group_id',
            'insurance_payer_phone',
            'insurance_ocr_processed',
            'insurance_ocr_date',
            'insurance_ocr_data',
            'insurance_ocr_confidence'
        )
        ORDER BY column_name
    ");

    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredColumns = [
        'insurance_provider',
        'insurance_member_id',
        'insurance_group_id',
        'insurance_payer_phone',
        'insurance_ocr_processed',
        'insurance_ocr_date',
        'insurance_ocr_data',
        'insurance_ocr_confidence'
    ];

    foreach ($requiredColumns as $col) {
        $exists = in_array($col, $columns);
        echo "   $col: " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
    }
    echo "\n";

    if (count($columns) < count($requiredColumns)) {
        echo "   ⚠️  MISSING COLUMNS - Run migration: /admin/migrate-add-insurance-ocr-fields.php\n\n";
    }
} catch (Exception $e) {
    echo "   ERROR checking columns: " . $e->getMessage() . "\n\n";
}

// Check for patients with insurance cards
echo "4. PATIENTS WITH INSURANCE CARDS:\n";
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as total,
               COUNT(CASE WHEN ins_card_path IS NOT NULL AND ins_card_path != '' THEN 1 END) as with_card,
               COUNT(CASE WHEN insurance_ocr_processed = TRUE THEN 1 END) as ocr_processed
        FROM patients
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "   Total patients: " . $stats['total'] . "\n";
    echo "   With insurance card: " . $stats['with_card'] . "\n";
    echo "   OCR processed: " . ($stats['ocr_processed'] ?? 0) . "\n\n";

    // Show recent patients with insurance cards
    $stmt = $pdo->query("
        SELECT id, first_name, last_name, ins_card_path, insurance_ocr_processed, insurance_ocr_date
        FROM patients
        WHERE ins_card_path IS NOT NULL AND ins_card_path != ''
        ORDER BY created_at DESC
        LIMIT 5
    ");

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($patients)) {
        echo "   Recent patients with insurance cards:\n";
        foreach ($patients as $p) {
            $id = substr($p['id'], 0, 12);
            $name = $p['first_name'] . ' ' . $p['last_name'];
            $processed = $p['insurance_ocr_processed'] ? 'YES' : 'NO';
            $date = $p['insurance_ocr_date'] ?? 'never';
            echo "   - $name ($id...): OCR processed: $processed (Date: $date)\n";
        }
    } else {
        echo "   No patients with insurance cards found\n";
    }
    echo "\n";

} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Check file paths
echo "5. FILE SYSTEM PATHS:\n";
$testPaths = [
    '/opt/render/project/src/uploads/insurance',
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/insurance',
    __DIR__ . '/../uploads/insurance'
];

foreach ($testPaths as $path) {
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    echo "   $path\n";
    echo "      Exists: " . ($exists ? 'YES' : 'NO') . "\n";
    if ($exists) {
        echo "      Writable: " . ($writable ? 'YES' : 'NO') . "\n";
        $files = glob($path . '/*');
        echo "      Files: " . count($files) . "\n";
    }
}
echo "\n";

// Check recent error logs
echo "6. RECENT ERROR LOGS:\n";
$logFile = __DIR__ . '/../api/error.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $ocrLines = array_filter($lines, function($line) {
        return stripos($line, 'InsuranceOCR') !== false || stripos($line, 'insurance-ocr') !== false;
    });

    if (!empty($ocrLines)) {
        echo "   Recent OCR-related errors:\n";
        foreach (array_slice($ocrLines, -5) as $line) {
            echo "   " . trim($line) . "\n";
        }
    } else {
        echo "   No OCR-related errors found in logs\n";
    }
} else {
    echo "   Log file not found at: $logFile\n";
}
echo "\n";

// Summary
echo "=== SUMMARY ===\n";
if ($insuranceOCR && $insuranceOCR->isEnabled()) {
    echo "✓ OCR is ENABLED and ready to use\n";
    if (getenv('ANTHROPIC_API_KEY')) {
        echo "✓ Anthropic API key is configured\n";
    } else {
        echo "⚠️  Anthropic API key is NOT set (OCR will fail)\n";
    }
} else {
    echo "✗ OCR is DISABLED\n";
    echo "\nTo enable OCR:\n";
    echo "1. Set environment variable: INSURANCE_OCR_ENABLED=true\n";
    echo "2. Set environment variable: ANTHROPIC_API_KEY=your-api-key\n";
    echo "3. Optionally set: INSURANCE_OCR_PROVIDER=anthropic (default)\n";
    echo "\nIn Render dashboard:\n";
    echo "   Environment → Add Environment Variable\n";
}
