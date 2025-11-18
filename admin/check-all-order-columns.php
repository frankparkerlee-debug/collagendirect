<?php
/**
 * Check All Order Columns - Complete Diagnostic
 * Lists every column in orders table and verifies all INSERT columns exist
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Order Table Columns Diagnostic ===\n\n";

// Get all columns from orders table
echo "Step 1: Fetching all columns from orders table...\n\n";
$stmt = $pdo->query("
    SELECT column_name, data_type, character_maximum_length, is_nullable
    FROM information_schema.columns
    WHERE table_name = 'orders'
    ORDER BY ordinal_position
");

$existing_columns = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existing_columns[] = $row['column_name'];
    $length = $row['character_maximum_length'] ? "({$row['character_maximum_length']})" : '';
    $nullable = $row['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
    echo sprintf("  %-35s %-20s %s\n", $row['column_name'], $row['data_type'] . $length, $nullable);
}

echo "\nTotal columns: " . count($existing_columns) . "\n\n";

// Now check which columns from the INSERT statement exist
echo "Step 2: Checking INSERT statement columns...\n\n";

$insert_columns = [
    'id',
    'patient_id',
    'user_id',
    'product',
    'product_id',
    'product_price',
    'cpt',
    'status',
    'frequency',
    'delivery_mode',
    'shipments_remaining',
    'created_at',
    'updated_at',
    'insurer_name',
    'member_id',
    'group_id',
    'payer_phone',
    'prior_auth',
    'payment_type',
    'wound_location',
    'wound_laterality',
    'wound_notes',
    'exudate_level',
    'wounds_data',
    'last_eval_date',
    'start_date',
    'qty_per_change',
    'duration_days',
    'additional_instructions',
    'secondary_dressing',
    'notes_text',
    'shipping_name',
    'shipping_phone',
    'shipping_address',
    'shipping_city',
    'shipping_state',
    'shipping_zip',
    'rx_note_path',
    'rx_note_mime',
    'ins_card_path',
    'ins_card_mime',
    'id_card_path',
    'id_card_mime',
    'e_sign_user_id',
    'e_sign_name',
    'e_sign_title',
    'e_sign_at',
    'e_sign_ip',
    'review_status'
];

$missing = [];
foreach ($insert_columns as $col) {
    if (in_array($col, $existing_columns)) {
        echo "  ✓ $col\n";
    } else {
        echo "  ✗ MISSING: $col\n";
        $missing[] = $col;
    }
}

echo "\n";

if (count($missing) > 0) {
    echo "=== MISSING COLUMNS ===\n";
    echo "The following columns are in INSERT but not in table:\n";
    foreach ($missing as $col) {
        echo "  - $col\n";
    }
} else {
    echo "=== All INSERT columns exist in table ===\n";
}

echo "\n=== Diagnostic Complete ===\n";
