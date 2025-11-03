# Twilio SMS/MMS Photo Upload Setup

## 1. Create Twilio Account

1. Go to https://www.twilio.com/try-twilio
2. Sign up for free trial ($15 credit)
3. Verify your phone number

## 2. Get Your Credentials

After signup, you'll need these values:

1. **Account SID**: Found on dashboard (looks like: `ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`)
2. **Auth Token**: Found on dashboard (click "Show" to reveal)
3. **Phone Number**:
   - Go to Phone Numbers → Buy a Number
   - Search for a number with SMS and MMS capabilities
   - Purchase (costs ~$1/month)

## 3. Add Credentials to Environment

Add these to your `.env` file or environment variables:

```bash
TWILIO_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_TOKEN=your_auth_token_here
TWILIO_PHONE=+15551234567
```

For Render.com deployment, add these as Environment Variables in the dashboard.

## 4. Configure Webhook for Incoming MMS

1. Go to Phone Numbers → Manage → Active Numbers
2. Click your phone number
3. Scroll to "Messaging Configuration"
4. Set "A MESSAGE COMES IN" webhook to:
   ```
   https://collagendirect.health/api/twilio/receive-mms.php
   ```
5. Set to HTTP POST
6. Save

## 5. Install Twilio SDK

```bash
cd /path/to/collagendirect
composer require twilio/sdk
```

## 6. Test Setup

Visit: `https://collagendirect.health/api/twilio/test.php`

This will send a test SMS to verify configuration.

## Cost Estimates

- **Base**: $15/month (includes phone number)
- **Outbound SMS**: $0.0079 per message
- **Inbound MMS (photo)**: $0.02 per message
- **Example**: 200 photos/month = ~$21/month

## Compliance Notes

- Patients must opt-in to SMS communications
- Include opt-out instructions in messages
- HIPAA: Twilio is HIPAA-compliant when configured properly
- Consider using Twilio's HIPAA-eligible services for production
