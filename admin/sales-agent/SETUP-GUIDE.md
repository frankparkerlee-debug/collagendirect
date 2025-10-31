# Sales Outreach Agent - Setup Guide

## Overview

This automated sales agent finds physicians across 8 states (TX, OK, AZ, LA, AL, FL, TN, GA), performs personalized outreach, and nurtures them through the sales funnel until they're ready for account manager handoff.

## Features

✅ **Automated Lead Generation** - Searches NPI Registry for wound care physicians
✅ **Email Sequence Automation** - 4-step drip campaign (cold → follow-up → follow-up → breakup)
✅ **Lead Scoring** - Automatically scores leads based on engagement
✅ **Auto-Qualification** - Hands off qualified leads to account manager
✅ **State-by-State Tracking** - Monitor performance across all 8 states
✅ **SendGrid Integration** - Professional email templates with tracking

## Installation

### 1. Run Database Migration

First, create all necessary database tables:

```bash
php /var/www/html/admin/sales-agent/run-migration.php
```

Or visit: https://collagendirect.health/admin/sales-agent/setup.php

This creates 6 tables:
- `leads` - Physician and practice information
- `outreach_campaigns` - Campaign management
- `outreach_log` - Email tracking (opens, clicks, replies)
- `email_templates` - Reusable email templates
- `sms_templates` - SMS message templates
- `call_scripts` - Phone scripts for reps

### 2. Configure SendGrid

The SendGrid API key is already configured in `config.php`:
- API Key: `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`
- From Email: `sales@collagendirect.health`

**SendGrid Templates Created:**
1. **Cold Outreach** - `d-ffb45888b631435c9261460d993c6a37`
2. **Follow-up ROI** - `d-6e834e33b85d477e88afb5c38e0e550e`
3. **Breakup Email** - `d-53cc9016294241d1864885852e3e0f12`

### 3. Set Up Cron Job

Add this to your crontab to run daily automation at 9am CT:

```bash
0 9 * * * cd /var/www/html/admin/sales-agent && php outreach-automation.php >> /var/log/outreach-automation.log 2>&1
```

Or on Render, add to `render.yaml`:

```yaml
services:
  - type: web
    name: collagendirect
    env: docker
    # ... existing config ...

    # Add cron job
    schedule:
      - name: Daily Outreach Automation
        schedule: "0 9 * * *"  # 9am daily
        command: "php /var/www/html/admin/sales-agent/outreach-automation.php"
```

## Usage

### Generate Leads

**Web Interface:**
1. Go to https://collagendirect.health/admin/sales-agent/dashboard.php
2. Click "Find New Leads"
3. Select states and specialties
4. Click "Start Lead Generation"

**Command Line:**
```bash
php /var/www/html/admin/sales-agent/lead-finder.php
```

This will:
- Search NPI Registry for physicians in target states
- Filter by wound care specialties
- Enrich with estimated contact info
- Calculate initial lead score
- Save to database

### Monitor Campaigns

**Dashboard:** https://collagendirect.health/admin/sales-agent/dashboard.php

View:
- Total leads by state
- Email performance (open rate, click rate, reply rate)
- Qualified leads ready for handoff
- Recent activity (last 24 hours)

### Manual Campaign

1. Go to https://collagendirect.health/admin/sales-agent/create-campaign.php
2. Select template
3. Choose targeting (state, specialty, status)
4. Click "Launch Campaign"

## Automation Workflow

### Day 0: Lead Created
- Lead added to database from NPI Registry
- Status: `new`
- Initial lead score assigned

### Day 1: Cold Outreach
- **Template:** Cold Outreach (Time Savings)
- **Subject:** "Dr. {{name}} - Save 6 Hours Per Week"
- **Focus:** 90% time savings (20 min → 2 min ordering)
- Status changes to: `contacted`

### Day 3: Follow-up #1
- **Template:** Cold Outreach (same template, different context)
- **Subject:** "Dr. {{name}} - Save 6 Hours Per Week"
- **Focus:** Soft touch - reinforce time savings benefit without hard ROI claims
- Only sent if no reply to Day 0 email

### Day 7: Follow-up #2 (NOW show ROI)
- **Template:** Follow-up ROI
- **Subject:** "Following up - {{practice_name}}"
- **Focus:** ROI numbers ($12K+ revenue, $36K+ savings) - NOW we've built trust
- Only sent if no reply to previous emails
- They've seen us twice, more receptive to specific claims

### Day 14: Breakup Email
- **Template:** Breakup Email
- **Subject:** "Should I close your file?"
- **Focus:** 3 response options (follow up later, not interested, let's talk)
- Status changes to: `nurture` with 6-month follow-up date

### Qualification & Handoff

Leads are automatically qualified when:
- Lead score ≥ 50 points
- OR clicked demo link
- OR replied to any email

**When qualified:**
1. Status changes to `qualified`
2. Priority changes to `high`
3. Email sent to account manager (parker@collagendirect.health)
4. Lead appears in "Top Qualified Leads" dashboard

## Lead Scoring System

Points are awarded for:
- **High-value specialty:** +30 (Wound Care), +25 (Podiatry), +20 (Dermatology)
- **Has email:** +10
- **Has phone:** +5
- **Email opened:** +5
- **Link clicked:** +10
- **Demo link clicked:** +20
- **Replied to email:** +30
- **Estimated monthly volume:** +1 per patient (max +20)

## Files Reference

### Core Files
- `config.php` - Database and SendGrid configuration
- `schema-postgresql.sql` - Database schema
- `run-migration.php` - Database migration script

### Lead Generation
- `lead-finder.php` - NPI Registry search class
- `lead-finder-ui.php` - Web interface for lead generation
- `lead-finder-api.php` - AJAX API endpoint

### Automation
- `outreach-automation.php` - Daily automation script (run via cron)
- `sendgrid-integration.php` - SendGrid email sending class

### User Interface
- `dashboard.php` - Main dashboard with stats
- `index.php` - Lead management table
- `add-lead.php` - Manually add leads
- `create-campaign.php` - Create email campaigns
- `setup.php` - One-time database setup

## Email Sequence Details

### Email 1: Cold Outreach (Time Savings)
**When:** Immediately after lead is created
**Goal:** Get attention with time savings benefit
**CTA:** "Would you be open to a 10-minute call?"

**Key Points:**
- 20 minutes → 2 minutes (90% reduction)
- 36-72 hour delivery
- Pre-verified insurance

### Email 2: Follow-up Soft Touch (Day 3)
**When:** 3 days after cold outreach, no reply
**Goal:** Gentle reminder of time savings benefit
**CTA:** "Would you be open to a 10-minute call?"

**Key Points:**
- Same messaging as Email 1 (reinforce consistency)
- No aggressive ROI claims yet
- Build familiarity through repetition

### Email 3: ROI Focus (Day 7)
**When:** 7 days after cold outreach, no reply
**Goal:** NOW show concrete financial benefits (trust is building)
**CTA:** "Calculate Your ROI" (link to demo site)

**Key Numbers:**
- $12,000+ extra monthly revenue
- $36,000+ annual savings
- 6 hours saved per week
- They've seen us twice - more receptive to specific claims

### Email 4: Breakup Email (Day 14)
**When:** 14 days after cold outreach, no reply
**Goal:** Last chance to engage, or move to long-term nurture
**CTA:** Reply with 1, 2, or 3

**Options:**
1. Follow up in 6 months
2. Not interested (unsubscribe)
3. Let's talk now

## Testing

### Test Automation (Dry Run)

```bash
php /var/www/html/admin/sales-agent/outreach-automation.php --dry-run
```

This will show what emails WOULD be sent without actually sending them.

### Check Lead Counts

```sql
-- Total leads by state
SELECT state, COUNT(*) FROM leads GROUP BY state;

-- Total leads by status
SELECT status, COUNT(*) FROM leads GROUP BY status;

-- Email engagement rates
SELECT
  COUNT(*) as total_sent,
  COUNT(CASE WHEN opened_at IS NOT NULL THEN 1 END) as opened,
  COUNT(CASE WHEN clicked_at IS NOT NULL THEN 1 END) as clicked
FROM outreach_log
WHERE outreach_type = 'email';
```

## Troubleshooting

### No emails being sent

Check:
1. SendGrid API key is valid
2. `sales@collagendirect.health` is verified in SendGrid
3. Check SendGrid dashboard for bounces/blocks
4. Run with `--dry-run` to see what would be sent

### Leads not being created

Check:
1. Database tables exist (run migration)
2. NPI Registry API is responding (check https://npiregistry.cms.hhs.gov/api/)
3. Check PHP error logs

### Cron job not running

Check:
1. Cron is installed and running
2. PHP path is correct in crontab
3. Check `/var/log/outreach-automation.log` for errors
4. Verify file permissions

## Support

For issues or questions, contact:
- Email: parker@collagendirect.health
- Dashboard: https://collagendirect.health/admin/sales-agent/dashboard.php
