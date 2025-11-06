# Delivery Confirmation & Wound Photo SMS Implementation Plan

## Overview
Implementation plan for two SMS workflows:
1. **Delivery Confirmation** - Insurance compliance requirement
2. **Wound Photo Requests** - Automated and manual photo prompts

---

## Use Case 1: Delivery Confirmation SMS

### Current Implementation (admin/orders.php)
**Status:** ✅ Partially Complete

**Flow:**
1. Admin marks order as "Delivered" in `/admin/orders.php`
2. System sends SMS: "Hi {name}, your wound care supplies from Dr. {physician} were delivered. Please confirm receipt: https://collagendirect.health/confirm-delivery?token={token}"
3. Creates record in `delivery_confirmations` table
4. Stores: order_id, patient_phone, confirmation_token, sms_sid, sms_status, sms_sent_at

### Missing Components:

#### 1. Confirmation Response Handler
**File:** `/api/confirm-delivery.php` (NEW)

**Purpose:** Web endpoint where patients click to confirm delivery

**Logic:**
```php
- GET /api/confirm-delivery.php?token={token}
- Validate token (check expiration, single-use)
- Update delivery_confirmations:
  - confirmed_at = NOW()
  - confirmation_method = 'web_link'
  - ip_address = $_SERVER['REMOTE_ADDR']
- Show success page: "Thank you! Delivery confirmed."
- Redirect to patient portal or show contact info
```

#### 2. SMS Reply Handler
**File:** `/api/twilio/delivery-confirmation-reply.php` (NEW)

**Purpose:** Handle SMS replies ("YES", "DELIVERED", etc.)

**Twilio Webhook Setup:**
- Configure in Twilio Console
- "A MESSAGE COMES IN" → https://collagendirect.health/api/twilio/delivery-confirmation-reply.php

**Logic:**
```php
- Receive: From, Body
- Normalize phone number
- Find delivery_confirmation by patient_phone WHERE confirmed_at IS NULL
- Check for keywords: YES, DELIVERED, CONFIRM, etc.
- Update delivery_confirmations:
  - confirmed_at = NOW()
  - confirmation_method = 'sms_reply'
  - sms_reply_text = {body}
- Send reply SMS: "Thank you! Your delivery confirmation has been recorded."
```

#### 3. Admin View: Confirmation Status
**File:** `/admin/orders.php` (MODIFY)

**Add columns to orders table:**
- Delivery Confirmed: ✅ Yes / ❌ Pending / ⏰ Expired
- Confirmation Method: Web Link / SMS Reply
- Confirmed At: timestamp

**Database query enhancement:**
```sql
LEFT JOIN delivery_confirmations dc ON dc.order_id = o.id
```

#### 4. Compliance Report
**File:** `/admin/reports/delivery-confirmations.php` (NEW)

**Purpose:** Export for insurance audits

**Fields:**
- Order ID
- Patient Name
- Delivery Date
- Confirmation Date
- Time to Confirm (hours)
- Confirmation Method
- SMS SID (Twilio proof)
- Status: Confirmed / Pending / Expired

---

## Use Case 2: Wound Photo Requests

### Current Implementation (portal/index.php)
**Status:** ✅ Manual Request Works

**Existing:**
- `request_wound_photo` action (lines 986-1080)
- Creates `photo_requests` table record
- Sends SMS with upload link
- `receive-mms.php` handles photo uploads

### Missing Components:

#### 1. Automated Photo Prompts Based on Frequency

**File:** `/api/cron/send-wound-photo-prompts.php` (NEW)

**Cron Schedule:** Daily at 10 AM Central
```bash
0 15 * * * cd /var/www/html && php api/cron/send-wound-photo-prompts.php
```

**Logic:**
```php
// Find orders needing photo prompts
SELECT o.id, o.patient_id, o.frequency_per_week, o.created_at, o.delivered_at,
       p.first_name, p.last_name, p.phone,
       u.first_name AS phys_first, u.last_name AS phys_last
FROM orders o
JOIN patients p ON p.id = o.patient_id
LEFT JOIN users u ON u.id = o.user_id
WHERE o.status = 'delivered'
  AND o.delivered_at IS NOT NULL
  AND p.phone IS NOT NULL
  AND o.frequency_per_week > 0  // e.g. 3 = 3x per week = every 2.33 days

// Calculate next prompt date based on frequency
// frequency_per_week = 3 → prompt every 2-3 days
// frequency_per_week = 7 → prompt daily
// frequency_per_week = 1 → prompt weekly

// Check last photo_requests.created_at for this order
// If enough time passed, send new prompt

// Send SMS: "Hi {name}, it's time to send updated wound photos for your treatment.
//            Reply with photos or tap: https://collagendirect.health/upload?token={token}"
```

**Calculation:**
```php
function calculateDaysBetweenPrompts(int $frequencyPerWeek): float {
  if ($frequencyPerWeek <= 0) return 365; // Never
  return 7.0 / $frequencyPerWeek;
}

// Examples:
// frequency_per_week = 7 → 1 day
// frequency_per_week = 3 → 2.33 days
// frequency_per_week = 2 → 3.5 days
// frequency_per_week = 1 → 7 days
```

#### 2. Manual Photo Prompt in Portal
**File:** `/portal/index.php` (MODIFY - already exists!)

**Status:** ✅ Already Implemented (lines 986-1080)

**Enhancement Needed:**
- Add button in patient profile: "Request Wound Photos"
- Add button in order details: "Request Photos for This Order"
- Show last photo request date/time
- Show photo request history

#### 3. Associate Photos with Orders

**Current Issue:** MMS photos are stored but not linked to specific orders

**File:** `/api/twilio/receive-mms.php` (MODIFY)

**Enhancement:**
```php
// When photo received via MMS
// 1. Look up photo_request by patient phone (most recent active)
// 2. If found, link photo to photo_request.order_id
// 3. Create wound_photo_history record with order_id
// 4. Send reply SMS: "Photo received! Your physician will review it shortly."
```

**Database:**
```sql
-- Add order_id to wound_photo_uploads
ALTER TABLE wound_photo_uploads
ADD COLUMN order_id UUID REFERENCES orders(id);

-- Index for performance
CREATE INDEX idx_wound_photo_uploads_order_id ON wound_photo_uploads(order_id);
```

#### 4. Photo Review with Order Context

**File:** `/portal/photo-reviews.php` (MODIFY)

**Enhancement:**
- Show associated order ID in photo review
- Display: "Order #ABC123 - {Product Name}"
- Link to order details
- Show wound location from order
- Display dressing change frequency

---

## Phone Number Issues

### Problem: Portal users can't save patient phone numbers

**Investigation Needed:**
1. Check `validPhone()` function - might be too strict
2. Check if phone normalization is applied
3. Verify user authentication in portal/index.php

**File:** `/portal/index.php` (line 945)

**Current validation:**
```php
if(!validPhone($phone)) jerr('Phone must be 10 digits');
```

**Fix:** Use phone normalization from twilio_sms.php
```php
require_once __DIR__ . '/../api/lib/twilio_sms.php';
$phone = normalize_phone_number($phone);
if (!$phone) jerr('Invalid phone number format');
```

**Apply to all phone fields:**
- patient.phone
- patient.cell_phone
- insurance_payer_phone

---

## Database Schema

### delivery_confirmations
**Status:** ✅ Already exists

**Enhancements needed:**
```sql
ALTER TABLE delivery_confirmations
ADD COLUMN confirmation_method VARCHAR(20), -- 'web_link', 'sms_reply'
ADD COLUMN sms_reply_text TEXT,
ADD COLUMN ip_address VARCHAR(50),
ADD COLUMN token_expires_at TIMESTAMP DEFAULT (NOW() + INTERVAL '7 days');

CREATE INDEX idx_delivery_confirmations_token ON delivery_confirmations(confirmation_token);
CREATE INDEX idx_delivery_confirmations_phone ON delivery_confirmations(patient_phone);
```

### photo_requests
**Status:** ✅ Already exists

**No changes needed** - already has order_id

### wound_photo_uploads
**Enhancements needed:**
```sql
ALTER TABLE wound_photo_uploads
ADD COLUMN order_id UUID REFERENCES orders(id),
ADD COLUMN prompt_type VARCHAR(20) DEFAULT 'manual'; -- 'manual', 'automated', 'scheduled'

CREATE INDEX idx_wound_photo_uploads_order_id ON wound_photo_uploads(order_id);
```

---

## Implementation Priority

### Phase 1: Critical (Insurance Compliance)
1. ✅ Fix phone number saving validation
2. ✅ Create `/api/confirm-delivery.php` web endpoint
3. ✅ Create `/api/twilio/delivery-confirmation-reply.php` SMS handler
4. ✅ Add confirmation status to admin orders view
5. ✅ Test full delivery confirmation flow

### Phase 2: Photo Automation
1. ✅ Create `/api/cron/send-wound-photo-prompts.php`
2. ✅ Set up cron job for daily execution
3. ✅ Enhance MMS handler to link photos to orders
4. ✅ Add order_id to wound_photo_uploads table
5. ✅ Test automated photo prompts

### Phase 3: Portal Enhancements
1. ✅ Add manual photo request buttons in portal
2. ✅ Show photo request history
3. ✅ Display order context in photo reviews
4. ✅ Create compliance report export

---

## Workflow Diagrams

### Delivery Confirmation Flow
```
Admin marks delivered (orders.php)
    ↓
SMS sent to patient
    ↓
Patient clicks link OR replies "YES"
    ↓
confirmation_method = 'web_link' or 'sms_reply'
    ↓
confirmed_at = NOW()
    ↓
Admin sees ✅ Confirmed in orders view
```

### Automated Photo Prompt Flow
```
Order delivered with frequency_per_week = 3
    ↓
Cron runs daily at 10 AM
    ↓
Calculate: 7 / 3 = 2.33 days between prompts
    ↓
Check last photo_requests.created_at
    ↓
If 2.33 days passed, send new SMS
    ↓
Patient sends MMS photo
    ↓
receive-mms.php links to order_id
    ↓
Photo appears in portal photo-reviews.php
```

---

## Testing Checklist

### Delivery Confirmation
- [ ] Admin marks order delivered
- [ ] SMS received by patient
- [ ] Patient clicks web link → confirmed
- [ ] Patient replies "YES" → confirmed
- [ ] Confirmation shows in admin view
- [ ] Export compliance report

### Photo Prompts
- [ ] Manual prompt from portal works
- [ ] Automated prompt sent based on frequency
- [ ] Patient sends MMS photo
- [ ] Photo linked to correct order
- [ ] Photo appears in photo-reviews with order context
- [ ] Multiple orders for same patient handled correctly

### Phone Numbers
- [ ] Portal user can save patient phone
- [ ] Phone normalized to E.164 format
- [ ] Invalid phone rejected with helpful error
- [ ] Admin user can save patient phone
- [ ] All phone formats accepted (555-1234, (555) 123-4567, etc.)

---

## Configuration

### Twilio Webhooks (Console Setup)

**Phone Number:** +18884156880

**Messaging Configuration:**
1. **A MESSAGE COMES IN** (Delivery confirmations)
   - Webhook URL: `https://collagendirect.health/api/twilio/delivery-confirmation-reply.php`
   - HTTP POST

2. **A MESSAGE COMES IN** (Photo uploads - already configured)
   - Webhook URL: `https://collagendirect.health/api/twilio/receive-mms.php`
   - HTTP POST

**Note:** Need to handle routing - determine if incoming SMS is:
- Delivery confirmation reply (lookup by phone + pending confirmation)
- Photo upload (MMS with media)
- General reply (forward to admin)

---

## Estimated Implementation Time

- Phase 1 (Compliance): 4-6 hours
- Phase 2 (Automation): 3-4 hours
- Phase 3 (Enhancements): 2-3 hours

**Total:** 9-13 hours development time

---

## Next Steps

1. Review this plan with stakeholders
2. Prioritize phases based on business need
3. Begin Phase 1 implementation
4. Test thoroughly in production
5. Monitor SMS logs and confirmation rates
6. Iterate based on patient feedback
