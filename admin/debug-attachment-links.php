<?php
// Debug script to investigate attachment link issues
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "===================================\n";
echo "ATTACHMENT LINKS DEBUG\n";
echo "===================================\n\n";

// Function to check if file exists
function check_file_exists($path, $label) {
    echo "\n$label:\n";
    echo "  DB Path: " . ($path ?: 'NULL') . "\n";

    if (!$path) {
        echo "  Status: No path in database\n";
        return;
    }

    // Try different path resolutions
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');

    // Option 1: /public/uploads/...
    if (strpos($path, '/public/uploads/') === 0) {
        $abs1 = $docRoot . $path;
        echo "  Absolute (public): $abs1\n";
        echo "  Exists: " . (file_exists($abs1) ? 'YES' : 'NO') . "\n";
    }

    // Option 2: /uploads/...
    if (strpos($path, '/uploads/') === 0) {
        $abs2 = $docRoot . $path;
        echo "  Absolute (no public): $abs2\n";
        echo "  Exists: " . (file_exists($abs2) ? 'YES' : 'NO') . "\n";

        $abs3 = $docRoot . '/public' . $path;
        echo "  Absolute (add public): $abs3\n";
        echo "  Exists: " . (file_exists($abs3) ? 'YES' : 'NO') . "\n";
    }

    // Option 3: Relative path
    if (strpos($path, '/') !== 0) {
        $abs4 = $docRoot . '/uploads/' . $path;
        echo "  Absolute (relative): $abs4\n";
        echo "  Exists: " . (file_exists($abs4) ? 'YES' : 'NO') . "\n";
    }

    // Check what file.dl.php would resolve to
    $rel = $path;
    if (strpos($rel, '/public/uploads/') === 0) {
        $relLocal = substr($rel, strlen('/public'));
    } elseif (strpos($rel, '/uploads/') === 0) {
        $relLocal = $rel;
    } else {
        $relLocal = '/uploads/' . basename($rel);
    }

    $docRootCheck = realpath(__DIR__ . '/..');
    $absResolved = realpath($docRootCheck . $relLocal);
    $uploadsRoot = realpath($docRootCheck . '/uploads');

    echo "  file.dl.php would resolve to: " . ($absResolved ?: 'FAILED') . "\n";
    echo "  Uploads root: $uploadsRoot\n";

    if ($absResolved) {
        echo "  Is inside uploads: " . (strncmp($absResolved, $uploadsRoot, strlen($uploadsRoot)) === 0 ? 'YES' : 'NO') . "\n";
        echo "  Is file: " . (is_file($absResolved) ? 'YES' : 'NO') . "\n";
    }
}

// Get sample patients with attachments
echo "Querying patients with attachments...\n\n";

try {
    $stmt = $pdo->query("
        SELECT id, first_name, last_name,
               aob_path AS note_path,
               id_card_path,
               ins_card_path
        FROM patients
        WHERE aob_path IS NOT NULL
           OR id_card_path IS NOT NULL
           OR ins_card_path IS NOT NULL
        LIMIT 5
    ");
    $patients = $stmt->fetchAll();

    echo "Found " . count($patients) . " patients with attachments\n";
    echo str_repeat("=", 50) . "\n\n";

    foreach ($patients as $p) {
        echo "Patient ID {$p['id']}: {$p['first_name']} {$p['last_name']}\n";
        echo str_repeat("-", 50) . "\n";

        check_file_exists($p['note_path'], 'NOTES');
        check_file_exists($p['id_card_path'], 'ID CARD');
        check_file_exists($p['ins_card_path'], 'INSURANCE CARD');

        echo "\n" . str_repeat("=", 50) . "\n\n";
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Check orders with attachments too
echo "\n\nQuerying orders with attachments...\n\n";

try {
    $stmt = $pdo->query("
        SELECT o.id, o.patient_id, o.tracking_number,
               p.first_name, p.last_name,
               p.aob_path AS note_path,
               p.id_card_path,
               p.ins_card_path
        FROM orders o
        LEFT JOIN patients p ON p.id = o.patient_id
        WHERE p.aob_path IS NOT NULL
           OR p.id_card_path IS NOT NULL
           OR p.ins_card_path IS NOT NULL
        LIMIT 3
    ");
    $orders = $stmt->fetchAll();

    echo "Found " . count($orders) . " orders with patient attachments\n";
    echo str_repeat("=", 50) . "\n\n";

    foreach ($orders as $o) {
        echo "Order ID {$o['id']} - Patient: {$o['first_name']} {$o['last_name']}\n";
        echo str_repeat("-", 50) . "\n";

        check_file_exists($o['note_path'], 'NOTES');
        check_file_exists($o['id_card_path'], 'ID CARD');
        check_file_exists($o['ins_card_path'], 'INSURANCE CARD');

        echo "\n" . str_repeat("=", 50) . "\n\n";
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Check actual uploads directory structure
echo "\n\nChecking uploads directory structure...\n\n";

$docRoot = realpath(__DIR__ . '/..');
echo "Document root: $docRoot\n";

$uploadsDirs = [
    '/uploads',
    '/public/uploads',
    '/uploads/notes',
    '/uploads/ids',
    '/uploads/insurance',
    '/public/uploads/notes',
    '/public/uploads/ids',
    '/public/uploads/insurance'
];

foreach ($uploadsDirs as $dir) {
    $abs = realpath($docRoot . $dir);
    if ($abs && is_dir($abs)) {
        echo "  ✅ $dir exists: $abs\n";
        $files = glob($abs . '/*');
        if ($files) {
            echo "     Contains " . count($files) . " files\n";
            // Show first 3 files
            foreach (array_slice($files, 0, 3) as $file) {
                echo "     - " . basename($file) . "\n";
            }
        } else {
            echo "     (empty)\n";
        }
    } else {
        echo "  ❌ $dir does not exist\n";
    }
}

echo "\n===================================\n";
echo "DEBUG COMPLETE\n";
echo "===================================\n";
