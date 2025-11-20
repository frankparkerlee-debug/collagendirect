<?php
header('Content-Type: text/plain');

// Check git commit on production
$gitCommit = trim(shell_exec('git rev-parse HEAD 2>&1') ?: 'Unable to determine');
$gitBranch = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>&1') ?: 'Unable to determine');
$gitShort = substr($gitCommit, 0, 7);

echo "=== DEPLOYMENT VERSION CHECK ===\n\n";
echo "Git Commit: $gitShort ($gitCommit)\n";
echo "Git Branch: $gitBranch\n\n";

// Check last commit message
$lastCommit = shell_exec('git log -1 --pretty=format:"%h %ai %s" 2>&1');
echo "Last Commit:\n$lastCommit\n\n";

// Check if critical files have been updated
echo "=== FILE MODIFICATION TIMES ===\n";
$files = [
  'api/portal/orders.create.php',
  'admin/api-order-detail.php',
  'admin/billing.php'
];

foreach ($files as $file) {
  $path = __DIR__ . '/../' . $file;
  if (file_exists($path)) {
    $mtime = filemtime($path);
    echo "$file: " . date('Y-m-d H:i:s', $mtime) . "\n";
  } else {
    echo "$file: NOT FOUND\n";
  }
}

echo "\n=== CHECK SPECIFIC CODE ===\n";

// Check if the detailed debug code is present in orders.create.php
$ordersFile = file_get_contents(__DIR__ . '/../api/portal/orders.create.php');
if (strpos($ordersFile, 'file_rx_note[error=') !== false) {
  echo "✓ Detailed debug logging: PRESENT\n";
} else {
  echo "✗ Detailed debug logging: NOT PRESENT (old version)\n";
}

// Check if multi-product fix is present
if (strpos($ordersFile, 'foreach ($all_order_ids as $oid)') !== false) {
  echo "✓ Multi-product order fix: PRESENT\n";
} else {
  echo "✗ Multi-product order fix: NOT PRESENT (old version)\n";
}

// Check if API endpoint includes rx_note_path
$apiFile = file_get_contents(__DIR__ . '/api-order-detail.php');
if (strpos($apiFile, 'o.rx_note_path') !== false) {
  echo "✓ API rx_note_path field: PRESENT\n";
} else {
  echo "✗ API rx_note_path field: NOT PRESENT (old version)\n";
}

// Check if billing has grouping
$billingFile = file_get_contents(__DIR__ . '/billing.php');
if (strpos($billingFile, 'WITH grouped_orders AS') !== false) {
  echo "✓ Billing grouping query: PRESENT\n";
} else {
  echo "✗ Billing grouping query: NOT PRESENT (old version)\n";
}

echo "\n";
?>
