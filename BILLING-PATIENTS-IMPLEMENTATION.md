# Billing & Patient Management Implementation Plan

## Overview
Comprehensive billing and patient management system with role-based access, revenue tracking, and manufacturer notifications.

## 1. admin/billing.php Enhancements

### 1.1 Role-Based Access Control
**Requirements:**
- Super Admin: See all orders
- Employees: See orders from assigned physicians only
- Manufacturer: See all orders (read-only)

**Implementation:**
```php
if ($adminRole === 'superadmin' || $adminRole === 'manufacturer') {
  // No filter - see all orders
  $query = "SELECT ... FROM orders o ...";
} else {
  // Filter by assigned physicians
  $query = "SELECT ... FROM orders o
            INNER JOIN admin_physicians ap ON ap.physician_user_id = o.user_id
            WHERE ap.admin_id = ?";
}
```

### 1.2 Document Access Columns
**Columns to Add:**
1. **ID Card** - View/Download patient ID uploads
2. **Insurance Card** - View/Download insurance card uploads
3. **Clinical Notes** - View/Download clinical notes
4. **Order PDF** - Generate and download order summary PDF
5. **Download All** (Manufacturer only) - ZIP all documents

**File Locations:**
- IDs: `/uploads/ids/`
- Insurance: `/uploads/insurance/`
- Notes: `/uploads/notes/`
- Order PDFs: Generated on-demand

**UI Implementation:**
```html
<td>
  <a href="/admin/file.dl.php?type=id&patient_id=XXX" target="_blank">View ID</a>
  <a href="/admin/file.dl.php?type=insurance&patient_id=XXX" target="_blank">View Ins</a>
  <a href="/admin/file.dl.php?type=notes&order_id=XXX" target="_blank">View Notes</a>
  <a href="/admin/order.pdf.php?id=XXX" target="_blank">Order PDF</a>
  <?php if ($adminRole === 'manufacturer'): ?>
    <a href="/admin/download-all.php?order_id=XXX">Download All</a>
  <?php endif; ?>
</td>
```

### 1.3 Revenue Calculation
**Formula:** `CPT Price × Quantity × Frequency × Duration`

**Database Query:**
```sql
SELECT
  o.*,
  p.first_name, p.last_name,
  pr.cpt_code,
  rr.rate_non_rural as cpt_price,
  o.frequency,
  o.shipments_remaining as duration,
  (rr.rate_non_rural * [quantity_calculation] * [frequency_per_week] * o.shipments_remaining) as projected_revenue
FROM orders o
LEFT JOIN patients p ON p.id = o.patient_id
LEFT JOIN products pr ON pr.id = o.product_id
LEFT JOIN reimbursement_rates rr ON rr.cpt_code = pr.cpt_code
```

**Frequency Calculation:**
- Daily = 7 patches/week
- Every other day = 3.5 patches/week
- Weekly = 1 patch/week

**Display:**
```html
<td class="text-right font-medium">$<?= number_format($row['projected_revenue'], 2) ?></td>
```

---

## 2. Dashboard Graphics

### 2.1 Revenue by Physician Chart
**Chart Type:** Bar chart or column chart
**Data Points:**
```sql
SELECT
  u.first_name, u.last_name, u.practice_name,
  SUM([revenue_calculation]) as total_revenue
FROM orders o
JOIN users u ON u.id = o.user_id
WHERE o.status NOT IN ('rejected', 'cancelled')
GROUP BY u.id
ORDER BY total_revenue DESC
LIMIT 10
```

**Implementation:** Chart.js or similar
```javascript
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: ['Dr. Smith', 'Dr. Jones', ...],
    datasets: [{
      label: 'Projected Revenue',
      data: [25000, 18000, ...]
    }]
  }
});
```

### 2.2 Revenue by Product Chart
**Chart Type:** Pie chart or donut chart
**Data Points:**
```sql
SELECT
  pr.name,
  pr.cpt_code,
  COUNT(o.id) as order_count,
  SUM([revenue_calculation]) as total_revenue
FROM orders o
JOIN products pr ON pr.id = o.product_id
WHERE o.status NOT IN ('rejected', 'cancelled')
GROUP BY pr.id
ORDER BY total_revenue DESC
```

---

## 3. Manufacturer Notifications

### 3.1 Email on Order Submission
**Trigger:** When order status changes to 'submitted' or 'pending'

**Implementation:**
```php
// In order submission handler
if ($newStatus === 'submitted') {
  // Get manufacturer emails
  $manufacturers = $pdo->query("SELECT email FROM admin_users WHERE role = 'manufacturer'");

  foreach ($manufacturers as $mfg) {
    send_order_notification_email(
      $mfg['email'],
      $orderId,
      $patientName,
      $physicianName,
      $documentLinks
    );
  }
}
```

**Email Content:**
- Subject: "New Order Submitted - [Patient Name]"
- Body: Order details, physician info, patient info
- Attachments: ID, Insurance, Notes, Order PDF

### 3.2 Portal Notification
**Database Table:** Already have `order_alerts` table or use `messages`

**Notification Display:**
- Badge on admin nav
- List on dashboard
- Link to order details

---

## 4. admin/patients.php

### 4.1 Patient List with Role-Based Access
**Query:**
```sql
SELECT
  p.*,
  u.first_name as physician_first,
  u.last_name as physician_last,
  u.practice_name,
  COUNT(o.id) as order_count,
  MAX(o.created_at) as last_order_date
FROM patients p
LEFT JOIN users u ON u.id = p.user_id
LEFT JOIN orders o ON o.patient_id = p.id
[WHERE clause based on role]
GROUP BY p.id
ORDER BY p.created_at DESC
```

**Role Filtering:**
- Super Admin: All patients
- Employees: Patients from assigned physicians only
- Manufacturer: All patients

### 4.2 Document Columns
Same as billing page:
- View ID Card
- View Insurance Card
- View Clinical Notes
- View All Orders
- Download All (Manufacturer)

### 4.3 Pre-Authorization Status
**Additional Columns:**
- Pre-Auth Status (dropdown: Pending, Approved, Denied)
- Pre-Auth Notes (textarea)
- Pre-Auth Date

**Table Addition:**
```sql
ALTER TABLE patients
ADD COLUMN preauth_status VARCHAR(20) DEFAULT 'pending',
ADD COLUMN preauth_notes TEXT,
ADD COLUMN preauth_date DATE;
```

---

## 5. Dashboard Updates

### 5.1 Revenue Summary Cards
- Total Projected Revenue
- Revenue This Month
- Revenue by Status (Approved, Pending, etc.)

### 5.2 Charts
1. **Revenue by Physician** (Bar chart)
2. **Revenue by Product** (Pie chart)
3. **Revenue Trend** (Line chart over time)
4. **Order Status Distribution** (Donut chart)

### 5.3 Recent Activity
- Recent orders with revenue
- Manufacturer notifications
- Pre-authorization requests

---

## Implementation Priority

### Phase 1 (Critical - Immediate)
1. ✅ Role-based access for billing.php
2. ✅ Document access columns in billing.php
3. ✅ Revenue calculation and display
4. ✅ Create admin/patients.php with basic functionality

### Phase 2 (High Priority)
1. Manufacturer email notifications on order submission
2. Portal notifications for manufacturers
3. Dashboard revenue charts

### Phase 3 (Enhancement)
1. Download All functionality
2. Pre-authorization workflow
3. Advanced filtering and search

---

## Database Schema Changes Required

### New Tables
```sql
-- For pre-authorization tracking
CREATE TABLE IF NOT EXISTS patient_preauthorizations (
  id SERIAL PRIMARY KEY,
  patient_id VARCHAR(64) NOT NULL,
  order_id VARCHAR(64),
  status VARCHAR(20) DEFAULT 'pending',
  notes TEXT,
  requested_by VARCHAR(64),
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  approved_by VARCHAR(64),
  approved_at TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- For manufacturer notifications
CREATE TABLE IF NOT EXISTS manufacturer_notifications (
  id SERIAL PRIMARY KEY,
  order_id VARCHAR(64) NOT NULL,
  notification_type VARCHAR(50),
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id)
);
```

### Existing Table Updates
```sql
-- Add revenue tracking to orders
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS calculated_revenue DECIMAL(10,2),
ADD COLUMN IF NOT EXISTS revenue_updated_at TIMESTAMP;
```

---

## File Structure

```
/admin/
  billing.php          (Enhanced with documents & revenue)
  patients.php         (NEW - Patient management)
  download-all.php     (NEW - ZIP all documents)
  index.php            (Updated dashboard with charts)

/api/lib/
  manufacturer_notify.php  (NEW - Email notifications)
  revenue_calculator.php   (NEW - Revenue calculations)
```

---

## Testing Checklist

- [ ] Super admin sees all orders in billing
- [ ] Employee sees only assigned physician orders
- [ ] Manufacturer sees all orders
- [ ] Document links work for all file types
- [ ] Revenue calculation is accurate
- [ ] Manufacturer receives email on order submission
- [ ] Notifications appear in manufacturer portal
- [ ] Dashboard charts display correctly
- [ ] Patients page shows correct data based on role
- [ ] Download All creates proper ZIP file
- [ ] Pre-authorization workflow functions

---

## Security Considerations

1. **File Access:** Verify user has permission before serving files
2. **Download All:** Limit to manufacturer role only
3. **Email Attachments:** Use secure file paths, not direct URLs
4. **Revenue Data:** Ensure calculated values match database
5. **Role Enforcement:** Double-check role on every query
