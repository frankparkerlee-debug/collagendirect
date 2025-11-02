# HubSpot-Integrated Sales Agent - Complete Setup Guide

## 🎯 What This System Does

The sales agent handles the **complete physician lifecycle**:

### 1. **FIND** → Automatic lead generation
- Searches NPI Registry for physicians across 8 states
- Finds Wound Care, Podiatry, Dermatology specialists
- Pushes leads to HubSpot with initial data

### 2. **CONTACT** → Pre-registration outreach
- Day 0: Cold outreach (time savings focus)
- Day 3: Soft follow-up (build familiarity)
- Day 7: ROI focus ($12K+ revenue, $36K+ savings)
- Day 14: Breakup email (move to 6-month nurture)
- **All activity synced to HubSpot**

### 3. **REGISTER** → Drive portal signups
- Track when physicians register
- Welcome email sequence
- Create tasks for account manager to help with first order

### 4. **NURTURE** → Keep referrals flowing
- Day 7 after registration: First order reminder (if no orders yet)
- Day 30: At-risk alert (no orders in 30 days)
- Day 90: Reactivation campaign (churned physicians)
- **All tracked in HubSpot**

---

## 📋 Prerequisites

### 1. HubSpot Account
**Recommended Plan:** Sales Hub Starter ($45/mo for 2 users)

**Required Features:**
- ✅ CRM (unlimited contacts)
- ✅ Email sequences
- ✅ Deal pipeline
- ✅ Activity logging (emails, calls, notes)
- ✅ Tasks & reminders
- ✅ API access

**Sign up:** https://www.hubspot.com/products/sales

### 2. HubSpot API Key
1. Go to Settings → Integrations → Private Apps
2. Create new private app named "CollagenDirect Sales Agent"
3. Enable scopes:
   - `crm.objects.contacts.read`
   - `crm.objects.contacts.write`
   - `crm.objects.deals.read`
   - `crm.objects.deals.write`
   - `crm.objects.companies.read`
   - `crm.objects.companies.write`
   - `crm.schemas.contacts.read`
   - `crm.schemas.deals.read`
4. Copy the API key (starts with `pat-na1-...`)

---

## 🚀 Installation Steps

### Step 1: Add HubSpot API Key to Environment

On Render.com:
1. Go to your service → Environment
2. Add new environment variable:
   - Key: `HUBSPOT_API_KEY`
   - Value: `your-hubspot-api-key-here`
3. Save changes (service will redeploy)

Or add to `.env` file locally:
```
HUBSPOT_API_KEY=your-hubspot-api-key-here
```

### Step 2: Set Up HubSpot Deal Pipeline

In HubSpot:
1. Go to Settings → Objects → Deals
2. Create new pipeline: "Physician Acquisition"
3. Add these stages:

```
1. New Lead (prospect found)
2. Contacted (outreach sent)
3. Engaged (opened/clicked email)
4. Registration Started (viewed portal)
5. Registered ✅ (active portal account)
6. First Referral ✅ (placed first order)
7. Active & Nurturing ✅ (ongoing orders)
8. At Risk ⚠️ (no orders 30 days)
9. Churned ❌ (no orders 90 days)
```

### Step 3: Create Custom Properties

In HubSpot Settings → Properties → Contact Properties, create:

| Property Name | Type | Description |
|--------------|------|-------------|
| `specialty` | Single-line text | Medical specialty |
| `lead_score` | Number | Lead score 0-100 |
| `estimated_monthly_volume` | Number | Est. patients/month |
| `npi_number` | Single-line text | NPI identifier |
| `lead_source` | Dropdown | Source (NPI Registry, Referral, etc.) |
| `last_order_date` | Date | Date of most recent order |
| `total_orders` | Number | Lifetime order count |
| `lifetime_value` | Number | Total revenue |
| `portal_user_id` | Single-line text | Portal database ID |
| `portal_status` | Dropdown | active, inactive, suspended |
| `registration_date` | Date | When they signed up |

### Step 4: Run Database Migration

**Option A - Via Web:**
Visit: https://collagendirect.health/admin/sales-agent/setup.php
Click "Run Database Migration"

**Option B - Via Command Line:**
```bash
php /var/www/html/admin/sales-agent/run-migration.php
```

This creates 6 tables:
- `leads` - Physician contacts
- `outreach_campaigns` - Email campaigns
- `outreach_log` - Activity tracking
- `email_templates` - Template library
- `sms_templates` - SMS messages
- `call_scripts` - Phone scripts

**Add HubSpot column to leads table:**
```sql
ALTER TABLE leads ADD COLUMN hubspot_contact_id VARCHAR(50);
ALTER TABLE leads ADD COLUMN hubspot_deal_id VARCHAR(50);
ALTER TABLE leads ADD COLUMN welcome_sent BOOLEAN DEFAULT FALSE;
```

### Step 5: Set Up Daily Automation (Cron Job)

**On Render (render.yaml):**
```yaml
services:
  - type: web
    name: collagendirect
    env: docker
    # ... existing config ...

    # Add cron job for daily automation
    schedule:
      - name: Daily Sales Automation
        schedule: "0 9 * * *"  # 9am CT daily
        command: "php /var/www/html/admin/sales-agent/complete-automation.php"
```

**Or manually via crontab:**
```bash
0 9 * * * cd /var/www/html/admin/sales-agent && php complete-automation.php >> /var/log/sales-automation.log 2>&1
```

---

## 🎬 How to Use

### Generate Initial Leads

1. **Log into admin panel:** https://collagendirect.health/admin/
2. **Go to Lead Finder:** https://collagendirect.health/admin/sales-agent/lead-finder-ui.php
3. **Select states** (keep all 8 selected: TX, OK, AZ, LA, AL, FL, TN, GA)
4. **Select specialties** (keep all 6 selected)
5. **Click "Start Lead Generation"**

**What happens:**
- ✅ Searches NPI Registry (public database)
- ✅ Finds 500-1,500 physicians
- ✅ Saves to local database
- ✅ **Pushes each lead to HubSpot** with contact + deal
- ✅ Logs initial note in HubSpot timeline

**Time:** ~1 minute for all 48 combinations (8 states × 6 specialties)

### Monitor in HubSpot

**Contacts:**
- Go to Contacts → All Contacts
- Filter by "Lead Source" = "NPI Registry"
- You'll see all physicians with full data

**Deals:**
- Go to Sales → Deals
- Filter by Pipeline: "Physician Acquisition"
- See all physicians by stage

**Activities:**
- Click any contact to see timeline
- View all emails sent, notes logged, tasks created

---

## 📊 Daily Automation Flow

When the cron job runs daily at 9am CT:

### Pre-Registration Outreach (Stages 1-2)

**Leads with status = "new"**
→ Send cold outreach email (Day 0)
→ Log email to HubSpot
→ Update deal stage to "Contacted"

**Leads contacted 3 days ago, no reply**
→ Send soft follow-up (Day 3)
→ Log to HubSpot

**Leads contacted 7 days ago, no reply**
→ Send ROI follow-up (Day 7)
→ Log to HubSpot

**Leads contacted 14 days ago, no reply**
→ Send breakup email (Day 14)
→ Update status to "nurture"
→ Log to HubSpot

### Post-Registration Nurture (Stages 3-4)

**New registrations in last 24 hours**
→ Send welcome email
→ Call HubSpot API: `trackRegistration()`
→ Update lifecycle stage to "customer"
→ Create task: "Check for first referral in 7 days"

**Registered 7+ days, no orders yet**
→ Send first order reminder
→ Log note to HubSpot
→ Create high-priority task for account manager

**No orders in 30 days**
→ Send at-risk email
→ Log warning note to HubSpot
→ Create urgent task: "Call at-risk physician"
→ Update deal stage to "At Risk"

**No orders in 90 days**
→ Send reactivation campaign
→ Log churned note to HubSpot
→ Update deal stage to "Churned"

---

## 🔗 HubSpot Integration Details

### What Gets Synced to HubSpot

**When a lead is found:**
```
✅ Contact created with full data
✅ Deal created in "New Lead" stage
✅ Note logged: "New lead found via NPI Registry"
```

**When an email is sent:**
```
✅ Email activity logged
✅ Subject, body, timestamp recorded
✅ Associated with contact
```

**When physician registers:**
```
✅ Lifecycle stage → "customer"
✅ Registration date set
✅ Note logged with portal user ID
✅ Task created for account manager
✅ Deal stage → "Registered"
```

**When physician places order:**
```
✅ Note logged with order details
✅ Last order date updated
✅ Total orders incremented
✅ Lifetime value updated
✅ Deal stage → "Active & Nurturing"
```

**When physician goes at-risk:**
```
✅ Warning note logged
✅ Urgent task created for account manager
✅ Deal stage → "At Risk"
```

---

## 📧 Email Reply Management

**Where replies go:**
- All automated emails come from: `sales@collagendirect.health`
- Physicians reply to: `sales@collagendirect.health`

**Set up HubSpot inbox:**
1. Go to Settings → Inbox → Connect Email
2. Connect `sales@collagendirect.health`
3. Enable "Log emails automatically"
4. Enable "Create contact records from replies"

**What happens when they reply:**
- ✅ Reply appears in HubSpot inbox
- ✅ Automatically logged to contact timeline
- ✅ Notification sent to assigned owner
- ✅ Lead score increases (+30 points)
- ✅ Deal stage can be manually updated

---

## 🎯 Lead Scoring System

Points are automatically calculated and synced to HubSpot:

| Activity | Points |
|----------|--------|
| High-value specialty (Wound Care) | +30 |
| Medium-value specialty (Podiatry) | +25 |
| Has email address | +10 |
| Has phone number | +5 |
| Email opened | +5 |
| Link clicked | +10 |
| Demo link clicked | +20 |
| Replied to email | +30 |
| Estimated monthly volume | +1 per patient (max +20) |

**Qualification threshold:** 50 points

When a lead hits 50 points:
- ✅ Status → "qualified"
- ✅ Deal stage → "Engaged"
- ✅ Email notification to account manager
- ✅ High-priority task created in HubSpot

---

## 🧪 Testing

### Test Without Sending Emails (Dry Run)

```bash
php /var/www/html/admin/sales-agent/complete-automation.php --dry-run
```

This shows what WOULD happen without actually sending emails or updating HubSpot.

### Test Lead Generation Locally

```bash
php /var/www/html/admin/sales-agent/lead-finder.php
```

### Check HubSpot Sync

After running automation, check HubSpot:
1. Go to Contacts → Recent
2. Click a contact
3. Verify timeline shows:
   - Initial note about NPI Registry
   - Email activities
   - Any tasks created

---

## 📈 Expected Results

### After First Lead Generation Run:
- **500-1,500 leads** in HubSpot
- All with deals in "New Lead" stage
- All with initial notes logged

### After Week 1 of Automation:
- **200-300 cold outreach emails** sent
- **20-30 follow-up emails** sent
- All activity logged in HubSpot

### After Month 1:
- **20-50 qualified leads** (score ≥ 50)
- **10-30 replies** to emails
- **5-10 registrations** (physician signups)
- All tracked in HubSpot pipeline

---

## 🆘 Troubleshooting

### Leads Not Appearing in HubSpot

**Check:**
1. Is `HUBSPOT_API_KEY` environment variable set?
2. Does API key have correct scopes?
3. Run `php lead-finder.php` and check for errors

**Fix:**
- Verify API key in HubSpot Settings → Private Apps
- Check error logs: `/var/log/sales-automation.log`

### Emails Sending But Not Logging to HubSpot

**Check:**
1. Is `hubspot_contact_id` populated in leads table?
2. Run database query: `SELECT hubspot_contact_id FROM leads LIMIT 10;`

**Fix:**
- Re-run lead finder to create HubSpot contacts
- Manually sync existing leads:
```php
php -r "require 'hubspot-integration.php'; $hs = new HubSpotIntegration(); /* sync code */"
```

### Custom Properties Not Showing

**Fix:**
1. Go to HubSpot Settings → Properties
2. Create each custom property manually (see Step 3 above)
3. Re-run automation

---

## 📞 Support

**Questions?**
- Email: parker@collagendirect.health
- HubSpot Help: https://help.hubspot.com
- API Docs: https://developers.hubspot.com/docs/api/overview

---

## 🎉 You're All Set!

Your complete sales agent is now running with full HubSpot integration:

✅ Automatic lead generation (NPI Registry)
✅ Automated outreach sequences (4 emails over 14 days)
✅ Post-registration nurture (welcome, reminders, at-risk)
✅ Full HubSpot sync (contacts, deals, activities, tasks)
✅ Account manager handoff workflow
✅ Referral tracking and reminders

**Next Steps:**
1. Run your first lead generation
2. Check HubSpot to see leads populate
3. Let automation run for 14 days
4. Review qualified leads in HubSpot
5. Start closing deals!
