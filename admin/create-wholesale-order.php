<?php
/**
 * Admin: Create Wholesale Order on Behalf of Practice
 * Supports multiple patients with multiple products + office stock orders
 */

require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/db.php';

// Get list of all practices/physicians for selection
$practicesStmt = $pdo->query("
  SELECT id, practice_name, first_name, last_name, user_type, email
  FROM users
  WHERE user_type IN ('practice_admin', 'physician', 'dme_wholesale')
    AND (deleted_at IS NULL OR deleted_at > NOW())
  ORDER BY practice_name ASC, last_name ASC
");
$practices = $practicesStmt->fetchAll(PDO::FETCH_ASSOC);

// Selected practice for order creation
$selectedPracticeId = $_GET['practice_id'] ?? '';
$selectedPractice = null;

if ($selectedPracticeId) {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$selectedPracticeId]);
  $selectedPractice = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all active products
$productsStmt = $pdo->query("SELECT * FROM products WHERE active = TRUE ORDER BY name ASC");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get custom pricing for selected practice
$customPricing = [];
if ($selectedPracticeId) {
  $pricingStmt = $pdo->prepare("
    SELECT product_id, custom_price, discount_percentage
    FROM practice_pricing
    WHERE user_id = ?
  ");
  $pricingStmt->execute([$selectedPracticeId]);
  while ($row = $pricingStmt->fetch(PDO::FETCH_ASSOC)) {
    $customPricing[$row['product_id']] = $row;
  }
}

// Build product data for JavaScript
$productDataForJS = [];
foreach ($products as $product) {
  $piecesPerBox = $product['pieces_per_box'] ?? 1;
  $defaultPricePerBox = $product['price_wholesale'];
  $defaultPricePerPiece = $piecesPerBox > 0 ? $defaultPricePerBox / $piecesPerBox : 0;

  // Check for custom pricing
  $pricePerPiece = $defaultPricePerPiece;
  if (isset($customPricing[$product['id']])) {
    $pricePerPiece = (float)$customPricing[$product['id']]['custom_price'];
  }
  $pricePerBox = $pricePerPiece * $piecesPerBox;

  $productDataForJS[$product['id']] = [
    'id' => $product['id'],
    'name' => $product['name'],
    'pieces_per_box' => $piecesPerBox,
    'price_per_box' => $pricePerBox
  ];
}
?>

<div class="main-content">
  <div style="max-width: 1600px; margin: 0 auto; padding: 2rem;">

    <!-- Page Header -->
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
        Create Wholesale Order
      </h1>
      <p style="color: var(--ink-light); font-size: 0.875rem;">
        Create wholesale orders for patients or office stock on behalf of a practice
      </p>
    </div>

    <!-- Practice Selection -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
      <h3 style="font-size: 1.125rem; font-weight: 600; color: var(--ink); margin-bottom: 1.5rem;">
        Select Practice / Physician
      </h3>

      <form method="GET" id="practice-select-form">
        <div style="display: flex; gap: 1rem; align-items: flex-end;">
          <div style="flex: 1; max-width: 600px;">
            <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
              Practice / Provider
            </label>
            <select name="practice_id" onchange="this.form.submit()"
                    style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);"
                    required>
              <option value="">-- Select Practice/Physician --</option>
              <?php foreach ($practices as $practice): ?>
                <option value="<?= htmlspecialchars($practice['id']) ?>"
                        <?= $selectedPracticeId === $practice['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($practice['practice_name'] ?? ($practice['first_name'] . ' ' . $practice['last_name'])) ?>
                  (<?= htmlspecialchars($practice['user_type']) ?>) - <?= htmlspecialchars($practice['email']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </form>
    </div>

    <?php if ($selectedPractice): ?>
      <!-- Order Creation Form -->
      <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
        <h3 style="font-size: 1.125rem; font-weight: 600; color: var(--ink); margin-bottom: 1.5rem;">
          Create Order for: <?= htmlspecialchars($selectedPractice['practice_name'] ?? ($selectedPractice['first_name'] . ' ' . $selectedPractice['last_name'])) ?>
        </h3>

        <!-- Tab Navigation -->
        <div style="border-bottom: 2px solid var(--border); margin-bottom: 2rem;">
          <div style="display: flex; gap: 1rem;">
            <button type="button" class="tab-button active" onclick="switchTab('patients')"
                    style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: none; border-bottom: 3px solid var(--brand); background: transparent; color: var(--brand); cursor: pointer;">
              Patient Orders
            </button>
            <button type="button" class="tab-button" onclick="switchTab('office')"
                    style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: none; border-bottom: 3px solid transparent; background: transparent; color: var(--muted); cursor: pointer;">
              Office Stock
            </button>
          </div>
        </div>

        <!-- Patient Orders Tab -->
        <div id="patients-tab" class="tab-content">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h4 style="font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0;">
              Patients
            </h4>
            <button type="button" onclick="addPatient()"
                    style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; border: 1px solid var(--brand); border-radius: var(--radius); background: var(--brand-light); color: var(--brand); cursor: pointer;">
              + Add Patient
            </button>
          </div>

          <div id="patients-container">
            <!-- Patients will be added dynamically -->
          </div>
        </div>

        <!-- Office Stock Tab -->
        <div id="office-tab" class="tab-content" style="display: none;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h4 style="font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0;">
              Office Stock Items
            </h4>
            <button type="button" onclick="addOfficeStockItem()"
                    style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; border: 1px solid var(--brand); border-radius: var(--radius); background: var(--brand-light); color: var(--brand); cursor: pointer;">
              + Add Product
            </button>
          </div>

          <div id="office-stock-container">
            <!-- Office stock items will be added dynamically -->
          </div>
        </div>

        <!-- Order Summary -->
        <div style="background: var(--bg-gray); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-top: 2rem; margin-bottom: 1.5rem;">
          <h4 style="font-size: 1rem; font-weight: 600; color: var(--ink); margin-bottom: 1rem;">
            Order Summary
          </h4>
          <div id="summary-details" style="font-size: 0.875rem; color: var(--muted); margin-bottom: 1rem;">
            No items added yet
          </div>
          <div style="border-top: 1px solid var(--border); padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 1rem; font-weight: 700; color: var(--ink);">Grand Total:</span>
            <span id="grand-total" style="font-size: 1.25rem; font-weight: 700; color: var(--brand);">$0.00</span>
          </div>
        </div>

        <!-- Additional Notes -->
        <div style="margin-bottom: 2rem;">
          <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
            Additional Notes
          </label>
          <textarea id="order-notes" rows="3"
                    style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);"></textarea>
        </div>

        <!-- Submit Buttons -->
        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
          <button type="button" onclick="window.location.href='?'"
                  style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: 1px solid var(--border); border-radius: var(--radius); background: white; color: var(--ink); cursor: pointer;">
            Cancel
          </button>
          <button type="button" onclick="submitOrder()"
                  style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: none; border-radius: var(--radius); background: var(--brand); color: white; cursor: pointer;">
            Create Order
          </button>
        </div>

        <!-- Success/Error Messages -->
        <div id="message-container" style="display: none; margin-top: 1rem;"></div>
      </div>
    <?php else: ?>
      <!-- Empty State -->
      <div class="card" style="padding: 3rem; text-align: center;">
        <svg style="width: 64px; height: 64px; margin: 0 auto 1rem; opacity: 0.3; color: var(--muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
        </svg>
        <p style="font-size: 1rem; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem;">
          No Practice Selected
        </p>
        <p style="font-size: 0.875rem; color: var(--muted);">
          Please select a practice or physician above to create a wholesale order
        </p>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
// Product catalog
const productData = <?= json_encode($productDataForJS) ?>;
const practiceId = '<?= htmlspecialchars($selectedPracticeId) ?>';
const adminId = '<?= htmlspecialchars($admin['id']) ?>';

// State management
let patientCounter = 0;
let officeStockCounter = 0;
let patients = [];
let officeStockItems = [];

// Tab switching
function switchTab(tab) {
  const patientsTab = document.getElementById('patients-tab');
  const officeTab = document.getElementById('office-tab');
  const tabButtons = document.querySelectorAll('.tab-button');

  tabButtons.forEach(btn => {
    btn.style.borderBottom = '3px solid transparent';
    btn.style.color = 'var(--muted)';
  });

  if (tab === 'patients') {
    patientsTab.style.display = 'block';
    officeTab.style.display = 'none';
    tabButtons[0].style.borderBottom = '3px solid var(--brand)';
    tabButtons[0].style.color = 'var(--brand)';
  } else {
    patientsTab.style.display = 'none';
    officeTab.style.display = 'block';
    tabButtons[1].style.borderBottom = '3px solid var(--brand)';
    tabButtons[1].style.color = 'var(--brand)';
  }
}

// Add patient
function addPatient() {
  const patientIndex = patientCounter++;
  const container = document.getElementById('patients-container');

  const patientCard = document.createElement('div');
  patientCard.id = `patient-${patientIndex}`;
  patientCard.className = 'patient-card';
  patientCard.style.cssText = 'border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1.5rem; background: white;';

  patientCard.innerHTML = `
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h5 style="font-size: 0.9375rem; font-weight: 600; color: var(--ink); margin: 0;">Patient ${patientIndex + 1}</h5>
      <button type="button" onclick="removePatient(${patientIndex})"
              style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border: 1px solid var(--error); border-radius: var(--radius); background: white; color: var(--error); cursor: pointer;">
        Remove Patient
      </button>
    </div>

    <!-- Patient Info -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
      <div>
        <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">First Name *</label>
        <input type="text" id="patient-${patientIndex}-first-name" required
               style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
      </div>
      <div>
        <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Last Name *</label>
        <input type="text" id="patient-${patientIndex}-last-name" required
               style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
      </div>
      <div>
        <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.75rem;">Date of Birth *</label>
        <input type="date" id="patient-${patientIndex}-dob" required
               style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
      </div>
    </div>

    <!-- Products for this patient -->
    <div style="margin-top: 1rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <label style="font-weight: 500; color: var(--ink); font-size: 0.875rem;">Products</label>
        <button type="button" onclick="addProductToPatient(${patientIndex})"
                style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border: 1px solid var(--brand); border-radius: var(--radius); background: var(--brand-light); color: var(--brand); cursor: pointer;">
          + Add Product
        </button>
      </div>
      <div id="patient-${patientIndex}-products">
        <!-- Products will be added here -->
      </div>
      <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border); text-align: right;">
        <span style="font-weight: 600; color: var(--ink); font-size: 0.875rem;">Patient Subtotal: </span>
        <span id="patient-${patientIndex}-total" style="font-size: 1rem; font-weight: 700; color: var(--brand);">$0.00</span>
      </div>
    </div>
  `;

  container.appendChild(patientCard);
  patients.push({ index: patientIndex, products: [] });

  // Add first product row automatically
  addProductToPatient(patientIndex);
}

// Add product to patient
function addProductToPatient(patientIndex) {
  const container = document.getElementById(`patient-${patientIndex}-products`);
  const productIndex = Date.now();

  const productRow = document.createElement('div');
  productRow.id = `patient-${patientIndex}-product-${productIndex}`;
  productRow.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 0.75rem; align-items: end; padding: 0.75rem; background: var(--bg-gray); border-radius: var(--radius); margin-bottom: 0.5rem;';

  productRow.innerHTML = `
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Product</label>
      <select class="product-select" onchange="updateProductInfo(${patientIndex}, ${productIndex})"
              style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
        <option value="">Select product...</option>
        ${Object.values(productData).map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
      </select>
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Boxes</label>
      <input type="number" min="1" value="1" class="boxes-input" onchange="calculatePatientTotal(${patientIndex})"
             style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Subtotal</label>
      <div class="product-subtotal" style="padding: 0.5rem; font-weight: 600; color: var(--ink);">$0.00</div>
    </div>
    <div style="padding-bottom: 0.25rem;">
      <button type="button" onclick="removeProductFromPatient(${patientIndex}, ${productIndex})"
              style="background: var(--error); color: white; border: none; border-radius: var(--radius); width: 32px; height: 32px; cursor: pointer; font-size: 1.125rem;">×</button>
    </div>
  `;

  container.appendChild(productRow);
}

// Add office stock item
function addOfficeStockItem() {
  const container = document.getElementById('office-stock-container');
  const itemIndex = Date.now();

  const itemRow = document.createElement('div');
  itemRow.id = `office-stock-${itemIndex}`;
  itemRow.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 0.75rem; align-items: end; padding: 0.75rem; background: var(--bg-gray); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 0.75rem;';

  itemRow.innerHTML = `
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Product</label>
      <select class="product-select" onchange="updateOfficeStockInfo(${itemIndex})"
              style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
        <option value="">Select product...</option>
        ${Object.values(productData).map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
      </select>
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Boxes</label>
      <input type="number" min="1" value="1" class="boxes-input" onchange="calculateOfficeStockTotal()"
             style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Subtotal</label>
      <div class="product-subtotal" style="padding: 0.5rem; font-weight: 600; color: var(--ink);">$0.00</div>
    </div>
    <div style="padding-bottom: 0.25rem;">
      <button type="button" onclick="removeOfficeStockItem(${itemIndex})"
              style="background: var(--error); color: white; border: none; border-radius: var(--radius); width: 32px; height: 32px; cursor: pointer; font-size: 1.125rem;">×</button>
    </div>
  `;

  container.appendChild(itemRow);
  calculateOfficeStockTotal();
}

// Update product info when selected
function updateProductInfo(patientIndex, productIndex) {
  const row = document.getElementById(`patient-${patientIndex}-product-${productIndex}`);
  const select = row.querySelector('.product-select');
  const productId = select.value;

  if (productId && productData[productId]) {
    const product = productData[productId];
    const boxes = parseInt(row.querySelector('.boxes-input').value) || 0;
    const subtotal = boxes * product.price_per_box;
    row.querySelector('.product-subtotal').textContent = '$' + subtotal.toFixed(2);
  } else {
    row.querySelector('.product-subtotal').textContent = '$0.00';
  }

  calculatePatientTotal(patientIndex);
}

function updateOfficeStockInfo(itemIndex) {
  const row = document.getElementById(`office-stock-${itemIndex}`);
  const select = row.querySelector('.product-select');
  const productId = select.value;

  if (productId && productData[productId]) {
    const product = productData[productId];
    const boxes = parseInt(row.querySelector('.boxes-input').value) || 0;
    const subtotal = boxes * product.price_per_box;
    row.querySelector('.product-subtotal').textContent = '$' + subtotal.toFixed(2);
  } else {
    row.querySelector('.product-subtotal').textContent = '$0.00';
  }

  calculateOfficeStockTotal();
}

// Calculate patient subtotal
function calculatePatientTotal(patientIndex) {
  const container = document.getElementById(`patient-${patientIndex}-products`);
  const productRows = container.querySelectorAll('[id^="patient-' + patientIndex + '-product-"]');

  let total = 0;
  productRows.forEach(row => {
    const select = row.querySelector('.product-select');
    const productId = select.value;
    if (productId && productData[productId]) {
      const boxes = parseInt(row.querySelector('.boxes-input').value) || 0;
      const subtotal = boxes * productData[productId].price_per_box;
      row.querySelector('.product-subtotal').textContent = '$' + subtotal.toFixed(2);
      total += subtotal;
    }
  });

  document.getElementById(`patient-${patientIndex}-total`).textContent = '$' + total.toFixed(2);
  updateGrandTotal();
}

function calculateOfficeStockTotal() {
  const container = document.getElementById('office-stock-container');
  const itemRows = container.querySelectorAll('[id^="office-stock-"]');

  let total = 0;
  itemRows.forEach(row => {
    const select = row.querySelector('.product-select');
    const productId = select.value;
    if (productId && productData[productId]) {
      const boxes = parseInt(row.querySelector('.boxes-input').value) || 0;
      const subtotal = boxes * productData[productId].price_per_box;
      row.querySelector('.product-subtotal').textContent = '$' + subtotal.toFixed(2);
      total += subtotal;
    }
  });

  updateGrandTotal();
}

// Remove functions
function removePatient(patientIndex) {
  const card = document.getElementById(`patient-${patientIndex}`);
  if (card) {
    card.remove();
    updateGrandTotal();
  }
}

function removeProductFromPatient(patientIndex, productIndex) {
  const row = document.getElementById(`patient-${patientIndex}-product-${productIndex}`);
  if (row) {
    row.remove();
    calculatePatientTotal(patientIndex);
  }
}

function removeOfficeStockItem(itemIndex) {
  const row = document.getElementById(`office-stock-${itemIndex}`);
  if (row) {
    row.remove();
    calculateOfficeStockTotal();
  }
}

// Update grand total and summary
function updateGrandTotal() {
  let grandTotal = 0;
  let itemCount = 0;
  let patientCount = 0;

  // Count patient orders
  const patientCards = document.querySelectorAll('.patient-card');
  patientCards.forEach(card => {
    const totalEl = card.querySelector('[id$="-total"]');
    if (totalEl) {
      const totalText = totalEl.textContent.replace('$', '').replace(',', '');
      const total = parseFloat(totalText) || 0;
      if (total > 0) {
        grandTotal += total;
        patientCount++;
      }
    }

    const products = card.querySelectorAll('.product-select');
    products.forEach(select => {
      if (select.value) itemCount++;
    });
  });

  // Count office stock orders
  const officeStockItems = document.querySelectorAll('#office-stock-container [id^="office-stock-"]');
  let officeStockCount = 0;
  officeStockItems.forEach(row => {
    const select = row.querySelector('.product-select');
    if (select && select.value) {
      const productId = select.value;
      if (productData[productId]) {
        const boxes = parseInt(row.querySelector('.boxes-input').value) || 0;
        grandTotal += boxes * productData[productId].price_per_box;
        itemCount++;
        officeStockCount++;
      }
    }
  });

  // Update display
  document.getElementById('grand-total').textContent = '$' + grandTotal.toFixed(2);

  const summaryDetails = document.getElementById('summary-details');
  if (itemCount === 0) {
    summaryDetails.innerHTML = '<p style="color: var(--muted); margin: 0;">No items added yet</p>';
  } else {
    let summary = [];
    if (patientCount > 0) summary.push(`${patientCount} patient${patientCount !== 1 ? 's' : ''}`);
    if (officeStockCount > 0) summary.push(`${officeStockCount} office stock item${officeStockCount !== 1 ? 's' : ''}`);
    summaryDetails.innerHTML = `<p style="margin: 0;">${summary.join(' + ')} • ${itemCount} total product${itemCount !== 1 ? 's' : ''}</p>`;
  }
}

// Submit order
function submitOrder() {
  // Collect patient orders
  const patientOrders = [];
  const patientCards = document.querySelectorAll('.patient-card');

  patientCards.forEach(card => {
    const patientId = card.id.replace('patient-', '');
    const firstName = document.getElementById(`patient-${patientId}-first-name`).value.trim();
    const lastName = document.getElementById(`patient-${patientId}-last-name`).value.trim();
    const dob = document.getElementById(`patient-${patientId}-dob`).value;

    if (!firstName || !lastName || !dob) return; // Skip incomplete patients

    const products = [];
    const productRows = card.querySelectorAll('[id^="patient-' + patientId + '-product-"]');
    productRows.forEach(row => {
      const select = row.querySelector('.product-select');
      const productId = select.value;
      if (productId) {
        const boxes = parseInt(row.querySelector('.boxes-input').value) || 0;
        if (boxes > 0) {
          products.push({
            product_id: productId,
            boxes: boxes,
            price_per_box: productData[productId].price_per_box
          });
        }
      }
    });

    if (products.length > 0) {
      patientOrders.push({
        patient: { first_name: firstName, last_name: lastName, dob: dob },
        products: products
      });
    }
  });

  // Collect office stock orders
  const officeStockOrders = [];
  const officeStockItems = document.querySelectorAll('#office-stock-container [id^="office-stock-"]');
  officeStockItems.forEach(row => {
    const select = row.querySelector('.product-select');
    const productId = select.value;
    if (productId) {
      const boxes = parseInt(row.querySelector('.boxes-input').value) || 0;
      if (boxes > 0) {
        officeStockOrders.push({
          product_id: productId,
          boxes: boxes,
          price_per_box: productData[productId].price_per_box
        });
      }
    }
  });

  if (patientOrders.length === 0 && officeStockOrders.length === 0) {
    showMessage('error', 'Please add at least one patient order or office stock item');
    return;
  }

  // Build order data
  const orderData = {
    practice_id: practiceId,
    patient_orders: patientOrders,
    office_stock: officeStockOrders,
    notes: document.getElementById('order-notes').value.trim(),
    created_by_admin: true,
    admin_id: adminId
  };

  // Submit to backend
  fetch('/api/admin/create-wholesale-order.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(orderData)
  })
  .then(response => response.json())
  .then(data => {
    if (data.ok) {
      showMessage('success', `Order created successfully! ${data.orders_created} order${data.orders_created !== 1 ? 's' : ''} created.`);
      setTimeout(() => window.location.href = '/admin/wholesale-orders.php', 2000);
    } else {
      showMessage('error', 'Error creating order: ' + (data.message || data.error || 'Unknown error'));
    }
  })
  .catch(error => {
    showMessage('error', 'Network error: ' + error.message);
  });
}

function showMessage(type, message) {
  const container = document.getElementById('message-container');
  const bgColor = type === 'success' ? 'var(--success-light)' : 'var(--error-light)';
  const borderColor = type === 'success' ? 'var(--success)' : 'var(--error)';
  const textColor = type === 'success' ? 'var(--success)' : 'var(--error)';

  container.innerHTML = `
    <div style="background: ${bgColor}; border: 1px solid ${borderColor}; border-radius: var(--radius); padding: 1rem; color: ${textColor};">
      ${message}
    </div>
  `;
  container.style.display = 'block';
  container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Initialize with one patient
if (document.getElementById('patients-container')) {
  addPatient();
}
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
