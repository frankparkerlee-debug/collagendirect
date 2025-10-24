<?php
/**
 * Messages Table Setup
 *
 * Creates the messages table for portal-admin communication
 */

// Simple password
$PASSWORD = 'setup-messages-2025';

if (!isset($_GET['password']) || $_GET['password'] !== $PASSWORD) {
    die('<!DOCTYPE html><html><head><title>Setup Required</title></head><body style="font-family:system-ui;max-width:600px;margin:50px auto;padding:20px;"><h1>🔒 Password Required</h1><form method="GET"><input type="password" name="password" placeholder="Enter setup password" required> <button type="submit">Run Setup</button></form></body></html>');
}

require __DIR__ . '/../api/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Messages Table Setup</title>
    <style>
        body{font-family:system-ui;max-width:900px;margin:30px auto;padding:20px;background:#f5f5f5}
        .box{background:white;padding:30px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);margin:20px 0}
        h1{color:#333;border-bottom:3px solid #47c6be;padding-bottom:10px}
        .success{color:#0a5f56;background:#eefaf8;padding:12px;border-radius:4px;margin:10px 0;border-left:4px solid #47c6be}
        .error{color:#d32f2f;background:#ffebee;padding:12px;border-radius:4px;margin:10px 0;border-left:4px solid #d32f2f}
        .info{background:#e3f2fd;padding:12px;border-radius:4px;margin:10px 0;border-left:4px solid #2196f3}
        .step{padding:15px;background:#f9f9f9;border-left:3px solid #47c6be;margin:10px 0}
        code{background:#f5f5f5;padding:2px 6px;border-radius:3px;font-family:monospace}
        pre{background:#f5f5f5;padding:15px;border-radius:4px;overflow-x:auto}
    </style>
</head>
<body>
    <div class="box">
        <h1>💬 Messages Table Setup</h1>

<?php

try {
    echo "<div class='success'>✓ Connected to database</div>";

    // Check if messages table exists
    echo "<div class='step'>Step 1: Checking for messages table...</div>";

    $tableCheck = $pdo->query("
        SELECT EXISTS (
            SELECT FROM pg_tables
            WHERE schemaname = 'public'
            AND tablename = 'messages'
        )
    ")->fetchColumn();

    if ($tableCheck) {
        echo "<div class='info'>ℹ Messages table already exists. Skipping creation.</div>";
    } else {
        echo "<div class='info'>⚠️ Messages table does not exist. Creating now...</div>";

        // Create messages table
        $pdo->exec("
            CREATE TABLE messages (
                id SERIAL PRIMARY KEY,
                sender_type VARCHAR(20) NOT NULL CHECK (sender_type IN ('provider', 'admin')),
                sender_id VARCHAR(64),
                sender_name VARCHAR(255),
                recipient_type VARCHAR(20) NOT NULL CHECK (recipient_type IN ('provider', 'admin', 'all_admins')),
                recipient_id VARCHAR(64),
                recipient_name VARCHAR(255),
                subject VARCHAR(500) NOT NULL,
                body TEXT NOT NULL,
                patient_id VARCHAR(64),
                order_id VARCHAR(64),
                is_read BOOLEAN DEFAULT FALSE,
                read_at TIMESTAMP,
                parent_message_id INTEGER,
                thread_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        echo "<div class='success'>✓ Messages table created</div>";

        // Add foreign keys
        $pdo->exec("
            ALTER TABLE messages
            ADD CONSTRAINT fk_messages_parent
            FOREIGN KEY (parent_message_id) REFERENCES messages(id) ON DELETE CASCADE
        ");

        $pdo->exec("
            ALTER TABLE messages
            ADD CONSTRAINT fk_messages_patient
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
        ");

        $pdo->exec("
            ALTER TABLE messages
            ADD CONSTRAINT fk_messages_order
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
        ");

        echo "<div class='success'>✓ Foreign keys added</div>";

        // Create indexes
        $pdo->exec("CREATE INDEX idx_messages_sender ON messages(sender_type, sender_id)");
        $pdo->exec("CREATE INDEX idx_messages_recipient ON messages(recipient_type, recipient_id)");
        $pdo->exec("CREATE INDEX idx_messages_thread ON messages(thread_id)");
        $pdo->exec("CREATE INDEX idx_messages_patient ON messages(patient_id)");
        $pdo->exec("CREATE INDEX idx_messages_order ON messages(order_id)");
        $pdo->exec("CREATE INDEX idx_messages_unread ON messages(is_read, recipient_type, recipient_id)");
        $pdo->exec("CREATE INDEX idx_messages_created ON messages(created_at DESC)");

        echo "<div class='success'>✓ Indexes created (7 total)</div>";

        // Create trigger function
        $pdo->exec("
            CREATE OR REPLACE FUNCTION update_messages_updated_at()
            RETURNS TRIGGER AS \$\$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");

        // Create trigger
        $pdo->exec("
            CREATE TRIGGER messages_updated_at
            BEFORE UPDATE ON messages
            FOR EACH ROW
            EXECUTE FUNCTION update_messages_updated_at()
        ");

        echo "<div class='success'>✓ Triggers created</div>";

        // Create view for unread counts
        $pdo->exec("
            CREATE OR REPLACE VIEW unread_message_counts AS
            SELECT
                recipient_type,
                recipient_id,
                COUNT(*) as unread_count
            FROM messages
            WHERE is_read = FALSE
            GROUP BY recipient_type, recipient_id
        ");

        echo "<div class='success'>✓ View created (unread_message_counts)</div>";
    }

    // Show table structure
    echo "<div class='step'>Step 2: Verifying table structure...</div>";

    $columns = $pdo->query("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'messages'
        ORDER BY ordinal_position
    ")->fetchAll();

    echo "<pre>";
    echo "Table: messages\n";
    echo str_repeat("-", 80) . "\n";
    echo sprintf("%-25s %-20s %-10s %s\n", "Column", "Type", "Nullable", "Default");
    echo str_repeat("-", 80) . "\n";

    foreach ($columns as $col) {
        printf("%-25s %-20s %-10s %s\n",
            $col['column_name'],
            $col['data_type'],
            $col['is_nullable'],
            $col['column_default'] ?? 'NULL'
        );
    }

    echo "</pre>";

    // Check indexes
    $indexes = $pdo->query("
        SELECT indexname
        FROM pg_indexes
        WHERE tablename = 'messages'
        ORDER BY indexname
    ")->fetchAll(PDO::FETCH_COLUMN);

    echo "<div class='info'>";
    echo "<strong>Indexes created:</strong> " . count($indexes) . "<br>";
    echo "<ul style='margin:10px 0;padding-left:20px'>";
    foreach ($indexes as $idx) {
        echo "<li><code>{$idx}</code></li>";
    }
    echo "</ul>";
    echo "</div>";

    // Show example queries
    echo "<div class='step'>Step 3: Example Usage</div>";

    echo "<div class='info'>";
    echo "<h3>Example: Send message from provider to all admins</h3>";
    echo "<pre>";
    echo "INSERT INTO messages (\n";
    echo "  sender_type, sender_id, sender_name,\n";
    echo "  recipient_type,\n";
    echo "  subject, body,\n";
    echo "  patient_id, order_id\n";
    echo ") VALUES (\n";
    echo "  'provider', 'user123', 'Dr. Smith',\n";
    echo "  'all_admins',\n";
    echo "  'Question about Order #12345',\n";
    echo "  'Hi, I have a question about this order...',\n";
    echo "  'patient123', 'order123'\n";
    echo ");";
    echo "</pre>";
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>Example: Get unread messages for a provider</h3>";
    echo "<pre>";
    echo "SELECT * FROM messages\n";
    echo "WHERE recipient_type = 'provider'\n";
    echo "  AND recipient_id = 'user123'\n";
    echo "  AND is_read = FALSE\n";
    echo "ORDER BY created_at DESC;";
    echo "</pre>";
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>Example: Mark message as read</h3>";
    echo "<pre>";
    echo "UPDATE messages\n";
    echo "SET is_read = TRUE, read_at = CURRENT_TIMESTAMP\n";
    echo "WHERE id = 123;";
    echo "</pre>";
    echo "</div>";

    // Success summary
    echo "<div class='success'>";
    echo "<h2>✅ Messages Table Setup Complete!</h2>";
    echo "<p><strong>What was created:</strong></p>";
    echo "<ul style='margin:10px 0;padding-left:20px'>";
    echo "<li>✓ <code>messages</code> table with 16 columns</li>";
    echo "<li>✓ 7 indexes for query performance</li>";
    echo "<li>✓ Foreign key constraints to patients and orders</li>";
    echo "<li>✓ Automatic updated_at timestamp trigger</li>";
    echo "<li>✓ Unread message counts view</li>";
    echo "</ul>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul style='margin:10px 0;padding-left:20px'>";
    echo "<li>1. Create API endpoints for sending/receiving messages</li>";
    echo "<li>2. Update portal UI to use real message data</li>";
    echo "<li>3. Add messaging interface to admin panel</li>";
    echo "<li>4. Test messaging between provider and admin</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>⚠️ Security Note</h3>";
    echo "<p>Delete this file after setup:</p>";
    echo "<code>rm admin/setup-messages.php</code>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Database Error</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    exit(1);
}

?>
    </div>
</body>
</html>
