-- Demo Portal Tables Migration
-- Creates tables for distributor demo portal with HIPAA-compliant ephemeral data
-- Uses existing users table for authentication (sales/admin/superadmin roles)

-- Demo sessions (tracks each demo instance with 24-hour expiry)
CREATE TABLE IF NOT EXISTS demo_sessions (
  id VARCHAR(64) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  user_id VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  started_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP WITH TIME ZONE DEFAULT (CURRENT_TIMESTAMP + INTERVAL '24 hours'),
  tour_completed BOOLEAN DEFAULT FALSE,
  tour_step_reached INTEGER DEFAULT 0,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Demo patients (synthetic data only - no real PHI)
CREATE TABLE IF NOT EXISTS demo_patients (
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
);

-- Demo orders (synthetic data only)
CREATE TABLE IF NOT EXISTS demo_orders (
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
);

-- Indexes for efficient queries
CREATE INDEX IF NOT EXISTS idx_demo_sessions_expires ON demo_sessions(expires_at);
CREATE INDEX IF NOT EXISTS idx_demo_sessions_user ON demo_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_demo_patients_session ON demo_patients(demo_session_id);
CREATE INDEX IF NOT EXISTS idx_demo_orders_session ON demo_orders(demo_session_id);
CREATE INDEX IF NOT EXISTS idx_demo_orders_patient ON demo_orders(demo_patient_id);

-- Add comment for documentation
COMMENT ON TABLE demo_sessions IS 'Demo sessions linked to users table, with 24-hour auto-expiry for HIPAA compliance';
COMMENT ON TABLE demo_patients IS 'Synthetic patient data for demo - no real PHI';
COMMENT ON TABLE demo_orders IS 'Synthetic order data for demo';
