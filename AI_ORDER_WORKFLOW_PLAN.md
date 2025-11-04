# AI-Assisted Order Workflow Implementation Plan

## Overview
Enable doctors to receive AI suggestions for order improvements and keep orders editable until admin approval.

## Current Workflow Issues
1. ❌ Orders cannot be edited once submitted
2. ❌ AI approval score is separate from order workflow
3. ❌ No way for doctor to accept/reject AI suggestions
4. ❌ Admin approval process is not clearly defined

## Proposed Workflow

### Phase 1: Doctor Creates Order
```
Doctor fills out order form
    ↓
AI analyzes order for completeness
    ↓
AI provides suggestions (optional improvements)
    ↓
Doctor can:
  - Accept AI suggestions (auto-fill improvements)
  - Reject and submit as-is
  - Edit manually
    ↓
Order submitted with status: "pending_admin_review"
```

### Phase 2: Admin Review
```
Admin sees order in dashboard
    ↓
Admin reviews order + AI assessment
    ↓
Admin can:
  - Approve → status: "approved" (order locked)
  - Request changes → status: "needs_revision" (order unlocked)
  - Reject → status: "rejected" (order locked)
    ↓
If "needs_revision":
  - Doctor receives notification
  - Doctor can edit order
  - Cycle repeats
```

## Database Schema Changes

### Add to `orders` table:
```sql
-- Order lifecycle status
ALTER TABLE orders ADD COLUMN review_status VARCHAR(50) DEFAULT 'draft';
-- Values: draft, pending_admin_review, approved, needs_revision, rejected

-- AI suggestions
ALTER TABLE orders ADD COLUMN ai_suggestions JSONB;
-- Stores AI recommendations for order improvements

-- AI suggestions acceptance tracking
ALTER TABLE orders ADD COLUMN ai_suggestions_accepted BOOLEAN DEFAULT FALSE;
ALTER TABLE orders ADD COLUMN ai_suggestions_accepted_at TIMESTAMP;

-- Edit lock (prevent editing after admin approval)
ALTER TABLE orders ADD COLUMN locked_at TIMESTAMP;
ALTER TABLE orders ADD COLUMN locked_by VARCHAR(32);

-- Admin review tracking
ALTER TABLE orders ADD COLUMN reviewed_by VARCHAR(32);
ALTER TABLE orders ADD COLUMN reviewed_at TIMESTAMP;
ALTER TABLE orders ADD COLUMN review_notes TEXT;
```

### Add table for order revisions history:
```sql
CREATE TABLE order_revisions (
  id SERIAL PRIMARY KEY,
  order_id VARCHAR(32) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  changed_by VARCHAR(32) NOT NULL REFERENCES users(id),
  changed_at TIMESTAMP DEFAULT NOW(),
  changes JSONB NOT NULL,
  reason TEXT,
  ai_suggested BOOLEAN DEFAULT FALSE
);
```

## UI/UX Changes

### For Doctors (Portal)

#### 1. Order Form Enhancement
```
┌─────────────────────────────────────┐
│ Create New Order                    │
├─────────────────────────────────────┤
│ [Order form fields...]              │
│                                     │
│ ┌─ AI Assistant ─────────────────┐ │
│ │ 💡 Suggestion:                  │ │
│ │ Based on the wound description, │ │
│ │ consider:                        │ │
│ │  • Increasing frequency to 3x/wk│ │
│ │  • Adding secondary ICD-10 code │ │
│ │                                  │ │
│ │ [Accept Suggestions] [Dismiss]  │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [Save Draft] [Submit for Review]   │
└─────────────────────────────────────┘
```

#### 2. Order Detail - Pending State
```
┌─────────────────────────────────────┐
│ Order Status: Pending Admin Review  │
├─────────────────────────────────────┤
│ ⏳ This order is awaiting review    │
│    by the manufacturer.             │
│                                     │
│ [Edit Order] [Cancel Order]        │
└─────────────────────────────────────┘
```

#### 3. Order Detail - Needs Revision
```
┌─────────────────────────────────────┐
│ Order Status: Revision Requested    │
├─────────────────────────────────────┤
│ ⚠️  Admin Feedback:                 │
│ "Please provide more specific wound │
│  measurements and location details."│
│                                     │
│ [Edit Order] [Contact Support]     │
└─────────────────────────────────────┘
```

### For Admins (Admin Panel)

#### Order Review Interface
```
┌─────────────────────────────────────────┐
│ Order Review - Randy Dittmar           │
├─────────────────────────────────────────┤
│ Status: Pending Review                  │
│                                         │
│ ┌─ AI Assessment ─────────────────┐    │
│ │ Approval Score: 75/100 (YELLOW)  │    │
│ │ Missing: Secondary ICD-10 code   │    │
│ │ Concern: Frequency may be low    │    │
│ └──────────────────────────────────┘    │
│                                         │
│ [Order Details...]                      │
│                                         │
│ Admin Actions:                          │
│ ┌────────────────────────────────┐     │
│ │ Feedback to Doctor (optional):  │     │
│ │ [text area]                     │     │
│ └────────────────────────────────┘     │
│                                         │
│ [✓ Approve] [✎ Request Changes] [✗ Reject] │
└─────────────────────────────────────────┘
```

## API Endpoints Needed

### 1. Generate AI Order Suggestions
```
POST /api/portal/generate_order_suggestions.php
Body: { order_data: {...} }
Response: {
  ok: true,
  suggestions: [
    {
      field: "frequency_per_week",
      current_value: "2",
      suggested_value: "3",
      reason: "Based on wound severity, 3x weekly is recommended for optimal healing"
    }
  ],
  approval_score: { score: "YELLOW", ... }
}
```

### 2. Update Order (while editable)
```
POST /api/portal/order.update
Body: {
  order_id: "...",
  updates: {...},
  accept_ai_suggestions: true/false
}
```

### 3. Admin Review Actions
```
POST /api/admin/order.review
Body: {
  order_id: "...",
  action: "approve" | "request_changes" | "reject",
  notes: "..."
}
```

## Implementation Phases

### Phase 1: Database & Backend (Week 1)
- [ ] Create migration for orders table columns
- [ ] Create order_revisions table
- [ ] Add order editing permissions logic
- [ ] Implement AI order suggestions generator
- [ ] Add order update API with edit lock checks

### Phase 2: Doctor Portal (Week 2)
- [ ] Add "Save Draft" functionality to order form
- [ ] Integrate AI suggestions display
- [ ] Add "Accept Suggestions" button
- [ ] Make order details editable when status allows
- [ ] Show clear status indicators

### Phase 3: Admin Panel (Week 3)
- [ ] Create order review interface
- [ ] Add AI assessment display in review
- [ ] Implement approve/request changes/reject actions
- [ ] Add revision history view
- [ ] Email notifications for status changes

### Phase 4: Universal Application (Week 4)
- [ ] Test with multiple doctors/patients
- [ ] Ensure permissions work across all user roles
- [ ] Add audit logging for all order changes
- [ ] Document workflow in user guides

## Security & Permissions

### Role-Based Access
```javascript
canEditOrder(order, user) {
  // Doctor can edit if:
  // 1. They created it AND
  // 2. It's not locked (approved/rejected) AND
  // 3. It's in draft or needs_revision status

  if (user.role === 'physician' || user.role === 'practice_admin') {
    return order.user_id === user.id
      && !order.locked_at
      && ['draft', 'needs_revision'].includes(order.review_status);
  }

  // Admin can always edit (with logging)
  if (user.role === 'superadmin') {
    return true;
  }

  return false;
}
```

## Benefits

1. **Improved Order Quality**: AI catches issues before submission
2. **Faster Approval**: Better orders = quicker admin review
3. **Better Communication**: Clear feedback loop between doctor and admin
4. **Audit Trail**: Complete history of all changes
5. **Scalability**: Works for any number of doctors/patients
6. **Compliance**: All changes logged and tracked

## Next Steps

1. Review and approve this plan
2. Create database migration
3. Build API endpoints
4. Update UI components
5. Test with Randy Dittmar test patient
6. Roll out to all users

---

**Status**: Planning Phase
**Priority**: High
**Estimated Effort**: 4 weeks
**Dependencies**: AI approval score system (✅ Complete)
