<?php
// /portal/run-migration.php
// Run this via web browser: https://collagendirect.onrender.com/portal/run-migration.php?key=change-me-in-production
declare(strict_types=1);

// Security: Only allow with secret key
$SECRET_KEY = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $SECRET_KEY) {
    http_response_code(403);
    die('Access denied. Provide ?key=SECRET in URL');
}

header('Content-Type: text/plain; charset=utf-8');

echo "============================================\n";
echo "Running Compliance Workflow Migration\n";
echo "============================================\n\n";

// Direct database connection
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'collagen_db';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '5432';

try {
    $pdo = new PDO(
        "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};options='--client_encoding=UTF8'",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "✓ Connected to database\n\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "Executing migration...\n\n";

    // Execute the migration (SQL embedded directly)
    $pdo->exec("
-- Migration: Compliance Workflow for DME Order Management
-- Date: 2025-10-23
-- Purpose: Add proper role management, order status tracking, and compliance fields

-- =====================================================
-- 1. ADD ROLE FIELD TO USERS TABLE
-- =====================================================
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='users' AND column_name='role') THEN
        ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'physician';
    END IF;
END \$\$;

-- =====================================================
-- 2. ADD PRACTICE-LEVEL DME LICENSE FLAG
-- =====================================================
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='users' AND column_name='has_dme_license') THEN
        ALTER TABLE users ADD COLUMN has_dme_license BOOLEAN DEFAULT FALSE;
    END IF;
END \$\$;

COMMENT ON COLUMN users.has_dme_license IS 'Whether this practice has their own DME license (affects order workflow)';

-- =====================================================
-- 3. ENHANCE ORDERS TABLE FOR COMPLIANCE WORKFLOW
-- =====================================================

-- Add delivery location (patient vs physician office)
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='delivery_location') THEN
        ALTER TABLE orders ADD COLUMN delivery_location VARCHAR(20) DEFAULT 'patient';
    END IF;
END \$\$;

-- Add tracking information (flexible for multiple carriers)
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='tracking_code') THEN
        ALTER TABLE orders ADD COLUMN tracking_code VARCHAR(255);
    END IF;
END \$\$;

DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='carrier') THEN
        ALTER TABLE orders ADD COLUMN carrier VARCHAR(50);
    END IF;
END \$\$;

-- Add payment method (insurance vs cash)
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='payment_method') THEN
        ALTER TABLE orders ADD COLUMN payment_method VARCHAR(20) DEFAULT 'insurance';
    END IF;
END \$\$;

-- Add cash price amount
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='cash_price') THEN
        ALTER TABLE orders ADD COLUMN cash_price DECIMAL(10,2);
    END IF;
END \$\$;

-- Add cash price approval tracking
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='cash_price_approved_at') THEN
        ALTER TABLE orders ADD COLUMN cash_price_approved_at TIMESTAMP;
    END IF;
END \$\$;

DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='cash_price_approved_by') THEN
        ALTER TABLE orders ADD COLUMN cash_price_approved_by VARCHAR(64);
    END IF;
END \$\$;

-- Add termination tracking
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='terminated_at') THEN
        ALTER TABLE orders ADD COLUMN terminated_at TIMESTAMP;
    END IF;
END \$\$;

DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='terminated_by') THEN
        ALTER TABLE orders ADD COLUMN terminated_by VARCHAR(64);
    END IF;
END \$\$;

DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='termination_reason') THEN
        ALTER TABLE orders ADD COLUMN termination_reason TEXT;
    END IF;
END \$\$;

-- Add super admin review tracking
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='reviewed_at') THEN
        ALTER TABLE orders ADD COLUMN reviewed_at TIMESTAMP;
    END IF;
END \$\$;

DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='reviewed_by') THEN
        ALTER TABLE orders ADD COLUMN reviewed_by VARCHAR(64);
    END IF;
END \$\$;

DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='review_notes') THEN
        ALTER TABLE orders ADD COLUMN review_notes TEXT;
    END IF;
END \$\$;

-- Add manufacturer order ID
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='manufacturer_order_id') THEN
        ALTER TABLE orders ADD COLUMN manufacturer_order_id VARCHAR(255);
    END IF;
END \$\$;

-- Add completeness check fields
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='is_complete') THEN
        ALTER TABLE orders ADD COLUMN is_complete BOOLEAN DEFAULT FALSE;
    END IF;
END \$\$;

DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='completeness_checked_at') THEN
        ALTER TABLE orders ADD COLUMN completeness_checked_at TIMESTAMP;
    END IF;
END \$\$;

DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='orders' AND column_name='missing_fields') THEN
        ALTER TABLE orders ADD COLUMN missing_fields TEXT[];
    END IF;
END \$\$;

-- =====================================================
-- 4. CREATE ORDER STATUS TYPE
-- =====================================================
DO \$\$
BEGIN
    -- Drop existing constraint if it exists
    ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;

    -- Add new constraint with all valid statuses
    ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (
        status IN (
            'draft',
            'submitted',
            'under_review',
            'incomplete',
            'verification_pending',
            'cash_price_required',
            'cash_price_approved',
            'approved',
            'in_production',
            'shipped',
            'delivered',
            'terminated',
            'cancelled'
        )
    );
END \$\$;

-- =====================================================
-- 5. CREATE ORDER STATUS HISTORY TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS order_status_history (
  id SERIAL PRIMARY KEY,
  order_id VARCHAR(64) NOT NULL,
  old_status VARCHAR(40),
  new_status VARCHAR(40) NOT NULL,
  changed_by VARCHAR(64),
  changed_by_role VARCHAR(50),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS osh_order ON order_status_history(order_id);
CREATE INDEX IF NOT EXISTS osh_created ON order_status_history(created_at DESC);

-- =====================================================
-- 6. CREATE ORDER ALERTS/NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS order_alerts (
  id SERIAL PRIMARY KEY,
  order_id VARCHAR(64) NOT NULL,
  alert_type VARCHAR(50) NOT NULL,
  message TEXT NOT NULL,
  severity VARCHAR(20) DEFAULT 'info',
  recipient_role VARCHAR(50) NOT NULL,
  is_read BOOLEAN DEFAULT FALSE,
  read_at TIMESTAMP,
  read_by VARCHAR(64),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS oa_order ON order_alerts(order_id);
CREATE INDEX IF NOT EXISTS oa_recipient ON order_alerts(recipient_role, is_read);
CREATE INDEX IF NOT EXISTS oa_created ON order_alerts(created_at DESC);

COMMENT ON COLUMN order_alerts.alert_type IS 'Type: cash_price_required, order_incomplete, termination_request, verification_failed, etc.';
COMMENT ON COLUMN order_alerts.severity IS 'Severity: info, warning, critical';
COMMENT ON COLUMN order_alerts.recipient_role IS 'Who needs to see this: physician, practice_admin, superadmin';

-- =====================================================
-- 7. ADD INDEXES FOR PERFORMANCE
-- =====================================================
CREATE INDEX IF NOT EXISTS ord_delivery_location ON orders(delivery_location);
CREATE INDEX IF NOT EXISTS ord_payment_method ON orders(payment_method);
CREATE INDEX IF NOT EXISTS ord_is_complete ON orders(is_complete);
CREATE INDEX IF NOT EXISTS ord_tracking ON orders(tracking_code) WHERE tracking_code IS NOT NULL;
CREATE INDEX IF NOT EXISTS users_role ON users(role);
CREATE INDEX IF NOT EXISTS users_dme_license ON users(has_dme_license);
    ");

    echo "✓ Step 1-7 completed (schema changes)\n\n";

    // Create the completeness function
    $pdo->exec("
CREATE OR REPLACE FUNCTION check_order_completeness(order_id_param VARCHAR(64))
RETURNS TABLE(is_complete BOOLEAN, missing_fields TEXT[]) AS \$\$
DECLARE
    ord RECORD;
    pat RECORD;
    missing TEXT[] := ARRAY[]::TEXT[];
BEGIN
    -- Get order and patient data
    SELECT o.*, p.* INTO ord
    FROM orders o
    JOIN patients p ON o.patient_id = p.id
    WHERE o.id = order_id_param;

    -- Check required patient fields
    IF ord.first_name IS NULL OR ord.first_name = '' THEN
        missing := array_append(missing, 'patient_first_name');
    END IF;
    IF ord.last_name IS NULL OR ord.last_name = '' THEN
        missing := array_append(missing, 'patient_last_name');
    END IF;
    IF ord.dob IS NULL THEN
        missing := array_append(missing, 'patient_dob');
    END IF;
    IF ord.sex IS NULL OR ord.sex = '' THEN
        missing := array_append(missing, 'patient_sex');
    END IF;
    IF ord.phone IS NULL OR ord.phone = '' THEN
        missing := array_append(missing, 'patient_phone');
    END IF;
    IF ord.address IS NULL OR ord.address = '' THEN
        missing := array_append(missing, 'patient_address');
    END IF;

    -- Check required order fields
    IF ord.product IS NULL OR ord.product = '' THEN
        missing := array_append(missing, 'product');
    END IF;
    IF ord.wound_location IS NULL OR ord.wound_location = '' THEN
        missing := array_append(missing, 'wound_location');
    END IF;
    IF ord.icd10_primary IS NULL OR ord.icd10_primary = '' THEN
        missing := array_append(missing, 'icd10_primary');
    END IF;
    IF ord.wound_length_cm IS NULL THEN
        missing := array_append(missing, 'wound_length_cm');
    END IF;
    IF ord.wound_width_cm IS NULL THEN
        missing := array_append(missing, 'wound_width_cm');
    END IF;

    -- Check required documents
    IF ord.id_card_path IS NULL OR ord.id_card_path = '' THEN
        missing := array_append(missing, 'patient_id_card');
    END IF;
    IF ord.ins_card_path IS NULL OR ord.ins_card_path = '' THEN
        missing := array_append(missing, 'insurance_card');
    END IF;
    IF ord.aob_path IS NULL OR ord.aob_path = '' THEN
        missing := array_append(missing, 'assignment_of_benefits');
    END IF;
    IF ord.rx_note_path IS NULL OR ord.rx_note_path = '' THEN
        missing := array_append(missing, 'clinical_documentation');
    END IF;

    -- Check signature
    IF ord.sign_name IS NULL OR ord.sign_name = '' THEN
        missing := array_append(missing, 'physician_signature');
    END IF;
    IF ord.signed_at IS NULL THEN
        missing := array_append(missing, 'signature_date');
    END IF;

    -- Check insurance info (if not cash)
    IF ord.payment_method != 'cash' THEN
        IF ord.insurance_provider IS NULL OR ord.insurance_provider = '' THEN
            missing := array_append(missing, 'insurance_provider');
        END IF;
        IF ord.insurance_member_id IS NULL OR ord.insurance_member_id = '' THEN
            missing := array_append(missing, 'insurance_member_id');
        END IF;
    END IF;

    -- Return result
    RETURN QUERY SELECT (array_length(missing, 1) IS NULL OR array_length(missing, 1) = 0), missing;
END;
\$\$ LANGUAGE plpgsql;
    ");

    echo "✓ Step 8 completed (completeness function)\n\n";

    // Create the trigger function
    $pdo->exec("
CREATE OR REPLACE FUNCTION log_order_status_change()
RETURNS TRIGGER AS \$\$
BEGIN
    IF (TG_OP = 'UPDATE' AND OLD.status IS DISTINCT FROM NEW.status) THEN
        INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, changed_by_role)
        VALUES (
            NEW.id,
            OLD.status,
            NEW.status,
            CURRENT_USER,
            'system'
        );
    END IF;
    RETURN NEW;
END;
\$\$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS order_status_change_trigger ON orders;
CREATE TRIGGER order_status_change_trigger
    AFTER UPDATE ON orders
    FOR EACH ROW
    EXECUTE FUNCTION log_order_status_change();
    ");

    echo "✓ Step 9 completed (status change trigger)\n\n";

    // Update existing data
    $pdo->exec("
-- Set default role for existing users without role
UPDATE users SET role = 'physician' WHERE role IS NULL;

-- Set has_dme_license to FALSE for all existing practices (can be updated via admin)
UPDATE users SET has_dme_license = FALSE WHERE has_dme_license IS NULL;

-- Update existing order statuses to new format if needed
UPDATE orders SET status = 'submitted' WHERE status = 'pending';
UPDATE orders SET status = 'draft' WHERE status = 'new';

COMMENT ON TABLE users IS 'Physician users and practice administrators';
COMMENT ON TABLE orders IS 'DME orders with full compliance tracking';
COMMENT ON TABLE order_status_history IS 'Audit trail of all order status changes';
COMMENT ON TABLE order_alerts IS 'Notifications for physicians and super admins requiring action';
    ");

    echo "✓ Step 10 completed (data updates)\n\n";

    echo "✓ Migration completed successfully!\n\n";

    // Verify the changes
    echo "Verifying schema updates...\n\n";

    // Check users table
    $stmt = $pdo->query("
        SELECT column_name, data_type, column_default
        FROM information_schema.columns
        WHERE table_name = 'users'
        AND column_name IN ('role', 'has_dme_license')
        ORDER BY column_name
    ");

    echo "Users table new columns:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['column_name']} ({$row['data_type']})\n";
    }
    echo "\n";

    // Check orders table
    $stmt = $pdo->query("
        SELECT column_name, data_type
        FROM information_schema.columns
        WHERE table_name = 'orders'
        AND column_name IN (
            'delivery_location', 'tracking_code', 'carrier',
            'payment_method', 'cash_price', 'terminated_at',
            'reviewed_at', 'is_complete', 'missing_fields'
        )
        ORDER BY column_name
    ");

    echo "Orders table new columns:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['column_name']} ({$row['data_type']})\n";
    }
    echo "\n";

    // Check new tables
    $stmt = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_name IN ('order_status_history', 'order_alerts')
        ORDER BY table_name
    ");

    echo "New tables created:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['table_name']}\n";
    }
    echo "\n";

    // Check function exists
    $stmt = $pdo->query("
        SELECT routine_name
        FROM information_schema.routines
        WHERE routine_schema = 'public'
        AND routine_name = 'check_order_completeness'
    ");

    if ($stmt->fetch()) {
        echo "✓ check_order_completeness() function created\n";
    }

    // Check trigger exists
    $stmt = $pdo->query("
        SELECT trigger_name
        FROM information_schema.triggers
        WHERE trigger_name = 'order_status_change_trigger'
    ");

    if ($stmt->fetch()) {
        echo "✓ order_status_change_trigger created\n";
    }

    echo "\n============================================\n";
    echo "Migration completed successfully!\n";
    echo "============================================\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
