# Insurance-Based Billing Routing System
**Date:** November 4, 2025
**Status:** Implementation Plan

## Requirements Summary

Based on user feedback, the system should:

1. **Insurance-based routing** - Practice sets which insurers they bill direct vs route to CollagenDirect
2. **Skip admin review for direct bill** - Practice handles their own orders, no admin bottleneck
3. **Wholesale pricing for DME-licensed practices** - Show running balance
4. **Same documentation requirements** - Direct bill still needs full compliance docs
5. **Flexible shipping** - Direct bill can ship to practice OR patient

---

## Database Schema

### 1. Add Billing Routing Configuration Table

```sql
CREATE TABLE practice_billing_routes (
  id SERIAL PRIMARY KEY,
  user_id VARCHAR(32) NOT NULL,
  insurer_name VARCHAR(255) NOT NULL,
  billing_route VARCHAR(50) NOT NULL, -- 'collagen_direct' or 'practice_dme'
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW(),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE(user_id, insurer_name)
);

CREATE INDEX idx_billing_routes_user ON practice_billing_routes(user_id);
```

**Example data:**
```
user_id: abc123 (Dr. Smith's practice)
├─ Aetna → practice_dme
├─ BlueCross → practice_dme
├─ UHC → collagen_direct
├─ Medicare → collagen_direct
└─ Humana → collagen_direct
```

### 2. Add Column to Orders Table

```sql
ALTER TABLE orders
ADD COLUMN billed_by VARCHAR(50) DEFAULT 'collagen_direct';

-- Auto-calculated based on practice routing rules
-- But can be overridden manually if needed
```

### 3. Add Practice Account Balance Tracking

```sql
CREATE TABLE practice_account_transactions (
  id SERIAL PRIMARY KEY,
  user_id VARCHAR(32) NOT NULL,
  order_id VARCHAR(32),
  transaction_type VARCHAR(50) NOT NULL, -- 'wholesale_purchase', 'payment', 'credit', 'adjustment'
  amount DECIMAL(10,2) NOT NULL,
  balance_after DECIMAL(10,2) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT NOW(),
  created_by VARCHAR(32),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

CREATE INDEX idx_practice_transactions_user ON practice_account_transactions(user_id);
CREATE INDEX idx_practice_transactions_order ON practice_account_transactions(order_id);

-- Running balance view
CREATE VIEW practice_account_balances AS
SELECT
  user_id,
  SUM(amount) as current_balance,
  COUNT(*) as transaction_count,
  MAX(created_at) as last_transaction
FROM practice_account_transactions
GROUP BY user_id;
```

---

## Implementation Plan

### Phase 1: Practice Billing Settings UI

**Location:** Portal Settings Page

```html
<!-- /portal/index.php?page=settings -->

<section class="card p-5 mb-6">
  <h3 class="text-lg font-semibold mb-4">Billing Routing Configuration</h3>

  <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-4">
    <p class="text-sm text-blue-900">
      <strong>Configure which insurance companies you bill directly vs route to CollagenDirect.</strong><br>
      Direct bill orders use wholesale pricing and skip admin review. You handle billing and compliance.
    </p>
  </div>

  <!-- Top 15 Southern US Insurers + Other -->
  <div class="mb-6">
    <h4 class="font-semibold mb-3">Top Insurance Providers (Southern US)</h4>
    <p class="text-sm text-slate-600 mb-3">Configure routing for the most common insurance companies in the Southern United States.</p>
    <div class="grid md:grid-cols-2 gap-3">

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">UnitedHealthcare (UHC)</span>
        <select name="route_uhc" class="form-select" onchange="saveRoute('UnitedHealthcare', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">BlueCross BlueShield</span>
        <select name="route_bcbs" class="form-select" onchange="saveRoute('BlueCross BlueShield', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Aetna</span>
        <select name="route_aetna" class="form-select" onchange="saveRoute('Aetna', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Humana</span>
        <select name="route_humana" class="form-select" onchange="saveRoute('Humana', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Cigna</span>
        <select name="route_cigna" class="form-select" onchange="saveRoute('Cigna', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Medicare</span>
        <select name="route_medicare" class="form-select" onchange="saveRoute('Medicare', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Anthem</span>
        <select name="route_anthem" class="form-select" onchange="saveRoute('Anthem', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Centene / Ambetter</span>
        <select name="route_centene" class="form-select" onchange="saveRoute('Centene', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Medicaid</span>
        <select name="route_medicaid" class="form-select" onchange="saveRoute('Medicaid', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Florida Blue</span>
        <select name="route_florida_blue" class="form-select" onchange="saveRoute('Florida Blue', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Molina Healthcare</span>
        <select name="route_molina" class="form-select" onchange="saveRoute('Molina Healthcare', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">WellCare</span>
        <select name="route_wellcare" class="form-select" onchange="saveRoute('WellCare', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Oscar Health</span>
        <select name="route_oscar" class="form-select" onchange="saveRoute('Oscar Health', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">TriCare</span>
        <select name="route_tricare" class="form-select" onchange="saveRoute('TriCare', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Bright Health</span>
        <select name="route_bright" class="form-select" onchange="saveRoute('Bright Health', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

      <div class="flex items-center justify-between p-3 bg-slate-50 rounded">
        <span class="font-medium">Other / Unlisted</span>
        <select name="route_other" class="form-select" onchange="saveRoute('Other', this.value)">
          <option value="collagen_direct" selected>CollagenDirect</option>
          <option value="practice_dme">Direct Bill</option>
        </select>
      </div>

    </div>
  </div>

  <!-- Custom Insurer Addition -->
  <div class="border-t pt-4">
    <h4 class="font-semibold mb-3">Add Custom Insurance Provider</h4>
    <div class="flex gap-2">
      <input
        type="text"
        id="custom-insurer-name"
        placeholder="Insurance company name..."
        class="flex-1"
      >
      <select id="custom-insurer-route" class="form-select">
        <option value="collagen_direct">CollagenDirect Billing</option>
        <option value="practice_dme">Direct Bill (My DME)</option>
      </select>
      <button
        class="btn btn-primary"
        onclick="addCustomRoute()"
      >
        Add Route
      </button>
    </div>
  </div>

  <!-- Current Routes Table -->
  <div class="mt-6">
    <h4 class="font-semibold mb-3">All Configured Routes</h4>
    <div id="routes-table" class="overflow-x-auto">
      <!-- Dynamically loaded -->
    </div>
  </div>

  <!-- Default Fallback -->
  <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded p-4">
    <h4 class="font-semibold mb-2">Default Billing Route (Unlisted Insurers)</h4>
    <p class="text-sm text-slate-600 mb-3">
      For insurance companies not listed above, orders will be routed to:
    </p>
    <select name="default_route" class="form-select" onchange="saveDefaultRoute(this.value)">
      <option value="collagen_direct" selected>CollagenDirect Billing</option>
      <option value="practice_dme">Direct Bill (My DME)</option>
    </select>
  </div>

</section>

<script>
async function saveRoute(insurerName, route) {
  try {
    const response = await fetch('/api/portal/billing-routes.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'set_route',
        insurer_name: insurerName,
        billing_route: route
      })
    });

    const result = await response.json();
    if (result.ok) {
      showToast('Billing route updated successfully', 'success');
      loadRoutesTable();
    } else {
      showToast('Failed to update route: ' + result.error, 'error');
    }
  } catch (error) {
    showToast('Error: ' + error.message, 'error');
  }
}

async function loadRoutesTable() {
  // Fetch and display all configured routes
  const response = await fetch('/api/portal/billing-routes.php?action=get_routes');
  const result = await response.json();

  if (result.ok) {
    const table = document.getElementById('routes-table');
    table.innerHTML = `
      <table class="w-full text-sm">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Insurance Provider</th>
            <th class="py-2">Billing Route</th>
            <th class="py-2">Action</th>
          </tr>
        </thead>
        <tbody>
          ${result.routes.map(r => `
            <tr class="border-b">
              <td class="py-2">${esc(r.insurer_name)}</td>
              <td class="py-2">
                ${r.billing_route === 'practice_dme'
                  ? '<span class="pill" style="background: #dcfce7; color: #166534;">Direct Bill (My DME)</span>'
                  : '<span class="pill" style="background: #dbeafe; color: #1e40af;">CollagenDirect</span>'
                }
              </td>
              <td class="py-2">
                <button class="btn btn-sm btn-ghost" onclick="deleteRoute('${esc(r.insurer_name)}')">
                  Delete
                </button>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
  }
}

// Load routes on page load
if (document.getElementById('routes-table')) {
  loadRoutesTable();
}
</script>
```

### Phase 2: Automatic Billing Route Calculation

**Location:** Order creation logic

```php
// /api/portal/orders.create.php

/**
 * Determine billing route based on insurance company
 */
function determineBillingRoute($userId, $insurerName) {
  global $pdo;

  // Check if user has DME license
  $user = $pdo->prepare("SELECT has_dme_license, user_type, default_billing_route FROM users WHERE id = ?");
  $user->execute([$userId]);
  $userData = $user->fetch(PDO::FETCH_ASSOC);

  // If no DME license, always route to CollagenDirect
  if (!$userData['has_dme_license']) {
    return 'collagen_direct';
  }

  // If DME wholesale (never uses CollagenDirect billing), always practice_dme
  if ($userData['user_type'] === 'dme_wholesale') {
    return 'practice_dme';
  }

  // For hybrid practices, check routing configuration
  if ($userData['user_type'] === 'dme_hybrid') {
    // Look up specific insurer route
    $route = $pdo->prepare("
      SELECT billing_route
      FROM practice_billing_routes
      WHERE user_id = ? AND insurer_name = ?
    ");
    $route->execute([$userId, $insurerName]);
    $routeData = $route->fetch(PDO::FETCH_ASSOC);

    if ($routeData) {
      return $routeData['billing_route'];
    }

    // No specific route found, use default
    return $userData['default_billing_route'] ?? 'collagen_direct';
  }

  // Default: CollagenDirect
  return 'collagen_direct';
}

// In order creation
$insurerName = $_POST['insurance_company'] ?? '';
$billedBy = determineBillingRoute($userId, $insurerName);

// Add to INSERT statement
$stmt = $pdo->prepare("
  INSERT INTO orders (
    id, patient_id, user_id, product, product_id, product_price,
    status, billed_by, ...
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ...)
");
$stmt->execute([
  $orderId, $patientId, $userId, $product, $productId, $price,
  'pending', $billedBy, ...
]);

// Record wholesale transaction if practice_dme
if ($billedBy === 'practice_dme') {
  recordWholesalePurchase($userId, $orderId, $price);
}
```

### Phase 3: Order Review Workflow Branching

```php
// /api/portal/orders.create.php (continued)

// Set initial review status based on billing route
if ($billedBy === 'practice_dme') {
  // Direct bill - skip admin review, mark as approved immediately
  $reviewStatus = 'approved';
  $reviewedAt = date('Y-m-d H:i:s');
  $reviewedBy = 'system'; // Auto-approved for direct bill
  $adminReviewRequired = false;
} else {
  // CollagenDirect billing - requires admin review
  $reviewStatus = 'pending_admin_review';
  $reviewedAt = null;
  $reviewedBy = null;
  $adminReviewRequired = true;
}

$stmt = $pdo->prepare("
  INSERT INTO orders (
    id, patient_id, user_id, billed_by, review_status, reviewed_at, reviewed_by, ...
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ...)
");
$stmt->execute([
  $orderId, $patientId, $userId, $billedBy, $reviewStatus, $reviewedAt, $reviewedBy, ...
]);
```

### Phase 4: Wholesale Account Balance Display

**Location:** Dashboard widget

```php
<!-- /portal/index.php (dashboard) -->

<?php if ($user['has_dme_license']): ?>
<section class="card p-5 mb-6">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-semibold">Wholesale Account Balance</h3>
    <button class="btn btn-sm btn-ghost" onclick="window.location='?page=account-statement'">
      View Statement
    </button>
  </div>

  <?php
  $balance = $pdo->prepare("
    SELECT current_balance, transaction_count, last_transaction
    FROM practice_account_balances
    WHERE user_id = ?
  ");
  $balance->execute([$userId]);
  $accountData = $balance->fetch(PDO::FETCH_ASSOC);

  $currentBalance = $accountData['current_balance'] ?? 0;
  $balanceColor = $currentBalance >= 0 ? 'text-green-600' : 'text-red-600';
  ?>

  <div class="grid md:grid-cols-3 gap-4">
    <div class="p-4 bg-slate-50 rounded">
      <div class="text-sm text-slate-600 mb-1">Current Balance</div>
      <div class="text-2xl font-semibold <?php echo $balanceColor; ?>">
        $<?php echo number_format(abs($currentBalance), 2); ?>
        <?php if ($currentBalance < 0): ?>
          <span class="text-sm">(owed)</span>
        <?php else: ?>
          <span class="text-sm">(credit)</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="p-4 bg-slate-50 rounded">
      <div class="text-sm text-slate-600 mb-1">Total Transactions</div>
      <div class="text-2xl font-semibold text-slate-900">
        <?php echo $accountData['transaction_count'] ?? 0; ?>
      </div>
    </div>

    <div class="p-4 bg-slate-50 rounded">
      <div class="text-sm text-slate-600 mb-1">Last Activity</div>
      <div class="text-sm text-slate-900">
        <?php echo $accountData['last_transaction']
          ? date('M j, Y', strtotime($accountData['last_transaction']))
          : 'No activity'; ?>
      </div>
    </div>
  </div>

  <?php if ($currentBalance < -1000): ?>
  <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded">
    <p class="text-sm text-red-900">
      ⚠️ Your account balance is significantly negative. Please contact billing to arrange payment.
    </p>
  </div>
  <?php endif; ?>

</section>
<?php endif; ?>
```

### Phase 5: Order Display with Billing Indicator

```javascript
// /portal/order-workflow.js - Update viewOrderDetailsEnhanced

// Add billing indicator to order display
let billingIndicator = '';
if (order.billed_by) {
  if (order.billed_by === 'practice_dme') {
    billingIndicator = `
      <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
        <div class="flex items-center gap-2">
          <span class="text-lg">🏥</span>
          <div>
            <div class="font-semibold text-sm text-green-900">Direct Bill (Your DME)</div>
            <div class="text-xs text-green-700">
              You are billing insurance/patient directly.
              Auto-approved, no admin review required.
            </div>
          </div>
        </div>
      </div>
    `;
  } else {
    billingIndicator = `
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
        <div class="flex items-center gap-2">
          <span class="text-lg">💼</span>
          <div>
            <div class="font-semibold text-sm text-blue-900">CollagenDirect Billing (MD-DME)</div>
            <div class="text-xs text-blue-700">
              CollagenDirect will process and bill for this order.
              Subject to admin review.
            </div>
          </div>
        </div>
      </div>
    `;
  }
}

content.innerHTML = `
  <div class="grid gap-6">
    <!-- Billing Indicator -->
    ${billingIndicator}

    <!-- Rest of order details -->
    ...
  </div>
`;
```

### Phase 6: AI Pre-auth for Direct Bill

Direct bill orders can still use AI suggestions for their own pre-auth process:

```php
// In AI suggestions generation
if ($order['billed_by'] === 'practice_dme') {
  // Still generate AI suggestions for practice's own review
  // But don't require admin approval
  $suggestions = generateAISuggestions($order);

  // Mark as "advisory only" for direct bill
  $suggestions['advisory_only'] = true;
  $suggestions['message'] = 'These suggestions are for your internal review. Your order is auto-approved.';
}
```

---

## API Endpoints

### 1. Billing Routes Management

**File:** `/api/portal/billing-routes.php`

```php
<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../db.php';

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Check if user has DME license
$user = $pdo->prepare("SELECT has_dme_license, user_type FROM users WHERE id = ?");
$user->execute([$userId]);
$userData = $user->fetch(PDO::FETCH_ASSOC);

if (!$userData['has_dme_license']) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'DME license required for billing routes']);
  exit;
}

try {
  switch ($action) {
    case 'get_routes':
      $stmt = $pdo->prepare("
        SELECT insurer_name, billing_route, updated_at
        FROM practice_billing_routes
        WHERE user_id = ?
        ORDER BY insurer_name
      ");
      $stmt->execute([$userId]);
      $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo json_encode(['ok' => true, 'routes' => $routes]);
      break;

    case 'set_route':
      $data = json_decode(file_get_contents('php://input'), true);
      $insurerName = trim($data['insurer_name'] ?? '');
      $billingRoute = $data['billing_route'] ?? '';

      if (empty($insurerName) || !in_array($billingRoute, ['collagen_direct', 'practice_dme'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
        exit;
      }

      // Upsert route
      $stmt = $pdo->prepare("
        INSERT INTO practice_billing_routes (user_id, insurer_name, billing_route, updated_at)
        VALUES (?, ?, ?, NOW())
        ON CONFLICT (user_id, insurer_name)
        DO UPDATE SET billing_route = EXCLUDED.billing_route, updated_at = NOW()
      ");
      $stmt->execute([$userId, $insurerName, $billingRoute]);

      echo json_encode(['ok' => true, 'message' => 'Route updated successfully']);
      break;

    case 'delete_route':
      $insurerName = $_POST['insurer_name'] ?? '';

      $stmt = $pdo->prepare("
        DELETE FROM practice_billing_routes
        WHERE user_id = ? AND insurer_name = ?
      ");
      $stmt->execute([$userId, $insurerName]);

      echo json_encode(['ok' => true, 'message' => 'Route deleted successfully']);
      break;

    case 'set_default':
      $defaultRoute = $_POST['default_route'] ?? '';

      if (!in_array($defaultRoute, ['collagen_direct', 'practice_dme'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid route']);
        exit;
      }

      $stmt = $pdo->prepare("
        UPDATE users
        SET default_billing_route = ?
        WHERE id = ?
      ");
      $stmt->execute([$defaultRoute, $userId]);

      echo json_encode(['ok' => true, 'message' => 'Default route updated']);
      break;

    default:
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Invalid action']);
  }

} catch (Exception $e) {
  error_log("Billing routes error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
```

### 2. Wholesale Transactions

**File:** `/api/portal/account-transactions.php`

```php
<?php
// Record wholesale purchase
function recordWholesalePurchase($userId, $orderId, $amount) {
  global $pdo;

  // Get current balance
  $balanceQuery = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as balance
    FROM practice_account_transactions
    WHERE user_id = ?
  ");
  $balanceQuery->execute([$userId]);
  $currentBalance = $balanceQuery->fetch(PDO::FETCH_ASSOC)['balance'];

  // Deduct purchase (negative amount)
  $newBalance = $currentBalance - $amount;

  // Record transaction
  $stmt = $pdo->prepare("
    INSERT INTO practice_account_transactions
      (user_id, order_id, transaction_type, amount, balance_after, description, created_by)
    VALUES (?, ?, 'wholesale_purchase', ?, ?, ?, 'system')
  ");
  $stmt->execute([
    $userId,
    $orderId,
    -$amount,
    $newBalance,
    "Wholesale purchase for order $orderId"
  ]);

  return $newBalance;
}

// Record payment
function recordPayment($userId, $amount, $description = 'Payment received') {
  global $pdo;

  // Similar logic but positive amount
  ...
}
```

---

## Migration Script

**File:** `/admin/migrate-billing-routing.php`

```php
<?php
require_once __DIR__ . '/../api/db.php';

echo "Migrating billing routing system...\n\n";

try {
  $pdo->beginTransaction();

  // 1. Add billed_by column to orders
  echo "1. Adding billed_by column to orders table...\n";
  $pdo->exec("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS billed_by VARCHAR(50) DEFAULT 'collagen_direct'
  ");
  echo "   ✓ Column added\n\n";

  // 2. Create billing routes table
  echo "2. Creating practice_billing_routes table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS practice_billing_routes (
      id SERIAL PRIMARY KEY,
      user_id VARCHAR(32) NOT NULL,
      insurer_name VARCHAR(255) NOT NULL,
      billing_route VARCHAR(50) NOT NULL,
      created_at TIMESTAMP DEFAULT NOW(),
      updated_at TIMESTAMP DEFAULT NOW(),
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      UNIQUE(user_id, insurer_name)
    )
  ");
  $pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_billing_routes_user
    ON practice_billing_routes(user_id)
  ");
  echo "   ✓ Table created\n\n";

  // 3. Create transactions table
  echo "3. Creating practice_account_transactions table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS practice_account_transactions (
      id SERIAL PRIMARY KEY,
      user_id VARCHAR(32) NOT NULL,
      order_id VARCHAR(32),
      transaction_type VARCHAR(50) NOT NULL,
      amount DECIMAL(10,2) NOT NULL,
      balance_after DECIMAL(10,2) NOT NULL,
      description TEXT,
      created_at TIMESTAMP DEFAULT NOW(),
      created_by VARCHAR(32),
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
    )
  ");
  $pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_practice_transactions_user
    ON practice_account_transactions(user_id)
  ");
  $pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_practice_transactions_order
    ON practice_account_transactions(order_id)
  ");
  echo "   ✓ Table created\n\n";

  // 4. Add default_billing_route to users
  echo "4. Adding default_billing_route column to users...\n";
  $pdo->exec("
    ALTER TABLE users
    ADD COLUMN IF NOT EXISTS default_billing_route VARCHAR(50) DEFAULT 'collagen_direct'
  ");
  echo "   ✓ Column added\n\n";

  // 5. Backfill existing orders
  echo "5. Backfilling existing orders...\n";

  // Wholesale practices → practice_dme
  $pdo->exec("
    UPDATE orders o
    SET billed_by = 'practice_dme'
    FROM users u
    WHERE o.user_id = u.id
      AND u.user_type = 'dme_wholesale'
      AND o.billed_by IS NULL
  ");

  // Everyone else → collagen_direct
  $pdo->exec("
    UPDATE orders
    SET billed_by = 'collagen_direct'
    WHERE billed_by IS NULL
  ");

  echo "   ✓ Existing orders backfilled\n\n";

  $pdo->commit();
  echo "✅ Migration completed successfully!\n";

} catch (Exception $e) {
  $pdo->rollBack();
  echo "❌ Migration failed: " . $e->getMessage() . "\n";
  exit(1);
}
```

---

## How Doctors/DME Companies Capture Billing Data

This is a critical question - practices billing directly need comprehensive billing data to submit claims.

### Solution: Dedicated Export for Direct Bill Orders

#### 1. Direct Bill Orders Dashboard Page

**Location:** `/portal/index.php?page=direct-bill-orders`

```html
<section class="card p-5">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">Direct Bill Orders</h2>
    <div class="flex gap-2">
      <input
        type="month"
        id="billing-month"
        value="<?php echo date('Y-m'); ?>"
        class="form-control"
        onchange="loadDirectBillOrders()"
      >
      <button class="btn btn-primary" onclick="exportDirectBillOrders()">
        Export for Billing
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="flex gap-2 mb-4">
    <select id="filter-status" class="form-select" onchange="loadDirectBillOrders()">
      <option value="">All Statuses</option>
      <option value="pending">Pending</option>
      <option value="shipped">Shipped</option>
      <option value="delivered">Delivered</option>
    </select>
    <select id="filter-insurer" class="form-select" onchange="loadDirectBillOrders()">
      <option value="">All Insurers</option>
      <!-- Populated dynamically -->
    </select>
  </div>

  <!-- Orders Table -->
  <div class="overflow-x-auto">
    <table class="w-full text-sm" id="direct-bill-orders-table">
      <thead class="border-b">
        <tr class="text-left">
          <th class="py-2">Order Date</th>
          <th class="py-2">Patient</th>
          <th class="py-2">DOB</th>
          <th class="py-2">Product</th>
          <th class="py-2">HCPCS</th>
          <th class="py-2">Insurance</th>
          <th class="py-2">Member ID</th>
          <th class="py-2">Wholesale Cost</th>
          <th class="py-2">Status</th>
          <th class="py-2">Action</th>
        </tr>
      </thead>
      <tbody id="direct-bill-orders-body">
        <!-- Loaded via JavaScript -->
      </tbody>
    </table>
  </div>

  <!-- Summary -->
  <div class="mt-4 p-4 bg-slate-50 rounded">
    <div class="grid md:grid-cols-4 gap-4">
      <div>
        <div class="text-sm text-slate-600">Total Orders</div>
        <div class="text-2xl font-semibold" id="summary-count">0</div>
      </div>
      <div>
        <div class="text-sm text-slate-600">Total Wholesale Cost</div>
        <div class="text-2xl font-semibold text-red-600" id="summary-cost">$0.00</div>
      </div>
      <div>
        <div class="text-sm text-slate-600">Estimated Billable</div>
        <div class="text-2xl font-semibold text-green-600" id="summary-billable">$0.00</div>
      </div>
      <div>
        <div class="text-sm text-slate-600">Potential Margin</div>
        <div class="text-2xl font-semibold text-blue-600" id="summary-margin">$0.00</div>
      </div>
    </div>
  </div>
</section>
```

#### 2. Export CSV Format (for Billing Software)

**Comprehensive billing export with ALL data needed for claim submission**

```php
// /api/portal/export-direct-bill-orders.php

<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="direct_bill_orders_' . date('Y-m') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$userId = $_SESSION['user_id'];
$month = $_GET['month'] ?? date('Y-m');
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// Get all direct bill orders for this practice
$stmt = $pdo->prepare("
  SELECT
    -- Order Info
    o.id as order_id,
    o.created_at as order_date,
    o.status as order_status,
    o.product,
    o.product_price as wholesale_cost,
    o.frequency,
    o.shipments_remaining,

    -- Patient Demographics
    p.first_name as patient_first,
    p.last_name as patient_last,
    p.dob as patient_dob,
    p.sex as patient_gender,
    p.mrn as patient_mrn,
    p.ssn as patient_ssn,
    p.phone as patient_phone,
    p.email as patient_email,

    -- Patient Address
    p.address as patient_address,
    p.city as patient_city,
    p.state as patient_state,
    p.zip as patient_zip,

    -- Insurance Information
    p.insurance_company as insurer_name,
    p.insurance_id as member_id,
    p.group_number as group_id,
    p.insurance_phone as payer_phone,
    o.prior_auth as prior_auth_number,

    -- Clinical Information
    o.wound_location,
    o.wound_laterality,
    o.wound_notes,
    o.wounds_data,
    o.icd10_primary,
    o.icd10_secondary,

    -- Product Details
    pr.sku as product_sku,
    pr.hcpcs_code,
    pr.description as product_description,
    pr.size,
    pr.quantity_per_box,

    -- Physician/Provider Info
    u.first_name as provider_first,
    u.last_name as provider_last,
    u.npi as provider_npi,
    u.credential_type as provider_credential,
    u.specialty as provider_specialty,
    u.practice_name,
    u.practice_address,
    u.practice_city,
    u.practice_state,
    u.practice_zip,
    u.practice_phone,
    u.practice_fax,
    u.tax_id as practice_tax_id,

    -- Shipping Info (if shipped)
    o.shipping_address,
    o.shipping_city,
    o.shipping_state,
    o.shipping_zip,
    o.tracking_number,
    o.carrier,
    o.shipped_at,
    o.delivered_at,

    -- Documentation Paths
    p.id_card_path,
    p.ins_card_path,
    o.rx_note_path,
    o.clinical_photo_path,

    -- Signatures
    o.e_sign_name,
    o.e_sign_title,
    o.e_sign_at

  FROM orders o
  JOIN patients p ON p.id = o.patient_id
  LEFT JOIN products pr ON pr.id = o.product_id
  JOIN users u ON u.id = o.user_id
  WHERE o.user_id = ?
    AND o.billed_by = 'practice_dme'
    AND o.created_at >= ?
    AND o.created_at <= ?
  ORDER BY o.created_at DESC
");
$stmt->execute([$userId, $startDate, $endDate . ' 23:59:59']);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output CSV
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Comprehensive CSV Header for Billing
fputcsv($output, [
  // Order Identification
  'Order ID',
  'Order Date',
  'Order Status',

  // Service/Product Information
  'Product Name',
  'Product SKU',
  'HCPCS Code',
  'Product Description',
  'Size',
  'Quantity',
  'Wholesale Cost',
  'Frequency',
  'Shipments Remaining',

  // Patient Demographics (for claim)
  'Patient Last Name',
  'Patient First Name',
  'Patient DOB',
  'Patient Gender',
  'Patient MRN',
  'Patient SSN (Last 4)',
  'Patient Phone',
  'Patient Email',

  // Patient Address
  'Patient Address',
  'Patient City',
  'Patient State',
  'Patient ZIP',

  // Insurance/Payer Information
  'Insurance Company',
  'Member ID',
  'Group ID',
  'Payer Phone',
  'Prior Authorization Number',

  // Clinical/Diagnosis
  'ICD-10 Primary',
  'ICD-10 Secondary',
  'Wound Location',
  'Wound Laterality',
  'Wound Notes',

  // Provider Information (for claim)
  'Provider Last Name',
  'Provider First Name',
  'Provider NPI',
  'Provider Credential',
  'Provider Specialty',

  // Practice Information (for claim)
  'Practice Name',
  'Practice Address',
  'Practice City',
  'Practice State',
  'Practice ZIP',
  'Practice Phone',
  'Practice Fax',
  'Practice Tax ID',

  // Shipping/Delivery (for delivery confirmation)
  'Shipping Address',
  'Shipping City',
  'Shipping State',
  'Shipping ZIP',
  'Tracking Number',
  'Carrier',
  'Shipped Date',
  'Delivered Date',

  // Documentation (file paths for reference)
  'Patient ID Card Path',
  'Insurance Card Path',
  'Rx Note Path',
  'Clinical Photo Path',

  // Signature/Authorization
  'E-Signature Name',
  'E-Signature Title',
  'E-Signature Date'
]);

// CSV Rows
foreach ($orders as $o) {
  // Parse wounds data if JSON
  $woundsData = json_decode($o['wounds_data'] ?? '[]', true);
  $primaryWound = $woundsData[0] ?? [];

  // Mask SSN to last 4 digits for security
  $ssnLast4 = !empty($o['patient_ssn']) ? substr($o['patient_ssn'], -4) : '';

  fputcsv($output, [
    // Order Identification
    $o['order_id'],
    date('Y-m-d', strtotime($o['order_date'])),
    $o['order_status'],

    // Service/Product
    $o['product'],
    $o['product_sku'],
    $o['hcpcs_code'],
    $o['product_description'],
    $o['size'],
    $o['quantity_per_box'],
    number_format($o['wholesale_cost'], 2),
    $o['frequency'],
    $o['shipments_remaining'],

    // Patient Demographics
    $o['patient_last'],
    $o['patient_first'],
    $o['patient_dob'],
    $o['patient_gender'],
    $o['patient_mrn'],
    $ssnLast4,
    $o['patient_phone'],
    $o['patient_email'],

    // Patient Address
    $o['patient_address'],
    $o['patient_city'],
    $o['patient_state'],
    $o['patient_zip'],

    // Insurance
    $o['insurer_name'],
    $o['member_id'],
    $o['group_id'],
    $o['payer_phone'],
    $o['prior_auth_number'],

    // Clinical
    $o['icd10_primary'] ?? ($primaryWound['icd10'] ?? ''),
    $o['icd10_secondary'],
    $o['wound_location'] ?? ($primaryWound['location'] ?? ''),
    $o['wound_laterality'] ?? ($primaryWound['laterality'] ?? ''),
    $o['wound_notes'],

    // Provider
    $o['provider_last'],
    $o['provider_first'],
    $o['provider_npi'],
    $o['provider_credential'],
    $o['provider_specialty'],

    // Practice
    $o['practice_name'],
    $o['practice_address'],
    $o['practice_city'],
    $o['practice_state'],
    $o['practice_zip'],
    $o['practice_phone'],
    $o['practice_fax'],
    $o['practice_tax_id'],

    // Shipping
    $o['shipping_address'],
    $o['shipping_city'],
    $o['shipping_state'],
    $o['shipping_zip'],
    $o['tracking_number'],
    $o['carrier'],
    $o['shipped_at'] ? date('Y-m-d', strtotime($o['shipped_at'])) : '',
    $o['delivered_at'] ? date('Y-m-d', strtotime($o['delivered_at'])) : '',

    // Documentation
    $o['id_card_path'],
    $o['ins_card_path'],
    $o['rx_note_path'],
    $o['clinical_photo_path'],

    // Signature
    $o['e_sign_name'],
    $o['e_sign_title'],
    $o['e_sign_at'] ? date('Y-m-d H:i:s', strtotime($o['e_sign_at'])) : ''
  ]);
}

fclose($output);
exit;
```

#### 3. Individual Order Detail View (for Manual Billing)

When a practice clicks on a specific direct bill order:

```html
<!-- Order Detail Modal -->
<dialog id="dlg-direct-bill-detail" class="dialog">
  <div class="dialog-header">
    <h3>Direct Bill Order Details</h3>
    <button onclick="this.closest('dialog').close()">×</button>
  </div>
  <div class="dialog-body">

    <!-- Ready-to-Bill Card -->
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
      <h4 class="font-semibold text-green-900 mb-2">✓ Ready to Bill</h4>
      <p class="text-sm text-green-800">
        All required information is complete. You can submit this claim to the insurance company.
      </p>
    </div>

    <!-- Claim Summary (HCFA 1500 Ready) -->
    <div class="grid md:grid-cols-2 gap-4 mb-4">

      <!-- Patient Info (Box 2-7) -->
      <div class="card p-4">
        <h5 class="font-semibold mb-2">Patient Information</h5>
        <div class="space-y-1 text-sm">
          <div><strong>Name:</strong> Smith, John A</div>
          <div><strong>DOB:</strong> 03/15/1965</div>
          <div><strong>Gender:</strong> Male</div>
          <div><strong>Address:</strong> 123 Main St, Atlanta, GA 30301</div>
          <div><strong>Phone:</strong> (404) 555-1234</div>
        </div>
      </div>

      <!-- Insurance Info (Box 1, 11) -->
      <div class="card p-4">
        <h5 class="font-semibold mb-2">Insurance Information</h5>
        <div class="space-y-1 text-sm">
          <div><strong>Payer:</strong> Aetna</div>
          <div><strong>Member ID:</strong> W123456789</div>
          <div><strong>Group:</strong> 00123</div>
          <div><strong>Payer Phone:</strong> (800) 123-4567</div>
          <div><strong>Prior Auth:</strong> PA2024001234</div>
        </div>
      </div>

      <!-- Service Info (Box 24) -->
      <div class="card p-4">
        <h5 class="font-semibold mb-2">Service Details</h5>
        <div class="space-y-1 text-sm">
          <div><strong>Date of Service:</strong> 11/04/2025</div>
          <div><strong>HCPCS Code:</strong> A6010</div>
          <div><strong>Product:</strong> CollagenMatrix Classic 4x4</div>
          <div><strong>Quantity:</strong> 10</div>
          <div><strong>Diagnosis (ICD-10):</strong> L97.421</div>
        </div>
      </div>

      <!-- Provider Info (Box 33) -->
      <div class="card p-4">
        <h5 class="font-semibold mb-2">Rendering Provider</h5>
        <div class="space-y-1 text-sm">
          <div><strong>Name:</strong> Dr. Jane Doe, MD</div>
          <div><strong>NPI:</strong> 1234567890</div>
          <div><strong>Tax ID:</strong> 12-3456789</div>
          <div><strong>Practice:</strong> Atlanta Wound Care</div>
          <div><strong>Address:</strong> 456 Medical Dr, Atlanta, GA 30302</div>
        </div>
      </div>

    </div>

    <!-- Quick Actions -->
    <div class="flex gap-2">
      <button class="btn btn-primary" onclick="copyBillingData()">
        Copy Billing Data
      </button>
      <button class="btn btn-secondary" onclick="printBillingSheet()">
        Print Billing Sheet
      </button>
      <button class="btn btn-ghost" onclick="downloadDocuments()">
        Download All Documents
      </button>
    </div>

    <!-- Documents Checklist -->
    <div class="mt-4 p-4 bg-slate-50 rounded">
      <h5 class="font-semibold mb-2">Supporting Documentation</h5>
      <div class="space-y-2 text-sm">
        <div class="flex items-center gap-2">
          <span class="text-green-600">✓</span>
          <a href="/uploads/id-cards/xyz.pdf" target="_blank" class="text-blue-600 hover:underline">
            Patient ID Card
          </a>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-green-600">✓</span>
          <a href="/uploads/insurance-cards/abc.pdf" target="_blank" class="text-blue-600 hover:underline">
            Insurance Card
          </a>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-green-600">✓</span>
          <a href="/uploads/clinical-notes/123.pdf" target="_blank" class="text-blue-600 hover:underline">
            Clinical Note
          </a>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-green-600">✓</span>
          <a href="/uploads/wound-photos/456.jpg" target="_blank" class="text-blue-600 hover:underline">
            Wound Photo
          </a>
        </div>
      </div>
    </div>

  </div>
</dialog>
```

#### 4. Integration with Practice Management Systems

Many practices use billing software. We can provide:

**Option A: API Integration**
```javascript
// Webhook when direct bill order is created/updated
POST https://practice-billing-system.com/api/claims/import
{
  "order_id": "abc123",
  "patient": {
    "first_name": "John",
    "last_name": "Smith",
    "dob": "1965-03-15",
    "gender": "M",
    "address": "123 Main St",
    "city": "Atlanta",
    "state": "GA",
    "zip": "30301"
  },
  "insurance": {
    "payer_name": "Aetna",
    "member_id": "W123456789",
    "group_id": "00123"
  },
  "service": {
    "date_of_service": "2025-11-04",
    "hcpcs_code": "A6010",
    "diagnosis_codes": ["L97.421"],
    "quantity": 10
  },
  "provider": {
    "npi": "1234567890",
    "name": "Jane Doe",
    "credential": "MD"
  }
}
```

**Option B: HL7 EDI 837 Format**
For practices with advanced billing software, export in standard EDI format

**Option C: Simple Excel/CSV Import**
Most billing software accepts CSV imports - our export format matches common templates

---

## Summary: How Practices Capture Billing Data

### Quick Answer:
**Practices have 3 ways to get billing data:**

1. **CSV Export** - Monthly export with ALL billing data (50+ fields)
   - Patient demographics
   - Insurance details
   - Product/HCPCS codes
   - Provider information
   - Clinical documentation paths
   - Can import directly into most billing software

2. **Individual Order View** - Detailed "ready to bill" card
   - Copy/paste functionality
   - Print billing sheet
   - Download all supporting documents
   - Perfect for manual HCFA 1500 completion

3. **API/Integration** (Future)
   - Real-time sync with practice management system
   - Automatic claim creation
   - Webhook notifications

### What Data Do They Get?

**Everything needed for a complete insurance claim:**
- Patient demographics (name, DOB, address, phone)
- Insurance information (payer, member ID, group, prior auth)
- Service details (date, HCPCS code, diagnosis codes, quantity)
- Provider information (NPI, Tax ID, practice address)
- Shipping/delivery confirmation
- All supporting documentation (ID cards, insurance cards, clinical notes, photos)

### Workflow Example:

1. Dr. Smith creates order for patient with Aetna insurance
2. System routes to "Direct Bill" (based on practice settings)
3. Order auto-approved, products shipped
4. End of month: Dr. Smith exports CSV with all November direct bill orders
5. Import CSV into practice billing software (or manually enter)
6. Submit claims to insurance companies
7. Track payments in practice accounting system
8. Portal shows wholesale cost balance owed to CollagenDirect

---

## Next Steps

1. **Review this implementation plan**
2. **Confirm approach aligns with requirements**
3. **Run migration script**
4. **Implement Phase 1 (Settings UI with 15 Southern insurers)**
5. **Implement Phase 2 (Auto-routing)**
6. **Implement Phase 3 (Direct Bill Export)**
7. **Test with hybrid practice workflow**

---

**Estimated Implementation Time:** 6-8 hours (with export functionality)
**Ready to start when approved!**
