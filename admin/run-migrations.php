#!/usr/bin/env php
<?php
/**
 * Run PreAuth Agent Database Migrations
 *
 * This script runs all preauth database migrations using the existing PDO connection
 */

// Ensure this script is run from command line only
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line\n");
}

echo "========================================\n";
echo "PreAuth Agent Database Migrations\n";
echo "========================================\n\n";

// Load database connection (CLI version without headers/sessions)
require_once __DIR__ . '/db-cli.php';

echo "✓ Database connected successfully\n";
echo "  Database: {$DB_NAME}\n";
echo "  Host: {$DB_HOST}:{$DB_PORT}\n\n";

// Migration files to run
$migrations = [
    '001_create_preauth_requests_table.sql',
    '002_create_preauth_rules_table.sql',
    '003_create_preauth_audit_log_table.sql',
    '004_create_eligibility_cache_table.sql'
];

$migrationsDir = __DIR__ . '/migrations';
$failed = false;

foreach ($migrations as $migrationFile) {
    $filePath = $migrationsDir . '/' . $migrationFile;

    if (!file_exists($filePath)) {
        echo "✗ Migration file not found: {$migrationFile}\n";
        $failed = true;
        continue;
    }

    echo "Running migration: {$migrationFile}\n";

    // Read SQL file
    $sql = file_get_contents($filePath);

    if ($sql === false) {
        echo "✗ Failed to read migration file: {$migrationFile}\n";
        $failed = true;
        continue;
    }

    try {
        // Execute the SQL
        // Note: We can't use prepared statements for DDL, so we execute directly
        $result = $pdo->exec($sql);

        echo "✓ {$migrationFile} completed successfully\n\n";

    } catch (PDOException $e) {
        // Check if error is "already exists" which is okay
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "✓ {$migrationFile} - tables already exist (skipped)\n\n";
        } else {
            echo "✗ {$migrationFile} failed:\n";
            echo "  Error: " . $e->getMessage() . "\n\n";
            $failed = true;
        }
    }
}

echo "========================================\n";

if (!$failed) {
    echo "✓ All migrations completed successfully!\n\n";

    // Verify tables were created
    echo "Verifying tables...\n";
    try {
        $stmt = $pdo->query("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name LIKE 'preauth%'
            OR table_name = 'eligibility_cache'
            ORDER BY table_name
        ");

        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo "Created tables:\n";
        foreach ($tables as $table) {
            echo "  ✓ {$table}\n";
        }

        // Check preauth_rules data
        echo "\nChecking preauth_rules data...\n";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM preauth_rules");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "  ✓ {$count} carrier rules loaded\n";

    } catch (PDOException $e) {
        echo "Warning: Could not verify tables: " . $e->getMessage() . "\n";
    }

    echo "\n";
    echo "Next steps:\n";
    echo "1. Review carrier rules: SELECT * FROM preauth_rules;\n";
    echo "2. Set up cron jobs (see PREAUTH_AGENT_README.md)\n";
    echo "3. Configure carrier API credentials\n";
    echo "4. Test with: php admin/cron/preauth-agent.php --task=all\n";
    echo "5. Access admin dashboard: /admin/preauth-dashboard.php\n";

    exit(0);
} else {
    echo "✗ Some migrations failed\n";
    echo "Please review the errors above\n";
    exit(1);
}
