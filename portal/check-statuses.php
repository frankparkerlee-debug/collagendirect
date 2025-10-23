<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

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

    $stmt = $pdo->query("SELECT DISTINCT status FROM orders ORDER BY status");

    echo "Existing order statuses:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . ($row['status'] ?? 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
