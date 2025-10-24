<?php
// /portal/add-cell-phone-column.php
// Run once via web browser: https://collagendirect.onrender.com/portal/add-cell-phone-column.php?key=change-me-in-production
declare(strict_types=1);

// Security: Only allow with secret key
$SECRET_KEY = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $SECRET_KEY) {
    http_response_code(403);
    die('Access denied. Provide ?key=SECRET in URL');
}

header('Content-Type: text/plain; charset=utf-8');

echo "Adding cell_phone column to patients table...\n\n";

// Direct database connection
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'collagen_db';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '5432';

try {
    $pdo = new PDO(
        "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Add column
    $pdo->exec("ALTER TABLE patients ADD COLUMN IF NOT EXISTS cell_phone VARCHAR(20)");
    echo "✓ Added cell_phone column\n\n";

    // Verify
    $result = $pdo->query("
        SELECT column_name, data_type, character_maximum_length
        FROM information_schema.columns
        WHERE table_name = 'patients' AND column_name = 'cell_phone'
    ")->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo "Column details:\n";
        echo "  Name: {$result['column_name']}\n";
        echo "  Type: {$result['data_type']}\n";
        echo "  Max Length: " . ($result['character_maximum_length'] ?? 'N/A') . "\n\n";
        echo "✓ Migration completed successfully!\n";
    } else {
        echo "⚠ Column not found after creation. Please check manually.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
