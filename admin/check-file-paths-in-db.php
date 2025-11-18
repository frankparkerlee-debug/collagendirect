<?php
/**
 * Check File Paths in Database
 * Shows what file paths are stored in the database vs what actually exists
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== File Paths in Database ===\n\n";

// Check patients table
echo "1. Patient Documents:\n";
$patients = $pdo->query("
    SELECT id, first_name, last_name, id_card_path, ins_card_path, notes_path, aob_path
    FROM patients
    WHERE id_card_path IS NOT NULL OR ins_card_path IS NOT NULL OR notes_path IS NOT NULL OR aob_path IS NOT NULL
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

if (count($patients) === 0) {
    echo "  No patients with uploaded documents found\n\n";
} else {
    foreach ($patients as $p) {
        echo "  Patient: {$p['first_name']} {$p['last_name']} (ID: {$p['id']})\n";
        if ($p['id_card_path']) {
            $exists = file_exists('/var/www/html' . $p['id_card_path']) ? '✓' : '✗';
            echo "    ID Card: $exists {$p['id_card_path']}\n";
        }
        if ($p['ins_card_path']) {
            $exists = file_exists('/var/www/html' . $p['ins_card_path']) ? '✓' : '✗';
            echo "    Insurance: $exists {$p['ins_card_path']}\n";
        }
        if ($p['notes_path']) {
            $exists = file_exists('/var/www/html' . $p['notes_path']) ? '✓' : '✗';
            echo "    Notes: $exists {$p['notes_path']}\n";
        }
        if ($p['aob_path']) {
            $exists = file_exists('/var/www/html' . $p['aob_path']) ? '✓' : '✗';
            echo "    AOB: $exists {$p['aob_path']}\n";
        }
        echo "\n";
    }
}

// Check orders table
echo "2. Order Documents:\n";
$orders = $pdo->query("
    SELECT id, patient_id, rx_note_path, baseline_wound_photo_path
    FROM orders
    WHERE rx_note_path IS NOT NULL OR baseline_wound_photo_path IS NOT NULL
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

if (count($orders) === 0) {
    echo "  No orders with uploaded documents found\n\n";
} else {
    foreach ($orders as $o) {
        echo "  Order ID: {$o['id']}\n";
        if ($o['rx_note_path']) {
            $exists = file_exists('/var/www/html' . $o['rx_note_path']) ? '✓' : '✗';
            echo "    RX Note: $exists {$o['rx_note_path']}\n";
        }
        if ($o['baseline_wound_photo_path']) {
            $exists = file_exists('/var/www/html' . $o['baseline_wound_photo_path']) ? '✓' : '✗';
            echo "    Wound Photo: $exists {$o['baseline_wound_photo_path']}\n";
        }
        echo "\n";
    }
}

echo "=== Summary ===\n";
echo "Patients with files: " . count($patients) . "\n";
echo "Orders with files: " . count($orders) . "\n";
