<?php
/**
 * Copy schema from old database to new database
 * This script connects to both databases and copies table structures
 */

declare(strict_types=1);

header('Content-Type: text/plain');

echo "=== Database Schema Copy Tool (v2) ===\n\n";

// OLD DATABASE credentials
$oldDb = [
    'host' => 'dpg-d3t3i83e5dus73flkang-a.oregon-postgres.render.com',
    'port' => '5432',
    'dbname' => 'collagen_db',
    'user' => 'collagen_db_user',
    'password' => 'collagen_db_user'
];

// NEW DATABASE credentials (from environment variables)
$newDb = [
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT') ?: '5432',
    'dbname' => getenv('DB_NAME'),
    'user' => getenv('DB_USER'),
    'password' => getenv('DB_PASS')
];

echo "Connecting to OLD database...\n";
try {
    $oldPdo = new PDO(
        "pgsql:host={$oldDb['host']};port={$oldDb['port']};dbname={$oldDb['dbname']};sslmode=require",
        $oldDb['user'],
        $oldDb['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Connected to old database\n\n";
} catch (PDOException $e) {
    die("✗ Failed to connect to old database: " . $e->getMessage() . "\n");
}

echo "Connecting to NEW database...\n";
try {
    $newPdo = new PDO(
        "pgsql:host={$newDb['host']};port={$newDb['port']};dbname={$newDb['dbname']}",
        $newDb['user'],
        $newDb['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Connected to new database\n\n";
} catch (PDOException $e) {
    die("✗ Failed to connect to new database: " . $e->getMessage() . "\n");
}

// Get all tables from old database
echo "Fetching table list from old database...\n";
$tables = $oldPdo->query("
    SELECT tablename
    FROM pg_tables
    WHERE schemaname = 'public'
    ORDER BY tablename
")->fetchAll(PDO::FETCH_COLUMN);

echo "Found " . count($tables) . " tables\n\n";

// For each table, get its CREATE TABLE statement
$createdTables = 0;
$skippedTables = 0;
$errors = [];

foreach ($tables as $table) {
    echo "Processing table: $table\n";

    try {
        // Check if table already exists in new database
        $exists = $newPdo->query("
            SELECT EXISTS (
                SELECT FROM pg_tables
                WHERE schemaname = 'public'
                AND tablename = '$table'
            )
        ")->fetchColumn();

        if ($exists) {
            echo "  - Already exists, skipping\n";
            $skippedTables++;
            continue;
        }

        // Get table structure using pg_dump-like query
        // This gets the CREATE TABLE statement from PostgreSQL's system catalogs
        $createStmt = $oldPdo->query("
            SELECT
                'CREATE TABLE ' || quote_ident(table_name) || ' (' ||
                string_agg(
                    quote_ident(column_name) || ' ' ||
                    data_type ||
                    CASE
                        WHEN character_maximum_length IS NOT NULL
                        THEN '(' || character_maximum_length || ')'
                        ELSE ''
                    END ||
                    CASE
                        WHEN column_default IS NOT NULL
                        THEN ' DEFAULT ' || column_default
                        ELSE ''
                    END ||
                    CASE
                        WHEN is_nullable = 'NO'
                        THEN ' NOT NULL'
                        ELSE ''
                    END,
                    ', '
                ) || ')'
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = '$table'
            GROUP BY table_name
        ")->fetchColumn();

        if ($createStmt) {
            // Execute CREATE TABLE on new database
            $newPdo->exec($createStmt);
            echo "  ✓ Created table\n";
            $createdTables++;
        } else {
            echo "  ✗ Could not generate CREATE statement\n";
            $errors[] = $table;
        }

    } catch (PDOException $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        $errors[] = $table . ": " . $e->getMessage();
    }
}

echo "\n=== Summary ===\n";
echo "Tables created: $createdTables\n";
echo "Tables skipped (already exist): $skippedTables\n";
echo "Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrors encountered:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\nNote: This basic copy may not include all constraints, indexes, and foreign keys.\n";
echo "For a complete schema copy, use pg_dump as described in the migration script.\n";
