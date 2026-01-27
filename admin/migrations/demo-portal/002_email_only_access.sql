-- Migration: Enable email-only demo access (no password required)
-- This allows anyone to access the demo portal with just an email address

-- Remove the foreign key constraint on user_id (now accepts guest demo users)
ALTER TABLE demo_sessions DROP CONSTRAINT IF EXISTS demo_sessions_user_id_fkey;

-- Add columns to track guest demo users
ALTER TABLE demo_sessions ADD COLUMN IF NOT EXISTS demo_email VARCHAR(255);
ALTER TABLE demo_sessions ADD COLUMN IF NOT EXISTS demo_name VARCHAR(255);

-- Create index for email lookups
CREATE INDEX IF NOT EXISTS idx_demo_sessions_email ON demo_sessions(demo_email);

-- Update comment
COMMENT ON TABLE demo_sessions IS 'Demo sessions - supports both authenticated users and email-only guest access. 24-hour auto-expiry for HIPAA compliance.';
