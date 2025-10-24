<?php
// /portal/add-wounds-data-column.php
// Run once via web browser: https://collagendirect.onrender.com/portal/add-wounds-data-column.php?key=change-me-in-production
declare(strict_types=1);

// Security: Only allow with secret key
$SECRET_KEY = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $SECRET_KEY) {
    http_response_code(403);
    die('Access denied. Provide ?key=SECRET in URL');
}

header('Content-Type: text/plain; charset=utf-8');

echo "Adding wounds_data JSONB column to orders table...\n\n";

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
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS wounds_data JSONB");
    echo "✓ Added wounds_data column\n\n";

    // Migrate existing single-wound data to wounds_data array
    echo "Migrating existing wound data...\n";
    $pdo->exec("
        UPDATE orders
        SET wounds_data = jsonb_build_array(
            jsonb_build_object(
                'location', wound_location,
                'laterality', wound_laterality,
                'length_cm', wound_length_cm,
                'width_cm', wound_width_cm,
                'depth_cm', wound_depth_cm,
                'type', wound_type,
                'stage', wound_stage,
                'icd10_primary', icd10_primary,
                'icd10_secondary', icd10_secondary,
                'notes', wound_notes
            )
        )
        WHERE wounds_data IS NULL
          AND (wound_location IS NOT NULL OR wound_length_cm IS NOT NULL)
    ");
    echo "✓ Migrated existing wound data to wounds_data array\n\n";

    // Verify
    $result = $pdo->query("
        SELECT column_name, data_type
        FROM information_schema.columns
        WHERE table_name = 'orders' AND column_name = 'wounds_data'
    ")->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo "Column details:\n";
        echo "  Name: {$result['column_name']}\n";
        echo "  Type: {$result['data_type']}\n\n";

        // Show sample
        $sample = $pdo->query("
            SELECT id, wounds_data
            FROM orders
            WHERE wounds_data IS NOT NULL
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if ($sample) {
            echo "Sample wounds_data:\n";
            echo json_encode(json_decode($sample['wounds_data']), JSON_PRETTY_PRINT) . "\n\n";
        }

        echo "✓ Migration completed successfully!\n";
    } else {
        echo "⚠ Column not found after creation. Please check manually.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
