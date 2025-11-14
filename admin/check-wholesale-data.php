<?php
// Diagnostic script to check wholesale orders data
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
if (function_exists('require_admin')) require_admin();

header('Content-Type: text/plain');

echo "=== Wholesale Orders Diagnostic ===\n\n";

// Check if billed_by column exists
echo "1. Checking if billed_by column exists...\n";
try {
  $checkCol = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='orders' AND column_name='billed_by'");
  $hasBilledBy = $checkCol->rowCount() > 0;
  echo "   ✓ billed_by column exists: " . ($hasBilledBy ? "YES" : "NO") . "\n\n";
} catch (Throwable $e) {
  echo "   ✗ Error checking column: " . $e->getMessage() . "\n\n";
  exit;
}

if (!$hasBilledBy) {
  echo "   ERROR: billed_by column does not exist!\n";
  echo "   You need to run the migration to add this column.\n";
  exit;
}

// Check total orders
echo "2. Total orders in system...\n";
try {
  $totalStmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE (review_status IS NULL OR review_status != 'draft')");
  $total = $totalStmt->fetch(PDO::FETCH_ASSOC);
  echo "   Total orders (non-draft): " . $total['count'] . "\n\n";
} catch (Throwable $e) {
  echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Check wholesale orders
echo "3. Wholesale orders (billed_by = 'practice_dme')...\n";
try {
  $wholesaleStmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM orders
    WHERE billed_by = 'practice_dme'
      AND (review_status IS NULL OR review_status != 'draft')
  ");
  $wholesale = $wholesaleStmt->fetch(PDO::FETCH_ASSOC);
  echo "   Wholesale orders: " . $wholesale['count'] . "\n\n";
} catch (Throwable $e) {
  echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Check all billed_by values
echo "4. All distinct billed_by values...\n";
try {
  $billedByStmt = $pdo->query("
    SELECT billed_by, COUNT(*) as count
    FROM orders
    WHERE (review_status IS NULL OR review_status != 'draft')
    GROUP BY billed_by
    ORDER BY count DESC
  ");
  $billedByResults = $billedByStmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($billedByResults)) {
    echo "   No orders found with billed_by values\n";
  } else {
    foreach ($billedByResults as $row) {
      $value = $row['billed_by'] ?? 'NULL';
      echo "   - billed_by = '{$value}': {$row['count']} orders\n";
    }
  }
  echo "\n";
} catch (Throwable $e) {
  echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Show sample wholesale orders
echo "5. Sample wholesale orders (first 5)...\n";
try {
  $sampleStmt = $pdo->query("
    SELECT
      o.id,
      o.created_at,
      o.billed_by,
      o.status,
      o.product,
      u.practice_name,
      p.first_name || ' ' || p.last_name as patient_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN patients p ON o.patient_id = p.id
    WHERE o.billed_by = 'practice_dme'
      AND (o.review_status IS NULL OR o.review_status != 'draft')
    ORDER BY o.created_at DESC
    LIMIT 5
  ");
  $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($samples)) {
    echo "   No wholesale orders found!\n";
  } else {
    foreach ($samples as $order) {
      echo "   Order #{$order['id']}\n";
      echo "     - Created: {$order['created_at']}\n";
      echo "     - Status: {$order['status']}\n";
      echo "     - Practice: {$order['practice_name']}\n";
      echo "     - Patient: {$order['patient_name']}\n";
      echo "     - Product: {$order['product']}\n";
      echo "     - Billed By: {$order['billed_by']}\n";
      echo "\n";
    }
  }
} catch (Throwable $e) {
  echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Check for NULL billed_by orders that might need to be wholesale
echo "6. Orders with NULL billed_by (potentially missing wholesale designation)...\n";
try {
  $nullStmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM orders
    WHERE billed_by IS NULL
      AND (review_status IS NULL OR review_status != 'draft')
  ");
  $nullCount = $nullStmt->fetch(PDO::FETCH_ASSOC);
  echo "   Orders with NULL billed_by: " . $nullCount['count'] . "\n";

  if ($nullCount['count'] > 0) {
    echo "   Note: These orders may need to be marked as wholesale or referral\n";
  }
  echo "\n";
} catch (Throwable $e) {
  echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

echo "=== Diagnostic Complete ===\n";
