<?php
/**
 * Migration: Add accepts_sms column to patients table
 * This tracks whether patient consents to receiving SMS text messages
 */

require_once __DIR__ . '/../api/lib/db.php';

$db = db();

// Check if column already exists
$result = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='patients' AND column_name='accepts_sms'");
$exists = $result->fetchColumn();

if ($exists) {
    echo "✓ accepts_sms column already exists\n";
    exit(0);
}

// Add the column
$db->exec("ALTER TABLE patients ADD COLUMN accepts_sms BOOLEAN DEFAULT FALSE");

echo "✓ Added accepts_sms column to patients table\n";
echo "  - Type: BOOLEAN\n";
echo "  - Default: FALSE (opt-in required)\n";
echo "  - Purpose: Track SMS consent for delivery confirmations\n";
