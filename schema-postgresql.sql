-- PostgreSQL Schema for CollagenDirect
-- Converted from MySQL schema

-- Table: admin_users
CREATE TABLE admin_users (
  id SERIAL PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role VARCHAR(50) DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: admin_physicians
CREATE TABLE admin_physicians (
  admin_id INTEGER NOT NULL,
  physician_user_id VARCHAR(64) NOT NULL,
  PRIMARY KEY (admin_id, physician_user_id),
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

CREATE INDEX ap_physician ON admin_physicians(physician_user_id);

-- Table: login_attempts
CREATE TABLE login_attempts (
  id SERIAL PRIMARY KEY,
  email VARCHAR(255),
  ip_hash VARCHAR(255),
  success BOOLEAN,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: users
CREATE TABLE users (
  id VARCHAR(64) PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(120),
  last_name VARCHAR(120),
  practice_name VARCHAR(255),
  npi VARCHAR(20),
  status VARCHAR(20) DEFAULT 'pending',
  account_type VARCHAR(40) DEFAULT 'referral',
  agree_msa BOOLEAN DEFAULT FALSE,
  agree_baa BOOLEAN DEFAULT FALSE,
  reset_token VARCHAR(255),
  reset_expires TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: patients
CREATE TABLE patients (
  id VARCHAR(64) PRIMARY KEY,
  user_id VARCHAR(64) NOT NULL,
  first_name VARCHAR(120),
  last_name VARCHAR(120),
  dob DATE,
  sex VARCHAR(10),
  mrn VARCHAR(50),
  phone VARCHAR(15),
  email VARCHAR(255),
  address VARCHAR(255),
  city VARCHAR(120),
  state VARCHAR(10),
  zip VARCHAR(15),
  insurance_provider VARCHAR(255),
  insurance_member_id VARCHAR(100),
  insurance_group_id VARCHAR(100),
  insurance_payer_phone VARCHAR(50),
  id_card_path VARCHAR(255),
  id_card_name VARCHAR(255),
  id_card_mime VARCHAR(100),
  ins_card_path VARCHAR(255),
  ins_card_name VARCHAR(255),
  ins_card_mime VARCHAR(100),
  aob_path VARCHAR(255),
  aob_signed_at TIMESTAMP,
  aob_ip VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX pat_user ON patients(user_id);
CREATE INDEX pat_name ON patients(last_name, first_name);

-- Table: orders
CREATE TABLE orders (
  id VARCHAR(64) PRIMARY KEY,
  patient_id VARCHAR(64) NOT NULL,
  user_id VARCHAR(64) NOT NULL,
  product VARCHAR(255),
  product_id INTEGER,
  product_price DECIMAL(10,2),
  frequency VARCHAR(100),
  delivery_mode VARCHAR(100),
  status VARCHAR(40) DEFAULT 'submitted',
  shipments_remaining INTEGER,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  shipped_at TIMESTAMP,
  delivered_at TIMESTAMP,
  insurer_name VARCHAR(255),
  member_id VARCHAR(100),
  group_id VARCHAR(100),
  payer_phone VARCHAR(50),
  sign_name VARCHAR(255),
  sign_title VARCHAR(255),
  signed_at TIMESTAMP,
  prior_auth VARCHAR(100),
  payment_type VARCHAR(20) DEFAULT 'insurance',
  wound_location VARCHAR(120),
  wound_laterality VARCHAR(30),
  wound_notes TEXT,
  shipping_name VARCHAR(255),
  shipping_phone VARCHAR(50),
  shipping_address VARCHAR(255),
  shipping_city VARCHAR(120),
  shipping_state VARCHAR(10),
  shipping_zip VARCHAR(15),
  rx_note_path VARCHAR(255),
  rx_note_name VARCHAR(255),
  rx_note_mime VARCHAR(100),
  carrier_status VARCHAR(50),
  carrier_eta TIMESTAMP,
  expires_at TIMESTAMP,
  ins_card_path VARCHAR(255),
  ins_card_name VARCHAR(255),
  ins_card_mime VARCHAR(100),
  patient_id_path VARCHAR(255),
  patient_id_name VARCHAR(255),
  patient_id_mime VARCHAR(100),
  icd10_primary VARCHAR(10),
  icd10_secondary VARCHAR(10),
  wound_length_cm DECIMAL(6,2),
  wound_width_cm DECIMAL(6,2),
  wound_depth_cm DECIMAL(6,2),
  wound_type VARCHAR(50),
  wound_stage VARCHAR(20),
  last_eval_date DATE,
  start_date DATE,
  frequency_per_week INTEGER,
  qty_per_change INTEGER,
  duration_days INTEGER,
  refills_allowed INTEGER,
  additional_instructions TEXT,
  cpt VARCHAR(20),
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX ord_patient ON orders(patient_id);
CREATE INDEX ord_user ON orders(user_id);
CREATE INDEX ord_status ON orders(status);

-- Table: products
CREATE TABLE products (
  id SERIAL PRIMARY KEY,
  sku VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  price_admin DECIMAL(10,2),
  price_wholesale DECIMAL(10,2),
  category VARCHAR(100),
  size VARCHAR(50),
  hcpcs_code VARCHAR(20),
  cpt_code VARCHAR(20),
  active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX prod_sku ON products(sku);
CREATE INDEX prod_active ON products(active);

-- Insert default product data
INSERT INTO products (sku, name, description, price_admin, price_wholesale, category, size, hcpcs_code, cpt_code, active) VALUES
('ALG-2X2', 'AlgiHeal™ Alginate', 'Calcium alginate wound dressing for moderate to heavy exudate', 12.50, 8.00, 'Alginate', '2x2', 'A6196', '97597', TRUE),
('ALG-4X4', 'AlgiHeal™ Alginate', 'Calcium alginate wound dressing for moderate to heavy exudate', 18.00, 12.00, 'Alginate', '4x4', 'A6197', '97597', TRUE);

