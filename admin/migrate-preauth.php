<?php
/**
 * PreAuth Agent Migration Script (Web-accessible for Render deployment)
 *
 * Access via: https://collagendirect.health/admin/migrate-preauth.php?secret=YOUR_SECRET
 */

// Security: Require secret token
$required_secret = getenv('MIGRATION_SECRET') ?: 'preauth_migration_2024';
$provided_secret = $_GET['secret'] ?? '';

if ($provided_secret !== $required_secret) {
    http_response_code(403);
    die('Forbidden: Invalid or missing secret');
}

// Load database connection (CLI version to avoid session/header issues)
require_once __DIR__ . '/db-cli.php';

// Set content type
header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "PreAuth Agent Database Migrations\n";
echo "========================================\n\n";

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
$results = [];

foreach ($migrations as $migrationFile) {
    $filePath = $migrationsDir . '/' . $migrationFile;

    if (!file_exists($filePath)) {
        echo "✗ Migration file not found: {$migrationFile}\n";
        $results[] = ['file' => $migrationFile, 'status' => 'not_found'];
        $failed = true;
        continue;
    }

    echo "Running migration: {$migrationFile}\n";

    // Read SQL file
    $sql = file_get_contents($filePath);

    if ($sql === false) {
        echo "✗ Failed to read migration file: {$migrationFile}\n";
        $results[] = ['file' => $migrationFile, 'status' => 'read_error'];
        $failed = true;
        continue;
    }

    try {
        // Execute the SQL
        $result = $pdo->exec($sql);

        echo "✓ {$migrationFile} completed successfully\n\n";
        $results[] = ['file' => $migrationFile, 'status' => 'success'];

    } catch (PDOException $e) {
        // Check if error is "already exists" which is okay
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "✓ {$migrationFile} - tables already exist (skipped)\n\n";
            $results[] = ['file' => $migrationFile, 'status' => 'already_exists'];
        } else {
            echo "✗ {$migrationFile} failed:\n";
            echo "  Error: " . $e->getMessage() . "\n\n";
            $results[] = ['file' => $migrationFile, 'status' => 'error', 'error' => $e->getMessage()];
            $failed = true;
        }
    }

    // Flush output so progress shows in real-time
    flush();
    if (ob_get_level() > 0) {
        ob_flush();
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
            AND (table_name LIKE 'preauth%' OR table_name = 'eligibility_cache')
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

        // Show sample rules
        echo "\nSample carrier rules:\n";
        $stmt = $pdo->query("SELECT carrier_name, hcpcs_code, requires_preauth FROM preauth_rules LIMIT 5");
        $sampleRules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sampleRules as $rule) {
            echo "  - {$rule['carrier_name']} / {$rule['hcpcs_code']}: " . ($rule['requires_preauth'] ? 'YES' : 'NO') . "\n";
        }

    } catch (PDOException $e) {
        echo "Warning: Could not verify tables: " . $e->getMessage() . "\n";
    }

    echo "\n";
    echo "========================================\n";
    echo "✅ MIGRATION COMPLETE\n";
    echo "========================================\n\n";
    echo "Next steps:\n";
    echo "1. Review carrier rules in database\n";
    echo "2. Set up cron jobs (see PREAUTH_AGENT_README.md)\n";
    echo "3. Configure carrier API credentials in environment\n";
    echo "4. Test with sample order\n";
    echo "5. Access admin dashboard: /admin/preauth-dashboard.php\n\n";

    // Log success
    error_log("PreAuth migrations completed successfully");

} else {
    echo "✗ Some migrations failed\n";
    echo "Please review the errors above\n\n";

    // Log failure
    error_log("PreAuth migrations failed: " . json_encode($results));
}

echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
