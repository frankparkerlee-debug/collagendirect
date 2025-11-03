-- Create preauth_audit_log table for HIPAA-compliant audit trail
-- Tracks all actions taken on preauthorization requests for compliance

CREATE TABLE IF NOT EXISTS preauth_audit_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),

    -- Reference to preauth request
    preauth_request_id UUID NOT NULL REFERENCES preauth_requests(id) ON DELETE CASCADE,

    -- Action Details
    action VARCHAR(50) NOT NULL,
    -- Actions: created, submitted, approved, denied, cancelled, updated, retry_scheduled,
    --          status_changed, document_added, email_sent, api_call, manual_override

    -- Who performed the action
    actor_type VARCHAR(50) NOT NULL,
    -- Values: system, admin, agent, patient, physician, carrier
    actor_id UUID, -- User ID if applicable
    actor_name VARCHAR(255), -- Name of actor for display

    -- What changed
    field_name VARCHAR(100), -- Name of field that changed (if applicable)
    old_value TEXT, -- Previous value
    new_value TEXT, -- New value
    change_reason TEXT, -- Reason for change (if manual override)

    -- Context Information
    status_before VARCHAR(50), -- Preauth status before action
    status_after VARCHAR(50), -- Preauth status after action

    -- API/External System Tracking
    external_system VARCHAR(100), -- Name of external system (Availity, carrier portal, etc.)
    external_request_id VARCHAR(255), -- External tracking/transaction ID
    external_response_code VARCHAR(50), -- HTTP status or response code
    external_response_message TEXT, -- Response message from external system

    -- Request/Response Data
    request_payload JSONB, -- Full request sent to external system
    response_payload JSONB, -- Full response received

    -- System Context
    ip_address INET, -- IP address of request (if manual action)
    user_agent TEXT, -- User agent string (if web request)
    session_id VARCHAR(255), -- Session ID (if applicable)

    -- Result
    success BOOLEAN DEFAULT TRUE, -- Whether the action succeeded
    error_message TEXT, -- Error message if failed
    error_code VARCHAR(50), -- Error code if failed

    -- Timing
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    duration_ms INTEGER, -- How long the action took (milliseconds)

    -- Additional metadata
    metadata JSONB, -- Flexible field for additional context
    notes TEXT -- Human-readable notes about this event
);

-- Indexes for audit queries and compliance reporting
CREATE INDEX idx_audit_preauth_id ON preauth_audit_log(preauth_request_id);
CREATE INDEX idx_audit_action ON preauth_audit_log(action);
CREATE INDEX idx_audit_actor_type ON preauth_audit_log(actor_type);
CREATE INDEX idx_audit_actor_id ON preauth_audit_log(actor_id) WHERE actor_id IS NOT NULL;
CREATE INDEX idx_audit_created_at ON preauth_audit_log(created_at DESC);
CREATE INDEX idx_audit_external_system ON preauth_audit_log(external_system) WHERE external_system IS NOT NULL;
CREATE INDEX idx_audit_success ON preauth_audit_log(success) WHERE success = FALSE;
CREATE INDEX idx_audit_status_changes ON preauth_audit_log(status_before, status_after) WHERE status_before IS DISTINCT FROM status_after;

-- GIN indexes for JSONB searches
CREATE INDEX idx_audit_request_payload ON preauth_audit_log USING GIN (request_payload);
CREATE INDEX idx_audit_response_payload ON preauth_audit_log USING GIN (response_payload);
CREATE INDEX idx_audit_metadata ON preauth_audit_log USING GIN (metadata);

-- Partitioning by time for better performance (optional, can enable later)
-- This table will grow large over time, so consider partitioning by month/quarter

-- Helper function to easily log preauth actions
CREATE OR REPLACE FUNCTION log_preauth_action(
    p_preauth_request_id UUID,
    p_action VARCHAR,
    p_actor_type VARCHAR,
    p_actor_id UUID DEFAULT NULL,
    p_actor_name VARCHAR DEFAULT NULL,
    p_success BOOLEAN DEFAULT TRUE,
    p_error_message TEXT DEFAULT NULL,
    p_metadata JSONB DEFAULT NULL
)
RETURNS UUID AS $$
DECLARE
    v_log_id UUID;
BEGIN
    INSERT INTO preauth_audit_log (
        preauth_request_id,
        action,
        actor_type,
        actor_id,
        actor_name,
        success,
        error_message,
        metadata
    ) VALUES (
        p_preauth_request_id,
        p_action,
        p_actor_type,
        p_actor_id,
        p_actor_name,
        p_success,
        p_error_message,
        p_metadata
    )
    RETURNING id INTO v_log_id;

    RETURN v_log_id;
END;
$$ LANGUAGE plpgsql;

-- Trigger to automatically log status changes in preauth_requests
CREATE OR REPLACE FUNCTION auto_log_preauth_status_change()
RETURNS TRIGGER AS $$
BEGIN
    -- Only log if status actually changed
    IF OLD.status IS DISTINCT FROM NEW.status THEN
        INSERT INTO preauth_audit_log (
            preauth_request_id,
            action,
            actor_type,
            field_name,
            old_value,
            new_value,
            status_before,
            status_after,
            actor_id,
            success
        ) VALUES (
            NEW.id,
            'status_changed',
            CASE
                WHEN NEW.auto_submitted THEN 'agent'
                WHEN NEW.updated_by IS NOT NULL THEN 'admin'
                ELSE 'system'
            END,
            'status',
            OLD.status,
            NEW.status,
            OLD.status,
            NEW.status,
            NEW.updated_by,
            TRUE
        );
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_auto_log_preauth_status_change
    AFTER UPDATE ON preauth_requests
    FOR EACH ROW
    EXECUTE FUNCTION auto_log_preauth_status_change();

-- View for common audit queries
CREATE OR REPLACE VIEW preauth_audit_summary AS
SELECT
    pal.id,
    pal.preauth_request_id,
    pr.order_id,
    pr.patient_id,
    pr.carrier_name,
    pr.hcpcs_code,
    pal.action,
    pal.actor_type,
    pal.actor_name,
    pal.status_before,
    pal.status_after,
    pal.success,
    pal.error_message,
    pal.external_system,
    pal.created_at,
    pal.duration_ms
FROM preauth_audit_log pal
JOIN preauth_requests pr ON pal.preauth_request_id = pr.id
ORDER BY pal.created_at DESC;

-- Comments for documentation
COMMENT ON TABLE preauth_audit_log IS 'HIPAA-compliant audit trail for all preauthorization actions';
COMMENT ON COLUMN preauth_audit_log.action IS 'Type of action: created, submitted, approved, denied, cancelled, updated, etc.';
COMMENT ON COLUMN preauth_audit_log.actor_type IS 'Who performed action: system, admin, agent, patient, physician, carrier';
COMMENT ON COLUMN preauth_audit_log.external_system IS 'External system name: Availity, carrier portal, EDI gateway, etc.';
COMMENT ON COLUMN preauth_audit_log.duration_ms IS 'Duration of action in milliseconds (useful for API performance tracking)';
COMMENT ON FUNCTION log_preauth_action IS 'Helper function to easily log preauth actions from application code';
COMMENT ON VIEW preauth_audit_summary IS 'Convenient view joining audit log with preauth request details';
