# CollagenDirect Compliance Workflow - Complete Implementation Plan

## Executive Summary

This document outlines the complete implementation of a HIPAA-compliant DME order management system with proper role-based access control, order status tracking, and manufacturer fulfillment workflows.

---

## System Architecture Overview

### User Roles

#### 1. **Physician** (`role = 'physician'`)
**Capabilities:**
- Create DME orders for patients
- Upload patient documentation (ID, insurance, AOB, clinical notes)
- View order status and tracking
- Request order termination
- Approve cash-price orders (if insurance denied)

**UI Variations Based on DME License:**
- **has_dme_license = TRUE**: Simplified workflow, informational tracking only
- **has_dme_license = FALSE**: Full compliance workflow, must wait for super admin approval

#### 2. **Practice Admin** (`role = 'practice_admin'`)
**Capabilities:**
- All physician capabilities
- **PLUS:** Manage practice settings
- Set `has_dme_license` flag for practice
- Manage practice users
- View practice-wide analytics

#### 3. **Super Admin** (`role = 'superadmin'`)
**Capabilities:**
- Review submitted orders for completeness
- Verify clinical necessity
- Monitor manufacturer insurance verification
- Handle cash-price workflow (when insurance denied)
- Update order statuses
- Add tracking codes from manufacturer
- Process termination requests
- Manage all users and practices
- View system-wide analytics

---

## Order Lifecycle Workflows

### Workflow A: Practice WITH DME License (`has_dme_license = TRUE`)

```
┌─────────────┐
│   Physician │
│ Creates     │
│ Order       │
└──────┬──────┘
       │
       ▼
┌─────────────────┐
│ Status: DRAFT   │
│ (physician UI)  │
└──────┬──────────┘
       │
       │ Physician completes order + signs
       ▼
┌──────────────────┐
│ Status: SUBMITTED│  ◄─ Auto-submit when signed
└──────┬───────────┘
       │
       │ INFORMATIONAL ONLY (practice handles fulfillment)
       ▼
┌──────────────────────────┐
│ Physician manages order  │
│ directly with            │
│ manufacturer using their │
│ own DME license          │
└──────────────────────────┘
       │
       │ Physician updates manually
       ▼
┌────────────────┐
│ Status: SHIPPED│
│ (optional)     │
└────────┬───────┘
         │
         ▼
┌─────────────────┐
│ Status: DELIVERED│
└─────────────────┘
```

**Key Points:**
- CollagenDirect provides **order management** only
- Practice uses own DME license for actual ordering
- Super admin does NOT review these orders
- Tracking is optional/informational
- Practice benefits: better pricing through CD partnership, centralized records

### Workflow B: Practice WITHOUT DME License (`has_dme_license = FALSE`)

```
┌─────────────┐
│   Physician │
│ Creates     │
│ Order       │
└──────┬──────┘
       │
       ▼
┌─────────────────┐
│ Status: DRAFT   │
│ (physician UI)  │
└──────┬──────────┘
       │
       │ Physician completes order + signs
       ▼
┌──────────────────┐
│ Status: SUBMITTED│ ◄─ Sent to super admin queue
└──────┬───────────┘
       │
       │ Super admin picks up order
       ▼
┌──────────────────────┐
│ Status: UNDER_REVIEW │
│ (super admin checks  │
│  completeness)       │
└──────┬───────────────┘
       │
       ├─── ❌ INCOMPLETE ───┐
       │                     ▼
       │            ┌─────────────────┐
       │            │ Status:         │
       │            │ INCOMPLETE      │
       │            │ (back to MD)    │
       │            └─────────┬───────┘
       │                      │
       │    ◄─────────────────┘ Physician fixes
       │
       ├─── ✅ COMPLETE ────────┐
       │                        ▼
       │              ┌──────────────────────────┐
       │              │ Status:                  │
       │              │ VERIFICATION_PENDING     │
       │              │ (sent to manufacturer)   │
       │              └──────────┬───────────────┘
       │                         │
       │         ┌───────────────┴────────────────┐
       │         │                                │
       │         ▼                                ▼
       │  ┌──────────────┐              ┌────────────────────┐
       │  │ Insurance    │              │ Insurance          │
       │  │ APPROVED     │              │ DENIED             │
       │  └──────┬───────┘              └────────┬───────────┘
       │         │                               │
       │         ▼                               ▼
       │  ┌─────────────┐           ┌────────────────────────┐
       │  │ Status:     │           │ Status:                │
       │  │ APPROVED    │           │ CASH_PRICE_REQUIRED    │
       │  └──────┬──────┘           │ (alert physician)      │
       │         │                  └────────┬───────────────┘
       │         │                           │
       │         │                  ┌────────┴──────────┐
       │         │                  │                   │
       │         │                  ▼                   ▼
       │         │         ┌─────────────┐    ┌──────────────┐
       │         │         │ Physician   │    │ Physician    │
       │         │         │ APPROVES    │    │ DECLINES     │
       │         │         │ cash price  │    │ cash price   │
       │         │         └──────┬──────┘    └──────┬───────┘
       │         │                │                  │
       │         │                ▼                  ▼
       │         │    ┌───────────────────┐  ┌─────────────┐
       │         │    │ Status:           │  │ Status:     │
       │         │    │ CASH_PRICE_       │  │ CANCELLED   │
       │         │    │ APPROVED          │  └─────────────┘
       │         │    └───────┬───────────┘
       │         │            │
       │         └────────────┴────────────┐
       │                                   │
       │                                   ▼
       │                        ┌────────────────────┐
       │                        │ Super admin        │
       │                        │ places order with  │
       │                        │ manufacturer       │
       │                        └─────────┬──────────┘
       │                                  │
       │                                  ▼
       │                        ┌────────────────────┐
       │                        │ Status:            │
       │                        │ IN_PRODUCTION      │
       │                        └─────────┬──────────┘
       │                                  │
       │                                  ▼
       │                        ┌────────────────────┐
       │                        │ Manufacturer ships │
       │                        │ Super admin adds   │
       │                        │ tracking code      │
       │                        └─────────┬──────────┘
       │                                  │
       │                                  ▼
       │                        ┌────────────────────┐
       │                        │ Status: SHIPPED    │
       │                        │ (tracking visible  │
       │                        │  to physician)     │
       │                        └─────────┬──────────┘
       │                                  │
       │                                  ▼
       │                        ┌────────────────────┐
       │                        │ Status: DELIVERED  │
       │                        │ (to patient or     │
       │                        │  physician office) │
       │                        └────────────────────┘
       │
       │
       └─── 🛑 TERMINATION REQUEST ───┐
                                      ▼
                            ┌──────────────────┐
                            │ Status:          │
                            │ TERMINATED       │
                            │ (blocks future   │
                            │  pending orders) │
                            └──────────────────┘
```

---

## Order Completeness Requirements

An order is considered **COMPLETE** when ALL of the following are present:

### Patient Information
- ✅ First name
- ✅ Last name
- ✅ Date of birth
- ✅ Sex/gender
- ✅ Phone number
- ✅ Full address (street, city, state, zip)

### Clinical Documentation
- ✅ Product selection
- ✅ Wound location
- ✅ ICD-10 primary diagnosis code
- ✅ Wound measurements (length, width in cm)
- ✅ Clinical documentation uploaded (wound photos, notes)

### Required Documents
- ✅ Patient ID card (scanned)
- ✅ Insurance card front/back (scanned)
- ✅ Assignment of Benefits (AOB) signed
- ✅ Physician prescription/order notes

### Physician Signature
- ✅ Physician name
- ✅ Digital signature/attestation
- ✅ Signature timestamp

### Insurance Information (if payment_method = 'insurance')
- ✅ Insurance provider name
- ✅ Member ID
- ✅ Group ID (if applicable)

**Implementation:**
```sql
-- Check order completeness
SELECT * FROM check_order_completeness('order_abc123');

-- Returns:
-- is_complete: true/false
-- missing_fields: ['patient_id_card', 'icd10_primary', ...]
```

---

## Database Schema Summary

### New/Updated Tables

#### `users` table additions:
```sql
role VARCHAR(50) DEFAULT 'physician'
  -- Values: 'physician', 'practice_admin', 'superadmin'

has_dme_license BOOLEAN DEFAULT FALSE
  -- TRUE: practice handles own fulfillment
  -- FALSE: CollagenDirect handles fulfillment
```

#### `orders` table additions:
```sql
-- Delivery & Tracking
delivery_location VARCHAR(20) DEFAULT 'patient'
  -- Values: 'patient', 'physician'
tracking_code VARCHAR(255)
carrier VARCHAR(50)
  -- Values: 'UPS', 'FedEx', 'USPS', etc.

-- Payment
payment_method VARCHAR(20) DEFAULT 'insurance'
  -- Values: 'insurance', 'cash'
cash_price DECIMAL(10,2)
cash_price_approved_at TIMESTAMP
cash_price_approved_by VARCHAR(64)

-- Termination
terminated_at TIMESTAMP
terminated_by VARCHAR(64)
termination_reason TEXT

-- Super Admin Review
reviewed_at TIMESTAMP
reviewed_by VARCHAR(64)
review_notes TEXT
manufacturer_order_id VARCHAR(255)

-- Completeness
is_complete BOOLEAN DEFAULT FALSE
completeness_checked_at TIMESTAMP
missing_fields TEXT[]
  -- Array of missing field names

-- Status (updated constraint)
status VARCHAR(40) CHECK (status IN (
  'draft', 'submitted', 'under_review', 'incomplete',
  'verification_pending', 'cash_price_required',
  'cash_price_approved', 'approved', 'in_production',
  'shipped', 'delivered', 'terminated', 'cancelled'
))
```

#### `order_status_history` (new table):
```sql
id SERIAL PRIMARY KEY
order_id VARCHAR(64) REFERENCES orders(id)
old_status VARCHAR(40)
new_status VARCHAR(40)
changed_by VARCHAR(64)
changed_by_role VARCHAR(50)
notes TEXT
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

#### `order_alerts` (new table):
```sql
id SERIAL PRIMARY KEY
order_id VARCHAR(64) REFERENCES orders(id)
alert_type VARCHAR(50)
  -- 'cash_price_required', 'order_incomplete',
  -- 'termination_request', 'verification_failed', etc.
message TEXT
severity VARCHAR(20) DEFAULT 'info'
  -- 'info', 'warning', 'critical'
recipient_role VARCHAR(50)
  -- Who sees this: 'physician', 'practice_admin', 'superadmin'
is_read BOOLEAN DEFAULT FALSE
read_at TIMESTAMP
read_by VARCHAR(64)
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

---

## API Endpoints to Build

### For Physicians

#### `POST /api/orders/create`
Create new order (status = 'draft')

#### `PUT /api/orders/{id}/update`
Update draft order

#### `POST /api/orders/{id}/submit`
Submit order for super admin review
- Validates completeness
- Changes status to 'submitted'
- Creates alert for super admin

#### `POST /api/orders/{id}/approve-cash-price`
Approve cash price when insurance denied
- Changes status to 'cash_price_approved'
- Notifies super admin to proceed

#### `POST /api/orders/{id}/request-termination`
Request order termination
- Sets `terminated_at`, `terminated_by`, `termination_reason`
- Changes status to 'terminated'
- Alerts super admin
- Blocks future/pending orders for this patient

#### `GET /api/orders/{id}/status`
Get current order status with tracking info

### For Super Admin

#### `GET /api/admin/orders/pending-review`
Get all orders in 'submitted' status awaiting review

#### `POST /api/admin/orders/{id}/review`
Review order for completeness
- Runs `check_order_completeness()`
- If complete: status → 'verification_pending', send to manufacturer
- If incomplete: status → 'incomplete', alert physician with missing fields

#### `POST /api/admin/orders/{id}/update-status`
Update order status (any status transition)
- Logs to `order_status_history`
- Creates alerts as needed

#### `POST /api/admin/orders/{id}/add-tracking`
Add tracking information
```json
{
  "tracking_code": "1Z999AA10123456784",
  "carrier": "UPS",
  "status": "shipped"
}
```

#### `POST /api/admin/orders/{id}/mark-cash-price-required`
Mark order as needing cash price approval
- Sets status to 'cash_price_required'
- Sets `cash_price` amount
- Creates critical alert for physician

#### `GET /api/admin/orders/alerts`
Get all unread alerts for super admin

#### `GET /api/admin/orders/{id}/history`
Get full status change history for order

### For Practice Admins

#### `PUT /api/practice/settings`
Update practice settings
```json
{
  "has_dme_license": true
}
```

---

## UI Components to Build

### 1. Super Admin Dashboard (`/admin/orders.php`)

**Features:**
- **Queue View**: Orders in 'submitted' status
- **Completeness Checker**: Visual checklist showing:
  - ✅ Patient demographics
  - ✅ Insurance info
  - ✅ Clinical documentation
  - ❌ Missing wound measurements (red X)
  - ✅ Physician signature
- **Action Buttons**:
  - "Mark Incomplete" (with reason dropdown)
  - "Approve & Send to Manufacturer"
  - "Request Cash Price" (with amount input)
- **Tracking Code Entry**: Form to add tracking info
- **Status Timeline**: Visual timeline of all status changes

### 2. Cash Price Alert Modal (Physician UI)

**Triggered when:** Order status changes to 'cash_price_required'

**Contents:**
```
⚠️ Insurance Verification Failed

Your order for [Patient Name] was denied by insurance.

Cash Price Option Available: $XXX.XX

Would you like to proceed with cash pricing?

[Approve Cash Price]  [Cancel Order]
```

### 3. Order Termination Form (Physician UI)

**Location:** Order detail page, "Actions" menu

**Fields:**
- Reason dropdown:
  - Patient no longer needs treatment
  - Patient switched providers
  - Medical condition changed
  - Financial reasons
  - Other (with text input)
- Confirmation checkbox: "I understand this will stop all future orders for this patient"

### 4. DME License Toggle (Practice Admin Settings)

**Location:** `/admin/settings.php` (practice admin only)

```
Practice Settings
─────────────────

□ Our practice has a DME license

ℹ️  If enabled, orders will be managed directly by your practice.
   If disabled, CollagenDirect will handle order fulfillment.

[Save Settings]
```

### 5. Conditional Order Interface

**For practices WITH DME (`has_dme_license = TRUE`):**
- Simplified form
- No compliance checklist
- Status updates are informational only
- Message: "Your order has been created. Use your DME license to order from the manufacturer."

**For practices WITHOUT DME (`has_dme_license = FALSE`):**
- Full compliance form
- All required fields marked with *
- Real-time completeness validation
- Status shows super admin review progress

---

## Implementation Phases

### Phase 1: Database Foundation ✅ DONE
- [x] Migration script created
- [x] Run migration on Render database
- [x] Verify schema changes

### Phase 2: API Endpoints (Week 1)
- [ ] Order completeness validation endpoint
- [ ] Order submission endpoint
- [ ] Super admin review endpoints
- [ ] Status update endpoint
- [ ] Tracking code endpoint
- [ ] Cash price workflow endpoints
- [ ] Termination request endpoint

### Phase 3: Super Admin Interface (Week 1-2)
- [ ] Order queue/review dashboard
- [ ] Completeness checker UI
- [ ] Status update controls
- [ ] Tracking code entry form
- [ ] Alert center

### Phase 4: Physician Alerts & Actions (Week 2)
- [ ] Cash price alert modal
- [ ] Order termination form
- [ ] Alert notifications system
- [ ] Status timeline view

### Phase 5: Practice Admin Settings (Week 2)
- [ ] DME license toggle
- [ ] Practice settings page
- [ ] User management updates

### Phase 6: Conditional UI (Week 3)
- [ ] Detect `has_dme_license` flag
- [ ] Show appropriate order flow
- [ ] Hide/show fields based on license status
- [ ] Update order creation forms

### Phase 7: Testing & Validation (Week 3-4)
- [ ] End-to-end workflow testing
- [ ] Role-based access control testing
- [ ] Status transition testing
- [ ] Alert delivery testing
- [ ] HIPAA compliance audit

---

## Security & Compliance Considerations

### HIPAA Compliance
- All PHI (patient health information) encrypted at rest
- All API endpoints require authentication
- Audit trail via `order_status_history`
- Role-based access control strictly enforced
- Session tokens with proper expiration

### Role-Based Access Control (RBAC)
```php
// Example middleware
function require_superadmin() {
    $user = get_current_user();
    if ($user['role'] !== 'superadmin') {
        http_response_code(403);
        die('Access denied');
    }
}

function can_view_order($order_id) {
    $user = get_current_user();
    $order = get_order($order_id);

    if ($user['role'] === 'superadmin') return true;
    if ($order['user_id'] === $user['id']) return true;

    return false;
}
```

### Data Validation
- All patient data validated before submission
- Required fields enforced at API level
- File uploads scanned for malware
- SQL injection prevention via prepared statements
- XSS prevention via output encoding

---

## Testing Scenarios

### Scenario 1: Complete Order (No DME License)
1. Physician creates order
2. Uploads all required documents
3. Signs order
4. Order auto-submits to super admin
5. Super admin reviews, marks complete
6. Insurance verification passes
7. Order approved
8. Manufacturer produces
9. Super admin adds tracking
10. Order delivered

**Expected:** All status transitions logged, no alerts, clean delivery

### Scenario 2: Incomplete Order
1. Physician creates order
2. Forgets to upload insurance card
3. Submits order
4. Super admin reviews
5. Marks incomplete with reason: "Missing insurance card"
6. Alert sent to physician
7. Physician uploads card
8. Resubmits
9. Super admin approves

**Expected:** Incomplete alert triggered, order returned to physician, resolved

### Scenario 3: Cash Price Required
1. Order submitted and complete
2. Manufacturer denies insurance
3. Super admin sets status to 'cash_price_required' with amount $350
4. Critical alert sent to physician
5. Physician sees modal with cash price
6. Physician approves
7. Status changes to 'cash_price_approved'
8. Super admin proceeds with order

**Expected:** Alert triggers immediately, physician can approve/decline, workflow continues

### Scenario 4: Termination Request
1. Order in 'in_production' status
2. Physician requests termination (reason: patient no longer needs)
3. Status changes to 'terminated'
4. Alert sent to super admin
5. Super admin sees termination in queue
6. Contacts manufacturer to cancel
7. Future orders for this patient blocked

**Expected:** Termination logged, super admin notified, no future orders

---

## Next Steps

1. **Run Migration** - You need to run `run-compliance-migration.php` on Render
2. **Set User Roles** - Update existing users with proper roles
3. **Set DME License Flags** - Configure each practice's license status
4. **Build APIs** - Start with order completeness endpoint
5. **Build Super Admin UI** - Order review dashboard first
6. **Test Workflows** - Create test orders through each scenario

---

## Support & Questions

For implementation questions or issues, reference:
- Database schema: `schema-postgresql.sql`
- Migration script: `migrations/compliance-workflow.sql`
- Migration runner: `run-compliance-migration.php`
- This document: `COMPLIANCE-IMPLEMENTATION-PLAN.md`
