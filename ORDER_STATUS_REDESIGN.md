# Order Status Redesign

## Current Problem

Orders are classified as "previous orders" vs "active orders" based on the `status` field which has values like 'active', 'submitted', 'approved', 'stopped', 'completed'. This doesn't align with the actual workflow and prevents editing when needed.

## User Requirements

1. **Remove "Previous Order" classification** - No distinction between past and current
2. **New Status Values**: Submitted, Pending, Accepted, Expired
3. **Editability Rule**: Can only edit if status is NOT "Accepted" or "Expired"
4. **Timeline**: Orders are generally same-day or next-day to desired start date

## Proposed Solution

### Status Field Mapping

We have TWO status fields in the database:
1. **`status`** (old field) - submitted, active, approved, stopped, completed
2. **`review_status`** (new field) - draft, pending_admin_review, needs_revision, approved, rejected

**Proposed Unified Display Status:**

| Display Status | Editable? | Database Condition |
|---------------|-----------|-------------------|
| **Draft** | ✅ Yes | `review_status = 'draft'` |
| **Submitted** | ✅ Yes | `review_status = 'pending_admin_review'` AND `locked_at IS NULL` |
| **Needs Revision** | ✅ Yes | `review_status = 'needs_revision'` |
| **Accepted** | ❌ No | `review_status = 'approved'` OR `locked_at IS NOT NULL` |
| **Rejected** | ❌ No | `review_status = 'rejected'` |
| **Expired** | ❌ No | `created_at < NOW() - INTERVAL '30 days'` AND status != 'approved' |

### Display Logic

```javascript
function getOrderDisplayStatus(order) {
  // Check if expired (30 days old and not approved)
  const createdDate = new Date(order.created_at);
  const daysSinceCreated = (Date.now() - createdDate) / (1000 * 60 * 60 * 24);

  if (daysSinceCreated > 30 && order.review_status !== 'approved') {
    return { status: 'Expired', editable: false, color: 'gray' };
  }

  // Check review_status
  switch(order.review_status) {
    case 'draft':
      return { status: 'Draft', editable: true, color: 'slate' };

    case 'pending_admin_review':
      return { status: 'Submitted', editable: !order.locked_at, color: 'blue' };

    case 'needs_revision':
      return { status: 'Needs Revision', editable: true, color: 'orange' };

    case 'approved':
      return { status: 'Accepted', editable: false, color: 'green' };

    case 'rejected':
      return { status: 'Rejected', editable: false, color: 'red' };

    default:
      // Fallback for old orders without review_status
      if (order.status === 'approved' || order.status === 'active') {
        return { status: 'Accepted', editable: false, color: 'green' };
      }
      return { status: 'Submitted', editable: true, color: 'blue' };
  }
}

function canEditOrder(order) {
  const displayStatus = getOrderDisplayStatus(order);
  return displayStatus.editable;
}
```

### UI Changes Needed

#### 1. Patient Detail Page - Orders Section

**Before:** "Upcoming Orders" vs "Previous Orders"
**After:** All orders in one section with clear status badges

```html
<!-- All Orders Section -->
<div class="card p-6">
  <h4 class="font-semibold text-base mb-4">Orders</h4>
  <div class="space-y-2">
    ${orders.map(order => {
      const status = getOrderDisplayStatus(order);
      return `
        <div class="border-l-4 border-${status.color}-500 bg-${status.color}-50 p-3 rounded">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium text-sm">${esc(order.product)}</span>
                <span class="px-2 py-0.5 bg-${status.color}-100 text-${status.color}-800 rounded-full text-xs font-medium">
                  ${status.status}
                </span>
              </div>
              <div class="text-xs text-slate-600">
                ${fmt(order.created_at)} • ${esc(order.frequency)}
              </div>
            </div>
            <div class="flex gap-2">
              <button class="btn btn-sm" onclick='viewOrderDetailsEnhanced(${JSON.stringify(order)})'>
                View
              </button>
              ${status.editable ? `
                <button class="btn btn-sm btn-primary" onclick='openOrderEditDialog("${order.id}")'>
                  Edit
                </button>
              ` : ''}
            </div>
          </div>
        </div>
      `;
    }).join('')}
  </div>
</div>
```

#### 2. Dashboard Metrics

**Before:** "Active Orders" (approved & shipped)
**After:** Show counts by new statuses

```javascript
const metrics = {
  draft: orders.filter(o => getOrderDisplayStatus(o).status === 'Draft').length,
  submitted: orders.filter(o => getOrderDisplayStatus(o).status === 'Submitted').length,
  needsRevision: orders.filter(o => getOrderDisplayStatus(o).status === 'Needs Revision').length,
  accepted: orders.filter(o => getOrderDisplayStatus(o).status === 'Accepted').length,
  expired: orders.filter(o => getOrderDisplayStatus(o).status === 'Expired').length
};
```

### Migration Strategy

#### Option A: Update Existing Orders (Recommended)

```sql
-- Set review_status based on current status
UPDATE orders
SET review_status = CASE
  WHEN status IN ('approved', 'active') THEN 'approved'
  WHEN status = 'submitted' THEN 'pending_admin_review'
  WHEN status = 'stopped' THEN 'rejected'
  ELSE 'pending_admin_review'
END
WHERE review_status IS NULL OR review_status = 'draft';

-- Lock approved orders
UPDATE orders
SET locked_at = NOW(),
    locked_by = 'system'
WHERE review_status = 'approved' AND locked_at IS NULL;
```

#### Option B: Gradual Transition

Keep both systems working:
- New orders use `review_status`
- Old orders use `status`
- UI displays based on whichever is available

### Implementation Files

1. **portal/order-status-helper.js** - Status calculation logic
2. **portal/index.php** - Update order display sections
3. **admin/migrate-order-statuses.php** - Migration script

### Testing Checklist

- [ ] Draft order shows "Draft" badge, is editable
- [ ] Submitted order shows "Submitted" badge, is editable
- [ ] Needs revision order shows badge, is editable
- [ ] Accepted order shows "Accepted" badge, NOT editable
- [ ] 30+ day old unaccepted order shows "Expired", NOT editable
- [ ] Edit button only appears for editable orders
- [ ] viewOrderDetailsEnhanced shows correct edit options

## Next Steps

1. Create `order-status-helper.js` with status calculation
2. Update portal/index.php to use new status logic
3. Create migration script for existing orders
4. Test with various order scenarios
5. Update admin panel to use same statuses
