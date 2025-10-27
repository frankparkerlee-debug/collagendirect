# SendGrid Templates Required

## Overview

CollagenDirect requires **7 SendGrid Dynamic Templates** to be configured. Each template ID must be added to your Render environment variables.

## Required Environment Variables

Add these to your Render dashboard (Dashboard → Environment):

### 1. Authentication Templates

| Variable Name | Purpose | When Sent |
|--------------|---------|-----------|
| `SG_TMPL_PASSWORD_RESET` | Password reset email | When user clicks "Forgot Password" |
| `SG_TMPL_ACCOUNT_CONFIRMATION` | Patient account confirmation | When new patient account is created |
| `SG_TMPL_PHYSACCOUNT_CONFIRMATION` | Physician account confirmation | When new physician account is created |

### 2. Order Status Templates

| Variable Name | Purpose | When Sent |
|--------------|---------|-----------|
| `SG_TMPL_ORDER_RECEIVED` | Order received notification | When patient submits new order |
| `SG_TMPL_ORDER_APPROVED` | Order approved notification | When admin approves order |
| `SG_TMPL_ORDER_SHIPPED` | Order shipped notification | When admin adds tracking info |
| `SG_TMPL_ORDER_DELIVERED` | Order delivered notification | When shipment is delivered |

## How to Set Up

### Step 1: Create SendGrid Templates

1. Log into SendGrid at https://app.sendgrid.com
2. Navigate to **Email API → Dynamic Templates**
3. Click **Create a Dynamic Template**
4. Create each of the 7 templates listed above
5. For each template:
   - Give it a descriptive name (e.g., "CollagenDirect - Password Reset")
   - Add a version with your HTML design
   - Note the Template ID (format: `d-xxxxxxxxxxxxxxxxxxxxxxxx`)

### Step 2: Update SendGrid API Key

The current API key is returning 403 Forbidden errors. You need to:

1. Go to **Settings → API Keys**
2. Click **Create API Key**
3. Name it (e.g., "CollagenDirect Production")
4. Select **Full Access** for "Mail Send"
5. Copy the new API key (starts with `SG.`)
6. Update `SENDGRID_API_KEY` in Render environment variables

### Step 3: Add Template IDs to Render

1. Go to your Render dashboard
2. Select your service
3. Go to **Environment** tab
4. Add each template variable with its ID:

```
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxxxxxxx
SG_TMPL_PASSWORD_RESET=d-xxxxxxxxxxxxxxxxxxxxxxxx
SG_TMPL_ACCOUNT_CONFIRMATION=d-xxxxxxxxxxxxxxxxxxxxxxxx
SG_TMPL_PHYSACCOUNT_CONFIRMATION=d-xxxxxxxxxxxxxxxxxxxxxxxx
SG_TMPL_ORDER_RECEIVED=d-xxxxxxxxxxxxxxxxxxxxxxxx
SG_TMPL_ORDER_APPROVED=d-xxxxxxxxxxxxxxxxxxxxxxxx
SG_TMPL_ORDER_SHIPPED=d-xxxxxxxxxxxxxxxxxxxxxxxx
SG_TMPL_ORDER_DELIVERED=d-xxxxxxxxxxxxxxxxxxxxxxxx
```

5. Click **Save Changes**
6. Render will automatically redeploy your service

## Template Data Variables

Each template receives dynamic data. Here's what variables are available:

### Password Reset Template
```json
{
  "first_name": "John",
  "reset_url": "https://collagendirect.health/portal/reset/?selector=xxx&token=xxx",
  "support_email": "support@collagendirect.health",
  "year": "2025"
}
```

### Account Confirmation Templates
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "login_url": "https://collagendirect.health/portal/login",
  "support_email": "support@collagendirect.health",
  "year": "2025"
}
```

### Order Status Templates
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "order_id": "123",
  "order_date": "2025-10-27",
  "status": "Approved",
  "tracking_number": "1Z999AA10123456784",
  "carrier": "UPS",
  "tracking_url": "https://www.ups.com/track?tracknum=1Z999AA10123456784",
  "portal_url": "https://collagendirect.health/portal",
  "support_email": "support@collagendirect.health",
  "year": "2025"
}
```

## Testing

After setting up the templates and updating Render:

1. Wait 2-3 minutes for Render deployment to complete
2. Test password reset: https://collagendirect.health/portal/forgot
3. Check email diagnostic: https://collagendirect.onrender.com/admin/check-email-config.php
4. All templates should show ✓ with their template IDs
5. SendGrid API test should return status 200

## Troubleshooting

### 403 Forbidden Error
- API key is invalid, expired, or doesn't have Mail Send permissions
- Generate a new API key with Full Access to Mail Send

### Template Not Found
- Verify template ID is correct (starts with `d-`)
- Verify template is published (has at least one active version)

### Emails Not Sending
- Check SendGrid Activity Feed for delivery status
- Verify sender domain is authenticated in SendGrid
- Check spam folder

## Related Files

- Email sending function: `/api/lib/sg_curl.php`
- Password reset endpoint: `/api/auth/request_reset.php`
- Order status notifications: `/admin/run-notification-migration.php`
- Diagnostic tool: `/admin/check-email-config.php`
