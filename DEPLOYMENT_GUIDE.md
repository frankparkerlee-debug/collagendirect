# Order Workflow Implementation - Deployment Guide

## Overview

The AI-assisted order editing workflow has been fully implemented. This allows doctors to receive AI suggestions, edit orders until admin approval, and provides admins with AI-powered review tools - all working universally across all patients and doctors.

## What Was Built

### Phase 1: Backend Infrastructure ✅
- Database schema changes for order lifecycle
- API endpoints for AI suggestions, order updates, and admin review
- Audit trail system with complete revision tracking

### Phase 2: Doctor Portal UI ✅
- Enhanced order details viewer with AI suggestions
- Order edit dialog with all editable fields
- AI suggestion acceptance buttons
- Review status indicators

### Phase 3: Admin Panel UI ✅
- Enhanced order review interface
- AI assessment display
- Approve/Request Changes/Reject workflow
- Admin feedback system

## Files Created/Modified

### Database Migrations
1. `admin/add-order-lifecycle-fields.php` - Adds review_status, ai_suggestions, locking fields
2. `admin/add-order-revisions-table.php` - Creates audit trail table

### API Endpoints
3. `api/portal/generate_order_suggestions.php` - AI analyzes orders and suggests improvements
4. `api/portal/order.update.php` - Doctors can edit orders with permission checks
5. `api/portal/order.get.php` - Retrieve single order for editing
6. `api/admin/order.review.php` - Admins approve/reject/request changes
7. `api/admin/order.get.php` - Admins retrieve order details

### Frontend Components - Doctor Portal
8. `portal/order-workflow.js` - Enhanced order viewer with AI suggestions
9. `portal/order-edit-dialog.html` - Complete order editing interface

### Frontend Components - Admin Panel
10. `admin/order-review-enhanced.js` - Enhanced review interface with AI assessment

### Core Updates
11. `api/portal/orders.create.php` - Modified to set initial review_status
12. `admin/run-all-migrations.php` - Updated to include new migrations

## Deployment Steps

### Step 1: Commit and Push Changes

```bash
cd /Users/parkerlee/CollageDirect2.1/collagendirect

# Add all new files
git add admin/add-order-lifecycle-fields.php
git add admin/add-order-revisions-table.php
git add api/portal/generate_order_suggestions.php
git add api/portal/order.update.php
git add api/portal/order.get.php
git add api/admin/order.review.php
git add api/admin/order.get.php
git add portal/order-workflow.js
git add portal/order-edit-dialog.html
git add admin/order-review-enhanced.js

# Add modified files
git add api/portal/orders.create.php
git add admin/run-all-migrations.php

# Add documentation
git add AI_ORDER_WORKFLOW_PLAN.md
git add ORDER_WORKFLOW_IMPLEMENTATION_SUMMARY.md
git add DEPLOYMENT_GUIDE.md

# Commit
git commit -m "$(cat <<'EOF'
Implement AI-assisted order editing workflow

Backend Infrastructure:
- Add order lifecycle fields (review_status, ai_suggestions, locking)
- Create order_revisions table for complete audit trail
- Implement order editing API with permission checks
- Add admin review API (approve/reject/request changes)
- Build AI order suggestions generator

Doctor Portal UI:
- Enhanced order details viewer with AI suggestions
- Complete order edit dialog with all fields
- Accept AI suggestions button
- Review status indicators and feedback display

Admin Panel UI:
- Enhanced review interface with AI assessment
- Three-action workflow: approve/request changes/reject
- Admin feedback form
- Patient approval score integration

Features:
- Universal: Works for all doctors and all patients
- Complete audit trail: All changes tracked in order_revisions
- Email notifications: Doctors notified of review decisions
- Permission system: Role-based access with edit locking
- AI-powered: Suggestions for order improvements

Database changes:
- orders table: +9 columns for lifecycle management
- order_revisions table: Full revision history

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"

# Push to repository
git push origin main
```

### Step 2: Deploy to Render

Render will automatically detect the push and redeploy. Wait for deployment to complete.

### Step 3: Run Database Migrations

Once deployed, run migrations via web browser:

**Option A: Run individual migrations**
1. https://collagendirect.health/admin/add-order-lifecycle-fields.php
2. https://collagendirect.health/admin/add-order-revisions-table.php

**Option B: Run all migrations**
- https://collagendirect.health/admin/run-all-migrations.php

### Step 4: Integrate into Portal

Add the following to `portal/index.php`:

**1. Include the order workflow JavaScript (in the `<head>` or before closing `</body>`):**
```html
<script src="/portal/order-workflow.js"></script>
```

**2. Include the order edit dialog HTML (before closing `</body>`):**
```php
<?php include __DIR__ . '/order-edit-dialog.html'; ?>
```

**3. Replace the existing `viewOrderDetails()` function call:**

Find where orders are displayed and change:
```javascript
onclick="viewOrderDetails(order)"
```

To:
```javascript
onclick="viewOrderDetailsEnhanced(order)"
```

**4. Add action handler for order.get:**

Add to the action routing section in portal/index.php:
```php
if ($action === 'order.get') {
  require __DIR__ . '/../api/portal/order.get.php';
  exit;
}
```

### Step 5: Integrate into Admin Panel

Add to `admin/order-review.php`:

**1. Include the enhanced review JavaScript (before closing `</body>`):**
```html
<script src="/admin/order-review-enhanced.js"></script>
```

**2. Update the order click handler:**

Change:
```javascript
onclick="viewOrder('${order.id}')"
```

To:
```javascript
onclick="viewOrderEnhanced('${order.id}')"
```

## Testing Checklist

### Doctor Portal Testing
- [ ] Create a new order - verify it has `review_status = 'pending_admin_review'`
- [ ] View order details - verify AI suggestions appear (if available)
- [ ] Click "Accept All Suggestions" - verify order updates
- [ ] Click "Edit Order" - verify dialog opens with pre-filled data
- [ ] Make changes and save - verify order updates
- [ ] Try to edit an approved order - verify it's locked
- [ ] View order with "needs_revision" status - verify admin feedback appears

### Admin Panel Testing
- [ ] View order review queue - verify orders appear
- [ ] Click on pending order - verify enhanced modal opens
- [ ] Verify AI assessment appears (if patient has approval score)
- [ ] Click "Approve" - verify order locks and status changes to 'approved'
- [ ] Click "Request Changes" with notes - verify order unlocks and email sent
- [ ] Click "Reject" - verify order locks and status changes to 'rejected'
- [ ] Verify doctor receives email notification

### End-to-End Workflow Testing
1. **Doctor creates order** → Status: `pending_admin_review`
2. **AI suggests improvements** (if available)
3. **Doctor accepts suggestions** → Order updates
4. **Admin requests changes** with feedback → Status: `needs_revision`, order unlocked
5. **Doctor sees feedback** in order details
6. **Doctor edits order** → Makes changes based on feedback
7. **Doctor resubmits** → Status: `pending_admin_review`
8. **Admin approves** → Status: `approved`, order locked
9. **Doctor tries to edit** → Blocked (order is locked)

## API Documentation

### For Doctors

#### Generate AI Suggestions
```javascript
POST /api/portal/generate_order_suggestions.php
{
  "order_data": {
    "first_name": "Randy",
    "wound_location": "Left Foot",
    "frequency": "2x per week",
    // ... all order fields
  }
}

Response:
{
  "ok": true,
  "suggestions": [{
    "field": "frequency",
    "current_value": "2x per week",
    "suggested_value": "3x per week",
    "reason": "Based on wound severity...",
    "priority": "high"
  }],
  "approval_score": {
    "score": "YELLOW",
    "score_numeric": 75,
    "summary": "Order is mostly complete..."
  }
}
```

#### Get Order
```javascript
GET /api/portal/order.get.php?order_id=abc123...

Response:
{
  "ok": true,
  "order": { /* all order fields */ }
}
```

#### Update Order
```javascript
POST /api/portal/order.update.php
{
  "order_id": "abc123...",
  "updates": {
    "wound_location": "Left Foot, Plantar Surface",
    "frequency": "3x per week"
  },
  "accept_ai_suggestions": false,
  "reason": "Updated based on admin feedback"
}

Response:
{
  "ok": true,
  "message": "Order updated successfully",
  "changes_count": 2
}
```

### For Admins

#### Get Order (Admin)
```javascript
GET /api/admin/order.get.php?order_id=abc123...

Response:
{
  "ok": true,
  "order": {
    /* order fields */
    "physician_first_name": "John",
    "physician_last_name": "Doe",
    "patient_first_name": "Randy",
    "patient_last_name": "Dittmar"
  }
}
```

#### Review Order
```javascript
POST /api/admin/order.review.php
{
  "order_id": "abc123...",
  "action": "request_changes",  // or "approve" or "reject"
  "notes": "Please provide more specific wound measurements"
}

Response:
{
  "ok": true,
  "message": "Order reviewed successfully",
  "new_status": "needs_revision",
  "action": "request_changes"
}
```

## Database Schema Reference

### `orders` table new columns:
```sql
review_status VARCHAR(50) DEFAULT 'draft'
  -- Values: draft, pending_admin_review, needs_revision, approved, rejected

ai_suggestions JSONB
  -- Stores AI recommendations

ai_suggestions_accepted BOOLEAN DEFAULT FALSE
ai_suggestions_accepted_at TIMESTAMP

locked_at TIMESTAMP
  -- Non-null = order is locked from editing

locked_by VARCHAR(32)
  -- User ID who locked the order

reviewed_by VARCHAR(32)
  -- Admin user ID who reviewed

reviewed_at TIMESTAMP
  -- When last reviewed

review_notes TEXT
  -- Admin feedback to physician
```

### `order_revisions` table:
```sql
id SERIAL PRIMARY KEY
order_id VARCHAR(32) → orders(id)
changed_by VARCHAR(32) → users(id)
changed_at TIMESTAMP DEFAULT NOW()
changes JSONB
  -- {"field_name": {"old": "value", "new": "value"}}
reason TEXT
ai_suggested BOOLEAN DEFAULT FALSE
```

## Troubleshooting

### Orders not showing review_status
- Run migrations: `/admin/add-order-lifecycle-fields.php`
- Check column exists: `SELECT review_status FROM orders LIMIT 1;`

### Cannot edit orders
- Check `review_status` is 'draft' or 'needs_revision'
- Check `locked_at` is NULL
- Check user owns the order

### Admin review not working
- Verify admin auth exists in session
- Check admin role is 'superadmin'
- Verify order exists in database

### AI suggestions not appearing
- Check patient has approval score in `patient_approval_scores` table
- Verify `ai_suggestions` column has JSON data
- Check browser console for JavaScript errors

## Rollback Plan

If issues arise, you can roll back:

1. **Revert code changes:**
```bash
git revert HEAD
git push origin main
```

2. **Remove database columns (if needed):**
```sql
ALTER TABLE orders
  DROP COLUMN review_status,
  DROP COLUMN ai_suggestions,
  DROP COLUMN ai_suggestions_accepted,
  DROP COLUMN ai_suggestions_accepted_at,
  DROP COLUMN locked_at,
  DROP COLUMN locked_by,
  DROP COLUMN reviewed_by,
  DROP COLUMN reviewed_at,
  DROP COLUMN review_notes;

DROP TABLE order_revisions;
```

## Support

For issues or questions:
1. Check browser console for JavaScript errors
2. Check server logs for PHP errors
3. Review this deployment guide
4. Contact development team

---

**Status**: Ready for Deployment
**Date**: 2025-01-04
**Version**: 1.0.0
