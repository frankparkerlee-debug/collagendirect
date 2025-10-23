<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "PHP: ".PHP_VERSION."<br>";
require __DIR__.'/db.php';
echo "DB OK<br>";
echo "orders columns: ";
$cols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN, 0);
echo implode(', ', $cols);
