<?php
declare(strict_types=1);

echo "============================================\n";
echo "Running Compliance Workflow Migration\n";
echo "============================================\n\n";

// Direct database connection for CLI
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'collagen_db';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '5432';

try {
    $pdo = new PDO(
        "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};options='--client_encoding=UTF8'",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "✓ Connected to database\n\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    // Read the migration file
    $sql = file_get_contents(__DIR__ . '/migrations/compliance-workflow.sql');

    if ($sql === false) {
        throw new Exception("Failed to read migration file");
    }

    echo "Executing migration...\n\n";

    // Execute the migration
    $pdo->exec($sql);

    echo "✓ Migration completed successfully!\n\n";

    // Verify the changes
    echo "Verifying schema updates...\n\n";

    // Check users table
    $stmt = $pdo->query("
        SELECT column_name, data_type, column_default
        FROM information_schema.columns
        WHERE table_name = 'users'
        AND column_name IN ('role', 'has_dme_license')
        ORDER BY column_name
    ");

    echo "Users table new columns:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['column_name']} ({$row['data_type']})\n";
    }
    echo "\n";

    // Check orders table
    $stmt = $pdo->query("
        SELECT column_name, data_type
        FROM information_schema.columns
        WHERE table_name = 'orders'
        AND column_name IN (
            'delivery_location', 'tracking_code', 'carrier',
            'payment_method', 'cash_price', 'terminated_at',
            'reviewed_at', 'is_complete', 'missing_fields'
        )
        ORDER BY column_name
    ");

    echo "Orders table new columns:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['column_name']} ({$row['data_type']})\n";
    }
    echo "\n";

    // Check new tables
    $stmt = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_name IN ('order_status_history', 'order_alerts')
        ORDER BY table_name
    ");

    echo "New tables created:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['table_name']}\n";
    }
    echo "\n";

    // Check function exists
    $stmt = $pdo->query("
        SELECT routine_name
        FROM information_schema.routines
        WHERE routine_schema = 'public'
        AND routine_name = 'check_order_completeness'
    ");

    if ($stmt->fetch()) {
        echo "✓ check_order_completeness() function created\n";
    }

    // Check trigger exists
    $stmt = $pdo->query("
        SELECT trigger_name
        FROM information_schema.triggers
        WHERE trigger_name = 'order_status_change_trigger'
    ");

    if ($stmt->fetch()) {
        echo "✓ order_status_change_trigger created\n";
    }

    echo "\n============================================\n";
    echo "Migration completed successfully!\n";
    echo "============================================\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
