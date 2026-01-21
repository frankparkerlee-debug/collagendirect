<?php
/**
 * Migration: Create demo portal tables
 * Creates tables for distributor demo portal with HIPAA-compliant ephemeral data
 * Uses existing admins table for authentication (sales/admin/superadmin roles)
 */

require_once __DIR__ . '/../../../api/db.php';

echo "<pre>";
echo "=== Creating Demo Portal Tables ===\n\n";

try {
    // Check if demo_sessions table already exists
    $checkStmt = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_name = 'demo_sessions'
    ");

    if ($checkStmt->rowCount() > 0) {
        echo "✓ Demo tables already exist\n";
        echo "</pre>";
        exit(0);
    }

    // Create demo_sessions table
    echo "Creating demo_sessions table...\n";
    $pdo->exec("
        CREATE TABLE demo_sessions (
            id VARCHAR(64) PRIMARY KEY DEFAULT gen_random_uuid()::text,
            admin_id INTEGER NOT NULL REFERENCES admins(id) ON DELETE CASCADE,
            started_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP WITH TIME ZONE DEFAULT (CURRENT_TIMESTAMP + INTERVAL '24 hours'),
            tour_completed BOOLEAN DEFAULT FALSE,
            tour_step_reached INTEGER DEFAULT 0,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Created demo_sessions table\n";

    // Create demo_patients table
    echo "Creating demo_patients table...\n";
    $pdo->exec("
        CREATE TABLE demo_patients (
            id VARCHAR(64) PRIMARY KEY DEFAULT gen_random_uuid()::text,
            demo_session_id VARCHAR(64) NOT NULL REFERENCES demo_sessions(id) ON DELETE CASCADE,
            first_name VARCHAR(120),
            last_name VARCHAR(120),
            dob DATE,
            sex VARCHAR(10),
            mrn VARCHAR(50),
            phone VARCHAR(20),
            email VARCHAR(255),
            address VARCHAR(255),
            city VARCHAR(120),
            state VARCHAR(10),
            zip VARCHAR(15),
            insurance_provider VARCHAR(255),
            insurance_member_id VARCHAR(100),
            insurance_group_number VARCHAR(100),
            wound_location VARCHAR(255),
            wound_type VARCHAR(100),
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Created demo_patients table\n";

    // Create demo_orders table
    echo "Creating demo_orders table...\n";
    $pdo->exec("
        CREATE TABLE demo_orders (
            id VARCHAR(64) PRIMARY KEY DEFAULT gen_random_uuid()::text,
            demo_session_id VARCHAR(64) NOT NULL REFERENCES demo_sessions(id) ON DELETE CASCADE,
            demo_patient_id VARCHAR(64) NOT NULL REFERENCES demo_patients(id) ON DELETE CASCADE,
            order_number VARCHAR(50),
            product VARCHAR(255),
            product_id INTEGER,
            product_size VARCHAR(100),
            quantity INTEGER DEFAULT 1,
            status VARCHAR(40) DEFAULT 'submitted',
            payment_type VARCHAR(50) DEFAULT 'referral',
            billed_by VARCHAR(50) DEFAULT 'collagen_direct',
            delivery_mode VARCHAR(100),
            frequency VARCHAR(100),
            shipping_name VARCHAR(255),
            shipping_address VARCHAR(255),
            shipping_city VARCHAR(120),
            shipping_state VARCHAR(10),
            shipping_zip VARCHAR(15),
            tracking_number VARCHAR(100),
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Created demo_orders table\n";

    // Create indexes
    echo "Creating indexes...\n";
    $pdo->exec("CREATE INDEX idx_demo_sessions_expires ON demo_sessions(expires_at)");
    $pdo->exec("CREATE INDEX idx_demo_sessions_admin ON demo_sessions(admin_id)");
    $pdo->exec("CREATE INDEX idx_demo_patients_session ON demo_patients(demo_session_id)");
    $pdo->exec("CREATE INDEX idx_demo_orders_session ON demo_orders(demo_session_id)");
    $pdo->exec("CREATE INDEX idx_demo_orders_patient ON demo_orders(demo_patient_id)");
    echo "✓ Created indexes\n";

    // Add comments
    $pdo->exec("COMMENT ON TABLE demo_sessions IS 'Demo sessions linked to admins table, with 24-hour auto-expiry for HIPAA compliance'");
    $pdo->exec("COMMENT ON TABLE demo_patients IS 'Synthetic patient data for demo - no real PHI'");
    $pdo->exec("COMMENT ON TABLE demo_orders IS 'Synthetic order data for demo'");
    echo "✓ Added table comments\n";

    echo "\n=== Migration Complete ===\n";
    echo "Demo portal tables created successfully.\n";
    echo "Sales/admin/superadmin users can now log in at /demo-portal/login.html\n";

} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "</pre>";
