<?php
/**
 * Wholesale Ordering System - Redesigned for Intuitive UX
 * Step 1: Patient Information (full details)
 * Step 2: Product Assignment (columns per product)
 * Step 3: Review & Submit
 */

// Start session for multi-step workflow
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// This file is included by portal/index.php
// index.php handles authentication - $user and $pdo are available
global $pdo, $user;

// Safety check (should never happen since index.php handles auth)
if (!isset($user) || !is_array($user) || !isset($user['id'])) {
  echo '<div style="padding: 2rem; text-align: center;">Please log in to access wholesale ordering.</div>';
  return;
}

$userId = $user['id'];
$step = $_GET['step'] ?? '1'; // 1=patients, 2=products, 3=review

// Fetch available products for Step 2
$products = $pdo->query("SELECT * FROM products WHERE active = true ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Load search-helpers for address autocomplete -->
<script src="/portal/search-helpers.js"></script>

<style>
:root {
  --brand: #10b981;
  --brand-dark: #059669;
  --ink: #1e293b;
  --muted: #64748b;
  --border: #e2e8f0;
  --bg: #f8fafc;
  --radius: 8px;
  --ring: rgba(16, 185, 129, 0.2);
}

.wholesale-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
}

.step-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  margin-bottom: 3rem;
}

.step-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.step-number {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: var(--bg);
  border: 2px solid var(--border);
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
}

.step-item.active .step-number {
  background: var(--brand);
  border-color: var(--brand);
  color: white;
}

.step-item.completed .step-number {
  background: var(--brand);
  border-color: var(--brand);
  color: white;
}

.step-arrow {
  color: var(--muted);
  font-size: 1.25rem;
}

/* Match portal button styles exactly */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  border-radius: var(--radius);
  padding: 0.4375rem 0.875rem;
  font-size: 0.875rem;
  font-weight: 500;
  transition: all 0.15s ease;
  border: 1px solid var(--border);
  background: #ffffff;
  color: var(--ink);
  cursor: pointer;
  text-decoration: none;
}

.btn:hover {
  background: #f9fafb;
  border-color: var(--muted);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--brand);
  color: #ffffff;
  border-color: var(--brand);
}

.btn-primary:hover:not(:disabled) {
  background: var(--brand-dark);
  border-color: var(--brand-dark);
}

.btn-secondary {
  background: white;
  color: var(--ink);
  border-color: var(--border);
}

.patient-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.patient-card {
  background: white;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1rem;
}

.patient-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.75rem;
}

.patient-name {
  font-weight: 600;
  color: var(--ink);
  font-size: 1rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--ink);
  margin-bottom: 0.375rem;
}

.form-control {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  font-size: 0.875rem;
  transition: border-color 0.15s ease;
}

.form-control:focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px var(--ring);
}

.product-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: var(--radius);
  overflow: hidden;
  border: 1px solid var(--border);
}

.product-table thead {
  background: var(--bg);
}

.product-table th {
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--muted);
  text-transform: uppercase;
  border-bottom: 1px solid var(--border);
}

.product-table td {
  padding: 1rem;
  border-bottom: 1px solid var(--border);
}

.product-table tbody tr:last-child td {
  border-bottom: none;
}

.quantity-input {
  width: 80px;
  padding: 0.375rem 0.5rem;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  text-align: center;
  font-size: 0.875rem;
}

.form-actions {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid var(--border);
}

.cost-summary {
  background: white;
  border: 2px solid var(--brand);
  border-radius: var(--radius);
  padding: 1.5rem;
  margin-bottom: 2rem;
}

.cost-summary-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
}

.cost-summary-total {
  border-top: 2px solid var(--border);
  padding-top: 1rem;
  margin-top: 1rem;
  font-size: 1.25rem;
  font-weight: 700;
}

.remove-btn {
  background: transparent;
  border: none;
  color: #ef4444;
  cursor: pointer;
  font-size: 1.25rem;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  transition: background 0.15s ease;
}

.remove-btn:hover {
  background: #fee2e2;
}
</style>

<div class="wholesale-container">
  <!-- Step Indicator -->
  <div class="step-indicator">
    <div class="step-item <?= $step == '1' ? 'active' : ($step > '1' ? 'completed' : '') ?>">
      <div class="step-number">1</div>
      <span style="font-size: 0.875rem; font-weight: 500;">Patients</span>
    </div>
    <div class="step-arrow">→</div>
    <div class="step-item <?= $step == '2' ? 'active' : ($step > '2' ? 'completed' : '') ?>">
      <div class="step-number">2</div>
      <span style="font-size: 0.875rem; font-weight: 500;">Products</span>
    </div>
    <div class="step-arrow">→</div>
    <div class="step-item <?= $step == '3' ? 'active' : '' ?>">
      <div class="step-number">3</div>
      <span style="font-size: 0.875rem; font-weight: 500;">Review</span>
    </div>
  </div>

  <?php if ($step == '1'): ?>
    <!-- STEP 1: Patient Information -->
    <div style="margin-bottom: 2rem;">
      <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">Patient Information</h2>
      <p style="color: var(--muted); font-size: 0.875rem;">Add patients who will receive products in this wholesale order.</p>
    </div>

    <form id="patients-form" method="POST" action="?page=wholesale&step=2">
      <div style="overflow-x: auto; margin-bottom: 1.5rem;">
        <table class="patient-table" style="width: 100%; border-collapse: collapse; background: white; border: 1px solid var(--border); border-radius: 8px;">
          <thead>
            <tr style="background-color: #f8f9fa; border-bottom: 2px solid var(--border);">
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--ink);">First Name</th>
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--ink);">Last Name</th>
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--ink);">Phone</th>
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--ink);">Address</th>
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--ink);">Delivery</th>
              <th style="padding: 0.75rem; text-align: center; font-weight: 600; color: var(--ink); width: 60px;">Action</th>
            </tr>
          </thead>
          <tbody id="patient-rows">
            <!-- Patient rows will be added here dynamically -->
          </tbody>
        </table>
      </div>

      <button type="button" class="btn" onclick="addPatientRow()" style="margin-bottom: 1.5rem;">
        <span style="font-size: 1.25rem;">+</span> Add Patient
      </button>

      <div class="form-actions">
        <a href="?page=wholesale" class="btn-secondary btn">Cancel</a>
        <button type="submit" class="btn btn-primary" id="next-btn" disabled>Next: Select Products →</button>
      </div>
    </form>

    <script>
    let patientRowCount = 0;

    function addPatientRow() {
      const tbody = document.getElementById('patient-rows');
      const index = patientRowCount++;

      const row = document.createElement('tr');
      row.dataset.index = index;
      row.style.borderBottom = '1px solid var(--border)';
      row.innerHTML = `
        <td style="padding: 0.75rem;">
          <input type="text"
                 name="patients[${index}][first_name]"
                 class="form-control"
                 placeholder="First name"
                 required
                 style="min-width: 120px;">
        </td>
        <td style="padding: 0.75rem;">
          <input type="text"
                 name="patients[${index}][last_name]"
                 class="form-control"
                 placeholder="Last name"
                 required
                 style="min-width: 120px;">
        </td>
        <td style="padding: 0.75rem;">
          <input type="tel"
                 name="patients[${index}][phone]"
                 class="form-control phone-input"
                 placeholder="(555) 123-4567"
                 required
                 style="min-width: 140px;">
        </td>
        <td style="padding: 0.75rem;">
          <input type="text"
                 id="address-${index}"
                 name="patients[${index}][address]"
                 class="form-control address-input"
                 placeholder="Start typing address..."
                 required
                 style="min-width: 250px;">
        </td>
        <td style="padding: 0.75rem;">
          <select name="patients[${index}][delivery_mode]"
                  class="form-control"
                  required
                  style="min-width: 160px;">
            <option value="">Select...</option>
            <option value="ship_to_patient">Ship to Patient</option>
            <option value="ship_to_office">Ship to Office</option>
          </select>
        </td>
        <td style="padding: 0.75rem; text-align: center;">
          <button type="button"
                  class="remove-btn"
                  onclick="removePatientRow(${index})"
                  title="Remove patient"
                  style="background: #dc3545; color: white; border: none; border-radius: 4px; width: 32px; height: 32px; cursor: pointer; font-size: 1.25rem; line-height: 1;">×</button>
        </td>
      `;

      tbody.appendChild(row);

      // Initialize phone formatting
      const phoneInput = row.querySelector('.phone-input');
      phoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 0) {
          if (value.length <= 3) {
            value = `(${value}`;
          } else if (value.length <= 6) {
            value = `(${value.slice(0,3)}) ${value.slice(3)}`;
          } else {
            value = `(${value.slice(0,3)}) ${value.slice(3,6)}-${value.slice(6,10)}`;
          }
        }
        e.target.value = value;
      });

      // Initialize address autocomplete
      if (typeof initAddressAutocomplete === 'function') {
        initAddressAutocomplete(`address-${index}`, (addressData) => {
          console.log('Address selected:', addressData);
        });
      }

      validatePatientForm();
    }

    function removePatientRow(index) {
      const row = document.querySelector(`tr[data-index="${index}"]`);
      if (row) {
        row.remove();
        validatePatientForm();
      }
    }

    function validatePatientForm() {
      const rows = document.querySelectorAll('#patient-rows tr');
      const nextBtn = document.getElementById('next-btn');
      nextBtn.disabled = rows.length === 0;
    }

    // Add first patient row automatically
    addPatientRow();
    </script>

  <?php elseif ($step == '2'): ?>
    <!-- STEP 2: Product Assignment - Patient-Centric with Grouped Products -->
    <?php
    // Get patients from POST or session
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patients'])) {
      $_SESSION['wholesale_patients'] = $_POST['patients'];
    }

    $patients = $_SESSION['wholesale_patients'] ?? [];

    if (empty($patients)):
    ?>
      <div style="text-align: center; padding: 3rem;">
        <p style="color: var(--muted); margin-bottom: 1rem;">No patients found. Please add patients first.</p>
        <a href="?page=wholesale&step=1" class="btn btn-primary">← Back to Patient Entry</a>
      </div>
    <?php else:
      // Extract core product names for simplified grouping
      // e.g., "AlgiHeal Alginate Dressing" -> "AlgiHeal Alginate"
      $productGroups = [];
      $coreProductNames = [];

      foreach ($products as $product) {
        $fullName = $product['name'];

        // Extract core product name (remove generic suffixes)
        $coreName = preg_replace('/(Dressing|Powder|Foam|Hydrogel|Kit)$/i', '', $fullName);
        $coreName = trim($coreName);

        // Store mapping
        if (!isset($coreProductNames[$coreName])) {
          $coreProductNames[$coreName] = $fullName;
        }

        if (!isset($productGroups[$coreName])) {
          $productGroups[$coreName] = [];
        }
        $productGroups[$coreName][] = $product;
      }

      // Sort core product names alphabetically
      ksort($productGroups);
    ?>

    <div style="margin-bottom: 1.5rem;">
      <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--ink); margin-bottom: 0.25rem;">Assign Products</h2>
      <p style="color: var(--muted); font-size: 0.8rem;">Select product and size, then enter quantity.</p>
    </div>

    <form method="POST" action="?page=wholesale&step=3">
      <!-- Hidden patient data -->
      <?php foreach ($patients as $index => $patient): ?>
        <input type="hidden" name="patients[<?= $index ?>][first_name]" value="<?= htmlspecialchars($patient['first_name'] ?? '') ?>">
        <input type="hidden" name="patients[<?= $index ?>][last_name]" value="<?= htmlspecialchars($patient['last_name'] ?? '') ?>">
        <input type="hidden" name="patients[<?= $index ?>][phone]" value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
        <input type="hidden" name="patients[<?= $index ?>][address]" value="<?= htmlspecialchars($patient['address'] ?? '') ?>">
        <input type="hidden" name="patients[<?= $index ?>][delivery_mode]" value="<?= htmlspecialchars($patient['delivery_mode'] ?? '') ?>">
      <?php endforeach; ?>

      <!-- Patient-Centric Product Assignment -->
      <div style="display: flex; flex-direction: column; gap: 1.25rem; margin-bottom: 1.5rem;">
        <?php foreach ($patients as $patIndex => $patient): ?>
          <div class="patient-product-card" style="background: white; border: 1px solid var(--border); border-radius: 6px; padding: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
              <h3 style="font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0;">
                <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
              </h3>
              <div style="color: var(--muted); font-size: 0.75rem;">
                <?= $patIndex + 1 ?>/<?= count($patients) ?>
              </div>
            </div>

            <!-- Product assignment rows for this patient -->
            <div class="product-rows" id="patient-<?= $patIndex ?>-products">
              <!-- Product rows will be added here dynamically -->
            </div>

            <button type="button" class="btn" onclick="addProductRow(<?= $patIndex ?>)" style="margin-top: 0.75rem; padding: 0.5rem 1rem; font-size: 0.875rem;">
              <span style="font-size: 1rem;">+</span> Add Product
            </button>

            <!-- Patient subtotal -->
            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border); text-align: right;">
              <span style="font-weight: 600; color: var(--ink); font-size: 0.875rem;">Subtotal: </span>
              <span id="patient-<?= $patIndex ?>-total" style="font-size: 1rem; font-weight: 700; color: var(--brand);">$0.00</span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Grand Total Summary -->
      <div class="cost-summary" style="background: #f8f9fa; border: 1px solid var(--border); border-radius: 6px; padding: 1rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
          <h3 style="font-size: 1rem; font-weight: 600; margin: 0;">Order Summary</h3>
        </div>
        <div id="summary-details" style="font-size: 0.875rem; margin-bottom: 0.75rem;">
          <p style="color: var(--muted); margin: 0;">Add products to see breakdown</p>
        </div>
        <div style="padding-top: 0.75rem; border-top: 1px solid var(--border);">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 1rem; font-weight: 700;">Grand Total:</span>
            <span id="grand-total" style="font-size: 1.25rem; font-weight: 700; color: var(--brand);">$0.00</span>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <a href="?page=wholesale&step=1" class="btn btn-secondary">← Back to Patients</a>
        <button type="submit" class="btn btn-primary" id="proceed-btn" disabled>Next: Review Order →</button>
      </div>
    </form>

    <script>
    // Product catalog with grouped sizes
    const productCatalog = <?= json_encode($productGroups) ?>;
    const productData = <?= json_encode(array_column($products, null, 'id')) ?>;

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
      row.style.cssText = 'display: grid; grid-template-columns: 2fr 1.25fr 1fr 50px; gap: 0.75rem; align-items: end; padding: 0.75rem; background: #f8f9fa; border-radius: 4px; margin-bottom: 0.5rem;';

      row.innerHTML = `
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem; color: var(--ink);">Product</label>
          <select class="form-control product-selector" onchange="updateSizeOptions(${patientIndex}, ${rowIndex})" required style="font-size: 0.875rem;">
            <option value="">Select...</option>
            ${Object.keys(productCatalog).map(category =>
              `<option value="${category}">${category}</option>`
            ).join('')}
          </select>
        </div>

        <div class="size-selector-container" style="display: none;">
          <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem; color: var(--ink);">Size</label>
          <select class="form-control size-selector" onchange="updateProductSelection(${patientIndex}, ${rowIndex})" required style="font-size: 0.875rem;">
            <option value="">Select...</option>
          </select>
        </div>

        <div class="quantity-container" style="display: none;">
          <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem; color: var(--ink);">Boxes</label>
          <input type="number" class="form-control quantity-input" min="1" max="100" placeholder="1" onchange="calculatePatientTotal(${patientIndex})" required style="font-size: 0.875rem;">
          <div class="price-info" style="font-size: 0.7rem; color: var(--muted); margin-top: 0.15rem;"></div>
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
        option.textContent = product.size || 'Standard';
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

      const selectedProductId = sizeSelect.value;

      if (!selectedProductId) {
        quantityContainer.style.display = 'none';
        return;
      }

      const product = productData[selectedProductId];
      const pricePerPiece = parseFloat(product.price_wholesale || 0);
      const piecesPerBox = parseInt(product.pieces_per_box || 10);
      const pricePerBox = pricePerPiece * piecesPerBox;

      priceInfo.textContent = `$${pricePerBox.toFixed(2)}/box (${piecesPerBox} pieces)`;
      productIdInput.value = selectedProductId;
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
        const productId = row.querySelector('.product-id-input').value;
        const quantityInput = row.querySelector('.quantity-input');
        const boxesInput = row.querySelector('.boxes-input');
        const boxes = parseInt(quantityInput.value) || 0;

        if (productId && boxes > 0) {
          const product = productData[productId];
          const pricePerPiece = parseFloat(product.price_wholesale || 0);
          const piecesPerBox = parseInt(product.pieces_per_box || 10);
          const pricePerBox = pricePerPiece * piecesPerBox;
          patientTotal += boxes * pricePerBox;
          boxesInput.value = boxes;
        }
      });

      document.getElementById(`patient-${patientIndex}-total`).textContent = '$' + patientTotal.toFixed(2);
      calculateGrandTotal();
    }

    function calculateGrandTotal() {
      let grandTotal = 0;
      let hasAnyProducts = false;
      const summaryDetails = [];

      <?php foreach ($patients as $patIndex => $patient): ?>
        const patient<?= $patIndex ?>Total = parseFloat(document.getElementById('patient-<?= $patIndex ?>-total').textContent.replace('$', ''));
        if (patient<?= $patIndex ?>Total > 0) {
          hasAnyProducts = true;
          summaryDetails.push({
            name: '<?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>',
            total: patient<?= $patIndex ?>Total
          });
        }
        grandTotal += patient<?= $patIndex ?>Total;
      <?php endforeach; ?>

      // Update summary
      const summaryDiv = document.getElementById('summary-details');
      if (summaryDetails.length === 0) {
        summaryDiv.innerHTML = '<p style="color: var(--muted);">Add products to see cost breakdown</p>';
      } else {
        let html = '<div style="display: flex; flex-direction: column; gap: 0.5rem;">';
        summaryDetails.forEach(item => {
          html += `
            <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
              <span>${item.name}</span>
              <span style="font-weight: 600;">$${item.total.toFixed(2)}</span>
            </div>
          `;
        });
        html += '</div>';
        summaryDiv.innerHTML = html;
      }

      document.getElementById('grand-total').textContent = '$' + grandTotal.toFixed(2);
      document.getElementById('proceed-btn').disabled = !hasAnyProducts;
    }

    // Initialize - add first product row for each patient
    <?php foreach ($patients as $patIndex => $patient): ?>
      addProductRow(<?= $patIndex ?>);
    <?php endforeach; ?>
    </script>

    <?php endif; ?>

  <?php elseif ($step == '3'): ?>
    <!-- STEP 3: Review & Submit -->
    <div style="text-align: center; padding: 3rem;">
      <h2 style="font-size: 1.5rem; margin-bottom: 1rem;">Review & Submit</h2>
      <p style="color: var(--muted); margin-bottom: 2rem;">Order review and PDF generation will be implemented next.</p>
      <div class="form-actions" style="justify-content: center;">
        <a href="?page=wholesale&step=2" class="btn btn-secondary">← Back to Products</a>
        <button class="btn btn-primary">Submit Order</button>
      </div>
    </div>

  <?php endif; ?>
</div>
