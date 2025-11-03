-- Create preauth_requests table for tracking insurance preauthorization requests
-- This table integrates with the existing orders and patients tables

CREATE TABLE IF NOT EXISTS preauth_requests (
    id VARCHAR(64) PRIMARY KEY,

    -- Foreign Keys
    order_id VARCHAR(64) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    patient_id VARCHAR(64) NOT NULL REFERENCES patients(id) ON DELETE CASCADE,

    -- Request Details
    preauth_number VARCHAR(100), -- Carrier-assigned preauth/reference number
    carrier_name VARCHAR(255) NOT NULL, -- Insurance carrier name
    carrier_payer_id VARCHAR(50), -- Payer ID for EDI/Availity
    member_id VARCHAR(100) NOT NULL, -- Patient's insurance member ID
    group_id VARCHAR(100), -- Patient's insurance group ID

    -- Product Information
    hcpcs_code VARCHAR(10) NOT NULL, -- HCPCS code being authorized (A6010, etc.)
    product_name VARCHAR(255) NOT NULL,
    quantity_requested INTEGER NOT NULL,
    units_per_month INTEGER, -- Monthly supply requested

    -- Clinical Information
    icd10_primary VARCHAR(10) NOT NULL, -- Primary diagnosis code
    icd10_secondary VARCHAR(10), -- Secondary diagnosis code
    medical_necessity_letter TEXT, -- AI-generated letter from Claude
    physician_notes TEXT, -- Additional clinical notes from physician

    -- Status Tracking
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    -- Status values: pending, submitted, approved, denied, expired, cancelled, need_info

    submission_date TIMESTAMP WITH TIME ZONE,
    approval_date TIMESTAMP WITH TIME ZONE,
    denial_date TIMESTAMP WITH TIME ZONE,
    expiration_date TIMESTAMP WITH TIME ZONE, -- When preauth expires

    -- Response Information
    denial_reason TEXT, -- Carrier's reason for denial
    approval_duration_days INTEGER, -- How many days the approval is valid
    approved_quantity INTEGER, -- Quantity approved (may differ from requested)
    carrier_response_data JSONB, -- Full carrier API response for audit

    -- Automation Tracking
    auto_submitted BOOLEAN DEFAULT FALSE, -- Was this auto-submitted by agent?
    retry_count INTEGER DEFAULT 0, -- Number of retry attempts
    last_retry_date TIMESTAMP WITH TIME ZONE,
    next_retry_date TIMESTAMP WITH TIME ZONE, -- For automatic retry scheduling

    -- Communication
    carrier_phone VARCHAR(20),
    carrier_fax VARCHAR(20),
    carrier_portal_url TEXT,

    -- Audit Fields
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_by VARCHAR(64), -- Admin user who created (if manual)
    updated_by VARCHAR(64), -- Last admin user who updated

    -- Indexes for common queries
    CONSTRAINT valid_status CHECK (status IN ('pending', 'submitted', 'approved', 'denied', 'expired', 'cancelled', 'need_info'))
);

-- Indexes for performance
CREATE INDEX idx_preauth_order_id ON preauth_requests(order_id);
CREATE INDEX idx_preauth_patient_id ON preauth_requests(patient_id);
CREATE INDEX idx_preauth_status ON preauth_requests(status);
CREATE INDEX idx_preauth_carrier_name ON preauth_requests(carrier_name);
CREATE INDEX idx_preauth_submission_date ON preauth_requests(submission_date);
CREATE INDEX idx_preauth_expiration_date ON preauth_requests(expiration_date);
CREATE INDEX idx_preauth_next_retry ON preauth_requests(next_retry_date) WHERE next_retry_date IS NOT NULL;

-- Trigger to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_preauth_requests_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_update_preauth_requests_updated_at
    BEFORE UPDATE ON preauth_requests
    FOR EACH ROW
    EXECUTE FUNCTION update_preauth_requests_updated_at();

-- Comments for documentation
COMMENT ON TABLE preauth_requests IS 'Tracks insurance preauthorization requests for DME orders';
COMMENT ON COLUMN preauth_requests.preauth_number IS 'Carrier-assigned preauthorization or reference number';
COMMENT ON COLUMN preauth_requests.status IS 'Current status: pending, submitted, approved, denied, expired, cancelled, need_info';
COMMENT ON COLUMN preauth_requests.auto_submitted IS 'Indicates if this was automatically submitted by the PreAuth Agent';
COMMENT ON COLUMN preauth_requests.medical_necessity_letter IS 'AI-generated medical necessity letter created by Claude API';
