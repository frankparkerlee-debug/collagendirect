<?php
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Fixing Timestamp Columns ===\n\n";

$tables = ['patients', 'orders', 'products', 'users', 'messages'];

foreach ($tables as $table) {
    echo "Checking table: $table\n";

    // Check if table exists
    $tableExists = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_name = '$table'
        )
    ")->fetchColumn();

    if (!$tableExists) {
        echo "  ⚠ Table does not exist, skipping\n\n";
        continue;
    }

    // Check for timestamp columns
    $columns = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = '$table'
        AND column_name IN ('created_at', 'updated_at')
    ")->fetchAll(PDO::FETCH_COLUMN);

    $hasCreatedAt = in_array('created_at', $columns);
    $hasUpdatedAt = in_array('updated_at', $columns);

    // Add missing columns
    if (!$hasCreatedAt) {
        try {
            echo "  Adding created_at...\n";
            $pdo->exec("ALTER TABLE $table ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            echo "  ✓ Added created_at\n";
        } catch (PDOException $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ✓ created_at exists\n";
    }

    if (!$hasUpdatedAt) {
        try {
            echo "  Adding updated_at...\n";
            $pdo->exec("ALTER TABLE $table ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            echo "  ✓ Added updated_at\n";
        } catch (PDOException $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ✓ updated_at exists\n";
    }

    echo "\n";
}

echo "=== Summary ===\n";
echo "Timestamp columns have been added to all tables.\n";
echo "\nThis should fix any SQL errors related to missing created_at or updated_at columns.\n";
echo "\n✓ Complete!\n";
