# Order Grouping Fix Specification

## Problem Statement
Multi-product orders (order groups) are currently displayed as separate line items in the revenue report, making it confusing to understand which products belong together and what the shared order details are.

## Current Structure (Database)
✅ Database structure is CORRECT:
- `order_groups` table - contains shared wound/patient information
- `orders` table - has `order_group_id` field (VARCHAR, nullable)
- Each product in a multi-product order gets its own order record
- All orders in the group share the same `order_group_id`

## Required Changes to Revenue Report

### 1. Query Modification
**File**: `admin/revenue-report.php` line ~78

**Current**:
```php
SELECT o.id, o.created_at, o.patient_id, ..., o.billed_by
FROM orders o
...
ORDER BY o.created_at DESC
```

**Should be**:
```php
SELECT
  o.id,
  o.created_at,
  o.patient_id,
  o.order_group_id,  -- ADD THIS
  ...,
  o.billed_by
FROM orders o
...
ORDER BY
  COALESCE(o.order_group_id, o.id) DESC,  -- Group orders together
  o.created_at DESC,
  o.product_id ASC  -- Consistent product ordering within group
```

### 2. Grouping Logic
**File**: `admin/revenue-report.php` after line ~111

**Add**:
```php
/* Group orders by order_group_id */
$grouped_orders = [];
foreach ($orders as $order) {
  $key = $order['order_group_id'] ?? $order['id'];  // Use group ID or fall back to order ID

  if (!isset($grouped_orders[$key])) {
    $grouped_orders[$key] = [
      'is_group' => !empty($order['order_group_id']),
      'group_id' => $order['order_group_id'],
      'orders' => []
    ];
  }

  $grouped_orders[$key]['orders'][] = $order;
}
```

### 3. Revenue Calculation Per Group
**File**: `admin/revenue-report.php` line ~124

**Change loop from**:
```php
foreach ($orders as $order) {
  // Calculate for single order
  $reportData[] = [single order data];
}
```

**To**:
```php
foreach ($grouped_orders as $group) {
  $is_multi_product = $group['is_group'];
  $orders_in_group = $group['orders'];

  // Use first order for shared data
  $first_order = $orders_in_group[0];

  // Shared data (same for all products)
  $patient_name = trim(($first_order['patient_first'] ?? '') . ' ' . ($first_order['patient_last'] ?? ''));
  $physician_name = trim(($first_order['phys_first'] ?? '') . ' ' . ($first_order['phys_last'] ?? ''));
  $date = $first_order['created_at'];
  $isWholesale = ($first_order['billed_by'] ?? 'collagen_direct') === 'practice_dme';

  // Calculate for each product
  $products_detail = [];
  $group_total_boxes = 0;
  $group_total_revenue = 0;
  $group_total_cost = 0;

  foreach ($orders_in_group as $order) {
    // [Existing calculation logic for single order]
    // Store per-product details
    $products_detail[] = [
      'product_name' => $order['product_name'],
      'boxes' => $totalBoxes,
      'cost' => $order_cost,
      'revenue' => $revenue
    ];

    $group_total_boxes += $totalBoxes;
    $group_total_revenue += $revenue;
    $group_total_cost += $order_cost;
  }

  // Add ONE row per group
  $reportData[] = [
    'order_id' => $group['group_id'] ?? $first_order['id'],
    'is_multi_product' => $is_multi_product,
    'date' => $date,
    'patient_name' => $patient_name,
    'physician_name' => $physician_name,
    'practice_name' => $first_order['practice_name'] ?? '',
    'products' => $products_detail,  // Array of products
    'product_name' => $is_multi_product
      ? count($orders_in_group) . ' products'
      : $first_order['product_name'],
    'order_type' => $isWholesale ? 'Wholesale' : 'Referral',
    'boxes' => $group_total_boxes,
    'cost_per_box' => null,  // N/A for groups
    'total_cost' => $group_total_cost,
    'revenue' => $group_total_revenue,
    'profit' => $group_total_revenue - $group_total_cost,
    'calculation_steps' => [],  // Would need to aggregate
    'status' => $first_order['status']
  ];
}
```

### 4. Display Updates
**File**: `admin/revenue-report.php` line ~390+

**For HTML display**, update the table row to show:
```php
<tr>
  <td><?=$row['order_id']?></td>
  <td><?=e(substr($row['date'], 0, 10))?></td>
  <td><?=e($row['patient_name'])?></td>
  <td>
    <?=e($row['physician_name'])?><br>
    <small class="text-slate-500"><?=e($row['practice_name'])?></small>
  </td>
  <td>
    <?php if ($row['is_multi_product']): ?>
      <details>
        <summary><?=$row['product_name']?></summary>
        <ul class="mt-1 text-xs">
          <?php foreach ($row['products'] as $p): ?>
            <li><?=e($p['product_name'])?> - <?=$p['boxes']?> boxes</li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php else: ?>
      <?=e($row['product_name'])?>
    <?php endif; ?>
  </td>
  ...
</tr>
```

### 5. CSV Export Update
**File**: `admin/revenue-report.php` line ~227+

**For multi-product groups**, either:
- Option A: Export each product as a separate row with a group ID column
- Option B: Combine products in one row with semicolon-separated list

## Testing Checklist
- [ ] Single product orders still display correctly
- [ ] Multi-product orders show as one row with all products listed
- [ ] Revenue totals match (group total = sum of individual products)
- [ ] Box counts are correct for each product
- [ ] CSV export works for both single and grouped orders
- [ ] Calculation steps show correctly
- [ ] Dashboard and billing pages also need same grouping logic

## Estimated Effort
**Medium** - 2-3 hours of careful implementation and testing

## Priority
**High** - This is causing confusion in revenue reporting and making it hard to understand actual order volumes
