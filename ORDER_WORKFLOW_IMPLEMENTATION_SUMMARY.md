# Order Workflow Implementation - Phase 1 Complete

## What Was Built

I've implemented the backend infrastructure for the AI-assisted order editing workflow. This allows doctors to receive AI suggestions for order improvements and edit orders until admin approval - working universally across all patients and doctors.

## Files Created

### Database Migrations
1. **[admin/add-order-lifecycle-fields.php](admin/add-order-lifecycle-fields.php)**
   - Adds `review_status` column (draft, pending_admin_review, approved, needs_revision, rejected)
   - Adds `ai_suggestions` JSONB column to store AI recommendations
   - Adds `ai_suggestions_accepted` and `ai_suggestions_accepted_at` tracking
   - Adds `locked_at`, `locked_by` for edit locking
   - Adds `reviewed_by`, `reviewed_at`, `review_notes` for admin tracking
   - Sets existing orders to 'approved' status

2. **[admin/add-order-revisions-table.php](admin/add-order-revisions-table.php)**
   - Creates `order_revisions` table for complete audit trail
   - Tracks who changed what, when, and why
   - Flags AI-suggested changes separately

### API Endpoints
3. **[api/portal/generate_order_suggestions.php](api/portal/generate_order_suggestions.php)**
   - Analyzes order data using Claude AI
   - Returns specific field-level suggestions with reasons
   - Calculates approval score (GREEN/YELLOW/RED)
   - Identifies missing items and concerns

4. **[api/portal/order.update.php](api/portal/order.update.php)**
   - Updates existing orders with edit lock checks
   - Only allows edits in 'draft' or 'needs_revision' status
   - Prevents editing after admin approval/rejection
   - Records all changes in order_revisions table
   - Supports accepting AI suggestions automatically

5. **[api/admin/order.review.php](api/admin/order.review.php)**
   - Admin endpoint to approve/reject/request changes
   - Locks orders when approved or rejected
   - Unlocks orders when requesting changes
   - Sends email notifications to physicians
   - Records review actions in audit trail

### Updated Files
6. **[api/portal/orders.create.php](api/portal/orders.create.php)** (Modified)
   - Sets `review_status = 'pending_admin_review'` for new orders
   - Ensures all new orders enter the workflow correctly

7. **[admin/run-all-migrations.php](admin/run-all-migrations.php)** (Updated)
   - Added new migrations to the list

### Helper Scripts
8. **[run-order-workflow-migrations.sh](run-order-workflow-migrations.sh)**
   - Script to run migrations on production via SSH
   - Makes deployment simple

## How It Works

### For Doctors (Order Creation Flow)
```
1. Doctor fills out order form
2. [FUTURE] AI analyzes and suggests improvements
3. [FUTURE] Doctor can accept/reject/edit suggestions
4. Order submits with status: "pending_admin_review"
5. Order is now editable until admin reviews
```

### For Admins (Review Flow)
```
1. Admin sees order in dashboard (status: pending_admin_review)
2. [FUTURE] Admin sees AI assessment with order
3. Admin can:
   - Approve → Order locked, status = "approved"
   - Request Changes → Order unlocked, status = "needs_revision", doctor notified
   - Reject → Order locked, status = "rejected"
```

### For Doctors (Revision Flow)
```
1. Doctor receives notification of "needs_revision"
2. Doctor edits order (now unlocked)
3. Order resubmits with status: "pending_admin_review"
4. Cycle repeats until approved
```

## Permissions Model

The `canEditOrder()` function in [order.update.php](api/portal/order.update.php) implements universal permissions:

```php
// Doctor can edit if:
// 1. They created it AND
// 2. It's not locked (approved/rejected) AND
// 3. It's in draft or needs_revision status

// Superadmin can always edit (with logging)
```

## What Still Needs To Be Done

### Phase 2: Doctor Portal UI (Next Step)
- [ ] Integrate AI suggestions display into order form
- [ ] Add "Accept Suggestions" button
- [ ] Show order status clearly (pending, needs revision, approved)
- [ ] Make order details editable when status allows
- [ ] Add "Edit Order" button for needs_revision status

### Phase 3: Admin Panel UI
- [ ] Create order review interface
- [ ] Display AI assessment alongside order details
- [ ] Add approve/request changes/reject buttons
- [ ] Show revision history
- [ ] Filter orders by review status

### Phase 4: Testing & Rollout
- [ ] Test with Randy Dittmar test patient
- [ ] Verify email notifications work
- [ ] Test full workflow: create → suggest → edit → approve
- [ ] Test revision cycle: request changes → edit → resubmit
- [ ] Deploy to production

## Database Schema Changes

### `orders` table - New columns:
- `review_status` VARCHAR(50) DEFAULT 'draft'
- `ai_suggestions` JSONB
- `ai_suggestions_accepted` BOOLEAN DEFAULT FALSE
- `ai_suggestions_accepted_at` TIMESTAMP
- `locked_at` TIMESTAMP
- `locked_by` VARCHAR(32)
- `reviewed_by` VARCHAR(32)
- `reviewed_at` TIMESTAMP
- `review_notes` TEXT

### `order_revisions` table - New table:
- `id` SERIAL PRIMARY KEY
- `order_id` VARCHAR(32) → references orders(id)
- `changed_by` VARCHAR(32) → references users(id)
- `changed_at` TIMESTAMP
- `changes` JSONB (stores before/after values)
- `reason` TEXT
- `ai_suggested` BOOLEAN

## API Usage Examples

### Generate AI Suggestions
```javascript
const response = await fetch('/api/portal/generate_order_suggestions.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    order_data: {
      first_name: 'Randy',
      last_name: 'Dittmar',
      wound_location: 'Left Foot',
      frequency: '2x per week',
      // ... all order fields
    }
  })
});

const { ok, suggestions, approval_score } = await response.json();
// suggestions = [{field, current_value, suggested_value, reason, priority}, ...]
// approval_score = {score: 'GREEN', score_numeric: 85, summary: '...'}
```

### Update Order
```javascript
const response = await fetch('/api/portal/order.update.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    order_id: 'abc123...',
    updates: {
      wound_location: 'Left Foot, Plantar Surface',
      frequency: '3x per week'
    },
    accept_ai_suggestions: false,
    reason: 'Updating based on doctor review'
  })
});
```

### Admin Review
```javascript
const response = await fetch('/api/admin/order.review.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    order_id: 'abc123...',
    action: 'request_changes', // or 'approve' or 'reject'
    notes: 'Please provide more specific wound measurements'
  })
});
```

## Running the Migrations

### Option 1: Via SSH (Recommended for Production)
```bash
./run-order-workflow-migrations.sh
```

### Option 2: Via Web Browser
Navigate to:
```
https://collagendirect.health/admin/add-order-lifecycle-fields.php
https://collagendirect.health/admin/add-order-revisions-table.php
```

### Option 3: Run All Migrations
```
https://collagendirect.health/admin/run-all-migrations.php
```

## Benefits Delivered

✅ **Universal Application**: Works for all doctors and all patients automatically
✅ **Complete Audit Trail**: Every change tracked in order_revisions
✅ **Permission System**: Role-based access with edit locking
✅ **AI Integration Ready**: Infrastructure for AI suggestions in place
✅ **Email Notifications**: Doctors notified of review decisions
✅ **Database Integrity**: Foreign key constraints and indexes
✅ **Backwards Compatible**: Existing orders marked as 'approved'

## Next Steps

1. **Run the migrations** on production database
2. **Test the APIs** with curl or Postman
3. **Build the UI** for doctor portal (Phase 2)
4. **Build the UI** for admin panel (Phase 3)
5. **Test with Randy Dittmar** test patient
6. **Roll out** to all users

---

**Status**: Phase 1 (Backend) Complete ✅
**Priority**: High
**Estimated Remaining Effort**: 2-3 weeks for UI implementation
**Dependencies**: AI approval score system (✅ Complete)
