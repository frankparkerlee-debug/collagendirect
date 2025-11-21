<?php
/**
 * Step 2: Assign Products to Patients OR Office Stock
 * UPDATED: Now uses portal-style product grouping (same as portal/wholesale-new.php)
 */
$orderType = $_SESSION['admin_order_type'] ?? 'patient_orders';
$isOfficeStock = ($orderType === 'office_stock');

// Group products by core name (same logic as portal/wholesale-new.php lines 781-810)
$productGroups = [];
foreach ($products as $product) {
  $fullName = $product['name'];

  // Remove HCPCS code in parentheses (e.g., "(A6196)")
  $nameWithoutHCPCS = preg_replace('/\s*\([A-Z0-9\/]+\)\s*$/i', '', $fullName);

  // Remove size dimensions
  $coreName = preg_replace('/\s+\d+(?:\.\d+)?\s*x\s*\d+(?:\.\d+)?$/i', '', $nameWithoutHCPCS); // "2x2", "4.33x4.33"
  $coreName = preg_replace('/\s+\d+"x\d+"$/i', '', $coreName); // 9"x9"
  $coreName = preg_replace('/\s+\d+(?:\.\d+)?[a-z]+\s+Bottle$/i', '', $coreName); // "8oz Bottle"
  $coreName = preg_replace('/\s+\d+(?:\.\d+)?g\s+Rope$/i', ' Rope', $coreName); // "2g Rope" -> "Rope"
  $coreName = preg_replace('/\s+\d+(?:\.\d+)?g$/i', '', $coreName); // "1.0g"
  $coreName = preg_replace('/\s+\d+ML$/i', '', $coreName); // "100ML", "250ML"
  $coreName = preg_replace('/\s+Medium$/i', '', $coreName); // "Medium"
  $coreName = preg_replace('/\s+\d+"x\d+"\(\d+(?:\.\d+)?"x\d+(?:\.\d+)?"\)$/i', '', $coreName); // 4"x4"(2"x2")

  $coreName = trim($coreName);

  if (!isset($productGroups[$coreName])) {
    $productGroups[$coreName] = [];
  }
  $productGroups[$coreName][] = $product;
}

// Sort core product names alphabetically
ksort($productGroups);
?>

<form method="POST" id="step2-form">
  <input type="hidden" name="action" value="save_products">

  <?php if ($isOfficeStock): ?>
    <!-- Office Stock Products -->
    <p style="margin-bottom: 2rem; color: var(--muted); font-size: 0.875rem;">
      Select products and quantities for office stock. No patient assignment needed.
    </p>

    <div style="background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 2rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h4 style="font-size: 1rem; font-weight: 600; color: var(--ink);">Office Stock Products</h4>
        <button type="button" onclick="addOfficeStockRow()"
                style="padding: 0.5rem 1rem; font-size: 0.875rem; border: 1px solid var(--brand); border-radius: var(--radius); background: var(--brand); color: white; cursor: pointer;">
          + Add Product
        </button>
      </div>

      <div id="office-stock-products" style="display: flex; flex-direction: column; gap: 0.75rem;">
        <!-- Product rows will be added here dynamically -->
      </div>

      <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border); text-align: right;">
        <span style="font-weight: 600; color: var(--ink);">Total: </span>
        <span id="office-stock-total" style="font-size: 1.25rem; font-weight: 700; color: var(--brand);">$0.00</span>
      </div>
    </div>

  <?php else: ?>
    <!-- Patient-Based Products -->
    <?php if (!empty($patients)): ?>
      <p style="margin-bottom: 1.5rem; color: var(--muted); font-size: 0.875rem;">
        Assign products to each patient below. You can add multiple products per patient.
      </p>

      <div style="display: flex; flex-direction: column; gap: 1.25rem; margin-bottom: 1.5rem;">
        <?php foreach ($patients as $patIndex => $patient): ?>
          <div class="patient-product-card" style="background: white; border: 1px solid var(--border); border-radius: 6px; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
              <h3 style="font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0;">
                Patient: <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
              </h3>
              <div style="color: var(--muted); font-size: 0.75rem;">
                <?= $patIndex + 1 ?>/<?= count($patients) ?>
              </div>
            </div>

            <!-- Product assignment rows for this patient -->
            <div class="product-rows" id="patient-<?= $patIndex ?>-products" style="display: flex; flex-direction: column; gap: 0.75rem;">
              <!-- Product rows will be added here dynamically -->
            </div>

            <button type="button" class="btn" onclick="addProductRow(<?= $patIndex ?>)"
                    style="margin-top: 0.75rem; padding: 0.5rem 1rem; font-size: 0.875rem; border: 1px solid var(--brand); border-radius: var(--radius); background: var(--brand-light); color: var(--brand); cursor: pointer;">
              + Add Product
            </button>

            <!-- Patient subtotal -->
            <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border); text-align: right;">
              <span style="font-weight: 600; color: var(--ink); font-size: 0.875rem;">Subtotal: </span>
              <span id="patient-<?= $patIndex ?>-total" style="font-size: 1rem; font-weight: 700; color: var(--brand);">$0.00</span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Grand Total -->
      <div style="background: var(--brand); color: white; border-radius: var(--radius); padding: 1.25rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 1.125rem; font-weight: 600;">Grand Total:</span>
          <span id="grand-total" style="font-size: 1.5rem; font-weight: 700;">$0.00</span>
        </div>
      </div>

    <?php else: ?>
      <p style="text-align: center; color: var(--muted); padding: 2rem;">No patients added in Step 1</p>
    <?php endif; ?>
  <?php endif; ?>

  <div style="display: flex; justify-content: space-between; padding-top: 1.5rem; border-top: 1px solid var(--border);">
    <button type="button" onclick="window.location.href='?practice_id=<?= urlencode($selectedPracticeId) ?>&step=1'"
            style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: 1px solid var(--border); border-radius: var(--radius); background: white; color: var(--ink); cursor: pointer;">
      ← Back to Patients
    </button>
    <button type="submit"
            style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: none; border-radius: var(--radius); background: var(--brand); color: white; cursor: pointer;">
      Next: Review Order →
    </button>
  </div>
</form>

<script>
// Product catalog with grouped sizes (same structure as portal)
const productCatalog = <?= json_encode($productGroups) ?>;
const productData = <?= json_encode(array_column($products, null, 'id')) ?>;
const savedProducts = <?= json_encode($savedProducts) ?>;

let productRowCounters = {};

function addProductRow(patientIndex) {
  if (!productRowCounters[patientIndex]) {
    productRowCounters[patientIndex] = 0;
  }
  const rowIndex = productRowCounters[patientIndex]++;
  const container = document.getElementById(`patient-${patientIndex}-products`);

  const row = document.createElement('div');
  row.className = 'product-assignment-row';
  row.dataset.patientIndex = patientIndex;
  row.dataset.rowIndex = rowIndex;
  row.style.cssText = 'display: grid; grid-template-columns: 2fr 1.25fr 1fr 50px; gap: 0.75rem; align-items: end; padding: 0.75rem; background: #f8f9fa; border-radius: 4px;';

  row.innerHTML = `
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem; color: var(--ink);">Product</label>
      <select class="form-control product-selector" onchange="updateSizeOptions(${patientIndex}, ${rowIndex})" required style="font-size: 0.875rem; width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
        <option value="">Select product...</option>
        ${Object.keys(productCatalog).map(category =>
          `<option value="${category}">${category}</option>`
        ).join('')}
      </select>
    </div>

    <div class="size-selector-container" style="display: none;">
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem; color: var(--ink);">Size</label>
      <select class="form-control size-selector" onchange="updateProductSelection(${patientIndex}, ${rowIndex})" required style="font-size: 0.875rem; width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
        <option value="">Select size...</option>
      </select>
    </div>

    <div class="quantity-container" style="display: none;">
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem; color: var(--ink);">Boxes</label>
      <input type="number" class="form-control quantity-input" min="1" max="100" value="1" onchange="calculatePatientTotal(${patientIndex})" required style="font-size: 0.875rem; width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
      <div class="price-info" style="font-size: 0.7rem; color: var(--muted); margin-top: 0.25rem;"></div>
    </div>

    <div style="padding-bottom: 0.25rem;">
      <button type="button" class="remove-btn" onclick="removeProductRow(${patientIndex}, ${rowIndex})" title="Remove" style="background: #dc3545; color: white; border: none; border-radius: 4px; width: 32px; height: 32px; cursor: pointer; font-size: 1.125rem; line-height: 1;">×</button>
    </div>

    <input type="hidden" class="product-id-input" name="products[${patientIndex}][${rowIndex}][product_id]" value="">
    <input type="hidden" class="boxes-input" name="products[${patientIndex}][${rowIndex}][boxes]" value="">
  `;

  container.appendChild(row);
}

function updateSizeOptions(patientIndex, rowIndex) {
  const row = document.querySelector(`[data-patient-index="${patientIndex}"][data-row-index="${rowIndex}"]`);
  const productSelect = row.querySelector('.product-selector');
  const sizeContainer = row.querySelector('.size-selector-container');
  const sizeSelect = row.querySelector('.size-selector');
  const quantityContainer = row.querySelector('.quantity-container');

  const selectedCategory = productSelect.value;

  if (!selectedCategory) {
    sizeContainer.style.display = 'none';
    quantityContainer.style.display = 'none';
    return;
  }

  const products = productCatalog[selectedCategory];
  sizeSelect.innerHTML = '<option value="">Select size...</option>';

  products.forEach(product => {
    const option = document.createElement('option');
    option.value = product.id;

    // Extract size from full product name
    let sizeText = product.size || 'Standard';
    option.textContent = sizeText;
    sizeSelect.appendChild(option);
  });

  sizeContainer.style.display = 'block';
  quantityContainer.style.display = 'none';
}

function updateProductSelection(patientIndex, rowIndex) {
  const row = document.querySelector(`[data-patient-index="${patientIndex}"][data-row-index="${rowIndex}"]`);
  const sizeSelect = row.querySelector('.size-selector');
  const quantityContainer = row.querySelector('.quantity-container');
  const priceInfo = row.querySelector('.price-info');
  const productIdInput = row.querySelector('.product-id-input');
  const boxesInput = row.querySelector('.boxes-input');
  const quantityInput = row.querySelector('.quantity-input');

  const productId = sizeSelect.value;

  if (!productId) {
    quantityContainer.style.display = 'none';
    productIdInput.value = '';
    boxesInput.value = '';
    return;
  }

  const product = productData[productId];
  if (!product) return;

  productIdInput.value = productId;
  boxesInput.value = quantityInput.value || '1';

  const pricePerBox = parseFloat(product.price_per_box || 0);
  const piecesPerBox = parseInt(product.pieces_per_box || 10);
  priceInfo.textContent = `$${pricePerBox.toFixed(2)}/box (${piecesPerBox} pcs)`;

  quantityContainer.style.display = 'block';
  calculatePatientTotal(patientIndex);
}

function removeProductRow(patientIndex, rowIndex) {
  const row = document.querySelector(`[data-patient-index="${patientIndex}"][data-row-index="${rowIndex}"]`);
  if (row) {
    row.remove();
    calculatePatientTotal(patientIndex);
  }
}

function calculatePatientTotal(patientIndex) {
  const container = document.getElementById(`patient-${patientIndex}-products`);
  const rows = container.querySelectorAll('.product-assignment-row');
  let patientTotal = 0;

  rows.forEach(row => {
    const productIdInput = row.querySelector('.product-id-input');
    const quantityInput = row.querySelector('.quantity-input');
    const boxesInput = row.querySelector('.boxes-input');

    const productId = productIdInput?.value;
    const quantity = parseInt(quantityInput?.value) || 0;

    if (productId && productData[productId]) {
      const pricePerBox = parseFloat(productData[productId].price_per_box || 0);
      patientTotal += pricePerBox * quantity;

      // Update hidden boxes input
      if (boxesInput) {
        boxesInput.value = quantity;
      }
    }
  });

  // Update patient subtotal
  const totalEl = document.getElementById(`patient-${patientIndex}-total`);
  if (totalEl) {
    totalEl.textContent = '$' + patientTotal.toFixed(2);
  }

  calculateGrandTotal();
}

function calculateGrandTotal() {
  let grandTotal = 0;

  // Sum all patient totals
  document.querySelectorAll('[id^="patient-"][id$="-total"]').forEach(el => {
    const value = parseFloat(el.textContent.replace('$', '')) || 0;
    grandTotal += value;
  });

  // Update grand total
  const grandTotalEl = document.getElementById('grand-total');
  if (grandTotalEl) {
    grandTotalEl.textContent = '$' + grandTotal.toFixed(2);
  }
}

// Office Stock Functions
let officeStockRowCounter = 0;

function addOfficeStockRow() {
  const container = document.getElementById('office-stock-products');
  const rowIndex = officeStockRowCounter++;

  const row = document.createElement('div');
  row.className = 'office-stock-row';
  row.dataset.rowIndex = rowIndex;
  row.style.cssText = 'display: grid; grid-template-columns: 2fr 1.25fr 1fr 50px; gap: 0.75rem; align-items: end; padding: 0.75rem; background: #f8f9fa; border-radius: 4px;';

  row.innerHTML = `
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem; color: var(--ink);">Product</label>
      <select class="form-control product-selector" onchange="updateOfficeStockSizeOptions(${rowIndex})" required style="font-size: 0.875rem; width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
        <option value="">Select product...</option>
        ${Object.keys(productCatalog).map(category =>
          `<option value="${category}">${category}</option>`
        ).join('')}
      </select>
    </div>

    <div class="size-selector-container" style="display: none;">
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem; color: var(--ink);">Size</label>
      <select class="form-control size-selector" onchange="updateOfficeStockProductSelection(${rowIndex})" required style="font-size: 0.875rem; width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
        <option value="">Select size...</option>
      </select>
    </div>

    <div class="quantity-container" style="display: none;">
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem; color: var(--ink);">Boxes</label>
      <input type="number" class="form-control quantity-input" min="1" max="100" value="1" onchange="calculateOfficeStockTotal()" required style="font-size: 0.875rem; width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
      <div class="price-info" style="font-size: 0.7rem; color: var(--muted); margin-top: 0.25rem;"></div>
    </div>

    <div style="padding-bottom: 0.25rem;">
      <button type="button" onclick="removeOfficeStockRow(${rowIndex})" title="Remove" style="background: #dc3545; color: white; border: none; border-radius: 4px; width: 32px; height: 32px; cursor: pointer; font-size: 1.125rem; line-height: 1;">×</button>
    </div>

    <input type="hidden" class="product-id-input" name="products[0][${rowIndex}][product_id]" value="">
    <input type="hidden" class="boxes-input" name="products[0][${rowIndex}][boxes]" value="">
  `;

  container.appendChild(row);
}

function updateOfficeStockSizeOptions(rowIndex) {
  const row = document.querySelector(`.office-stock-row[data-row-index="${rowIndex}"]`);
  const productSelect = row.querySelector('.product-selector');
  const sizeContainer = row.querySelector('.size-selector-container');
  const sizeSelect = row.querySelector('.size-selector');
  const quantityContainer = row.querySelector('.quantity-container');

  const selectedCategory = productSelect.value;

  if (!selectedCategory) {
    sizeContainer.style.display = 'none';
    quantityContainer.style.display = 'none';
    return;
  }

  const products = productCatalog[selectedCategory];
  sizeSelect.innerHTML = '<option value="">Select size...</option>';

  products.forEach(product => {
    const option = document.createElement('option');
    option.value = product.id;
    option.textContent = product.size || 'Standard';
    sizeSelect.appendChild(option);
  });

  sizeContainer.style.display = 'block';
  quantityContainer.style.display = 'none';
}

function updateOfficeStockProductSelection(rowIndex) {
  const row = document.querySelector(`.office-stock-row[data-row-index="${rowIndex}"]`);
  const sizeSelect = row.querySelector('.size-selector');
  const quantityContainer = row.querySelector('.quantity-container');
  const priceInfo = row.querySelector('.price-info');
  const productIdInput = row.querySelector('.product-id-input');
  const boxesInput = row.querySelector('.boxes-input');
  const quantityInput = row.querySelector('.quantity-input');

  const productId = sizeSelect.value;

  if (!productId) {
    quantityContainer.style.display = 'none';
    productIdInput.value = '';
    boxesInput.value = '';
    return;
  }

  const product = productData[productId];
  if (!product) return;

  productIdInput.value = productId;
  boxesInput.value = quantityInput.value || '1';

  const pricePerBox = parseFloat(product.price_per_box || 0);
  const piecesPerBox = parseInt(product.pieces_per_box || 10);
  priceInfo.textContent = `$${pricePerBox.toFixed(2)}/box (${piecesPerBox} pcs)`;

  quantityContainer.style.display = 'block';
  calculateOfficeStockTotal();
}

function removeOfficeStockRow(rowIndex) {
  const row = document.querySelector(`.office-stock-row[data-row-index="${rowIndex}"]`);
  if (row) {
    row.remove();
    calculateOfficeStockTotal();
  }
}

function calculateOfficeStockTotal() {
  const container = document.getElementById('office-stock-products');
  const rows = container.querySelectorAll('.office-stock-row');
  let total = 0;

  rows.forEach(row => {
    const productIdInput = row.querySelector('.product-id-input');
    const quantityInput = row.querySelector('.quantity-input');
    const boxesInput = row.querySelector('.boxes-input');

    const productId = productIdInput?.value;
    const quantity = parseInt(quantityInput?.value) || 0;

    if (productId && productData[productId]) {
      const pricePerBox = parseFloat(productData[productId].price_per_box || 0);
      total += pricePerBox * quantity;

      // Update hidden boxes input
      if (boxesInput) {
        boxesInput.value = quantity;
      }
    }
  });

  // Update office stock total
  const totalEl = document.getElementById('office-stock-total');
  if (totalEl) {
    totalEl.textContent = '$' + total.toFixed(2);
  }
}

// Load saved products if coming back from step 3
if (savedProducts && Object.keys(savedProducts).length > 0) {
  <?php if ($isOfficeStock): ?>
    // Load office stock products
    const officeProducts = savedProducts[0] || [];
    officeProducts.forEach(prod => {
      if (prod.product_id && prod.boxes) {
        addOfficeStockRow();
        // TODO: Set saved values
      }
    });
  <?php else: ?>
    // Load patient products
    <?php foreach ($patients as $patIndex => $patient): ?>
      const patient<?= $patIndex ?>Products = savedProducts[<?= $patIndex ?>] || [];
      patient<?= $patIndex ?>Products.forEach(prod => {
        if (prod.product_id && prod.boxes) {
          addProductRow(<?= $patIndex ?>);
          // TODO: Set saved values
        }
      });
    <?php endforeach; ?>
  <?php endif; ?>
}
</script>
