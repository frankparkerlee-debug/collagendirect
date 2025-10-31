<?php
/**
 * Run Sales Agent Database Migration
 *
 * This script creates all the necessary tables for the Sales Outreach Agent
 */

require_once(__DIR__ . '/config.php');

echo "Starting Sales Agent database migration...\n\n";

// Read the SQL file
$sql = file_get_contents(__DIR__ . '/schema-postgresql.sql');

// Split into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
    }
);

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (empty($statement)) continue;

    try {
        $pdo->exec($statement);
        $success_count++;

        // Extract table/trigger name for better logging
        if (preg_match('/CREATE TABLE.*?(\w+)\s*\(/i', $statement, $matches)) {
            echo "✓ Created table: {$matches[1]}\n";
        } elseif (preg_match('/CREATE INDEX.*?(\w+)\s+ON/i', $statement, $matches)) {
            echo "✓ Created index: {$matches[1]}\n";
        } elseif (preg_match('/CREATE.*?TRIGGER\s+(\w+)/i', $statement, $matches)) {
            echo "✓ Created trigger: {$matches[1]}\n";
        } elseif (preg_match('/CREATE.*?FUNCTION\s+(\w+)/i', $statement, $matches)) {
            echo "✓ Created function: {$matches[1]}\n";
        } elseif (preg_match('/INSERT INTO\s+(\w+)/i', $statement, $matches)) {
            echo "✓ Inserted data into: {$matches[1]}\n";
        } else {
            echo "✓ Executed statement\n";
        }
    } catch (PDOException $e) {
        $error_count++;
        $error_msg = $e->getMessage();

        // Skip "already exists" errors
        if (strpos($error_msg, 'already exists') !== false) {
            echo "⚠ Already exists (skipping)\n";
            $error_count--;
        } else {
            echo "✗ Error: " . $error_msg . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
        }
    }
}

echo "\n========================================\n";
echo "Migration complete!\n";
echo "Successful: $success_count\n";
echo "Errors: $error_count\n";
echo "========================================\n\n";

// Verify tables were created
echo "Verifying tables...\n\n";

$tables = ['leads', 'outreach_campaigns', 'outreach_log', 'email_templates', 'sms_templates', 'call_scripts'];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✓ Table '$table' exists ($count rows)\n";
    } catch (PDOException $e) {
        echo "✗ Table '$table' NOT FOUND\n";
    }
}

echo "\nDone!\n";
?>
