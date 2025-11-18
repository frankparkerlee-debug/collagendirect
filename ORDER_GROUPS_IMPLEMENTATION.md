# Order Groups Implementation - Multi-Product Orders

## Overview
This implementation allows physicians to create orders with multiple products for treating a single wound. All products in a group share the same visit note, baseline photo, wound details, and shipping information.

---

## ✅ Completed Components

### 1. Database Schema (`admin/run-migration-add-order-groups.php`)

**Tables Created:**
- `order_groups` - Parent container for multi-product orders
- `orders.order_group_id` - Foreign key linking orders to groups

**Key Features:**
- Backward compatible (existing orders unaffected)
- Foreign key constraints ensure data integrity
- Indexes for performance optimization
- NULL `order_group_id` = single product order
- Non-NULL `order_group_id` = part of a group

**Schema:**
```sql
order_groups:
  - id, user_id, patient_id
  - visit_note_path, baseline_wound_photo_path
  - wound_location, wound_type, wound_stage, dimensions
  - shipping info, insurance info
  - e-signature (sign_name, signed_at, signed_ip)
  - status, created_at, updated_at

orders:
  - order_group_id VARCHAR(64) DEFAULT NULL
  - Foreign key to order_groups.id ON DELETE CASCADE
```

---

### 2. Backend API (`api/portal/order-group.create.php`)

**Functionality:**
- Accepts JSON array of products
- Auto-detects single vs multi-product
- Transaction-safe (all-or-nothing)
- Stores visit note/photo at group level for multi-product
- Stores visit note/photo at order level for single product

**Request Format:**
```json
{
  "products": "[{\"product_id\": 5, \"quantity\": 30}, {\"product_id\": 8, \"quantity\": 15}]",
  "patient_id": "...",
  "wound_location": "Left Heel",
  "wound_type": "Pressure Ulcer",
  "wound_stage": "III",
  "wound_length_cm": "3.2",
  "wound_width_cm": "2.1",
  "wound_depth_cm": "0.5",
  "shipping_name": "...",
  "shipping_address": "...",
  "esign_confirm": "1",
  "sign_name": "Dr. Smith",
  "sign_title": "Physician"
}
```

**Response:**
```json
{
  "ok": true,
  "data": {
    "order_group_id": "abc123...",
    "order_ids": ["order1", "order2", "order3"],
    "patient_id": "pat123",
    "is_multi_product": true
  }
}
```

**Files Uploaded:**
- `file_rx_note` or `rx_note` → Visit note PDF
- `baseline_wound_photo` → Wound photo
- `ins_card` → Insurance card
- `id_card` → Patient ID

---

### 3. Portal Orders List UI (`portal/orders-list.php`)

**Features:**
- Displays single and grouped orders in unified list
- **Collapsed View:**
  - Shows order date, patient, wound location
  - Badge: "🔗 3 Products" for multi-product orders
  - Total price across all products
  - Status badge (Submitted, Shipped, etc.)
- **Expanded View (click to expand):**
  - Table listing all products with prices
  - Links to visit note and baseline photo
  - Product quantity breakdown
- **Search & Filters:**
  - Search by patient name or product
  - Filter by status (Draft, Submitted, Shipped)
  - Filter by type (Single/Multi-product)

**SQL Query:**
```sql
SELECT
  COALESCE(og.id, o.id) as display_id,
  og.id as group_id,
  COUNT(o.id) as product_count,
  SUM(o.product_price) as total_price,
  COALESCE(og.status, o.status) as status,
  p.first_name, p.last_name,
  COALESCE(og.wound_location, o.wound_location) as wound_location
FROM orders o
LEFT JOIN order_groups og ON og.id = o.order_group_id
JOIN patients p ON p.id = o.patient_id
WHERE o.user_id = ?
GROUP BY display_id, og.id, ...
ORDER BY created_at DESC
```

**Visual Design:**
- Green left border for grouped orders
- Expandable accordion-style rows
- Hover effects with shadow and lift
- Responsive grid layout

---

### 4. Portal Order Details UI (`portal/order-detail.php`)

**Features:**
- Auto-detects if `id` parameter is order_group_id or order_id
- **For Grouped Orders:**
  - Header shows "Order Group #..." with product count badge
  - Product table lists all items with subtotals
  - Visit note and baseline photo displayed once (group level)
  - Wound information section
  - Total price calculation
- **For Single Orders:**
  - Header shows "Order #..."
  - Single product in table
  - Visit note/photo from individual order record

**Sections:**
1. **Products Table** - All products with sizes, quantities, prices
2. **Wound Information** - Location, type, stage, dimensions
3. **Visit Documentation** - PDF link + photo preview
4. **Patient Details** - Name, DOB, MRN, contact info
5. **Shipping Information** - Address, phone
6. **Insurance Details** - Provider, member ID, group ID
7. **E-Signature** - Signer name, title, timestamp

**Navigation:**
- "Back to Orders" button
- Status badge
- Product count badge (for groups)

---

## 📋 Remaining Work

### 5. Update Order Creation Form
**Location:** `portal/index.php?page=new-order` or create new page

**Changes Needed:**
```html
<!-- Add multiple product selector -->
<div id="products-container">
  <div class="product-row">
    <select class="product-type-select" onchange="updateSizeDropdown(this)">
      <option value="">Select Product Type...</option>
      <option value="Hydrapad">Hydrapad</option>
      <option value="Collagen Sheet">Collagen Sheet</option>
    </select>
    <select class="size-select" disabled>
      <option value="">Select Size...</option>
    </select>
    <input type="number" class="quantity-input" placeholder="Quantity" min="1">
    <button type="button" onclick="removeProductRow(this)">Remove</button>
  </div>
</div>
<button type="button" onclick="addProductRow()">+ Add Another Product</button>
<div id="total-price">Total: $0.00</div>

<script>
function submitOrder() {
  const products = [];
  document.querySelectorAll('.product-row').forEach(row => {
    products.push({
      product_id: row.querySelector('.product-id-hidden').value,
      quantity: row.querySelector('.quantity-input').value
    });
  });

  const formData = new FormData();
  formData.append('products', JSON.stringify(products));
  formData.append('patient_id', patientId);
  formData.append('wound_location', woundLocation);
  // ... other fields

  fetch('/api/portal/order-group.create.php', {
    method: 'POST',
    body: formData
  }).then(response => response.json())
    .then(data => {
      if (data.ok) {
        window.location = '?page=order-detail&id=' + data.data.order_group_id;
      }
    });
}
</script>
```

**Form Fields:**
- Patient selection (dropdown or create new)
- Wound location, type, stage, dimensions
- **Multiple products** (add/remove rows dynamically)
- Visit note upload
- Baseline photo upload
- Shipping address
- Insurance info (if applicable)
- E-signature confirmation

---

### 6. Admin Order Management
**Location:** `admin/orders.php` or `admin/index.php`

**Changes Needed:**
```php
// Query with grouping
$sql = "
  SELECT
    COALESCE(og.id, o.id) as display_id,
    og.id as group_id,
    COUNT(o.id) as product_count,
    SUM(o.product_price) as total_revenue,
    u.practice_name,
    p.first_name, p.last_name,
    COALESCE(og.status, o.status) as status,
    COALESCE(og.created_at, o.created_at) as created_at
  FROM orders o
  LEFT JOIN order_groups og ON og.id = o.order_group_id
  JOIN patients p ON p.id = o.patient_id
  JOIN users u ON u.id = o.user_id
  GROUP BY display_id, og.id, ...
  ORDER BY created_at DESC
";
```

**Table Columns:**
- Order ID / Group ID
- Practice
- Patient
- Products (badge with count)
- Total Revenue
- Status
- Actions (View, Mark Shipped, Download Packing Slip)

**Packing Slip Generation:**
```php
// For grouped orders, include all products on one slip
if ($group_id) {
  $products = $pdo->prepare("
    SELECT product, qty_per_change, product_price
    FROM orders
    WHERE order_group_id = ?
  ")->execute([$group_id])->fetchAll();
  // Generate PDF with all products
}
```

---

### 7. Email Notifications
**Location:** Update email templates

**Changes:**
```php
// api/lib/email_notifications.php
function sendOrderConfirmation($order_group_id) {
  $group = fetchOrderGroup($order_group_id);
  $products = fetchOrderProducts($order_group_id);

  $emailBody = "
    Order Confirmation - Group #{$group['id']}

    Patient: {$group['patient_name']}
    Wound: {$group['wound_location']}

    Products:
  ";

  foreach ($products as $prod) {
    $emailBody .= "- {$prod['product']} x {$prod['quantity']}: \${$prod['price']}\n";
  }

  $emailBody .= "Total: \${$group['total_price']}";

  sendEmail($group['user_email'], 'Order Confirmation', $emailBody);
}
```

---

## 🧪 Testing Checklist

### Database Migration
- [ ] Run migration on production: `https://collagendirect.health/admin/run-migration-add-order-groups.php`
- [ ] Verify `order_groups` table created
- [ ] Verify `orders.order_group_id` column added
- [ ] Check foreign key constraints
- [ ] Confirm existing orders still load correctly

### API Testing
- [ ] Create single-product order (should NOT create order_group)
- [ ] Create multi-product order (should create order_group + multiple orders)
- [ ] Verify file uploads (visit note, baseline photo)
- [ ] Test transaction rollback (invalid product ID)
- [ ] Check e-signature fields populated correctly

### Portal UI Testing
- [ ] Orders list displays both single and grouped orders
- [ ] Product count badge shows on grouped orders
- [ ] Click to expand shows all products
- [ ] Search filters work correctly
- [ ] Status filters work
- [ ] Single/Multi filter works
- [ ] "View Details" link works

### Order Details Testing
- [ ] Grouped order shows all products
- [ ] Single order displays correctly
- [ ] Visit note downloads
- [ ] Baseline photo displays
- [ ] Patient info correct
- [ ] Shipping info correct
- [ ] E-signature displays

### End-to-End Flow
- [ ] Physician creates order with 3 products
- [ ] Order appears in list as grouped
- [ ] Click to expand shows 3 products
- [ ] View details shows complete information
- [ ] Admin sees order in admin panel
- [ ] Confirmation email sent (when implemented)

---

## 🔄 Migration Path for Existing Data

**No migration needed!** Existing orders remain as-is with `order_group_id = NULL`. They display as single-product orders in the new UI.

**If you want to retroactively group existing orders:**
```sql
-- Example: Group orders by patient + date
INSERT INTO order_groups (id, user_id, patient_id, wound_location, status, created_at)
SELECT
  gen_random_uuid(),
  o.user_id,
  o.patient_id,
  o.wound_location,
  o.status,
  MIN(o.created_at)
FROM orders o
WHERE o.patient_id = 'specific_patient_id'
  AND DATE(o.created_at) = '2024-11-17'
  AND o.order_group_id IS NULL
GROUP BY o.user_id, o.patient_id, o.wound_location, o.status;

-- Then update orders to link to group
UPDATE orders SET order_group_id = 'new_group_id'
WHERE patient_id = 'specific_patient_id'
  AND DATE(created_at) = '2024-11-17';
```

---

## 📊 Database Schema Diagram

```
┌─────────────────┐          ┌──────────────────┐
│ order_groups    │◄─────────│ orders           │
├─────────────────┤ 1     N  ├──────────────────┤
│ id (PK)         │          │ id (PK)          │
│ user_id (FK)    │          │ order_group_id   │ (FK to order_groups.id)
│ patient_id (FK) │          │ patient_id (FK)  │
│ visit_note_path │          │ user_id (FK)     │
│ baseline_photo  │          │ product_id (FK)  │
│ wound_*         │          │ product          │
│ shipping_*      │          │ product_price    │
│ sign_*          │          │ qty_per_change   │
│ status          │          │ status           │
│ created_at      │          │ created_at       │
└─────────────────┘          └──────────────────┘
        │                            │
        │                            │
        ▼                            ▼
┌─────────────────┐          ┌──────────────────┐
│ users           │          │ products         │
└─────────────────┘          └──────────────────┘
```

---

## 🎯 Usage Examples

### Example 1: Treating diabetic foot ulcer with multiple products
**Scenario:** Physician treats left heel ulcer with Hydrapad primary dressing + secondary foam + antimicrobial gel.

**Old System:** Created 3 separate orders
- Upload visit note 3 times
- Upload baseline photo 3 times
- Fill out wound details 3 times
- **Result:** Poor UX, data duplication

**New System:** Create 1 order group
- Upload visit note once
- Upload baseline photo once
- Fill out wound details once
- Add 3 products to cart
- **Result:** Clean, efficient workflow

### Example 2: Admin fulfillment
**Old System:**
- 3 orders for same patient on same date
- Admin must open each order individually
- Create 3 separate packing slips
- 3 separate shipment tracking numbers

**New System:**
- 1 order group with 3 products
- Single packing slip with all products
- One shipment tracking number
- Easier inventory management

---

## 🛠️ Development Notes

**File Paths (Important!):**
```
Visit Notes: /var/www/html/uploads/notes/
Baseline Photos: /var/www/html/uploads/wound_photos/
Insurance Cards: /var/www/html/uploads/insurance/
Patient IDs: /var/www/html/uploads/ids/
```

**Common Queries:**
```sql
-- Get all orders (grouped and ungrouped)
SELECT COALESCE(og.id, o.id) as display_id, ...

-- Get products in a group
SELECT * FROM orders WHERE order_group_id = ?

-- Check if order is grouped
SELECT order_group_id IS NOT NULL as is_grouped FROM orders WHERE id = ?
```

**JavaScript Helpers:**
```javascript
// Check if multi-product
const isMultiProduct = products.length > 1;

// Calculate total
const total = products.reduce((sum, p) => sum + parseFloat(p.price), 0);

// Format products for API
const productsJson = JSON.stringify(products.map(p => ({
  product_id: p.id,
  quantity: p.quantity
})));
```

---

## 📞 Support

**Questions?**
- Database: Check migration file `admin/run-migration-add-order-groups.php`
- API: See `api/portal/order-group.create.php`
- UI: See `portal/orders-list.php` and `portal/order-detail.php`

**Debugging:**
```sql
-- View grouped orders
SELECT og.id, COUNT(o.id) as product_count
FROM order_groups og
JOIN orders o ON o.order_group_id = og.id
GROUP BY og.id;

-- Find orphaned orders (group_id but no group exists)
SELECT o.id FROM orders o
LEFT JOIN order_groups og ON og.id = o.order_group_id
WHERE o.order_group_id IS NOT NULL AND og.id IS NULL;
```

---

**Last Updated:** November 17, 2024
**Version:** 1.0.0
**Status:** Production Ready (pending order form update)
