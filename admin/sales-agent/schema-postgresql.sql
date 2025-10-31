-- Sales Outreach Agent Database Schema (PostgreSQL)
-- This schema tracks leads, outreach campaigns, and engagement metrics

CREATE TABLE IF NOT EXISTS leads (
  id SERIAL PRIMARY KEY,

  -- Practice Information
  practice_name VARCHAR(255) NOT NULL,
  physician_name VARCHAR(255),
  specialty VARCHAR(100),
  address TEXT,
  city VARCHAR(100),
  state VARCHAR(2),
  zip VARCHAR(10),
  phone VARCHAR(20),
  email VARCHAR(255),
  website VARCHAR(255),

  -- Lead Scoring
  lead_score INT DEFAULT 0,
  lead_source VARCHAR(50), -- 'manual', 'web_scrape', 'referral', 'purchased_list'
  estimated_monthly_volume INT,

  -- Status Tracking
  status VARCHAR(50) DEFAULT 'new' CHECK (status IN ('new', 'contacted', 'qualified', 'demo_scheduled', 'registered', 'nurture', 'not_interested', 'do_not_contact')),
  priority VARCHAR(10) DEFAULT 'medium' CHECK (priority IN ('high', 'medium', 'low')),

  -- Assignment
  assigned_rep VARCHAR(255),

  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_contacted_at TIMESTAMP NULL,
  next_followup_date DATE NULL,

  -- Notes
  notes TEXT
);

CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
CREATE INDEX IF NOT EXISTS idx_leads_priority ON leads(priority);
CREATE INDEX IF NOT EXISTS idx_leads_assigned_rep ON leads(assigned_rep);
CREATE INDEX IF NOT EXISTS idx_leads_next_followup ON leads(next_followup_date);

-- Trigger to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
   NEW.updated_at = CURRENT_TIMESTAMP;
   RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_leads_updated_at BEFORE UPDATE ON leads
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TABLE IF NOT EXISTS outreach_campaigns (
  id SERIAL PRIMARY KEY,
  campaign_name VARCHAR(255) NOT NULL,
  campaign_type VARCHAR(20) NOT NULL CHECK (campaign_type IN ('email', 'sms', 'phone', 'direct_mail')),

  -- Campaign Details
  subject_line VARCHAR(255),
  message_template TEXT,

  -- Targeting
  target_specialty VARCHAR(100),
  target_state VARCHAR(2),
  target_status VARCHAR(50),
  min_volume INT,

  -- Status
  status VARCHAR(20) DEFAULT 'draft' CHECK (status IN ('draft', 'active', 'paused', 'completed')),

  -- Metrics
  total_sent INT DEFAULT 0,
  total_opened INT DEFAULT 0,
  total_clicked INT DEFAULT 0,
  total_replied INT DEFAULT 0,
  total_converted INT DEFAULT 0,

  -- Schedule
  start_date DATE,
  end_date DATE,

  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  created_by VARCHAR(255)
);

CREATE TRIGGER update_outreach_campaigns_updated_at BEFORE UPDATE ON outreach_campaigns
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TABLE IF NOT EXISTS outreach_log (
  id SERIAL PRIMARY KEY,
  lead_id INT NOT NULL,
  campaign_id INT,

  -- Outreach Details
  outreach_type VARCHAR(20) NOT NULL CHECK (outreach_type IN ('email', 'sms', 'phone_call', 'demo', 'meeting')),
  subject VARCHAR(255),
  message TEXT,

  -- Response Tracking
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  opened_at TIMESTAMP NULL,
  clicked_at TIMESTAMP NULL,
  replied_at TIMESTAMP NULL,

  -- Call-specific fields
  call_duration_seconds INT,
  call_outcome VARCHAR(20) CHECK (call_outcome IN ('answered', 'voicemail', 'no_answer', 'busy', 'wrong_number')),

  -- Follow-up
  requires_followup BOOLEAN DEFAULT FALSE,
  followup_date DATE,

  -- Meta
  sent_by VARCHAR(255),

  FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  FOREIGN KEY (campaign_id) REFERENCES outreach_campaigns(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_outreach_log_lead_id ON outreach_log(lead_id);
CREATE INDEX IF NOT EXISTS idx_outreach_log_campaign_id ON outreach_log(campaign_id);
CREATE INDEX IF NOT EXISTS idx_outreach_log_sent_at ON outreach_log(sent_at);

CREATE TABLE IF NOT EXISTS email_templates (
  id SERIAL PRIMARY KEY,
  template_name VARCHAR(255) NOT NULL,
  template_type VARCHAR(20) NOT NULL CHECK (template_type IN ('cold_outreach', 'followup', 'demo_invite', 'nurture', 'reactivation')),

  -- Email Content
  subject_line VARCHAR(255) NOT NULL,
  body_html TEXT NOT NULL,
  body_text TEXT,

  -- SendGrid template ID
  sendgrid_template_id VARCHAR(100),

  -- Variables available: {{physician_name}}, {{practice_name}}, {{city}}, {{specialty}}, {{rep_name}}, {{demo_link}}

  -- Metrics
  times_sent INT DEFAULT 0,
  open_rate DECIMAL(5,2),
  click_rate DECIMAL(5,2),
  reply_rate DECIMAL(5,2),

  -- Status
  is_active BOOLEAN DEFAULT TRUE,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  created_by VARCHAR(255)
);

CREATE TRIGGER update_email_templates_updated_at BEFORE UPDATE ON email_templates
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TABLE IF NOT EXISTS sms_templates (
  id SERIAL PRIMARY KEY,
  template_name VARCHAR(255) NOT NULL,
  template_type VARCHAR(20) NOT NULL CHECK (template_type IN ('cold_outreach', 'followup', 'demo_reminder', 'nurture')),

  -- SMS Content (160 char limit)
  message_text VARCHAR(160) NOT NULL,

  -- Variables available: {{physician_name}}, {{practice_name}}, {{rep_name}}, {{demo_link}}

  -- Metrics
  times_sent INT DEFAULT 0,
  reply_rate DECIMAL(5,2),

  -- Status
  is_active BOOLEAN DEFAULT TRUE,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  created_by VARCHAR(255)
);

CREATE TRIGGER update_sms_templates_updated_at BEFORE UPDATE ON sms_templates
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TABLE IF NOT EXISTS call_scripts (
  id SERIAL PRIMARY KEY,
  script_name VARCHAR(255) NOT NULL,
  script_type VARCHAR(20) NOT NULL CHECK (script_type IN ('cold_call', 'gatekeeper', 'voicemail', 'followup', 'demo_booking')),

  -- Script Content
  opening TEXT,
  discovery_questions TEXT,
  objection_responses TEXT,
  closing TEXT,

  -- Usage
  times_used INT DEFAULT 0,
  success_rate DECIMAL(5,2),

  is_active BOOLEAN DEFAULT TRUE,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER update_call_scripts_updated_at BEFORE UPDATE ON call_scripts
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert default email templates with SendGrid template IDs
INSERT INTO email_templates (template_name, template_type, subject_line, body_html, body_text, sendgrid_template_id, created_by) VALUES
('Cold Outreach - Time Savings', 'cold_outreach', 'Dr. {{physician_name}} - Save 6 Hours Per Week on Supply Orders',
'<p>Hi Dr. {{physician_name}},</p>

<p>I work with wound care {{specialty}} practices in {{city}} to help them reduce the time spent ordering collagen products from 20 minutes per order to just 2 minutes.</p>

<p><strong>Quick question:</strong> How much time does your staff currently spend on the phone ordering wound care supplies each week?</p>

<p>We''ve helped practices in Texas:</p>
<ul>
  <li>Cut ordering time by 90% (2 minutes vs 20 minutes)</li>
  <li>Get products in 36-72 hours (vs 5-7 days)</li>
  <li>Eliminate denied claims with pre-verification</li>
</ul>

<p>Would you be open to a quick 10-minute call to see if this makes sense for {{practice_name}}?</p>

<p>Best,<br>{{rep_name}}<br>CollagenDirect</p>',

'Hi Dr. {{physician_name}},

I work with wound care {{specialty}} practices in {{city}} to help them reduce the time spent ordering collagen products from 20 minutes per order to just 2 minutes.

Quick question: How much time does your staff currently spend on the phone ordering wound care supplies each week?

We''ve helped practices in Texas:
- Cut ordering time by 90% (2 minutes vs 20 minutes)
- Get products in 36-72 hours (vs 5-7 days)
- Eliminate denied claims with pre-verification

Would you be open to a quick 10-minute call to see if this makes sense for {{practice_name}}?

Best,
{{rep_name}}
CollagenDirect',
'd-ffb45888b631435c9261460d993c6a37',
'system'),

('Followup - ROI Focus', 'followup', 'Following up - {{practice_name}}',
'<p>Hi Dr. {{physician_name}},</p>

<p>I reached out last week about helping {{practice_name}} save time on wound care supply orders.</p>

<p>I know you''re busy, so I''ll be brief:</p>

<p><strong>Practices using CollagenDirect see:</strong></p>
<ul>
  <li>$12,000+ extra revenue per month (from seeing 6-8 more patients with time saved)</li>
  <li>$36,000+ annual savings (from eliminating denied claims)</li>
  <li>6 hours per week freed up for patient care</li>
</ul>

<p>Worth a 10-minute conversation? Reply with a good time and I''ll call you.</p>

<p>Best,<br>{{rep_name}}</p>',

'Hi Dr. {{physician_name}},

I reached out last week about helping {{practice_name}} save time on wound care supply orders.

I know you''re busy, so I''ll be brief:

Practices using CollagenDirect see:
- $12,000+ extra revenue per month (from seeing 6-8 more patients with time saved)
- $36,000+ annual savings (from eliminating denied claims)
- 6 hours per week freed up for patient care

Worth a 10-minute conversation? Reply with a good time and I''ll call you.

Best,
{{rep_name}}',
'd-6e834e33b85d477e88afb5c38e0e550e',
'system'),

('Breakup Email', 'followup', 'Should I close your file?',
'<p>Hi Dr. {{physician_name}},</p>

<p>I''ve reached out a couple times about how CollagenDirect can save {{practice_name}} 6+ hours per week and protect against denied claims.</p>

<p>I haven''t heard back, which is totally fine - you''re busy treating patients.</p>

<p><strong>Before I close your file, can you let me know which applies?</strong></p>

<p>1. Follow up in 6 months - Interested but timing isn''t right<br>
2. Not interested - Happy with current supplier (I''ll remove you from my list)<br>
3. Let''s talk now - I have 10 minutes to learn more</p>

<p>Just reply with 1, 2, or 3 and I''ll take it from there.</p>

<p>Thanks for your time,<br>{{rep_name}}<br>CollagenDirect</p>',

'Hi Dr. {{physician_name}},

I''ve reached out a couple times about how CollagenDirect can save {{practice_name}} 6+ hours per week and protect against denied claims.

I haven''t heard back, which is totally fine - you''re busy treating patients.

Before I close your file, can you let me know which applies?

1. Follow up in 6 months - Interested but timing isn''t right
2. Not interested - Happy with current supplier (I''ll remove you from my list)
3. Let''s talk now - I have 10 minutes to learn more

Just reply with 1, 2, or 3 and I''ll take it from there.

Thanks for your time,
{{rep_name}}
CollagenDirect',
'd-53cc9016294241d1864885852e3e0f12',
'system');

-- Insert default SMS templates
INSERT INTO sms_templates (template_name, template_type, message_text, created_by) VALUES
('Cold SMS - Quick Intro', 'cold_outreach', 'Hi Dr. {{physician_name}}, {{rep_name}} from CollagenDirect. Help {{practice_name}} get collagen in 36-72hrs vs 5-7 days? Quick call? {{demo_link}}', 'system'),
('Followup SMS', 'followup', 'Hi Dr. {{physician_name}}, following up on collagen supplies. Still spending 20min/order on phone? We cut that to 2min. Worth a chat?', 'system');

-- Insert default call scripts
INSERT INTO call_scripts (script_name, script_type, opening, discovery_questions, objection_responses, closing) VALUES
('Cold Call - Gatekeeper', 'gatekeeper',
'Good morning! This is {{rep_name}} from CollagenDirect. I work with wound care physicians to help them get faster product delivery and better reimbursement. Who typically handles wound care supply ordering for Dr. {{physician_name}}?',

'1. How long does it typically take to get collagen products from your current supplier?
2. How much time does your staff spend on the phone ordering supplies each week?
3. Have you experienced denied claims for wound care products?',

'**"We already have a supplier"**
That''s great to hear! I''m glad you have a supplier you trust. Most of our best clients felt the same way before they tried us. But here''s what I''m hearing from clinics every day: they''re frustrated with 5-7 day delivery times and surprise insurance denials. Even if you stay with your current supplier, would you be open to having a backup option for urgent cases?

**"We''re too busy"**
I completely understand. That''s exactly why I''m calling. The practices we work with were spending 6+ hours per week on supply orders. Now they spend less than an hour. That time goes back to patient care. Would 10 minutes next week work to show you how?

**"Send me information"**
I''d be happy to! Just so I send the right info - are you more interested in faster delivery times, eliminating denied claims, or reducing staff time on orders?',

'Based on what you''ve shared, it sounds like CollagenDirect could save {{practice_name}} significant time and money. Would you be open to a 10-minute call next week where I can show you exactly how this would work for your practice?

Great! I have Tuesday at 2pm or Thursday at 10am. Which works better for you?');
