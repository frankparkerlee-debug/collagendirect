# Backlink Agent Setup Guide

The Backlink Agent continuously discovers and executes backlink opportunities for CollagenDirect.

## 🚀 Features

### Automated Discovery
- **Google Custom Search**: Finds "write for us" and guest posting pages
- **Reddit Integration**: Discovers relevant discussions in healthcare subreddits
- **Resource Page Discovery**: Finds pages linking to similar resources
- **Continuous Operation**: Runs automatically on schedule

### Automated Execution
- **Email Outreach**: Sends personalized pitches via SendGrid
- **Content Generation**: Creates forum posts and blog comments
- **Submission Tracking**: PostgreSQL database tracks all activities
- **Follow-up Management**: Automatically follows up after 7 days

## 📋 Prerequisites

### Required
- PostgreSQL database (already set up)
- PHP 7.4+ with cURL support

### Optional (for full automation)
- **Google Custom Search API** - For discovering new opportunities
- **SendGrid API** - For automated email sending
- **Hunter.io API** - For finding contact emails

## 🔧 Configuration

### Step 1: Set Environment Variables

Add these to your Render environment variables or `.env` file:

```bash
# SendGrid (for email automation)
SENDGRID_KEY=SG.your_sendgrid_api_key_here

# Google Custom Search (for opportunity discovery)
GOOGLE_SEARCH_API_KEY=your_google_api_key_here
GOOGLE_SEARCH_CX=your_custom_search_engine_id_here

# Hunter.io (optional - for email finding)
HUNTER_IO_KEY=your_hunter_io_api_key_here

# Reddit (optional - uses public API by default)
REDDIT_CLIENT_ID=your_reddit_client_id
REDDIT_CLIENT_SECRET=your_reddit_client_secret
```

### Step 2: Get API Keys

#### SendGrid (Recommended - FREE tier available)
1. Go to https://sendgrid.com/
2. Sign up for free account (100 emails/day)
3. Create API key: Settings → API Keys → Create API Key
4. Set `SENDGRID_KEY` environment variable

#### Google Custom Search API (Recommended - 100 searches/day FREE)
1. Go to https://console.cloud.google.com/
2. Create new project
3. Enable "Custom Search API"
4. Create credentials → API Key
5. Create Custom Search Engine at https://cse.google.com/
   - Add sites: `*` (search entire web)
   - Get Search Engine ID (cx)
6. Set `GOOGLE_SEARCH_API_KEY` and `GOOGLE_SEARCH_CX`

#### Hunter.io (Optional - 50 searches/month FREE)
1. Go to https://hunter.io/
2. Sign up for free account
3. Get API key from Dashboard
4. Set `HUNTER_IO_KEY` environment variable

## 🎯 Usage

### Web Interface

Access the dashboard:
```
https://collagendirect.health/admin/backlink-agent.php
```

Click **"Run Campaign Now"** to:
- Discover new opportunities via Google and Reddit
- Process pending submissions
- Send outreach emails
- Generate comprehensive report

### Command Line (for Cron Jobs)

Run manually:
```bash
php /var/www/html/admin/backlink-agent.php
```

### Automated Scheduling

#### On Render

Add a cron job in `render.yaml`:
```yaml
- type: cron
  name: backlink-agent
  schedule: "0 9 * * *"  # Daily at 9 AM
  command: php /var/www/html/admin/backlink-agent.php
```

#### Via Linux Cron

Edit crontab:
```bash
crontab -e
```

Add line:
```bash
# Run daily at 9 AM
0 9 * * * php /var/www/html/admin/backlink-agent.php >> /var/log/backlink-agent.log 2>&1

# Run every Monday at 10 AM
0 10 * * 1 php /var/www/html/admin/backlink-agent.php >> /var/log/backlink-agent.log 2>&1

# Run twice per week (Monday and Thursday at 9 AM)
0 9 * * 1,4 php /var/www/html/admin/backlink-agent.php >> /var/log/backlink-agent.log 2>&1
```

## 📊 How It Works

### Discovery Phase (if APIs configured)

1. **Google Search**: Searches for phrases like:
   - "wound care" "write for us"
   - "medical device" "guest post"
   - "healthcare blog" "contribute"
   - inurl:write-for-us medical

2. **Reddit Discovery**: Scans subreddits:
   - r/nursing
   - r/medicine
   - r/podiatry
   - r/diabetes
   - r/AskDocs

3. **Resource Pages**: Finds:
   - Wound care resource pages
   - Medical device directories
   - Healthcare professional links

### Execution Phase

For each opportunity:

1. **Directory Submissions**: Prepares form data (flagged for manual review)
2. **Email Outreach**:
   - Finds contact email (via Hunter.io or scraping)
   - Generates personalized pitch
   - Sends via SendGrid (or logs for manual sending)
   - Schedules follow-up in 7 days
3. **Forum Posts**: Generates helpful content suggestions
4. **Tracking**: Records everything in database

### Follow-up Phase

- Checks for opportunities needing follow-up
- Sends gentle reminder emails
- Reschedules next follow-up

## 📈 Monitoring

### View Logs

Web interface shows real-time logs during execution.

Command line logs saved to:
```
/var/www/html/admin/backlink_agent.log
```

View logs:
```bash
tail -f /var/www/html/admin/backlink_agent.log
```

### Database Queries

Check total opportunities:
```sql
SELECT type, priority, status, COUNT(*)
FROM backlink_opportunities
GROUP BY type, priority, status
ORDER BY type, priority;
```

Check recent activity:
```sql
SELECT o.url, o.type, o.status, s.submission_date, s.success
FROM backlink_submissions s
JOIN backlink_opportunities o ON s.opportunity_id = o.id
ORDER BY s.submission_date DESC
LIMIT 20;
```

Find opportunities needing manual work:
```sql
SELECT * FROM backlink_opportunities
WHERE status = 'needs_manual'
ORDER BY priority DESC, created_at DESC;
```

## 🎛️ Configuration Options

### Without API Keys

Agent still works with hardcoded list of 10+ high-value opportunities:
- Thomas Register, Manta, Yellow Pages directories
- Wound Care Advisor, Today's Wound Clinic publications
- Healthcare forums and blogs

### With API Keys

Discovers 20-50+ new opportunities per run:
- Guest posting sites
- Reddit discussions
- Resource pages
- Blog comment opportunities

## 🔄 Recommended Schedule

- **Daily**: If you have SendGrid and want aggressive outreach
- **3x per week**: Balanced approach (Mon, Wed, Fri)
- **Weekly**: Conservative approach (every Monday)

## 🛡️ Best Practices

1. **Start Without APIs**: Test with hardcoded list first
2. **Add SendGrid Next**: Enable automated email sending
3. **Add Google Search**: Scale up discovery
4. **Monitor Results**: Check dashboard weekly
5. **Manual Review**: Review "needs_manual" items monthly

## 📞 Support

For issues or questions:
- Check logs: `/var/www/html/admin/backlink_agent.log`
- Review database: `SELECT * FROM backlink_opportunities ORDER BY created_at DESC LIMIT 10`
- Check environment variables are set correctly

## 🎯 Expected Results

### Week 1 (No APIs)
- 10-15 opportunities cataloged
- Email drafts prepared
- 2-3 directory submissions flagged

### Week 2-4 (With APIs)
- 50-100 opportunities discovered
- 10-20 outreach emails sent
- 5-10 forum participation opportunities
- 3-5 backlinks acquired

### Month 2+
- 200+ opportunities in database
- 20-30 backlinks acquired
- Improved domain authority
- Better search rankings
