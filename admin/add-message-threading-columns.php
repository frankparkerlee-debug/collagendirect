<?php
// Migration script to add message threading columns
// Run this once to add parent_message_id and thread_id to existing messages table

require __DIR__ . '/db.php';

echo "Adding message threading columns...\n";

try {
    // Add parent_message_id column if it doesn't exist
    $pdo->exec("
        ALTER TABLE messages
        ADD COLUMN IF NOT EXISTS parent_message_id INTEGER REFERENCES messages(id) ON DELETE CASCADE
    ");
    echo "✓ Added parent_message_id column\n";

    // Add thread_id column if it doesn't exist
    $pdo->exec("
        ALTER TABLE messages
        ADD COLUMN IF NOT EXISTS thread_id INTEGER
    ");
    echo "✓ Added thread_id column\n";

    // Create index for thread_id if it doesn't exist
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_messages_thread ON messages(thread_id)
    ");
    echo "✓ Created index on thread_id\n";

    // Update existing messages to set their thread_id to their own id (make them thread roots)
    $pdo->exec("
        UPDATE messages
        SET thread_id = id
        WHERE thread_id IS NULL AND parent_message_id IS NULL
    ");
    echo "✓ Updated existing messages to be thread roots\n";

    echo "\nMigration completed successfully!\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
