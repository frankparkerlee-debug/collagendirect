# Render Deployment Checklist

## Pre-Deployment Status
- ✅ All migrations committed and pushed
- ✅ Request Photo button added to patient detail page
- ✅ Photo-to-order linking implemented (database + API + webhook)
- ✅ Dockerfile updated with Composer and Twilio SDK installation
- ✅ All code committed to GitHub
- ✅ wound_photos upload directory added to Dockerfile

## Next: Render Deployment

Once you push to GitHub, Render will automatically:
1. Pull latest code
2. Build Docker image with updated Dockerfile
3. Run `composer install` during build
4. Install Twilio SDK to vendor/ directory
5. Create wound_photos upload directory

## Post-Deployment Verification Steps

### 1. Verify Twilio SDK Installation
Test via web browser:
```
https://collagendirect.health/admin/install-twilio-sdk.php
```

Expected output:
```
✓ composer.json found
✓ vendor directory exists
✓ Twilio SDK already installed
✓ Twilio SDK loaded successfully!

=== Installation Complete ===
The Twilio SDK is ready to use.
```

### 2. Run Migrations (if not already done)
```
https://collagendirect.health/admin/run-all-migrations.php
```

Expected: All 4 migrations showing "✓ Already applied"

### 3. Test Request Photo Button

1. Go to any patient detail page
2. Verify patient has phone number
3. Click "Request Photo" button
4. Optionally enter an order ID
5. Click OK

**Expected Results:**
- ✅ Confirmation message appears
- ✅ Patient receives SMS: "Hi [Name], please send a photo of your wound by replying to this message. Upload link: https://collagendirect.health/api/twilio/upload?token=[token]"
- ✅ Record created in photo_requests table

### 4. Test Photo Upload via SMS

1. Have patient reply to SMS with a photo
2. Check that webhook receives photo

**Expected Results:**
- ✅ Photo saved to /uploads/wound_photos/
- ✅ Record created in wound_photos table
- ✅ If order_id was provided, photo.order_id is set
- ✅ Photo appears in Photo Reviews page

### 5. Test Photo Review

1. Go to portal/?page=photo-reviews
2. Click on pending photo
3. Select assessment (Improving/Stable/Concern/Urgent)
4. Click Submit

**Expected Results:**
- ✅ Billable encounter created with CPT code (99213/99214/99215)
- ✅ Photo marked as reviewed
- ✅ Photo removed from pending queue

### 6. Test Billing Export

1. Review a few photos
2. Go to Photo Reviews page
3. Click "Export CSV"

**Expected Results:**
- ✅ CSV downloads with billing data
- ✅ Contains patient info, CPT codes, charges
- ✅ Ready for billing system import

## Troubleshooting

### If Twilio SDK Not Found
```bash
# SSH to Render
# Check if vendor directory exists
ls -la /var/www/html/vendor/

# Check if Twilio SDK installed
ls -la /var/www/html/vendor/twilio/

# Check Composer log
cat /var/log/composer.log
```

### If Photos Not Saving
```bash
# Check upload directory permissions
ls -la /var/www/html/uploads/wound_photos/

# Should show:
# drwxr-xr-x www-data www-data
```

### If SMS Not Sending
1. Verify Twilio credentials in Render environment variables:
   - TWILIO_ACCOUNT_SID
   - TWILIO_AUTH_TOKEN
   - TWILIO_PHONE_NUMBER

2. Check Twilio Console logs at https://console.twilio.com/

### If Webhook Not Receiving Photos
1. Verify webhook URL in Twilio Console:
   - Go to: Phone Numbers → Active Numbers
   - Click your number
   - "A MESSAGE COMES IN" should be:
     - URL: `https://collagendirect.health/api/twilio/receive-mms.php`
     - Method: POST

## System Ready Indicators

Once all tests pass, the system is fully operational:

- ✅ Physicians can request wound photos with optional order linking
- ✅ Patients receive SMS and can reply with photos
- ✅ Photos automatically linked to treatment orders
- ✅ Photos appear in review queue
- ✅ Billing codes generated automatically ($92-$180 per review)
- ✅ CSV export ready for billing systems

## Next Features to Implement

After system is operational, implement remaining features:

### Priority 1: Display Photos in Patient Profile (3-4 hours)
See [WOUND_PHOTO_ENHANCEMENTS.md](WOUND_PHOTO_ENHANCEMENTS.md) section #3
- Show photos grouped by order
- Display review status
- Clickable thumbnails

### Priority 2: Notification System (2-3 hours)
See [WOUND_PHOTO_ENHANCEMENTS.md](WOUND_PHOTO_ENHANCEMENTS.md) section #2
- Red dot indicator when new photos arrive
- Per-patient notification counts
- Auto-clear on view

### Priority 3: Automated Scheduler (4-5 hours)
See [WOUND_PHOTO_ENHANCEMENTS.md](WOUND_PHOTO_ENHANCEMENTS.md) section #4
- Scheduled photo requests based on treatment frequency
- Cron job setup
- 4x/week, daily, 3x/week, 2x/week options

## Revenue Projection

**Conservative** (10 photos/week):
- 10 photos × $92 (CPT 99213) = $920/week
- $3,680/month

**Moderate** (20 photos/week):
- 15 photos × $92 (CPT 99213) = $1,380
- 5 photos × $135 (CPT 99214) = $675
- **Total: $2,055/week = $8,220/month**

**High Volume** (40 photos/week):
- 20 photos × $92 (CPT 99213) = $1,840
- 15 photos × $135 (CPT 99214) = $2,025
- 5 photos × $180 (CPT 99215) = $900
- **Total: $4,765/week = $19,060/month**

## Documentation

| Document | Purpose |
|----------|---------|
| [DEPLOYMENT_STATUS.md](DEPLOYMENT_STATUS.md) | Overall system status |
| [PHOTO_REVIEW_SYSTEM.md](PHOTO_REVIEW_SYSTEM.md) | Complete API reference |
| [WOUND_PHOTO_ENHANCEMENTS.md](WOUND_PHOTO_ENHANCEMENTS.md) | Implementation guide for remaining features |
| [RENDER_DEPLOYMENT_CHECKLIST.md](RENDER_DEPLOYMENT_CHECKLIST.md) | This file - deployment verification |

## Support

If issues arise:
1. Check Render deployment logs
2. Verify environment variables
3. Test Twilio webhook manually
4. Review database migration status
5. Check file permissions on upload directories
