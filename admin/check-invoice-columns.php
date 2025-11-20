<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== CHECKING INVOICE COLUMNS ===\n\n";

$invoiceColumns = [
  'invoice_number',
  'invoice_date',
  'due_date',
  'payment_terms',
  'amount_due',
  'amount_paid',
  'balance_due'
];

echo "Checking which columns exist in the 'orders' table:\n\n";

foreach ($invoiceColumns as $col) {
  $exists = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = '$col'
  ")->fetchColumn();
  
  echo "  - $col: " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

echo "\n✓ Check complete!\n";
