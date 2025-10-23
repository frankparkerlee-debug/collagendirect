<?php
// /admin/panic.php — TEMPORARY DIAGNOSTIC (delete when done)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__.'/db.php';

echo "<pre>";
echo "PHP_VERSION: ".PHP_VERSION."\n";
echo "Connected to DB ✓\n";

// Show current MySQL user and schema
$u = $pdo->query("SELECT CURRENT_USER() AS u, DATABASE() AS d")->fetch();
echo "MYSQL_USER: {$u['u']}  DB: {$u['d']}\n";

// List 'orders' columns (handles permission issues)
try {
  $cols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN, 0);
  echo "orders columns: ".implode(', ', $cols)."\n";
} catch (Throwable $e) {
  echo "SHOW COLUMNS FAILED: ".$e->getMessage()."\n";
}

// Count basic tables
foreach (['orders','patients','users'] as $t) {
  try {
    $c = $pdo->query("SELECT COUNT(*) c FROM `$t`")->fetch()['c'] ?? 'n/a';
    echo "count($t): $c\n";
  } catch (Throwable $e) {
    echo "count($t) FAILED: ".$e->getMessage()."\n";
  }
}
echo "</pre>";
