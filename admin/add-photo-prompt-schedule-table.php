<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Creating Photo Prompt Schedule Table ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Check if table exists
    $checkTable = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_name = 'photo_prompt_schedule'
        )
    ")->fetchColumn();

    if ($checkTable) {
        echo "✓ Table 'photo_prompt_schedule' already exists\n\n";
    } else {
        echo "Creating 'photo_prompt_schedule' table...\n";

        $pdo->exec("
            CREATE TABLE photo_prompt_schedule (
                id SERIAL PRIMARY KEY,
                order_id VARCHAR(64) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
                patient_id VARCHAR(64) NOT NULL REFERENCES patients(id) ON DELETE CASCADE,

                -- Scheduling info
                frequency_days INT NOT NULL, -- How often to prompt (1=daily, 2=every 2 days, etc.)
                next_prompt_date DATE NOT NULL,
                last_prompt_sent_at TIMESTAMP NULL,

                -- Lifecycle
                start_date DATE NOT NULL, -- When prompts started (usually delivery date)
                end_date DATE NULL, -- When to stop prompting (calculated from product)
                active BOOLEAN DEFAULT TRUE,

                -- Tracking
                total_prompts_sent INT DEFAULT 0,
                total_photos_received INT DEFAULT 0,

                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP NOT NULL DEFAULT NOW(),

                UNIQUE(order_id)
            )
        ");

        echo "✓ Table created\n";

        // Create indexes
        echo "Creating indexes...\n";

        $pdo->exec("CREATE INDEX idx_photo_schedule_next_prompt ON photo_prompt_schedule(next_prompt_date, active)");
        echo "  ✓ Index on next_prompt_date + active\n";

        $pdo->exec("CREATE INDEX idx_photo_schedule_patient ON photo_prompt_schedule(patient_id)");
        echo "  ✓ Index on patient_id\n";

        $pdo->exec("CREATE INDEX idx_photo_schedule_order ON photo_prompt_schedule(order_id)");
        echo "  ✓ Index on order_id\n\n";
    }

    // Show table structure
    echo "Table structure:\n";
    echo "----------------------------------------\n";
    $columns = $pdo->query("
        SELECT column_name, data_type, character_maximum_length, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'photo_prompt_schedule'
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        $type = $col['data_type'];
        if ($col['character_maximum_length']) {
            $type .= "({$col['character_maximum_length']})";
        }
        $nullable = $col['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
        echo "  {$col['column_name']}: {$type} {$nullable}\n";
    }

    echo "\n✓ Migration complete!\n";
    echo "\nUsage:\n";
    echo "- Table tracks when to send photo prompts for each order\n";
    echo "- Cron job queries next_prompt_date to find orders needing prompts\n";
    echo "- Automatically created when order is marked 'delivered'\n";
    echo "- Updated each time prompt is sent\n";

} catch (Throwable $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Migration Complete ===\n";
