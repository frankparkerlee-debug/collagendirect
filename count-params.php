<?php
// Detailed parameter counting script

echo "=== SQL COLUMN COUNT ===\n\n";

$columns = [
    'id', 'patient_id', 'user_id', 'product', 'product_id', 'product_price',
    'status', 'shipments_remaining', 'delivery_mode', 'payment_type',
    'wound_location', 'wound_laterality', 'wound_notes',
    'shipping_name', 'shipping_phone', 'shipping_address', 'shipping_city',
    'shipping_state', 'shipping_zip',
    'sign_name', 'sign_title', 'signed_at', 'created_at', 'updated_at',
    'icd10_primary', 'icd10_secondary', 'wound_length_cm', 'wound_width_cm',
    'wound_depth_cm',
    'wound_type', 'wound_stage', 'last_eval_date', 'start_date',
    'frequency_per_week', 'qty_per_change', 'duration_days', 'refills_allowed',
    'additional_instructions',
    'cpt'
];

echo "Total columns: " . count($columns) . "\n\n";

foreach ($columns as $i => $col) {
    echo ($i + 1) . ". $col\n";
}

echo "\n=== SQL PLACEHOLDERS ===\n\n";

$sql = "VALUES (?,?,?,?,?,?,?,?,?,?,
                ?,?,?,
                ?,?,?,?,?,?,
                ?,?,NOW(),NOW(),NOW(),
                ?,?,?,?,?,?,
                ?,?,?,?,?,?,?,?,?,
                ?)";

// Count ? manually
preg_match_all('/\?/', $sql, $matches);
$placeholders = count($matches[0]);

echo "Total placeholders (?): $placeholders\n";
echo "Total NOW() calls: 3\n";
echo "Total parameters needed: " . ($placeholders + 3) . "\n\n";

if (($placeholders + 3) == count($columns)) {
    echo "✅ Columns match SQL structure!\n\n";
} else {
    echo "❌ MISMATCH!\n";
    echo "Columns: " . count($columns) . "\n";
    echo "Placeholders + NOW(): " . ($placeholders + 3) . "\n\n";
}

echo "=== EXECUTE ARRAY VALUES ===\n\n";

$execute_values = [
    '$oid', '$pid', '$userId', '$prod[\'name\']', '$prod[\'id\']',
    '$prod[\'price_admin\']', '\'submitted\'', '0', '$delivery_mode', '$payment_type',
    '($_POST[\'wound_location\']??null)', '($_POST[\'wound_laterality\']??null)',
    '($_POST[\'wound_notes\']??null)',
    '(string)$ship_name', '(string)$ship_phone', '(string)$ship_addr',
    '(string)$ship_city', '(string)$ship_state', '(string)$ship_zip',
    '$sign_name', '$sign_title',
    // NOW(), NOW(), NOW() - not in execute array
    '$icd10_primary', '$icd10_secondary', '$wlen', '$wwid', '$wdep', '$wtype',
    '$wstage', '$last_eval', '$start_date', '$freq_per_week', '$qty_per_change',
    '$duration_days', '$refills_allowed', '$additional_instructions',
    '$prod[\'cpt_code\'] ?? null'
];

echo "Total execute values: " . count($execute_values) . "\n\n";

foreach ($execute_values as $i => $val) {
    echo ($i + 1) . ". $val\n";
}

echo "\n=== COMPARISON ===\n\n";

if (count($execute_values) == $placeholders) {
    echo "✅ Execute array matches placeholder count!\n";
    echo "Execute values: " . count($execute_values) . "\n";
    echo "Placeholders: $placeholders\n";
} else {
    echo "❌ MISMATCH!\n";
    echo "Execute values: " . count($execute_values) . "\n";
    echo "Placeholders: $placeholders\n";
    echo "Difference: " . (count($execute_values) - $placeholders) . "\n";
}

echo "\n=== MAPPING ===\n\n";

$col_idx = 0;
$val_idx = 0;

for ($i = 0; $i < count($columns); $i++) {
    $col = $columns[$i];

    if (in_array($col, ['signed_at', 'created_at', 'updated_at'])) {
        echo ($i + 1) . ". $col → NOW()\n";
    } else {
        if ($val_idx < count($execute_values)) {
            echo ($i + 1) . ". $col → " . $execute_values[$val_idx] . "\n";
            $val_idx++;
        } else {
            echo ($i + 1) . ". $col → ❌ MISSING VALUE!\n";
        }
    }
}

if ($val_idx < count($execute_values)) {
    echo "\n❌ EXTRA VALUES:\n";
    for ($i = $val_idx; $i < count($execute_values); $i++) {
        echo "  - " . $execute_values[$i] . "\n";
    }
}
