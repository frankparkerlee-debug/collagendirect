# CollagenDirect Sales Outreach Agent

An automated sales outreach and lead management system for CollagenDirect to help acquire physician practices.

## Features

### 📊 Lead Management
- **Lead Database**: Track physician practices, contact info, specialty, location
- **Lead Scoring**: Automatically score leads based on specialty, volume, location
- **Pipeline Stages**: New → Contacted → Qualified → Demo Scheduled → Registered
- **Automated Follow-ups**: Schedule and track follow-up activities
- **Assignment**: Assign leads to sales reps

### 📧 Multi-Channel Outreach
- **Email Campaigns**: Automated email sequences with templates
- **SMS Campaigns**: Text message outreach for high-priority leads
- **Phone Call Scripts**: Structured scripts for cold calls, follow-ups, objection handling
- **Campaign Analytics**: Track opens, clicks, replies, conversions

### 🎯 Campaign Templates

#### Email Templates Included:
1. **Cold Outreach - Time Savings**: Focus on reducing ordering time from 20 min to 2 min
2. **Followup - No Response**: Highlight ROI ($12K+ extra revenue, $36K+ savings)
3. **Demo Invite**: Personalized demo invitation
4. **Nurture Campaign**: Keep warm leads engaged
5. **Reactivation**: Re-engage cold leads

#### SMS Templates Included:
1. **Cold SMS**: Quick intro with demo link
2. **Followup SMS**: Brief reminder about time savings

#### Call Scripts Included:
1. **Cold Call - Gatekeeper**: Get past front desk to decision maker
2. **Discovery Call**: Qualify the lead with key questions
3. **Objection Handling**: Responses to common objections
4. **Demo Booking**: Schedule the demo call

### 📈 Analytics Dashboard
- Total leads and conversion funnel
- Campaign performance metrics (open rate, reply rate, conversion rate)
- Follow-up reminders
- Rep performance tracking
- Revenue attribution

## Database Schema

### Tables:
1. **leads** - Practice and physician information, status, notes
2. **outreach_campaigns** - Campaign details, metrics, targeting
3. **outreach_log** - Individual outreach attempts, responses, outcomes
4. **email_templates** - Reusable email templates with variables
5. **sms_templates** - Reusable SMS templates
6. **call_scripts** - Phone call scripts for different scenarios

## Setup Instructions

### 1. Database Setup
```bash
# Run the schema SQL file
mysql -u your_username -p your_database < schema.sql
```

### 2. Configuration
Create `config.php` in `/admin/` directory:
```php
<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'collagendirect');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// Email configuration (for sending campaigns)
define('SMTP_HOST', 'smtp.your-provider.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@collagendirect.health');
define('SMTP_PASS', 'your-smtp-password');

// SMS configuration (Twilio recommended)
define('TWILIO_SID', 'your_twilio_sid');
define('TWILIO_TOKEN', 'your_twilio_token');
define('TWILIO_PHONE', '+1234567890');

// Base URL
define('BASE_URL', 'https://collagendirect.health');
?>
```

### 3. SendGrid Email Service Integration

**Step-by-Step SendGrid Setup:**

1. **Create SendGrid Account**
   - Go to https://signup.sendgrid.com/
   - Sign up for free tier (100 emails/day)
   - Verify your email address

2. **Generate API Key**
   - Login to SendGrid dashboard
   - Go to Settings → API Keys
   - Click "Create API Key"
   - Name it "CollagenDirect Sales Agent"
   - Select "Restricted Access"
   - Enable only "Mail Send" permission (Full Access)
   - Click "Create & View"
   - Copy the API key (you won't see it again!)

3. **Configure Sender Email**
   - Go to Settings → Sender Authentication
   - Click "Verify Single Sender"
   - Use: sales@collagendirect.health
   - Complete verification email

4. **Domain Authentication (Recommended for better deliverability)**
   - Go to Settings → Sender Authentication → Domain Authentication
   - Follow wizard to add DNS records to your domain
   - This prevents emails from going to spam

5. **Add API Key to Config**
   - Copy `config.example.php` to `config.php`
   - Set `SENDGRID_API_KEY` to your API key
   - Set `SENDGRID_FROM_EMAIL` to `sales@collagendirect.health`
   - Set `SENDGRID_FROM_NAME` to `CollagenDirect Sales Team`

6. **Setup Event Webhook (for tracking opens/clicks)**
   - Go to Settings → Mail Settings → Event Notification Settings
   - Enable "Event Webhook"
   - Set HTTP Post URL to: `https://collagendirect.health/admin/sales-agent/sendgrid-webhook.php`
   - Select events: Opened, Clicked, Bounced, Dropped, Unsubscribed, Spam Report
   - Optional: Add Authorization Header with secret token
   - Save settings

7. **Configure SPF/DKIM Records (for deliverability)**
   - Add these DNS records to prevent spam filtering:
   ```
   TXT record: v=spf1 include:sendgrid.net ~all
   CNAME: em1234.collagendirect.health → u1234567.wl123.sendgrid.net
   CNAME: s1._domainkey.collagendirect.health → s1.domainkey.u1234567.wl123.sendgrid.net
   CNAME: s2._domainkey.collagendirect.health → s2.domainkey.u1234567.wl123.sendgrid.net
   ```
   (Replace with actual values from SendGrid domain authentication)

8. **Test Email Sending**
   ```php
   require_once('sendgrid-integration.php');
   $mailer = new SendGridMailer();
   $result = $mailer->sendEmail([
       'to_email' => 'your-email@example.com',
       'to_name' => 'Test Recipient',
       'subject' => 'Test Email from CollagenDirect',
       'html_content' => '<h1>Test</h1><p>If you see this, SendGrid is working!</p>',
       'lead_id' => 1
   ]);
   print_r($result);
   ```

**SendGrid Free Tier Limits:**
- 100 emails per day (forever free)
- Upgrade to Essentials ($19.95/mo) for 50,000 emails/month
- Pro plan ($89.95/mo) for 100,000 emails/month + dedicated IP

**Best Practices:**
- ✅ Always include unsubscribe link
- ✅ Use descriptive subject lines (not "Hey" or "Quick question")
- ✅ Personalize with recipient name
- ✅ Keep emails under 102KB total size
- ✅ Avoid spam trigger words (Free, Guarantee, Act Now, etc.)
- ✅ Send from consistent sender name
- ✅ Monitor bounce/spam rates (keep under 2%)

### 4. SMS Service Integration
Recommended: Twilio for SMS campaigns
- Create Twilio account
- Add credentials to config.php
- Setup webhook for tracking replies

## Usage

### Adding Leads

**Manual Entry:**
1. Go to "Add Lead" from dashboard
2. Fill in practice information
3. Set priority and assign to rep
4. Add notes about the lead

**CSV Import:**
1. Prepare CSV with columns: practice_name, physician_name, specialty, phone, email, city, state
2. Go to "Import Leads"
3. Upload CSV file
4. Map columns and import

### Creating Campaigns

1. Go to "New Campaign"
2. Select campaign type (Email, SMS, Phone)
3. Choose template or create custom
4. Set targeting criteria (specialty, state, volume)
5. Schedule campaign
6. Launch campaign

### Managing Follow-ups

1. Dashboard shows "Follow-ups Today" count
2. Click to see list of leads requiring follow-up
3. Log activity (email sent, call made, demo scheduled)
4. Set next follow-up date
5. Update lead status

### Tracking Performance

**Campaign Metrics:**
- Total sent
- Open rate (email)
- Click rate (email)
- Reply rate
- Conversion rate (lead → registered)

**Rep Metrics:**
- Leads assigned
- Outreach activity
- Conversion rate
- Revenue attributed

## Outreach Best Practices

### Email Outreach
- **Subject Lines**: Focus on value ("Save 6 Hours Per Week" vs "CollagenDirect Introduction")
- **Personalization**: Use {{physician_name}}, {{practice_name}}, {{city}}
- **Keep It Short**: 3-4 paragraphs max
- **Single CTA**: One clear call-to-action
- **Follow-up Sequence**:
  - Day 0: Initial email
  - Day 3: Followup #1 (if no response)
  - Day 7: Followup #2 (different angle)
  - Day 14: Breakup email ("Should I close your file?")

### SMS Outreach
- **Be Brief**: 160 characters max
- **Include Name**: Personalize with doctor's name
- **Clear Value**: State benefit immediately
- **Easy Response**: Make it simple to reply
- **Timing**: Send between 10am-4pm, Tuesday-Thursday

### Phone Outreach
- **Get Past Gatekeeper**: "I work with wound care physicians to help them reduce ordering time"
- **Discovery Questions**: Ask about current pain points
- **Listen More Than Talk**: 60% listening, 40% talking
- **Book Next Step**: Always end with scheduled follow-up

## Value Propositions to Emphasize

### Primary Benefits:
1. **Time Savings**: 6 hours per week freed up (20 min → 2 min per order)
2. **Extra Revenue**: $12,000+ per month from seeing 6-8 more patients
3. **Cash Flow Protection**: $36,000+ annual savings from eliminating denials
4. **Faster Delivery**: 36-72 hours vs 5-7 days

### Objection Responses:
- **"We have a supplier"**: Even if you stay with them, wouldn't it be good to have a backup for urgent cases?
- **"We're too busy"**: That's exactly why I'm calling - save 6 hours per week that goes back to patient care
- **"Send me info"**: I'd be happy to! Are you more interested in faster delivery, eliminating denials, or reducing staff time?

## Integration with Demo Microsite

The demo microsite (`/demo/`) should be used during the sales process:
- **Initial Email**: Include link to ROI calculator
- **Follow-up Email**: Share full demo site
- **During Calls**: Reference specific sections (patient throughput, cash flow)
- **After Demo**: Send demo link for them to review with team

## Compliance & Legal

### CAN-SPAM Compliance (Email):
- ✅ Include physical mailing address
- ✅ Clear unsubscribe link
- ✅ Honor unsubscribe within 10 days
- ✅ Accurate "From" name and subject line

### TCPA Compliance (SMS):
- ✅ Obtain consent before texting
- ✅ Provide opt-out instructions
- ✅ Honor opt-outs immediately
- ✅ Only text business contacts (not personal cells without consent)

### Do Not Contact List:
- Maintain internal DNC list
- Check against list before all outreach
- Honor requests permanently

## Roadmap

### Phase 1 (Current):
- [x] Lead database and management
- [x] Email templates
- [x] SMS templates
- [x] Call scripts
- [x] Manual campaign creation

### Phase 2 (Planned):
- [ ] Automated drip campaigns
- [ ] Email tracking (opens, clicks)
- [ ] SMS two-way messaging
- [ ] Calendar integration for demo booking
- [ ] CRM integration

### Phase 3 (Future):
- [ ] AI-powered lead scoring
- [ ] Predictive analytics (best time to contact)
- [ ] Auto-personalization of messages
- [ ] Voice AI for initial outreach calls
- [ ] LinkedIn integration

## Support

For questions or issues:
- Email: tech@collagendirect.health
- Internal Slack: #sales-agent

## License

Internal use only - CollagenDirect proprietary software
