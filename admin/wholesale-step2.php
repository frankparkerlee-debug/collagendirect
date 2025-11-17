<?php
/**
 * Step 2: Assign Products to Patients OR Office Stock
 */
$orderType = $_SESSION['admin_order_type'] ?? 'patient_orders';
$isOfficeStock = ($orderType === 'office_stock');
?>

<form method="POST" id="step2-form">
  <input type="hidden" name="action" value="save_products">

  <?php if ($isOfficeStock): ?>
    <!-- Office Stock Products -->
    <p style="margin-bottom: 2rem; color: var(--muted); font-size: 0.875rem;">
      Select products and quantities for office stock. No patient assignment needed.
    </p>

    <div style="border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 2rem; background: white;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h4 style="font-size: 1rem; font-weight: 600; color: var(--ink);">Office Stock Products</h4>
        <button type="button" onclick="addOfficeStockRow()"
                style="padding: 0.375rem 0.75rem; font-size: 0.75rem; border: 1px solid var(--brand); border-radius: var(--radius); background: var(--brand-light); color: var(--brand); cursor: pointer;">
          + Add Product
        </button>
      </div>

      <div id="office-stock-products">
        <?php
        $officeProducts = $savedProducts[0] ?? [];
        if (!empty($officeProducts)):
          foreach ($officeProducts as $prodIndex => $prod):
        ?>
          <div class="product-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 0.75rem; align-items: end; padding: 0.75rem; background: var(--bg-gray); border-radius: var(--radius); margin-bottom: 0.5rem;">
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Product</label>
              <select name="products[0][<?= $prodIndex ?>][product_id]"
                      style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
                <option value="">Select product...</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= htmlspecialchars($product['id']) ?>" <?= ($prod['product_id'] ?? '') == $product['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($product['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Boxes</label>
              <input type="number" name="products[0][<?= $prodIndex ?>][boxes]" value="<?= htmlspecialchars($prod['boxes'] ?? '1') ?>" min="1"
                     style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
            </div>
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Price/Box</label>
              <div style="padding: 0.5rem; font-weight: 600;">$<?= isset($productDataForJS[$prod['product_id'] ?? '']) ? number_format($productDataForJS[$prod['product_id']]['price_per_box'], 2) : '0.00' ?></div>
            </div>
            <div>
              <button type="button" onclick="this.parentElement.parentElement.remove()"
                      style="background: var(--error); color: white; border: none; border-radius: var(--radius); width: 32px; height: 32px; cursor: pointer;">×</button>
            </div>
          </div>
        <?php
          endforeach;
        endif;
        ?>
      </div>
    </div>

  <?php else: ?>
    <!-- Patient-Based Products -->
    <p style="margin-bottom: 2rem; color: var(--muted); font-size: 0.875rem;">
      Assign products to each patient below. You can add multiple products per patient.
    </p>

    <?php if (!empty($patients)): ?>
    <?php foreach ($patients as $patIndex => $patient): ?>
      <div style="border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 2rem; background: white;">
        <div style="margin-bottom: 1rem;">
          <h4 style="font-size: 1rem; font-weight: 600; color: var(--ink); margin-bottom: 0.25rem;">
            Patient <?= $patIndex + 1 ?>: <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
          </h4>
          <p style="font-size: 0.75rem; color: var(--muted);">DOB: <?= htmlspecialchars($patient['dob']) ?></p>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <label style="font-weight: 500; color: var(--ink); font-size: 0.875rem;">Products</label>
          <button type="button" onclick="addProductRow(<?= $patIndex ?>)"
                  style="padding: 0.375rem 0.75rem; font-size: 0.75rem; border: 1px solid var(--brand); border-radius: var(--radius); background: var(--brand-light); color: var(--brand); cursor: pointer;">
            + Add Product
          </button>
        </div>

        <div id="patient-<?= $patIndex ?>-products" style="margin-bottom: 1rem;">
          <?php
          $patientProducts = $savedProducts[$patIndex] ?? [];
          if (!empty($patientProducts)):
            foreach ($patientProducts as $prodIndex => $prod):
          ?>
            <div class="product-row" data-patient="<?= $patIndex ?>" style="display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr 1fr auto; gap: 0.5rem; align-items: end; padding: 0.75rem; background: var(--bg-gray); border-radius: var(--radius); margin-bottom: 0.5rem;">
              <!-- Hidden field to store selected product ID -->
              <input type="hidden" name="products[<?= $patIndex ?>][<?= $prodIndex ?>][product_id]" class="product-id-input" value="<?= htmlspecialchars($prod['product_id'] ?? '') ?>">

              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Product Type</label>
                <select class="product-type-select" onchange="updateSizeDropdown(this)"
                        style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
                  <option value="">Select type...</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Size</label>
                <select class="size-select" onchange="updateProductId(this)"
                        style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);" disabled>
                  <option value="">Select size...</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Boxes</label>
                <input type="number" name="products[<?= $patIndex ?>][<?= $prodIndex ?>][boxes]" value="<?= htmlspecialchars($prod['boxes'] ?? '1') ?>" min="1" class="boxes-input" onchange="updateRowPrice(this)"
                       style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Pcs/Box</label>
                <div class="pieces-per-box" style="padding: 0.5rem; font-size: 0.75rem; color: var(--muted); text-align: center;">-</div>
              </div>
              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Price/Box</label>
                <div class="price-per-box" style="padding: 0.5rem; font-weight: 600; font-size: 0.875rem;">$<?= isset($productDataForJS[$prod['product_id'] ?? '']) ? number_format($productDataForJS[$prod['product_id']]['price_per_box'], 2) : '0.00' ?></div>
              </div>
              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Total</label>
                <div class="row-total" style="padding: 0.5rem; font-weight: 700; color: var(--brand); font-size: 0.875rem;">$<?= isset($productDataForJS[$prod['product_id'] ?? '']) ? number_format($productDataForJS[$prod['product_id']]['price_per_box'] * ($prod['boxes'] ?? 1), 2) : '0.00' ?></div>
              </div>
              <div>
                <button type="button" onclick="removeRow(this)"
                        style="background: var(--error); color: white; border: none; border-radius: var(--radius); width: 32px; height: 32px; cursor: pointer;">×</button>
              </div>
            </div>
          <?php
            endforeach;
          endif;
          ?>
        </div>

        <!-- Patient Total -->
        <div class="patient-total" data-patient="<?= $patIndex ?>" style="padding: 1rem; background: white; border-radius: var(--radius); border: 2px solid var(--brand); margin-top: 1rem;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: 600; color: var(--ink);">Patient Total:</span>
            <span class="patient-total-amount" style="font-size: 1.25rem; font-weight: 700; color: var(--brand);">$0.00</span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- Grand Total -->
    <div style="padding: 1.5rem; background: var(--brand); border-radius: var(--radius); margin-top: 2rem;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 700; color: white; font-size: 1.125rem;">GRAND TOTAL:</span>
        <span class="grand-total-amount" style="font-size: 1.5rem; font-weight: 700; color: white;">$0.00</span>
      </div>
    </div>

  <?php else: ?>
    <p style="text-align: center; color: var(--muted); padding: 2rem;">No patients added in Step 1</p>
  <?php endif; ?>
  <?php endif; ?>
  <!-- End Patient/Office Stock Conditional -->

  <div style="display: flex; justify-content: space-between; padding-top: 1.5rem; border-top: 1px solid var(--border);">
    <form method="POST" style="display: inline;">
      <input type="hidden" name="action" value="back_to_patients">
      <button type="submit"
              style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: 1px solid var(--border); border-radius: var(--radius); background: white; color: var(--ink); cursor: pointer;">
        ← Back to Patients
      </button>
    </form>
    <button type="submit"
            style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: none; border-radius: var(--radius); background: var(--brand); color: white; cursor: pointer;">
      Next: Review Order →
    </button>
  </div>
</form>

<script>
const productCatalog = <?= json_encode($productDataForJS) ?>;

// Parse products to create cascading structure
const productsByType = {};
Object.values(productCatalog).forEach(product => {
  // Extract type and size from product name
  // Pattern: "Product Type Size" (e.g., "Calcium Alginate 2x2", "Collagen 1\"x6\"")
  const nameParts = product.name.trim().split(/\s+/);
  let size = nameParts[nameParts.length - 1]; // Last part is size
  let type = nameParts.slice(0, -1).join(' '); // Everything else is type

  // If type is empty, it means we have a single-word product name, use it as type
  if (!type) {
    type = product.name;
    size = 'Standard';
  }

  if (!productsByType[type]) {
    productsByType[type] = {};
  }
  productsByType[type][size] = product;
});

function updateSizeDropdown(typeSelect) {
  const row = typeSelect.closest('.product-row');
  const sizeSelect = row.querySelector('.size-select');
  const productType = typeSelect.value;

  // Clear size dropdown
  sizeSelect.innerHTML = '<option value="">Select size...</option>';
  sizeSelect.disabled = !productType;

  // Clear product selection
  const productIdInput = row.querySelector('.product-id-input');
  productIdInput.value = '';

  if (productType && productsByType[productType]) {
    // Populate size dropdown
    Object.keys(productsByType[productType]).sort().forEach(size => {
      const option = document.createElement('option');
      option.value = size;
      option.textContent = size;
      sizeSelect.appendChild(option);
    });
    sizeSelect.disabled = false;
  }

  // Reset price displays
  row.querySelector('.pieces-per-box').textContent = '-';
  row.querySelector('.price-per-box').textContent = '-';
  row.querySelector('.row-total').textContent = '-';

  updatePatientTotal(row.dataset.patient);
}

function updateProductId(sizeSelect) {
  const row = sizeSelect.closest('.product-row');
  const typeSelect = row.querySelector('.product-type-select');
  const productIdInput = row.querySelector('.product-id-input');
  const productType = typeSelect.value;
  const size = sizeSelect.value;

  if (productType && size && productsByType[productType] && productsByType[productType][size]) {
    const product = productsByType[productType][size];
    productIdInput.value = product.id;
    updateRowPrice(row);
  } else {
    productIdInput.value = '';
    row.querySelector('.pieces-per-box').textContent = '-';
    row.querySelector('.price-per-box').textContent = '-';
    row.querySelector('.row-total').textContent = '-';
    updatePatientTotal(row.dataset.patient);
  }
}

function updateRowPrice(element) {
  const row = element.closest ? element.closest('.product-row') : element;
  if (!row) return;

  const productIdInput = row.querySelector('.product-id-input');
  const boxesInput = row.querySelector('.boxes-input');
  const piecesPerBoxDiv = row.querySelector('.pieces-per-box');
  const pricePerBoxDiv = row.querySelector('.price-per-box');
  const rowTotalDiv = row.querySelector('.row-total');

  const productId = productIdInput?.value;
  const boxes = parseInt(boxesInput?.value) || 0;

  if (productId && productCatalog[productId]) {
    const product = productCatalog[productId];
    const pricePerBox = product.price_per_box;
    const piecesPerBox = product.pieces_per_box || 1;
    const total = pricePerBox * boxes;

    piecesPerBoxDiv.textContent = piecesPerBox;
    pricePerBoxDiv.textContent = '$' + pricePerBox.toFixed(2);
    rowTotalDiv.textContent = '$' + total.toFixed(2);
  } else {
    piecesPerBoxDiv.textContent = '-';
    pricePerBoxDiv.textContent = '-';
    rowTotalDiv.textContent = '-';
  }

  // Update patient total
  if (row.dataset.patient) {
    updatePatientTotal(row.dataset.patient);
  }
}

function updatePatientTotal(patientIndex) {
  const rows = document.querySelectorAll(`.product-row[data-patient="${patientIndex}"]`);
  let total = 0;

  rows.forEach(row => {
    const rowTotalDiv = row.querySelector('.row-total');
    const rowTotalText = rowTotalDiv?.textContent.replace(/[$,]/g, '') || '0';
    const rowTotal = parseFloat(rowTotalText) || 0;
    total += rowTotal;
  });

  const patientTotalDiv = document.querySelector(`.patient-total[data-patient="${patientIndex}"] .patient-total-amount`);
  if (patientTotalDiv) {
    patientTotalDiv.textContent = '$' + total.toFixed(2);
  }

  // Update grand total
  updateGrandTotal();
}

function updateGrandTotal() {
  const patientTotals = document.querySelectorAll('.patient-total-amount');
  let grandTotal = 0;

  patientTotals.forEach(totalDiv => {
    const totalText = totalDiv.textContent.replace(/[$,]/g, '') || '0';
    const total = parseFloat(totalText) || 0;
    grandTotal += total;
  });

  const grandTotalDiv = document.querySelector('.grand-total-amount');
  if (grandTotalDiv) {
    grandTotalDiv.textContent = '$' + grandTotal.toFixed(2);
  }
}

function removeRow(button) {
  const row = button.closest('.product-row');
  const patientIndex = row?.dataset.patient;
  row.remove();
  if (patientIndex) {
    updatePatientTotal(patientIndex);
  }
}

function addProductRow(patientIndex) {
  const container = document.getElementById(`patient-${patientIndex}-products`);
  const prodIndex = Date.now();

  const row = document.createElement('div');
  row.className = 'product-row';
  row.dataset.patient = patientIndex;
  row.style.cssText = 'display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr 1fr auto; gap: 0.5rem; align-items: end; padding: 0.75rem; background: var(--bg-gray); border-radius: var(--radius); margin-bottom: 0.5rem;';

  // Build product type options
  const productTypes = Object.keys(productsByType).sort();
  const typeOptions = productTypes.map(type => `<option value="${type}">${type}</option>`).join('');

  row.innerHTML = `
    <input type="hidden" name="products[${patientIndex}][${prodIndex}][product_id]" class="product-id-input" value="">
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Product Type</label>
      <select class="product-type-select" onchange="updateSizeDropdown(this)"
              style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
        <option value="">Select type...</option>
        ${typeOptions}
      </select>
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Size</label>
      <select class="size-select" onchange="updateProductId(this)"
              style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);" disabled>
        <option value="">Select size...</option>
      </select>
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Boxes</label>
      <input type="number" name="products[${patientIndex}][${prodIndex}][boxes]" value="1" min="1" class="boxes-input" onchange="updateRowPrice(this)"
             style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Pcs/Box</label>
      <div class="pieces-per-box" style="padding: 0.5rem; font-size: 0.75rem; color: var(--muted); text-align: center;">-</div>
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Price/Box</label>
      <div class="price-per-box" style="padding: 0.5rem; font-weight: 600; font-size: 0.875rem;">-</div>
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Total</label>
      <div class="row-total" style="padding: 0.5rem; font-weight: 700; color: var(--brand); font-size: 0.875rem;">-</div>
    </div>
    <div>
      <button type="button" onclick="removeRow(this)"
              style="background: var(--error); color: white; border: none; border-radius: var(--radius); width: 32px; height: 32px; cursor: pointer;">×</button>
    </div>
  `;

  container.appendChild(row);
}

function addOfficeStockRow() {
  const container = document.getElementById('office-stock-products');
  const prodIndex = Date.now();

  const row = document.createElement('div');
  row.className = 'product-row';
  row.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 0.75rem; align-items: end; padding: 0.75rem; background: var(--bg-gray); border-radius: var(--radius); margin-bottom: 0.5rem;';

  row.innerHTML = `
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Product</label>
      <select name="products[0][${prodIndex}][product_id]"
              style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
        <option value="">Select product...</option>
        ${Object.values(productCatalog).map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
      </select>
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Boxes</label>
      <input type="number" name="products[0][${prodIndex}][boxes]" value="1" min="1"
             style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Price/Box</label>
      <div style="padding: 0.5rem; font-weight: 600;">-</div>
    </div>
    <div>
      <button type="button" onclick="this.parentElement.parentElement.remove()"
              style="background: var(--error); color: white; border: none; border-radius: var(--radius); width: 32px; height: 32px; cursor: pointer;">×</button>
    </div>
  `;

  container.appendChild(row);
}

// Initialize existing rows with saved data
function initializeExistingRow(row) {
  const productIdInput = row.querySelector('.product-id-input');
  const typeSelect = row.querySelector('.product-type-select');
  const sizeSelect = row.querySelector('.size-select');

  const productId = productIdInput?.value;

  if (productId && productCatalog[productId]) {
    const product = productCatalog[productId];

    // Find which type and size this product belongs to
    let foundType = null;
    let foundSize = null;

    for (const [type, sizes] of Object.entries(productsByType)) {
      for (const [size, prod] of Object.entries(sizes)) {
        if (prod.id === productId) {
          foundType = type;
          foundSize = size;
          break;
        }
      }
      if (foundType) break;
    }

    if (foundType && foundSize) {
      // Populate product type dropdown
      const productTypes = Object.keys(productsByType).sort();
      typeSelect.innerHTML = '<option value="">Select type...</option>';
      productTypes.forEach(type => {
        const option = document.createElement('option');
        option.value = type;
        option.textContent = type;
        option.selected = (type === foundType);
        typeSelect.appendChild(option);
      });

      // Populate size dropdown
      sizeSelect.innerHTML = '<option value="">Select size...</option>';
      sizeSelect.disabled = false;
      Object.keys(productsByType[foundType]).sort().forEach(size => {
        const option = document.createElement('option');
        option.value = size;
        option.textContent = size;
        option.selected = (size === foundSize);
        sizeSelect.appendChild(option);
      });

      // Update price display
      updateRowPrice(row);
    }
  } else {
    // New row - populate type dropdown
    const productTypes = Object.keys(productsByType).sort();
    typeSelect.innerHTML = '<option value="">Select type...</option>';
    productTypes.forEach(type => {
      const option = document.createElement('option');
      option.value = type;
      option.textContent = type;
      typeSelect.appendChild(option);
    });
  }
}

// Add initial product row for each patient if none exist
document.addEventListener('DOMContentLoaded', function() {
  <?php if ($isOfficeStock): ?>
    // Add initial office stock row if none exist
    if (document.querySelectorAll('#office-stock-products .product-row').length === 0) {
      addOfficeStockRow();
    }
  <?php else: ?>
    // Add initial patient product rows
    <?php foreach ($patients as $patIndex => $patient): ?>
      const existingRows<?= $patIndex ?> = document.querySelectorAll('#patient-<?= $patIndex ?>-products .product-row');
      if (existingRows<?= $patIndex ?>.length === 0) {
        addProductRow(<?= $patIndex ?>);
      } else {
        // Initialize existing rows with cascading dropdowns
        existingRows<?= $patIndex ?>.forEach(row => initializeExistingRow(row));
        // Calculate initial totals for existing rows
        updatePatientTotal(<?= $patIndex ?>);
      }
    <?php endforeach; ?>
  <?php endif; ?>
});
</script>
