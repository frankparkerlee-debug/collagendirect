# Email Notification System - Deployment Guide

## Overview

This guide covers deploying the complete email notification system for CollagenDirect, including:
1. Manufacturer order notifications with document attachments
2. Patient delivery confirmation emails (insurance compliance)
3. Password reset emails
4. New account welcome emails
5. Physician batched status updates

---

## Step 1: Run Database Migration

The system requires two new tables:
- `order_delivery_confirmations` - Track patient delivery confirmations
- `order_status_changes` - Log order status changes for physician notifications

### Deploy Migration

**Option A: Via Shell Script (Local)**
```bash
cd /Users/parkerlee/CollageDirect2.1/collagendirect
./run-notification-migration.sh
```

**Option B: Via Web (Remote)**
```bash
curl -s "https://collagendirect.onrender.com/migrations/run-notification-migration.php"
```

**Option C: Via Git Push (Automatic)**
```bash
git add migrations/
git commit -m "Add email notification database tables"
git push
# Then manually run the migration via curl
```

### Verify Migration

Check that tables were created:
```sql
SELECT table_name FROM information_schema.tables
WHERE table_name IN ('order_delivery_confirmations', 'order_status_changes');
```

Expected output: Both tables should appear.

---

## Step 2: Configure SendGrid Templates

### Required Template IDs (Already in .env.example)

```bash
SG_TMPL_PASSWORD_RESET=d-41ea629107c54e0abc1dcbd654c9e498
SG_TMPL_ACCOUNT_CONFIRM=d-c33b0338c94544bda58c885892ce2f53
SG_TMPL_ORDER_CONFIRM=d-c9ddf972a5d04477b5d8654fecfabbdc
```

### Verify Templates in SendGrid Dashboard

1. Log in to SendGrid: https://app.sendgrid.com
2. Navigate to Email API → Dynamic Templates
3. Verify these three templates exist:
   - **Password Reset** (d-41ea629107c54e0abc1dcbd654c9e498)
   - **Account Confirmation** (d-c33b0338c94544bda58c885892ce2f53)
   - **Order Confirmation** (d-c9ddf972a5d04477b5d8654fecfabbdc)

### Template Variables

Each template should support these handlebars variables:

**Password Reset Template:**
- `{{first_name}}` - User's first name
- `{{reset_url}}` - Password reset link
- `{{support_email}}` - Support email address
- `{{year}}` - Current year

**Account Confirmation Template:**
- `{{name}}` - User's full name
- `{{email}}` - User's email
- `{{temp_password}}` - Temporary password
- `{{login_url}}` - Login page URL
- `{{year}}` - Current year

**Order Confirmation Template:**
- `{{patient_name}}` - Patient's full name
- `{{order_id}}` - Order ID
- `{{product_name}}` - Product name
- `{{physician_name}}` - Prescribing physician
- `{{confirm_url}}` - Delivery confirmation link
- `{{support_email}}` - Support email
- `{{year}}` - Current year

---

## Step 3: Deploy Code Changes

### Files Modified/Created

**Modified:**
- `api/lib/order_manufacturer_notification.php` - Added document attachments

**Created:**
- `migrations/create-notification-tables.sql` - Database schema
- `migrations/run-notification-migration.php` - Migration runner
- `api/lib/patient_delivery_notification.php` - Patient emails
- `api/lib/physician_status_notification.php` - Physician batch emails
- `api/cron/send-delivery-confirmations.php` - Cron job
- `api/cron/send-physician-status-updates.php` - Cron job
- `api/patient/confirm-delivery.php` - Confirmation handler

### Deploy via Git

```bash
cd /Users/parkerlee/CollageDirect2.1/collagendirect

# Add all changes
git add api/lib/order_manufacturer_notification.php
git add api/lib/patient_delivery_notification.php
git add api/lib/physician_status_notification.php
git add api/cron/
git add api/patient/
git add migrations/
git add EMAIL_NOTIFICATIONS_MAP.md
git add EMAIL_NOTIFICATION_DEPLOYMENT.md

# Commit
git commit -m "Implement complete email notification system

- Fix manufacturer emails to attach documents (ID, Insurance, Notes)
- Add patient delivery confirmation emails (insurance compliance)
- Add physician batched status update emails
- Add database tables for tracking
- Add cron jobs for scheduled emails"

# Push to deploy
git push
```

---

## Step 4: Set Up Cron Jobs

Since Render free tier doesn't support cron jobs natively, we have several options:

### Option A: External Cron Service (Recommended)

Use a free service like **cron-job.org** or **EasyCron**:

1. **Delivery Confirmations** (Daily at 10 AM UTC)
   - URL: `https://collagendirect.onrender.com/api/cron/send-delivery-confirmations.php`
   - Schedule: `0 10 * * *`

2. **Physician Status Updates** (Daily at 5 PM UTC)
   - URL: `https://collagendirect.onrender.com/api/cron/send-physician-status-updates.php`
   - Schedule: `0 17 * * *`

### Option B: GitHub Actions (Free)

Create `.github/workflows/email-cron.yml`:

```yaml
name: Email Notification Cron Jobs

on:
  schedule:
    - cron: '0 10 * * *'  # Delivery confirmations - 10 AM UTC
    - cron: '0 17 * * *'  # Physician updates - 5 PM UTC

jobs:
  delivery-confirmations:
    if: github.event.schedule == '0 10 * * *'
    runs-on: ubuntu-latest
    steps:
      - name: Send Delivery Confirmations
        run: curl -s https://collagendirect.onrender.com/api/cron/send-delivery-confirmations.php

  physician-updates:
    if: github.event.schedule == '0 17 * * *'
    runs-on: ubuntu-latest
    steps:
      - name: Send Physician Status Updates
        run: curl -s https://collagendirect.onrender.com/api/cron/send-physician-status-updates.php
```

### Option C: Manual Testing (Development)

Run cron jobs manually for testing:

```bash
# Test delivery confirmations
curl -s "https://collagendirect.onrender.com/api/cron/send-delivery-confirmations.php"

# Test physician status updates
curl -s "https://collagendirect.onrender.com/api/cron/send-physician-status-updates.php"
```

---

## Step 5: Testing Each Notification

### 1. Test Manufacturer Order Notification

**Trigger:** Create a new order in the portal

**Expected Behavior:**
- Manufacturer receives email immediately
- Email includes 3 attachments: ID card, Insurance card, Prescription note
- Email body lists all order details

**Test:**
```bash
# Create test order via portal
# Then check manufacturer email inbox
```

**Verify Attachments:**
- Check that email has 3 PDF attachments
- Open each attachment to verify content

---

### 2. Test Patient Delivery Confirmation

**Trigger:** Order created 3 days ago with status "shipped" or "delivered"

**Expected Behavior:**
- Patient receives email with "Confirm Delivery" button
- Clicking button opens confirmation page
- Confirmation recorded in database

**Test:**
```bash
# Manually insert test record (backdated 3 days)
psql $DATABASE_URL -c "
INSERT INTO orders (user_id, patient_id, product_id, product, status, created_at)
VALUES ('test-user', 1, 1, 'CollaGEN Plus 4x4', 'shipped', NOW() - INTERVAL '3 days')
RETURNING id;
"

# Run cron job
curl -s "https://collagendirect.onrender.com/api/cron/send-delivery-confirmations.php"

# Check patient email
# Click "Confirm Delivery" link
# Verify confirmation page shows success
```

**Verify Database:**
```sql
SELECT * FROM order_delivery_confirmations WHERE order_id = [test_order_id];
-- Should show: sent_at populated, confirmed_at NULL (until clicked)
```

---

### 3. Test Password Reset

**Trigger:** User clicks "Forgot Password" on login page

**Expected Behavior:**
- User receives email with reset link
- Link expires in 15 minutes
- Uses SendGrid template

**Test:**
```bash
# Via portal login page: Click "Forgot Password"
# Enter email address
# Check email inbox
# Click reset link
# Set new password
```

**Already Working** - No changes needed.

---

### 4. Test New Account Created

**Trigger:** Admin creates new practice manager or physician account

**Expected Behavior:**
- New user receives welcome email with temporary password
- Email includes role-specific instructions

**Test:**
```bash
# Log in to admin panel: https://collagendirect.onrender.com/admin/
# Go to Users section
# Click "Add New User"
# Fill in details and submit
# Check new user's email inbox
```

**Already Working** - No changes needed.

---

### 5. Test Physician Status Updates

**Trigger:** Order status changes OR order expires within 7 days

**Expected Behavior:**
- Physician receives daily batched email
- Email groups multiple patients together
- Shows status changes and expiring orders

**Test:**
```bash
# Change order status to "shipped"
# Via admin panel: https://collagendirect.onrender.com/admin/orders.php
# Select order → Change status to "Shipped" → Add tracking number

# Run cron job
curl -s "https://collagendirect.onrender.com/api/cron/send-physician-status-updates.php"

# Check physician email inbox
# Verify email shows order status change with tracking info
```

**Verify Database:**
```sql
SELECT * FROM order_status_changes WHERE notification_sent_at IS NOT NULL;
-- Should show status change recorded and marked as notified
```

---

## Step 6: Monitoring and Logs

### Check Application Logs

**Render Dashboard:**
1. Go to https://dashboard.render.com
2. Select "collagendirect" service
3. Click "Logs" tab
4. Filter by `[order-notification]`, `[delivery-notification]`, or `[physician-status]`

**Log Patterns to Watch:**
```
[order-notification] Email sent to manufacturer for order #123
[delivery-notification] Email sent to patient for order #123
[physician-status] Batch email sent to user@example.com (3 updates)
```

### Check SendGrid Activity

1. Log in to SendGrid Dashboard
2. Go to Activity Feed
3. Filter by categories:
   - `delivery`, `confirmation` - Patient delivery emails
   - `physician`, `status` - Physician updates
   - `auth`, `password` - Password resets

### Common Issues

**Issue: Emails not sending**
- Check `SENDGRID_API_KEY` environment variable is set in Render
- Check SendGrid activity feed for errors
- Check application logs for `[order-notification] Failed to send`

**Issue: Attachments missing**
- Check file paths exist: `/uploads/ids/`, `/uploads/insurance/`, `/uploads/notes/`
- Check files have correct permissions
- Look for log entries: `[order-notification] File not found: /path/to/file`

**Issue: Cron jobs not running**
- Verify external cron service is configured
- Check cron job logs
- Test manually via curl

---

## Rollback Plan

If issues occur, rollback steps:

1. **Disable Cron Jobs:**
   - Pause external cron service jobs
   - Or comment out GitHub Actions workflow

2. **Revert Code Changes:**
   ```bash
   git revert [commit-hash]
   git push
   ```

3. **Drop Tables (If Needed):**
   ```sql
   DROP TABLE IF EXISTS order_delivery_confirmations;
   DROP TABLE IF EXISTS order_status_changes;
   ```

---

## Success Criteria

✅ All 5 notification types working:
- [x] Manufacturer order notifications with attachments
- [x] Patient delivery confirmations
- [x] Password reset emails
- [x] New account welcome emails
- [x] Physician batched status updates

✅ Database tables created successfully

✅ Cron jobs scheduled and running daily

✅ No errors in application logs

✅ Email delivery rates > 95% (check SendGrid)

---

## Support

For issues or questions:
- Email: support@collagendirect.health
- Documentation: [EMAIL_NOTIFICATIONS_MAP.md](EMAIL_NOTIFICATIONS_MAP.md)
- Logs: Render Dashboard → Logs
- SendGrid: https://app.sendgrid.com

---

**Deployment Date:** [To be filled in]
**Deployed By:** [To be filled in]
**Status:** Ready for deployment
