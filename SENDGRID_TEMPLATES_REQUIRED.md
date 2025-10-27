# SendGrid Templates Required

## Overview

CollagenDirect requires **7 SendGrid Dynamic Templates** to be configured. Each template ID must be added to your Render environment variables.

## Quick Audience Summary

| Template | Audience | Purpose |
|----------|----------|---------|
| `SG_TMPL_PASSWORD_RESET` | All users | Password reset link |
| `SG_TMPL_ACCOUNT_CONFIRMATION` | **Physicians/Practices** | Self-registration confirmation |
| `SG_TMPL_PHYSACCOUNT_CONFIRMATION` | **Physicians/Practices** | Admin-created account welcome |
| `SG_TMPL_ORDER_RECEIVED` | **Patients** | Order submission confirmation |
| `SG_TMPL_ORDER_APPROVED` | **Physicians** | Their patient's order was approved |
| `SG_TMPL_ORDER_SHIPPED` | **Patients** | Order shipped with tracking |
| `SG_TMPL_ORDER_DELIVERED` | **Manufacturer** | New order notification |

## Required Environment Variables

Add these to your Render dashboard (Dashboard → Environment):

### 1. Authentication Templates

| Variable Name | Purpose | Audience | When Sent |
|--------------|---------|----------|-----------|
| `SG_TMPL_PASSWORD_RESET` | Password reset email | All users (patients, physicians, admins) | When user clicks "Forgot Password" |
| `SG_TMPL_ACCOUNT_CONFIRMATION` | Account confirmation for physicians/practices | **Physicians & Practice Managers** | When they register their account |
| `SG_TMPL_PHYSACCOUNT_CONFIRMATION` | Physician account welcome | **Physicians & Practice Managers** | When admin creates their account |

### 2. Order Notifications

| Variable Name | Purpose | Audience | When Sent |
|--------------|---------|----------|-----------|
| `SG_TMPL_ORDER_RECEIVED` | Order received confirmation | **Patients** | When patient submits new order |
| `SG_TMPL_ORDER_APPROVED` | Order approved notification | **Physicians** | When admin approves their patient's order |
| `SG_TMPL_ORDER_SHIPPED` | Order shipped notification | **Patients** | When admin adds tracking info |
| `SG_TMPL_ORDER_DELIVERED` | New order notification to manufacturer | **Manufacturer** | When new order is submitted |

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

### 1. Password Reset Template (SG_TMPL_PASSWORD_RESET)
**Audience:** All users
```json
{
  "first_name": "John",
  "reset_url": "https://collagendirect.health/portal/reset/?selector=xxx&token=xxx",
  "support_email": "support@collagendirect.health",
  "year": "2025"
}
```

### 2. Account Confirmation for Physicians (SG_TMPL_ACCOUNT_CONFIRMATION)
**Audience:** Physicians & Practice Managers (self-registration)
```json
{
  "first_name": "Dr. John",
  "last_name": "Doe",
  "email": "john@practice.com",
  "practice_name": "Doe Medical Center",
  "login_url": "https://collagendirect.health/portal/login",
  "support_email": "support@collagendirect.health",
  "year": "2025"
}
```

### 3. Physician Account Welcome (SG_TMPL_PHYSACCOUNT_CONFIRMATION)
**Audience:** Physicians & Practice Managers (admin-created accounts)
```json
{
  "first_name": "Dr. John",
  "last_name": "Doe",
  "email": "john@practice.com",
  "practice_name": "Doe Medical Center",
  "temporary_password": "temp123xyz",
  "login_url": "https://collagendirect.health/portal/login",
  "support_email": "support@collagendirect.health",
  "year": "2025"
}
```

### 4. Order Received - Patient (SG_TMPL_ORDER_RECEIVED)
**Audience:** Patients
```json
{
  "first_name": "Jane",
  "last_name": "Smith",
  "order_id": "123",
  "order_date": "2025-10-27",
  "product_name": "CollagenDirect Injection 5ml",
  "physician_name": "Dr. John Doe",
  "practice_name": "Doe Medical Center",
  "portal_url": "https://collagendirect.health/portal",
  "support_email": "support@collagendirect.health",
  "year": "2025"
}
```

### 5. Order Approved - Physician (SG_TMPL_ORDER_APPROVED)
**Audience:** Physicians (notifying them their patient's order was approved)
```json
{
  "physician_first_name": "John",
  "physician_last_name": "Doe",
  "patient_first_name": "Jane",
  "patient_last_name": "Smith",
  "order_id": "123",
  "order_date": "2025-10-27",
  "product_name": "CollagenDirect Injection 5ml",
  "approved_date": "2025-10-28",
  "portal_url": "https://collagendirect.health/portal",
  "support_email": "support@collagendirect.health",
  "year": "2025"
}
```

### 6. Order Shipped - Patient (SG_TMPL_ORDER_SHIPPED)
**Audience:** Patients
```json
{
  "first_name": "Jane",
  "last_name": "Smith",
  "order_id": "123",
  "product_name": "CollagenDirect Injection 5ml",
  "tracking_number": "1Z999AA10123456784",
  "carrier": "UPS",
  "tracking_url": "https://www.ups.com/track?tracknum=1Z999AA10123456784",
  "shipped_date": "2025-10-28",
  "portal_url": "https://collagendirect.health/portal",
  "support_email": "support@collagendirect.health",
  "year": "2025"
}
```

### 7. New Order to Manufacturer (SG_TMPL_ORDER_DELIVERED)
**Audience:** Manufacturer
```json
{
  "order_id": "123",
  "order_date": "2025-10-27",
  "patient_name": "Jane Smith",
  "patient_dob": "1980-05-15",
  "patient_phone": "(555) 123-4567",
  "physician_name": "Dr. John Doe",
  "practice_name": "Doe Medical Center",
  "practice_phone": "(555) 987-6543",
  "product_name": "CollagenDirect Injection 5ml",
  "quantity": "1",
  "shipping_address": "123 Main St, City, ST 12345",
  "insurance_info": "Blue Cross - Policy #12345",
  "admin_portal_url": "https://collagendirect.health/admin/orders.php?id=123",
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
