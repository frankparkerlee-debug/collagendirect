<?php
// Diagnostic script to check patients table columns
// Access via: https://collagendirect.onrender.com/check-columns.php?token=temp-setup-token-2024

$token = $_GET['token'] ?? '';
if ($token !== 'temp-setup-token-2024') {
    http_response_code(403);
    die('Access denied');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== Patients Table Column Check ===\n\n";

require __DIR__ . '/api/db.php';

echo "Connected to database\n";
echo "  Host: " . getenv('DB_HOST') . "\n";
echo "  Database: " . getenv('DB_NAME') . "\n\n";

try {
    // Get all columns in patients table
    $result = $pdo->query("
        SELECT column_name, data_type, character_maximum_length, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'patients'
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "Columns in patients table:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-30s %-20s %-10s %-10s\n", "Column Name", "Data Type", "Max Length", "Nullable");
    echo str_repeat("-", 80) . "\n";

    foreach ($result as $col) {
        printf("%-30s %-20s %-10s %-10s\n",
            $col['column_name'],
            $col['data_type'],
            $col['character_maximum_length'] ?? 'N/A',
            $col['is_nullable']
        );
    }

    echo "\n" . str_repeat("-", 80) . "\n";
    echo "Total columns: " . count($result) . "\n\n";

    // Check for specific columns we expect
    $expected = ['sex', 'insurance_provider', 'insurance_member_id', 'insurance_group_id', 'insurance_payer_phone', 'aob_ip'];
    $found = array_column($result, 'column_name');

    echo "Checking for required columns:\n";
    foreach ($expected as $col) {
        $exists = in_array($col, $found);
        echo "  " . ($exists ? '✓' : '✗') . " $col\n";
    }

} catch (Exception $e) {
    die("\n✗ Error: " . $e->getMessage() . "\n");
}
