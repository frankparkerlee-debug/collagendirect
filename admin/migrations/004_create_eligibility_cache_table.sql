-- Create eligibility_cache table (optional but recommended)
-- This table caches insurance eligibility verification results to reduce API calls
-- and improve performance when the same member is verified multiple times

CREATE TABLE IF NOT EXISTS eligibility_cache (
    id VARCHAR(64) PRIMARY KEY,

    -- Insurance Information
    member_id VARCHAR(100) NOT NULL,
    carrier_name VARCHAR(255) NOT NULL,

    -- Eligibility Data
    eligibility_data JSONB NOT NULL,
    -- Example structure:
    -- {
    --   "eligible": true,
    --   "verification_method": "availity|manual|assumed",
    --   "coverage_active": true,
    --   "plan_name": "Blue Cross PPO",
    --   "effective_date": "2024-01-01",
    --   "copay": 20,
    --   "deductible": 1000,
    --   "verified_by": "user_id or system"
    -- }

    -- Timestamps
    verified_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),

    -- Ensure one cache entry per member/carrier combination
    UNIQUE(member_id, carrier_name)
);

-- Indexes for fast lookups
CREATE INDEX idx_eligibility_member ON eligibility_cache(member_id);
CREATE INDEX idx_eligibility_carrier ON eligibility_cache(carrier_name);
CREATE INDEX idx_eligibility_verified ON eligibility_cache(verified_at);
CREATE INDEX idx_eligibility_member_carrier ON eligibility_cache(member_id, carrier_name);

-- GIN index for JSONB searches
CREATE INDEX idx_eligibility_data ON eligibility_cache USING GIN (eligibility_data);

-- Trigger to update verified_at on updates
CREATE OR REPLACE FUNCTION update_eligibility_cache_verified_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.verified_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_update_eligibility_cache_verified_at
    BEFORE UPDATE ON eligibility_cache
    FOR EACH ROW
    EXECUTE FUNCTION update_eligibility_cache_verified_at();

-- Comments for documentation
COMMENT ON TABLE eligibility_cache IS 'Caches insurance eligibility verification results to reduce API calls';
COMMENT ON COLUMN eligibility_cache.eligibility_data IS 'JSONB object containing eligibility details from carrier or manual verification';
COMMENT ON COLUMN eligibility_cache.verified_at IS 'When this eligibility was last verified (auto-updates on each update)';
