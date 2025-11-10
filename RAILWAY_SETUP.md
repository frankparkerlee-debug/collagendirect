# Railway Deployment Setup Guide

This guide will help you deploy CollagenDirect to Railway.

## Prerequisites

1. Railway account (sign up at https://railway.app)
2. Railway CLI (optional): `npm i -g @railway/cli`

## Step-by-Step Deployment

### 1. Create a New Project on Railway

1. Go to https://railway.app/dashboard
2. Click "New Project"
3. Select "Deploy from GitHub repo"
4. Connect your GitHub account and select `frankparkerlee-debug/collagendirect`

### 2. Add PostgreSQL Database

1. In your Railway project, click "New"
2. Select "Database" → "Add PostgreSQL"
3. Railway will automatically provision a PostgreSQL database
4. Note: Railway automatically sets `DATABASE_URL` environment variable

### 3. Configure Environment Variables

You have three options to set up variables:

**Option A: Use the automated setup script (Recommended)**
```bash
./railway-setup-vars.sh
```

**Option B: Use the checklist**
Follow [RAILWAY_VARIABLES_CHECKLIST.md](RAILWAY_VARIABLES_CHECKLIST.md) for a step-by-step checklist.

**Option C: Manual setup in Railway dashboard**
In Railway project settings → Variables, add the following:

#### Database Variables (Auto-configured by Railway)
Railway automatically provides:
- `DATABASE_URL` (PostgreSQL connection string)

You need to add these for backward compatibility with the app:
- `DB_HOST` → Reference from PostgreSQL service
- `DB_PORT` → Reference from PostgreSQL service
- `DB_NAME` → Reference from PostgreSQL service
- `DB_USER` → Reference from PostgreSQL service
- `DB_PASS` → Reference from PostgreSQL service

#### Email Configuration (SendGrid)
- `SENDGRID_API_KEY` → Your SendGrid API key
- `SMTP_FROM` → `no-reply@collagendirect.health`
- `SMTP_FROM_NAME` → `CollagenDirect`

#### SendGrid Template IDs
- `SG_TMPL_PASSWORD_RESET` → `d-41ea629107c54e0abc1dcbd654c9e498`
- `SG_TMPL_ACCOUNT_CONFIRM` → `d-c33b0338c94544bda58c885892ce2f53`
- `SG_TMPL_PHYSACCOUNT_CONFIRM` → `d-12d5c5a34f5f4fe19424db7d88f44ab5`
- `SG_TMPL_ORDER_RECEIVED` → `d-32c6aee2093b4363b10a5ab4f23c9230`
- `SG_TMPL_ORDER_APPROVED` → `d-e73bec2b87bf45ba9108eb9c1fcf850b`
- `SG_TMPL_ORDER_SHIPPED` → `d-0b24b64993e149329a7d0702b0db4c65`
- `SG_TMPL_MANUFACTURER_ORDER` → `d-67cf6288aacd45b9a55a8d84fe0d2917`

#### Twilio Configuration (Optional - for SMS)
- `TWILIO_ACCOUNT_SID` → Your Twilio Account SID
- `TWILIO_AUTH_TOKEN` → Your Twilio Auth Token
- `TWILIO_PHONE_NUMBER` → Your Twilio phone number (format: +1234567890)

### 4. Configure Persistent Storage (Volumes)

Railway supports volumes for persistent file storage:

1. In your service settings, go to "Volumes"
2. Click "Add Volume"
3. Mount path: `/var/data/uploads`
4. Size: Choose appropriate size (start with 1GB, can scale up)

This will persist uploaded files (IDs, insurance docs, wound photos, etc.)

### 5. Database Reference Variables

To connect database variables properly:

1. Click on your web service
2. Go to "Variables" tab
3. Click "New Variable" → "Add Reference"
4. Select your PostgreSQL service and map:
   - `DB_HOST` → `PGHOST`
   - `DB_PORT` → `PGPORT`
   - `DB_NAME` → `PGDATABASE`
   - `DB_USER` → `PGUSER`
   - `DB_PASS` → `PGPASSWORD`

### 6. Deploy

Railway will automatically deploy when you push to your main branch on GitHub.

To trigger manual deployment:
- Click "Deploy" in the Railway dashboard
- Or push changes to your GitHub repository

### 7. Post-Deployment

1. Check deployment logs for any errors
2. Visit your Railway-provided URL (found in service settings)
3. Test the health check endpoint: `https://your-app.railway.app/portal/health.php`

## Key Differences from Render

| Feature | Render | Railway |
|---------|--------|---------|
| Config File | `render.yaml` | `railway.toml` (optional) |
| Database | Separate service definition | Add PostgreSQL from dashboard |
| Volumes | Defined in YAML | Add from service settings |
| Env Variables | In YAML or dashboard | Dashboard or CLI |
| Health Checks | In YAML | In `railway.toml` or dashboard |
| Auto-deploy | Yes (on push) | Yes (on push) |

## Troubleshooting

### Database Connection Issues
- Ensure database reference variables are properly set
- Check that PostgreSQL service is running
- Verify `DATABASE_URL` is available

### File Upload Issues
- Confirm volume is mounted at `/var/data/uploads`
- Check volume size hasn't reached capacity
- Verify directory permissions in logs

### Cron Jobs
- Railway supports cron jobs via the Dockerfile setup
- Check logs to confirm cron service started
- Cron logs are at `/var/log/cron.log`

## Useful Railway CLI Commands

```bash
# Login
railway login

# Link to project
railway link

# View logs
railway logs

# Add environment variable
railway variables set KEY=value

# Open dashboard
railway open
```

## Monitoring

Railway provides:
- Real-time deployment logs
- Service metrics (CPU, Memory, Network)
- Uptime monitoring
- Custom health check monitoring

Access these from your project dashboard.

## Cost Considerations

Railway pricing (as of 2024):
- Free tier: $5 credit/month
- Hobby plan: $5/month + usage
- PostgreSQL: ~$5-10/month depending on usage
- Volumes: Based on storage size

Monitor your usage in the Railway dashboard.
