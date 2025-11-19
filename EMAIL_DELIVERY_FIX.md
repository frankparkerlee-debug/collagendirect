# Email Delivery Issue - Root Cause & Fix

## Problem Summary
Users are not receiving emails after registration or password reset, even though test emails work locally.

## Root Cause
**The `.env` file is not deployed to production servers (Render).**

When your code runs on Render:
- The `api/.env` file does NOT exist on the production server
- The `env()` function in `api/lib/env.php` tries to load from the `.env` file first, then falls back to system environment variables via `getenv()`
- If `SENDGRID_API_KEY` is not set as a Render environment variable, the email sending functions fail silently

## Evidence

### Local Environment ✅
```bash
SENDGRID_API_KEY from env(): SET (69 chars, prefix: SG.NBDV)
.env file exists: YES
```

### Production Environment (Expected if not configured) ❌
```bash
SENDGRID_API_KEY from getenv(): NOT SET
.env file does NOT exist on Render
```

## Solution

You need to configure SendGrid environment variables in **Render Dashboard**:

### Step 1: Access Render Dashboard
1. Go to https://dashboard.render.com
2. Select your `collagendirect` web service
3. Click on "Environment" in the left sidebar

### Step 2: Add Required Environment Variables

Add the following environment variables with values from your `api/.env` file:

| Variable Name | Value | Required |
|---------------|-------|----------|
| `SENDGRID_API_KEY` | `<your-sendgrid-api-key>` | ✅ Yes |
| `SMTP_FROM` | `no-reply@collagendirect.health` | ✅ Yes |
| `SMTP_FROM_NAME` | `CollagenDirect` | ✅ Yes |
| `SG_TMPL_PASSWORD_RESET` | `d-41ea629107c54e0abc1dcbd654c9e498` | Optional |
| `SG_TMPL_ACCOUNT_CONFIRM` | `d-c33b0338c94544bda58c885892ce2f53` | Optional |
| `SG_TMPL_PHYSACCOUNT_CONFIRM` | `d-12d5c5a34f5f4fe19424db7d88f44ab5` | Optional |
| `SG_TMPL_ORDER_RECEIVED` | `d-32c6aee2093b4363b10a5ab4f23c9230` | Optional |
| `SG_TMPL_ORDER_APPROVED` | `d-e73bec2b87bf45ba9108eb9c1fcf850b` | Optional |
| `SG_TMPL_ORDER_SHIPPED` | `d-0b24b64993e149329a7d0702b0db4c65` | Optional |
| `SG_TMPL_MANUFACTURER_ORDER` | `d-67cf6288aacd45b9a55a8d84fe0d2917` | Optional |

### Step 3: Save and Redeploy
1. Click "Save Changes" in Render
2. Render will automatically redeploy your service
3. Wait for deployment to complete (~2-3 minutes)

### Step 4: Verify Email Sending Works

#### Option A: Test via Diagnostic Tool (Recommended)
1. Navigate to: https://collagendirect.health/admin/diagnose-email-issue.php
2. Review all checks (should show ✅ for API key)
3. Enter your email in the test form
4. Click "Send Test Email"
5. Check your inbox (and spam folder)

#### Option B: Test via Registration
1. Register a new test account at: https://collagendirect.health/portal
2. Check the email inbox for the welcome email
3. Also check spam/junk folder

#### Option C: Test via Password Reset
1. Go to: https://collagendirect.health/portal/reset
2. Enter an existing user's email
3. Click "Reset Password"
4. Check inbox for password reset email

## How the Email System Works

### Email Flow for Registration
1. User submits registration form → `api/register.php`
2. User record created in database
3. `api/register.php` calls `send_registration_welcome_email()` (line 191)
4. `lib/registration_welcome.php` loads `SENDGRID_API_KEY` via `getenv()`
5. Calls `sg_curl_send()` to SendGrid API
6. SendGrid delivers email to user's inbox

### Email Flow for Password Reset
1. User clicks "Forgot Password" → `api/auth/request_reset.php`
2. Reset token generated and stored in database
3. `request_reset.php` calls `send_password_reset_email()` (line 162)
4. `lib/email_notifications.php` loads template ID via `env()`
5. Calls `sg_send()` which uses SendGrid API
6. SendGrid delivers email with reset link

## Why Tests Work But Production Doesn't

**Local Testing:**
- Uses `api/.env` file which has `SENDGRID_API_KEY`
- `env()` function reads from `.env` file successfully
- Emails send correctly

**Production (Before Fix):**
- `.env` file doesn't exist (not in git, not uploaded)
- `env()` function tries to load `.env` → fails
- Falls back to `getenv()` → returns empty if not set in Render
- Email functions fail silently with error logs only

**Production (After Fix):**
- Environment variables set in Render dashboard
- `env()` function tries `.env` → fails (file doesn't exist)
- Falls back to `getenv()` → succeeds! ✅
- Email functions work correctly

## Additional Checks

### Verify SendGrid Account Status
1. Login to SendGrid: https://app.sendgrid.com
2. Check **Sender Authentication** - ensure `collagendirect.health` domain is verified
3. Check **Email Activity** - view recent email attempts and delivery status
4. Check **API Keys** - ensure the key is active (not revoked)

### Verify Domain Authentication (Important!)
If emails are sent but not received, the issue might be domain authentication:

1. Go to: https://app.sendgrid.com/settings/sender_auth
2. Authenticate your domain: `collagendirect.health`
3. Add the DNS records provided by SendGrid to your domain DNS
4. Wait for verification (can take up to 48 hours, usually faster)

Without domain authentication, emails may be marked as spam or rejected by recipient servers.

## Monitoring Email Delivery

### View SendGrid Activity Feed
https://app.sendgrid.com/email_activity

This shows:
- All emails sent via your API key
- Delivery status (delivered, bounced, spam, dropped)
- Recipient email addresses
- Timestamps
- Error messages if delivery failed

### Check Application Logs in Render
1. Go to Render Dashboard → Your Service
2. Click "Logs" tab
3. Search for `"email"` or `"SendGrid"`
4. Look for error messages like:
   - `"SendGrid API key not configured"`
   - `"Failed to send registration welcome email"`
   - `"Password reset email sent successfully"`

## Common Issues After Fix

### Issue 1: Emails go to spam
**Solution:** Configure domain authentication in SendGrid (see above)

### Issue 2: Emails not received at all
**Solutions:**
- Check SendGrid Activity Feed for delivery status
- Verify recipient email address is valid
- Check if recipient's mail server is blocking SendGrid IPs
- Try sending to a different email address (Gmail, Outlook, etc.)

### Issue 3: Template-based emails fail
**Symptoms:** Plain-text emails work, but template emails don't
**Solutions:**
- Verify template IDs in Render environment variables match SendGrid
- Check templates exist and are active in SendGrid dashboard
- Review template dynamic data fields match what's being sent

## Files Modified

### New Files
- `admin/diagnose-email-issue.php` - Comprehensive email diagnostic tool

### Existing Files (No Changes Needed)
- `api/lib/env.php` - Already has fallback to `getenv()`
- `api/lib/sg_curl.php` - Email sending functions
- `api/lib/registration_welcome.php` - Registration emails
- `api/lib/email_notifications.php` - Password reset & order emails
- `api/register.php` - Registration handler
- `api/auth/request_reset.php` - Password reset request handler

## Security Notes

⚠️ **DO NOT commit `.env` file to git** - It contains sensitive API keys

The `.env` file is already in `.gitignore`, which is correct. Always configure sensitive credentials via:
- Render Dashboard (production)
- Local `.env` file (development)
- Never hardcode in source code

## Summary

**Before Fix:**
- `.env` file not on production server
- `SENDGRID_API_KEY` not set in Render environment
- Emails fail silently
- Error logs show: "SendGrid API key not configured"

**After Fix:**
- Set `SENDGRID_API_KEY` in Render environment variables
- `env()` function retrieves key via `getenv()`
- Emails send successfully
- Users receive registration and password reset emails

**Action Required:** Add environment variables to Render Dashboard as specified in Step 2 above.
