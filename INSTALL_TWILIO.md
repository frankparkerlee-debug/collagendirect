# Install Twilio SDK on Production Server

The Twilio SDK needs to be installed on the production server to enable SMS/MMS photo uploads.

## Quick Install

Run these commands on the production server:

```bash
ssh collagendirect.health
cd /var/www/html
git pull
composer install
```

## If Composer Not Installed

If you get "composer: command not found", install Composer first:

```bash
ssh collagendirect.health
cd /var/www/html
curl -sS https://getcomposer.org/installer | php
php composer.phar install
```

## Verify Installation

After installation, verify the Twilio SDK is working:

```bash
php -r "require 'vendor/autoload.php'; echo 'Twilio SDK loaded successfully';"
```

You should see: `Twilio SDK loaded successfully`

## What This Enables

Once installed, the wound photo upload system will be fully operational:

1. ✅ Physicians can request wound photos from patients
2. ✅ Patients receive SMS: "Please send a photo of your wound by replying to this message"
3. ✅ Patients text photo back via MMS
4. ✅ Twilio webhook receives photo and saves to database
5. ✅ Photo appears in physician's "Photo Reviews" page
6. ✅ Physician reviews with one-click assessment
7. ✅ System generates E/M billing code and clinical note automatically

## Twilio Configuration Check

Your Twilio credentials should already be configured in the environment:

- `TWILIO_ACCOUNT_SID`
- `TWILIO_AUTH_TOKEN`
- `TWILIO_PHONE_NUMBER`

If not set, add them to your environment configuration (Render environment variables).

## Test the System

After installation:

1. Go to any patient detail page
2. Click "Request Wound Photo" button
3. Patient should receive SMS
4. Patient texts photo back
5. Photo appears in portal/?page=photo-reviews
6. Click photo, select assessment (Improving/Stable/Concern/Urgent)
7. System auto-generates CPT code ($92-$180)

## Troubleshooting

**Error: "Class 'Twilio\Rest\Client' not found"**
- Solution: Run `composer install` on the server

**Error: "vendor/autoload.php not found"**
- Solution: Run `composer install` to create vendor directory

**SMS not sending**
- Check Twilio credentials in environment variables
- Verify Twilio account is active and funded
- Check Twilio webhook is configured: https://collagendirect.health/api/twilio/receive-mms.php

**Photos not appearing after SMS reply**
- Check Twilio webhook logs: https://console.twilio.com/
- Verify webhook URL is correct in Twilio console
- Check server logs: `tail -f /var/log/php_errors.log`
