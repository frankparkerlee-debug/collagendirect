-- Create preauth_rules table for configuring carrier-specific preauthorization requirements
-- This allows the agent to know which carriers require preauth for which HCPCS codes

CREATE TABLE IF NOT EXISTS preauth_rules (
    id VARCHAR(64) PRIMARY KEY DEFAULT encode(gen_random_bytes(32), 'hex'),

    -- Carrier Information
    carrier_name VARCHAR(255) NOT NULL,
    carrier_payer_id VARCHAR(50), -- Payer ID for EDI/Availity integration
    carrier_aliases JSONB, -- Array of alternate carrier names ["Anthem BCBS", "Blue Cross", etc.]

    -- Product Rules
    hcpcs_code VARCHAR(10) NOT NULL, -- HCPCS code this rule applies to
    requires_preauth BOOLEAN NOT NULL DEFAULT TRUE,

    -- Threshold Rules
    quantity_threshold INTEGER, -- Preauth required if quantity exceeds this
    dollar_threshold DECIMAL(10,2), -- Preauth required if cost exceeds this

    -- Time-based Rules
    frequency_limit_days INTEGER, -- How often preauth can be approved (e.g., 90 days)
    approval_duration_days INTEGER DEFAULT 365, -- How long preauth is valid

    -- Clinical Requirements
    required_icd10_codes JSONB, -- Array of required diagnosis codes
    excluded_icd10_codes JSONB, -- Array of codes that make preauth ineligible
    requires_physician_notes BOOLEAN DEFAULT FALSE,
    requires_wound_measurements BOOLEAN DEFAULT FALSE,
    requires_prior_treatment_history BOOLEAN DEFAULT FALSE,

    -- Documentation Requirements
    required_documents JSONB, -- Array of required document types
    -- Example: ["prescription", "medical_records", "wound_photos", "aob"]

    -- Submission Method
    submission_method VARCHAR(50) NOT NULL DEFAULT 'manual',
    -- Values: manual, fax, portal, api, edi

    api_endpoint TEXT, -- API endpoint if submission_method = 'api'
    portal_url TEXT, -- Portal URL if submission_method = 'portal'
    fax_number VARCHAR(20), -- Fax number if submission_method = 'fax'

    -- Contact Information
    carrier_phone VARCHAR(20),
    carrier_email VARCHAR(255),
    provider_relations_phone VARCHAR(20),

    -- Processing Expectations
    typical_turnaround_days INTEGER DEFAULT 5, -- Expected days for approval
    auto_approval_eligible BOOLEAN DEFAULT FALSE, -- Can this be auto-approved?

    -- Special Instructions
    special_instructions TEXT, -- Notes for manual processing
    form_template_url TEXT, -- URL to downloadable preauth form

    -- Rule Status
    is_active BOOLEAN DEFAULT TRUE,
    effective_date DATE, -- When this rule becomes effective
    termination_date DATE, -- When this rule expires

    -- Priority for matching (higher = more specific rule)
    priority INTEGER DEFAULT 0,

    -- Audit Fields
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_by VARCHAR(64),
    updated_by VARCHAR(64),
    notes TEXT, -- Internal notes about this rule

    -- Composite unique constraint: one rule per carrier+hcpcs combination
    CONSTRAINT unique_carrier_hcpcs UNIQUE (carrier_name, hcpcs_code)
);

-- Indexes for fast rule lookup
CREATE INDEX idx_rules_carrier_name ON preauth_rules(carrier_name);
CREATE INDEX idx_rules_hcpcs_code ON preauth_rules(hcpcs_code);
CREATE INDEX idx_rules_active ON preauth_rules(is_active) WHERE is_active = TRUE;
CREATE INDEX idx_rules_carrier_hcpcs ON preauth_rules(carrier_name, hcpcs_code);
CREATE INDEX idx_rules_payer_id ON preauth_rules(carrier_payer_id);

-- GIN index for JSONB array searches
CREATE INDEX idx_rules_carrier_aliases ON preauth_rules USING GIN (carrier_aliases);
CREATE INDEX idx_rules_required_icd10 ON preauth_rules USING GIN (required_icd10_codes);

-- Trigger to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_preauth_rules_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_update_preauth_rules_updated_at
    BEFORE UPDATE ON preauth_rules
    FOR EACH ROW
    EXECUTE FUNCTION update_preauth_rules_updated_at();

-- Insert default rules for common carriers
INSERT INTO preauth_rules (carrier_name, hcpcs_code, requires_preauth, typical_turnaround_days, special_instructions) VALUES
    ('Medicare', 'A6010', TRUE, 3, 'Medicare Part B DME requires preauth for quantities over 30-day supply'),
    ('Medicare', 'A6021', TRUE, 3, 'Medicare Part B DME requires preauth for quantities over 30-day supply'),
    ('Medicare', 'A6210', TRUE, 3, 'Medicare Part B DME requires preauth for quantities over 30-day supply'),
    ('Medicaid', 'A6010', TRUE, 5, 'State Medicaid programs may have varying requirements - verify with local plan'),
    ('Medicaid', 'A6021', TRUE, 5, 'State Medicaid programs may have varying requirements - verify with local plan'),
    ('Medicaid', 'A6210', TRUE, 5, 'State Medicaid programs may have varying requirements - verify with local plan'),
    ('Blue Cross Blue Shield', 'A6010', TRUE, 5, 'BCBS plans vary by state - check specific plan requirements'),
    ('Blue Cross Blue Shield', 'A6021', TRUE, 5, 'BCBS plans vary by state - check specific plan requirements'),
    ('Blue Cross Blue Shield', 'A6210', TRUE, 5, 'BCBS plans vary by state - check specific plan requirements'),
    ('UnitedHealthcare', 'A6010', TRUE, 3, 'UHC typically requires preauth for advanced wound care products'),
    ('UnitedHealthcare', 'A6021', TRUE, 3, 'UHC typically requires preauth for advanced wound care products'),
    ('UnitedHealthcare', 'A6210', TRUE, 3, 'UHC typically requires preauth for advanced wound care products'),
    ('Aetna', 'A6010', TRUE, 5, 'Aetna requires medical necessity documentation'),
    ('Aetna', 'A6021', TRUE, 5, 'Aetna requires medical necessity documentation'),
    ('Aetna', 'A6210', TRUE, 5, 'Aetna requires medical necessity documentation'),
    ('Cigna', 'A6010', TRUE, 5, 'Cigna requires prior authorization for DME over $500'),
    ('Cigna', 'A6021', TRUE, 5, 'Cigna requires prior authorization for DME over $500'),
    ('Cigna', 'A6210', TRUE, 5, 'Cigna requires prior authorization for DME over $500'),
    ('Humana', 'A6010', TRUE, 5, 'Humana Medicare Advantage plans require preauth'),
    ('Humana', 'A6021', TRUE, 5, 'Humana Medicare Advantage plans require preauth'),
    ('Humana', 'A6210', TRUE, 5, 'Humana Medicare Advantage plans require preauth')
ON CONFLICT (carrier_name, hcpcs_code) DO NOTHING;

-- Comments for documentation
COMMENT ON TABLE preauth_rules IS 'Configuration rules for carrier-specific preauthorization requirements';
COMMENT ON COLUMN preauth_rules.carrier_aliases IS 'JSONB array of alternate carrier names for matching';
COMMENT ON COLUMN preauth_rules.submission_method IS 'How to submit: manual, fax, portal, api, edi';
COMMENT ON COLUMN preauth_rules.priority IS 'Higher priority rules match first (for handling specific exceptions)';
COMMENT ON COLUMN preauth_rules.auto_approval_eligible IS 'Whether this carrier+product can be auto-submitted by agent';
