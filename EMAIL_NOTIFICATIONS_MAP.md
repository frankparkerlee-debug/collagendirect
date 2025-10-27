# Email Notifications Mapping - CollagenDirect

## Summary Status

| Notification Type | Status | File Location | SendGrid Template | Trigger | Notes |
|------------------|--------|--------------|------------------|---------|-------|
| **1. Patient Order Received** | ❌ MISSING | N/A | `SG_TMPL_ORDER_CONFIRM` (configured but unused) | 2-3 days after order creation | Needs implementation |
| **2. User Password Reset** | ✅ WORKING | `api/auth/request_reset.php:163` | `SG_TMPL_PASSWORD_RESET` | User clicks "Forgot Password" | Fully implemented |
| **3. Manufacturer Order** | ✅ WORKING | `api/lib/order_manufacturer_notification.php:14` | None (plain text) | Order creation | Missing document attachments |
| **4. New Account Created** | ✅ WORKING | `api/lib/provider_welcome.php:9` | `SG_TMPL_ACCOUNT_CONFIRM` (exists but not used) | Admin creates account | Uses plain text, not template |
| **5. Physician Order Status** | ❌ MISSING | N/A | N/A | Daily batch for status changes | Needs full implementation |

---

## Detailed Analysis

### 1. Patient Order Received (Delivery Confirmation)
**Purpose**: Insurance compliance - confirm patient received order
**Status**: ❌ **NOT IMPLEMENTED**
**Requirements**:
- Send 2-3 days after `orders.created_at`
- Include "Click to confirm delivery" link
- Required for insurance compliance
- Must track patient confirmation in database

**Implementation Needed**:
- Create scheduled task system (cron job or polling script)
- Create new table: `order_delivery_confirmations`
  - `order_id`, `patient_email`, `sent_at`, `confirmed_at`, `confirmation_token`
- Create confirmation endpoint: `/api/patient/confirm-delivery.php`
- Use SendGrid template `SG_TMPL_ORDER_CONFIRM` (d-c9ddf972a5d04477b5d8654fecfabbdc)

**Files to Create**:
- `/api/lib/patient_delivery_notification.php` - Email sending function
- `/api/cron/send-delivery-confirmations.php` - Daily cron job
- `/api/patient/confirm-delivery.php` - Confirmation handler

---

### 2. User Password Reset
**Purpose**: Allow users to reset forgotten passwords
**Status**: ✅ **FULLY WORKING**
**File**: [api/auth/request_reset.php:163](api/auth/request_reset.php#L163)
**Template**: `SG_TMPL_PASSWORD_RESET` (d-41ea629107c54e0abc1dcbd654c9e498)

**Current Implementation**:
```php
$sentOk = sg_send(
  ['email' => $email, 'name' => $user['first_name'] ?? $email],
  null, null,
  [
    'template_id' => $templateId,
    'dynamic_data'=> [
      'first_name'    => $user['first_name'] ?? 'there',
      'reset_url'     => $resetUrl,
      'support_email' => 'support@collagendirect.health',
      'year'          => date('Y'),
    ],
    'categories' => ['auth','password']
  ]
);
```

**✅ No changes needed** - Working correctly with SendGrid template

---

### 3. Manufacturer Order Notification
**Purpose**: Alert manufacturer when new order is submitted
**Status**: ⚠️ **PARTIALLY WORKING** - Missing document attachments
**File**: [api/lib/order_manufacturer_notification.php:14](api/lib/order_manufacturer_notification.php#L14)
**Trigger**: [api/portal/orders.create.php:247](api/portal/orders.create.php#L247)

**Current Implementation**:
- ✅ Sends email to manufacturer on order creation
- ✅ Includes order details (patient, physician, product, insurance)
- ✅ Includes link to Order PDF
- ✅ Includes link to admin portal
- ❌ **MISSING**: Actual document attachments (ID card, Insurance card, Notes)

**Issue**: Email includes only URLs, not actual file attachments

**Required Changes**:
- Update `notify_manufacturer_of_order()` to attach files
- Use SendGrid attachments API
- Fetch files from uploads directory: `/uploads/ids/`, `/uploads/insurance/`, `/uploads/notes/`
- Base64 encode and attach to email

**Files to Modify**:
- [api/lib/order_manufacturer_notification.php:114-136](api/lib/order_manufacturer_notification.php#L114-L136) - Add attachments to SendGrid payload

---

### 4. New Account Created (Provider Welcome)
**Purpose**: Welcome new practice managers/physicians with login credentials
**Status**: ⚠️ **WORKING** but not using SendGrid template
**File**: [api/lib/provider_welcome.php:9](api/lib/provider_welcome.php#L9)
**Trigger**: [admin/users.php:50,101](admin/users.php#L50)
**Template Available**: `SG_TMPL_ACCOUNT_CONFIRM` (d-c33b0338c94544bda58c885892ce2f53) - **NOT CURRENTLY USED**

**Current Implementation**:
- ✅ Sends plain text email on account creation
- ✅ Includes temporary password
- ✅ Includes role-specific instructions
- ✅ Triggered correctly from admin panel
- ❌ Not using configured SendGrid template

**Recommendation**:
- **Option A**: Keep current plain text implementation (works fine)
- **Option B**: Migrate to SendGrid template for consistency

**No critical changes needed** - Already functional

---

### 5. Physician Order Status Updates (Batched)
**Purpose**: Notify physicians when order status changes
**Status**: ❌ **NOT IMPLEMENTED**
**Requirements**:
- Send daily batched email (combine multiple patients)
- Trigger on status changes: Shipped, Delivered, Expiring (within 7 days)
- Group by physician
- Professional summary format

**Implementation Needed**:
- Create status change tracking table: `order_status_changes`
  - `order_id`, `old_status`, `new_status`, `changed_at`, `notification_sent_at`
- Create daily batch job
- Query for status changes in last 24 hours where `notification_sent_at IS NULL`
- Group by `user_id` (physician)
- Send one email per physician with all their patients' updates
- Also check for orders expiring within 7 days

**Files to Create**:
- `/api/lib/physician_status_notification.php` - Email sending function
- `/api/cron/send-physician-status-updates.php` - Daily cron job
- Update `/api/admin/orders/update-status.php` to log status changes

---

## Database Schema Changes Required

### New Table: `order_delivery_confirmations`
```sql
CREATE TABLE order_delivery_confirmations (
  id SERIAL PRIMARY KEY,
  order_id INTEGER NOT NULL REFERENCES orders(id),
  patient_email VARCHAR(255) NOT NULL,
  confirmation_token VARCHAR(64) NOT NULL UNIQUE,
  sent_at TIMESTAMP NOT NULL DEFAULT NOW(),
  confirmed_at TIMESTAMP NULL,
  confirmed_ip VARCHAR(64) NULL,
  reminder_sent_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  INDEX idx_token (confirmation_token),
  INDEX idx_order (order_id),
  INDEX idx_sent_pending (sent_at, confirmed_at)
);
```

### New Table: `order_status_changes`
```sql
CREATE TABLE order_status_changes (
  id SERIAL PRIMARY KEY,
  order_id INTEGER NOT NULL REFERENCES orders(id),
  old_status VARCHAR(50) NULL,
  new_status VARCHAR(50) NOT NULL,
  changed_by VARCHAR(64) NULL REFERENCES users(id),
  changed_at TIMESTAMP NOT NULL DEFAULT NOW(),
  notification_sent_at TIMESTAMP NULL,
  INDEX idx_order (order_id),
  INDEX idx_notification (notification_sent_at),
  INDEX idx_changed (changed_at)
);
```

---

## Cron Job Configuration (Render.yaml)

Add to `render.yaml`:
```yaml
- type: cron
  name: collagen-delivery-confirmations
  schedule: "0 10 * * *"  # Daily at 10 AM UTC
  command: "php /var/www/html/api/cron/send-delivery-confirmations.php"

- type: cron
  name: collagen-physician-status-updates
  schedule: "0 17 * * *"  # Daily at 5 PM UTC (12 PM ET)
  command: "php /var/www/html/api/cron/send-physician-status-updates.php"
```

---

## Implementation Priority

### High Priority (Required for Compliance)
1. ✅ **Password Reset** - Already working
2. ⚠️ **Manufacturer Notification** - Add document attachments
3. ❌ **Patient Delivery Confirmation** - Required for insurance compliance

### Medium Priority (Operational)
4. ✅ **New Account Welcome** - Already working
5. ❌ **Physician Status Updates** - Improves physician experience

---

## Next Steps

1. **Immediate**: Fix manufacturer notification to attach documents
2. **Critical**: Implement patient delivery confirmation (insurance compliance)
3. **Important**: Implement physician batched status updates
4. **Optional**: Migrate welcome email to use SendGrid template

---

## Testing Checklist

- [ ] Password reset email sends with correct template
- [ ] Manufacturer email includes all 3-4 document attachments (ID, Insurance, Notes, Order PDF)
- [ ] Patient delivery confirmation sent 2-3 days after order
- [ ] Patient confirmation link works and records timestamp
- [ ] Welcome email sends on new account creation
- [ ] Physician batched email groups multiple patients correctly
- [ ] Status change notifications only send once per change
- [ ] Expiring order warnings sent 7 days before expiration

---

## File Reference Guide

### Email Sending Functions
- `sg_send()` - [api/lib/sg_curl.php:34](api/lib/sg_curl.php#L34) - Main SendGrid wrapper
- `sg_curl_send()` - [api/lib/sg_curl.php:144](api/lib/sg_curl.php#L144) - Low-level curl wrapper

### Existing Notification Files
- Password Reset: [api/auth/request_reset.php](api/auth/request_reset.php)
- Manufacturer: [api/lib/order_manufacturer_notification.php](api/lib/order_manufacturer_notification.php)
- Welcome: [api/lib/provider_welcome.php](api/lib/provider_welcome.php)

### Configuration
- Environment: [.env.example](.env.example) - SendGrid keys and template IDs
- Database: [api/db.php](api/db.php) - PDO connection

