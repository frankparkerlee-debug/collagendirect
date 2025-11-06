<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Adding confirmation_method to delivery_confirmations ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Check if column already exists
    $checkColumn = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.columns
            WHERE table_name = 'delivery_confirmations'
            AND column_name = 'confirmation_method'
        )
    ")->fetchColumn();

    if ($checkColumn) {
        echo "✓ Column 'confirmation_method' already exists\n";
    } else {
        echo "Adding 'confirmation_method' column...\n";

        $pdo->exec("
            ALTER TABLE delivery_confirmations
            ADD COLUMN confirmation_method VARCHAR(20),
            ADD COLUMN sms_reply_text TEXT
        ");

        echo "✓ Columns added successfully\n";
    }

    // Show updated structure
    echo "\nUpdated columns:\n";
    echo "----------------------------------------\n";
    $columns = $pdo->query("
        SELECT column_name, data_type, character_maximum_length
        FROM information_schema.columns
        WHERE table_name = 'delivery_confirmations'
        AND column_name IN ('confirmation_method', 'sms_reply_text')
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        $type = $col['data_type'];
        if ($col['character_maximum_length']) {
            $type .= "({$col['character_maximum_length']})";
        }
        echo "  {$col['column_name']}: {$type}\n";
    }

    echo "\n✓ Migration complete!\n";

} catch (Throwable $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Migration Complete ===\n";
