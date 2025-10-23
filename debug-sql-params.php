<?php
// Debug script to count SQL parameters

$sql = "INSERT INTO orders
        (id,patient_id,user_id,product,product_id,product_price,status,shipments_remaining,delivery_mode,payment_type,
         wound_location,wound_laterality,wound_notes,
         shipping_name,shipping_phone,shipping_address,shipping_city,shipping_state,shipping_zip,
         sign_name,sign_title,signed_at,created_at,updated_at,
         icd10_primary,icd10_secondary,wound_length_cm,wound_width_cm,wound_depth_cm,
         wound_type,wound_stage,last_eval_date,start_date,frequency_per_week,qty_per_change,duration_days,refills_allowed,additional_instructions,
         cpt)
        VALUES (?,?,?,?,?,?,?,?,?,?,
                ?,?,?,
                ?,?,?,?,?,?,
                ?,?,NOW(),NOW(),NOW(),
                ?,?,?,?,?,?,
                ?,?,?,?,?,?,?,?,?,
                ?)";

// Count columns
preg_match_all('/\w+(?=,|\))/', explode('VALUES', $sql)[0], $cols);
$column_count = count($cols[0]);

// Count placeholders
$placeholder_count = substr_count($sql, '?');

// Count NOW() functions
$now_count = substr_count($sql, 'NOW()');

echo "Column Count: {$column_count}\n";
echo "Placeholder Count: {$placeholder_count}\n";
echo "NOW() functions: {$now_count}\n";
echo "Total parameters needed: " . ($placeholder_count + $now_count) . "\n\n";

echo "Columns:\n";
foreach ($cols[0] as $i => $col) {
    echo ($i+1) . ". " . $col . "\n";
}

echo "\nNow counting the execute array values:\n\n";

$code = <<<'CODE'
[
  $oid,$pid,$userId,$prod['name'],$prod['id'],$prod['price_admin'],'submitted',0,$delivery_mode,$payment_type, // 10
  ($_POST['wound_location']??null),($_POST['wound_laterality']??null),($_POST['wound_notes']??null), // 13
  (string)$ship_name,(string)$ship_phone,(string)$ship_addr,(string)$ship_city,(string)$ship_state,(string)$ship_zip, // 19
  $sign_name,$sign_title, // 21
  $icd10_primary,$icd10_secondary,$wlen,$wwid,$wdep, // 26
  $wtype,$wstage,$last_eval,$start_date,$freq_per_week,$qty_per_change,$duration_days,$refills_allowed,$additional_instructions, // 35
  $prod['cpt_code'] ?? null // 36
]
CODE;

$values = preg_match_all('/\$[\w\[\]\']+/', $code, $matches);
echo "Execute array values: " . count($matches[0]) . "\n";

// The issue: signed_at, created_at, updated_at are NOW() in SQL (not placeholders)
// So we have 39 columns but only 36 placeholders + 3 NOW() = 39 total
echo "\n";
echo "Expected: {$column_count} columns = {$placeholder_count} placeholders + {$now_count} NOW() functions\n";
echo "Actual execute values: " . count($matches[0]) . "\n";

if (count($matches[0]) != $placeholder_count) {
    echo "\n❌ MISMATCH! Execute array has " . count($matches[0]) . " values but SQL has {$placeholder_count} placeholders\n";
} else {
    echo "\n✅ Match! Both have " . count($matches[0]) . " values\n";
}
