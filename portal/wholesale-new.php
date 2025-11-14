<?php
/**
 * New Wholesale Ordering System
 * Multi-patient, multi-product workflow with PDF generation
 */

// Start session for multi-step workflow
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// This file is included by portal/index.php
// index.php handles authentication - we can trust $cu is valid
global $pdo, $cu;

$userId = $cu['id'];
$activeTab = $_GET['tab'] ?? 'create';
$step = $_GET['step'] ?? '1'; // Multi-step workflow: 1=patients, 2=products, 3=review

// Load portal CSS variables for consistent styling
$portalStyles = "
  :root {
    --brand: #10b981;
    --brand-dark: #059669;
    --ink: #1e293b;
    --muted: #64748b;
    --border: #e2e8f0;
    --bg: #f8fafc;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
  }
";

// Fetch products for product selection
$productsStmt = $pdo->query("
  SELECT id, name, size, price_wholesale, pieces_per_box
  FROM products
  WHERE active = true
  ORDER BY name ASC, size ASC
");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's wholesale orders for manage tab
if ($activeTab === 'manage') {
  $ordersStmt = $pdo->prepare("
    SELECT
      o.id,
      o.created_at,
      o.product,
      o.shipments_remaining,
      o.product_price as unit_price,
      o.status,
      o.review_status,
      o.delivery_mode,
      p.first_name as pat_first,
      p.last_name as pat_last,
      p.mrn,
      pr.pieces_per_box,
      pr.price_wholesale,
      CONCAT_WS(', ', o.shipping_address, o.shipping_city, o.shipping_state, o.shipping_zip) as shipping_address
    FROM orders o
    JOIN patients p ON o.patient_id = p.id
    LEFT JOIN products pr ON o.product_id = pr.id
    WHERE o.user_id = ?
      AND o.billed_by = 'practice_dme'
      AND o.review_status != 'draft'
    ORDER BY o.created_at DESC
  ");
  $ordersStmt->execute([$userId]);
  $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

  // Calculate totals
  $totalOrders = count($orders);
  $totalSpent = 0;
  $pendingOrders = 0;
  $totalOwed = 0;

  foreach ($orders as $order) {
    $boxes = (int)($order['shipments_remaining'] ?? 0);
    $pieces_per_box = (int)($order['pieces_per_box'] ?? 10);
    $unit_price = (float)($order['unit_price'] ?? $order['price_wholesale'] ?? 0);
    $orderValue = $boxes * ($unit_price * $pieces_per_box);
    $totalSpent += $orderValue;

    if (in_array($order['status'], ['submitted', 'pending', 'awaiting_approval', 'approved'])) {
      $pendingOrders++;
      $totalOwed += $orderValue;
    }
  }
}
?>

<style>
<?= $portalStyles ?>

.wholesale-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
}

.tab-nav {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
  border-bottom: 2px solid var(--border);
}

.tab-button {
  padding: 1rem 1.5rem;
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  color: var(--muted);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 1rem;
}

.tab-button:hover {
  color: var(--ink);
}

.tab-button.active {
  color: var(--brand);
  border-bottom-color: var(--brand);
}

/* Match portal stat cards aesthetic */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  border: 1px solid var(--border);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: all 0.2s;
}

.stat-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.stat-label {
  font-size: 0.875rem;
  color: var(--muted);
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--ink);
  line-height: 1;
}

.stat-value.success { color: var(--success); }
.stat-value.warning { color: var(--warning); }
.stat-value.brand { color: var(--brand); }

/* Step indicator */
.step-indicator {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 2rem;
  gap: 1rem;
}

.step {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.step-number {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--bg);
  border: 2px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  color: var(--muted);
  transition: all 0.2s;
}

.step.active .step-number {
  background: var(--brand);
  border-color: var(--brand);
  color: white;
}

.step.completed .step-number {
  background: var(--success);
  border-color: var(--success);
  color: white;
}

.step-label {
  font-weight: 600;
  color: var(--muted);
}

.step.active .step-label {
  color: var(--ink);
}

.step-arrow {
  color: var(--border);
  font-size: 1.5rem;
}

/* Patient table */
.patient-table-container {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  border: 1px solid var(--border);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.patient-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1rem;
}

.patient-table th {
  text-align: left;
  padding: 0.75rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--muted);
  border-bottom: 2px solid var(--border);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.patient-table td {
  padding: 0.75rem;
  border-bottom: 1px solid var(--border);
}

.patient-table input,
.patient-table select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.patient-table input:focus,
.patient-table select:focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.remove-row-btn {
  background: var(--error);
  color: white;
  border: none;
  border-radius: 8px;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.remove-row-btn:hover {
  background: #dc2626;
}

.add-row-btn {
  background: var(--brand);
  color: white;
  border: none;
  border-radius: 12px;
  padding: 0.75rem 1.5rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

.add-row-btn:hover {
  background: var(--brand-dark);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-primary {
  background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
  color: white;
  border: none;
  border-radius: 12px;
  padding: 1rem 2rem;
  font-weight: 600;
  cursor: pointer;
  font-size: 1rem;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
  transition: all 0.2s;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.btn-secondary {
  background: white;
  color: var(--ink);
  border: 2px solid var(--border);
  border-radius: 12px;
  padding: 1rem 2rem;
  font-weight: 600;
  cursor: pointer;
  font-size: 1rem;
  transition: all 0.2s;
}

.btn-secondary:hover {
  border-color: var(--brand);
  color: var(--brand);
}

.form-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  margin-top: 2rem;
}

.alert {
  padding: 1rem 1.5rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  font-weight: 500;
}

.alert-success {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #86efac;
}

.alert-error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fca5a5;
}

/* Product selection */
.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.product-card {
  background: white;
  border: 2px solid var(--border);
  border-radius: 12px;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.2s;
}

.product-card:hover {
  border-color: var(--brand);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
}

.product-card.selected {
  border-color: var(--brand);
  background: rgba(16, 185, 129, 0.05);
}

.product-name {
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 0.5rem;
}

.product-price {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--brand);
}

.quantity-input {
  margin-top: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.quantity-input input {
  width: 80px;
  padding: 0.5rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  text-align: center;
  font-weight: 600;
}
</style>

<div class="wholesale-container">
  <!-- Tab Navigation -->
  <div class="tab-nav">
    <button class="tab-button <?= $activeTab === 'create' ? 'active' : '' ?>"
            onclick="window.location.href='?page=wholesale&tab=create'">
      New Order
    </button>
    <button class="tab-button <?= $activeTab === 'manage' ? 'active' : '' ?>"
            onclick="window.location.href='?page=wholesale&tab=manage'">
      My Orders
    </button>
  </div>

  <?php if ($activeTab === 'create'): ?>
    <!-- CREATE TAB - Multi-step workflow -->

    <!-- Step Indicator -->
    <div class="step-indicator">
      <div class="step <?= $step == '1' ? 'active' : ($step > '1' ? 'completed' : '') ?>">
        <div class="step-number">1</div>
        <div class="step-label">Add Patients</div>
      </div>
      <div class="step-arrow">→</div>
      <div class="step <?= $step == '2' ? 'active' : ($step > '2' ? 'completed' : '') ?>">
        <div class="step-number">2</div>
        <div class="step-label">Select Products</div>
      </div>
      <div class="step-arrow">→</div>
      <div class="step <?= $step == '3' ? 'active' : '' ?>">
        <div class="step-number">3</div>
        <div class="step-label">Review & Submit</div>
      </div>
    </div>

    <?php if ($step == '1'): ?>
      <!-- STEP 1: Add Patients -->
      <div class="patient-table-container">
        <h2 style="margin: 0 0 1.5rem 0; font-size: 1.5rem; color: var(--ink);">Add Patients</h2>
        <p style="color: var(--muted); margin-bottom: 2rem;">
          Add patients who will receive products in this wholesale order. You can add multiple patients at once.
        </p>

        <form id="patients-form" method="POST" action="?page=wholesale&tab=create&step=2">
          <table class="patient-table" id="patient-table">
            <thead>
              <tr>
                <th style="width: 150px;">First Name *</th>
                <th style="width: 150px;">Last Name *</th>
                <th style="width: 150px;">Phone Number *</th>
                <th style="width: 300px;">Address *</th>
                <th style="width: 150px;">Delivery Method *</th>
                <th style="width: 80px;">Actions</th>
              </tr>
            </thead>
            <tbody id="patient-rows">
              <!-- Initial row -->
              <tr class="patient-row">
                <td><input type="text" name="patients[0][first_name]" required placeholder="John"></td>
                <td><input type="text" name="patients[0][last_name]" required placeholder="Doe"></td>
                <td><input type="tel" name="patients[0][phone]" required placeholder="(555) 123-4567" class="phone-input"></td>
                <td><input type="text" name="patients[0][address]" required placeholder="Start typing address..." class="address-autocomplete"></td>
                <td>
                  <select name="patients[0][delivery_mode]" required>
                    <option value="">Choose...</option>
                    <option value="ship_to_patient">Ship to Patient</option>
                    <option value="ship_to_office">Ship to Office</option>
                  </select>
                </td>
                <td>
                  <button type="button" class="remove-row-btn" onclick="removePatientRow(this)" disabled>Remove</button>
                </td>
              </tr>
            </tbody>
          </table>

          <button type="button" class="add-row-btn" onclick="addPatientRow()">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Another Patient
          </button>

          <div class="form-actions">
            <button type="submit" class="btn-primary">
              Next: Select Products →
            </button>
          </div>
        </form>
      </div>

      <script>
      let patientRowCount = 1;

      function addPatientRow() {
        const tbody = document.getElementById('patient-rows');
        const row = document.createElement('tr');
        row.className = 'patient-row';
        row.innerHTML = `
          <td><input type="text" name="patients[${patientRowCount}][first_name]" required placeholder="John"></td>
          <td><input type="text" name="patients[${patientRowCount}][last_name]" required placeholder="Doe"></td>
          <td><input type="tel" name="patients[${patientRowCount}][phone]" required placeholder="(555) 123-4567" class="phone-input"></td>
          <td><input type="text" name="patients[${patientRowCount}][address]" required placeholder="Start typing address..." class="address-autocomplete"></td>
          <td>
            <select name="patients[${patientRowCount}][delivery_mode]" required>
              <option value="">Choose...</option>
              <option value="ship_to_patient">Ship to Patient</option>
              <option value="ship_to_office">Ship to Office</option>
            </select>
          </td>
          <td>
            <button type="button" class="remove-row-btn" onclick="removePatientRow(this)">Remove</button>
          </td>
        `;
        tbody.appendChild(row);
        patientRowCount++;

        // Re-initialize phone formatting and address autocomplete for new row
        initializeRowFeatures(row);
        updateRemoveButtons();
      }

      function removePatientRow(btn) {
        btn.closest('tr').remove();
        updateRemoveButtons();
      }

      function updateRemoveButtons() {
        const rows = document.querySelectorAll('.patient-row');
        rows.forEach((row, index) => {
          const removeBtn = row.querySelector('.remove-row-btn');
          removeBtn.disabled = rows.length === 1;
        });
      }

      function initializeRowFeatures(row) {
        // Phone number formatting
        const phoneInput = row.querySelector('.phone-input');
        if (phoneInput) {
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
        }

        // Address autocomplete - will be implemented with Google Places API
        const addressInput = row.querySelector('.address-autocomplete');
        if (addressInput && typeof initAddressAutocomplete === 'function') {
          initAddressAutocomplete(addressInput);
        }
      }

      // Initialize features for initial row
      document.addEventListener('DOMContentLoaded', function() {
        const initialRow = document.querySelector('.patient-row');
        if (initialRow) {
          initializeRowFeatures(initialRow);
        }
        updateRemoveButtons();
      });
      </script>

    <?php elseif ($step == '2'): ?>
      <!-- STEP 2: Select Products -->
      <?php
      // Retrieve patient data from session or POST
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patients'])) {
        $_SESSION['wholesale_patients'] = $_POST['patients'];
      }

      $patients = $_SESSION['wholesale_patients'] ?? [];
      $patientCount = count($patients);

      if ($patientCount === 0) {
        echo '<div class="alert alert-error">No patients found. Please go back and add patients.</div>';
        echo '<button type="button" class="btn-secondary" onclick="window.location.href=\'?page=wholesale&tab=create&step=1\'">← Back to Patients</button>';
      } else {
      ?>

      <div class="patient-table-container">
        <h2 style="margin: 0 0 0.5rem 0; font-size: 1.5rem; color: var(--ink);">Select Products</h2>
        <p style="color: var(--muted); margin-bottom: 2rem;">
          Choose products and quantities for your <?= $patientCount ?> patient<?= $patientCount > 1 ? 's' : '' ?>.
          You can assign different products to each patient.
        </p>

        <form id="products-form" method="POST" action="?page=wholesale&tab=create&step=3">
          <!-- Hidden patient data -->
          <?php foreach ($patients as $index => $patient): ?>
            <input type="hidden" name="patients[<?= $index ?>][first_name]" value="<?= htmlspecialchars($patient['first_name'] ?? '') ?>">
            <input type="hidden" name="patients[<?= $index ?>][last_name]" value="<?= htmlspecialchars($patient['last_name'] ?? '') ?>">
            <input type="hidden" name="patients[<?= $index ?>][phone]" value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
            <input type="hidden" name="patients[<?= $index ?>][address]" value="<?= htmlspecialchars($patient['address'] ?? '') ?>">
            <input type="hidden" name="patients[<?= $index ?>][delivery_mode]" value="<?= htmlspecialchars($patient['delivery_mode'] ?? '') ?>">
          <?php endforeach; ?>

          <!-- Product Selection Grid -->
          <div style="margin-bottom: 2rem;">
            <h3 style="font-size: 1.125rem; color: var(--ink); margin-bottom: 1rem;">Available Products</h3>

            <div class="product-grid">
              <?php foreach ($products as $product):
                $pricePerPiece = (float)($product['price_wholesale'] ?? 0);
                $piecesPerBox = (int)($product['pieces_per_box'] ?? 10);
                $pricePerBox = $pricePerPiece * $piecesPerBox;
              ?>
              <div class="product-card" data-product-id="<?= $product['id'] ?>" onclick="toggleProduct(this)">
                <div class="product-name"><?= htmlspecialchars($product['name']) ?> <?= htmlspecialchars($product['size']) ?></div>
                <div style="color: var(--muted); font-size: 0.875rem; margin-bottom: 0.5rem;">
                  <?= $piecesPerBox ?> pieces per box
                </div>
                <div class="product-price">$<?= number_format($pricePerBox, 2) ?>/box</div>
                <div style="font-size: 0.75rem; color: var(--muted);">
                  $<?= number_format($pricePerPiece, 2) ?> per piece
                </div>

                <div class="quantity-input" style="display: none;">
                  <label style="font-size: 0.875rem; color: var(--muted);">Boxes per patient:</label>
                  <input type="number"
                         name="products[<?= $product['id'] ?>][boxes]"
                         min="1"
                         max="100"
                         value="1"
                         class="product-quantity"
                         data-price-per-box="<?= $pricePerBox ?>"
                         data-pieces-per-box="<?= $piecesPerBox ?>"
                         data-price-per-piece="<?= $pricePerPiece ?>"
                         onchange="updateTotalCost()">
                </div>

                <input type="hidden" name="products[<?= $product['id'] ?>][product_id]" value="<?= $product['id'] ?>">
                <input type="hidden" name="products[<?= $product['id'] ?>][name]" value="<?= htmlspecialchars($product['name'] . ' ' . $product['size']) ?>">
                <input type="hidden" name="products[<?= $product['id'] ?>][price_per_piece]" value="<?= $pricePerPiece ?>">
                <input type="hidden" name="products[<?= $product['id'] ?>][pieces_per_box]" value="<?= $piecesPerBox ?>">
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Assignment Options -->
          <div style="background: var(--bg); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="font-size: 1.125rem; color: var(--ink); margin-bottom: 1rem;">Product Assignment</h3>
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
              <input type="radio" name="assignment_mode" value="all_patients" checked onchange="updateAssignmentMode()">
              <span style="font-weight: 500;">Apply selected products to ALL <?= $patientCount ?> patients</span>
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin-top: 0.75rem;">
              <input type="radio" name="assignment_mode" value="custom" onchange="updateAssignmentMode()">
              <span style="font-weight: 500;">Customize products per patient (advanced)</span>
            </label>
          </div>

          <!-- Cost Summary -->
          <div style="background: white; border: 2px solid var(--brand); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="font-size: 1.125rem; color: var(--ink); margin-bottom: 1rem;">Order Summary</h3>
            <div id="cost-breakdown" style="color: var(--muted); margin-bottom: 1rem;">
              <p>Select products to see cost breakdown</p>
            </div>
            <div style="border-top: 2px solid var(--border); padding-top: 1rem; margin-top: 1rem;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 1.25rem; font-weight: 700; color: var(--ink);">Total Cost:</span>
                <span id="total-cost" style="font-size: 2rem; font-weight: 700; color: var(--brand);">$0.00</span>
              </div>
              <div style="font-size: 0.875rem; color: var(--muted); margin-top: 0.5rem;">
                <span id="total-boxes">0</span> boxes × <span id="patient-count"><?= $patientCount ?></span> patients
              </div>
            </div>
          </div>

          <div class="form-actions">
            <button type="button" class="btn-secondary" onclick="window.location.href='?page=wholesale&tab=create&step=1'">
              ← Back to Patients
            </button>
            <button type="submit" class="btn-primary" id="proceed-btn" disabled>
              Next: Review Order →
            </button>
          </div>
        </form>
      </div>

      <script>
      let selectedProducts = new Set();
      const patientCount = <?= $patientCount ?>;

      function toggleProduct(card) {
        const productId = card.dataset.productId;
        const quantityInput = card.querySelector('.quantity-input');
        const quantityField = card.querySelector('.product-quantity');

        if (card.classList.contains('selected')) {
          // Deselect
          card.classList.remove('selected');
          quantityInput.style.display = 'none';
          quantityField.removeAttribute('required');
          selectedProducts.delete(productId);
        } else {
          // Select
          card.classList.add('selected');
          quantityInput.style.display = 'block';
          quantityField.setAttribute('required', 'required');
          selectedProducts.add(productId);
        }

        updateTotalCost();
        updateProceedButton();
      }

      function updateTotalCost() {
        let totalCost = 0;
        let totalBoxes = 0;
        let breakdown = [];

        document.querySelectorAll('.product-card.selected').forEach(card => {
          const input = card.querySelector('.product-quantity');
          const boxes = parseInt(input.value) || 0;
          const pricePerBox = parseFloat(input.dataset.pricePerBox) || 0;
          const productName = card.querySelector('.product-name').textContent;

          const productTotal = boxes * pricePerBox * patientCount;
          totalCost += productTotal;
          totalBoxes += boxes;

          breakdown.push({
            name: productName,
            boxes: boxes,
            pricePerBox: pricePerBox,
            total: productTotal
          });
        });

        // Update display
        document.getElementById('total-cost').textContent = '$' + totalCost.toFixed(2);
        document.getElementById('total-boxes').textContent = totalBoxes;

        // Update breakdown
        const breakdownEl = document.getElementById('cost-breakdown');
        if (breakdown.length === 0) {
          breakdownEl.innerHTML = '<p>Select products to see cost breakdown</p>';
        } else {
          let html = '<div style="display: flex; flex-direction: column; gap: 0.5rem;">';
          breakdown.forEach(item => {
            html += `
              <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                <span>${item.name}: ${item.boxes} boxes × ${patientCount} patients</span>
                <span style="font-weight: 600;">$${item.total.toFixed(2)}</span>
              </div>
            `;
          });
          html += '</div>';
          breakdownEl.innerHTML = html;
        }
      }

      function updateProceedButton() {
        const proceedBtn = document.getElementById('proceed-btn');
        proceedBtn.disabled = selectedProducts.size === 0;
      }

      function updateAssignmentMode() {
        const mode = document.querySelector('input[name="assignment_mode"]:checked').value;
        if (mode === 'custom') {
          alert('Custom assignment per patient will be available in the review step.');
        }
      }
      </script>

      <?php } ?>

    <?php elseif ($step == '3'): ?>
      <!-- STEP 3: Review & Submit (placeholder - will be implemented) -->
      <div class="patient-table-container">
        <h2>Review & Submit</h2>
        <p>Order review and PDF generation will be implemented next.</p>
        <div class="form-actions">
          <button type="button" class="btn-secondary" onclick="window.location.href='?page=wholesale&tab=create&step=2'">
            ← Back to Products
          </button>
          <button type="button" class="btn-primary">
            Submit Order
          </button>
        </div>
      </div>

    <?php endif; ?>

  <?php else: ?>
    <!-- MANAGE TAB - View existing orders -->

    <!-- Summary Cards - Matching portal aesthetic -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">Total Orders</div>
        <div class="stat-value"><?= $totalOrders ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-label">Pending Orders</div>
        <div class="stat-value warning"><?= $pendingOrders ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-label">Total Spent</div>
        <div class="stat-value success">$<?= number_format($totalSpent, 2) ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-label">Balance Owed</div>
        <div class="stat-value brand">$<?= number_format($totalOwed, 2) ?></div>
      </div>
    </div>

    <!-- Orders Table -->
    <div style="background: white; border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);">
      <div style="padding: 1.5rem; border-bottom: 1px solid var(--border);">
        <h2 style="margin: 0; font-size: 1.25rem; color: var(--ink);">Order History</h2>
      </div>

      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
          <thead style="background: var(--bg);">
            <tr>
              <th style="padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase;">Date</th>
              <th style="padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase;">Patient</th>
              <th style="padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase;">Product</th>
              <th style="padding: 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase;">Boxes</th>
              <th style="padding: 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase;">Total</th>
              <th style="padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase;">Status</th>
              <th style="padding: 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
            <tr>
              <td colspan="7" style="padding: 3rem; text-align: center; color: var(--muted);">
                <div style="font-size: 1.125rem; margin-bottom: 0.5rem;">No wholesale orders yet</div>
                <div style="font-size: 0.875rem;">
                  <a href="?page=wholesale&tab=create" style="color: var(--brand); text-decoration: underline;">Create your first wholesale order</a>
                </div>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($orders as $order):
              $boxes = (int)($order['shipments_remaining'] ?? 0);
              $pieces_per_box = (int)($order['pieces_per_box'] ?? 10);
              $unit_price = (float)($order['unit_price'] ?? $order['price_wholesale'] ?? 0);
              $orderValue = $boxes * ($unit_price * $pieces_per_box);

              $statusConfig = match($order['status']) {
                'submitted', 'pending' => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => 'Pending'],
                'awaiting_approval' => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => 'Awaiting Approval'],
                'approved' => ['color' => '#3b82f6', 'bg' => '#dbeafe', 'label' => 'Approved'],
                'in_transit' => ['color' => '#8b5cf6', 'bg' => '#ede9fe', 'label' => 'Shipped'],
                'delivered' => ['color' => '#10b981', 'bg' => '#d1fae5', 'label' => 'Delivered'],
                'cancelled' => ['color' => '#ef4444', 'bg' => '#fee2e2', 'label' => 'Cancelled'],
                'rejected' => ['color' => '#ef4444', 'bg' => '#fee2e2', 'label' => 'Rejected'],
                default => ['color' => '#64748b', 'bg' => '#f1f5f9', 'label' => ucfirst($order['status'])]
              };

              $canEdit = !in_array($order['review_status'], ['approved', 'rejected']) && !isset($order['locked_at']);
            ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 1rem; font-size: 0.875rem; color: var(--ink);">
                <?= date('m/d/Y', strtotime($order['created_at'])) ?>
                <div style="font-size: 0.75rem; color: var(--muted);"><?= date('g:i A', strtotime($order['created_at'])) ?></div>
              </td>
              <td style="padding: 1rem; font-size: 0.875rem;">
                <div style="font-weight: 500; color: var(--ink);">
                  <?= htmlspecialchars(trim(($order['pat_first'] ?? '') . ' ' . ($order['pat_last'] ?? ''))) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--muted);">MRN: <?= htmlspecialchars($order['mrn'] ?? 'N/A') ?></div>
              </td>
              <td style="padding: 1rem; font-size: 0.875rem; color: var(--muted); max-width: 200px;">
                <?= htmlspecialchars($order['product'] ?? '') ?>
              </td>
              <td style="padding: 1rem; text-align: right; font-size: 0.875rem;">
                <div style="font-weight: 600; color: var(--ink);"><?= $boxes ?></div>
                <div style="font-size: 0.75rem; color: var(--muted);"><?= $pieces_per_box ?> pcs/box</div>
              </td>
              <td style="padding: 1rem; text-align: right; font-size: 0.875rem; font-weight: 600; color: var(--ink);">
                $<?= number_format($orderValue, 2) ?>
                <div style="font-size: 0.75rem; color: var(--muted); font-weight: normal;">$<?= number_format($unit_price, 2) ?>/pc</div>
              </td>
              <td style="padding: 1rem; font-size: 0.875rem;">
                <span style="display: inline-flex; align-items: center; padding: 0.375rem 0.75rem; background: <?= $statusConfig['bg'] ?>; color: <?= $statusConfig['color'] ?>; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600;">
                  <?= $statusConfig['label'] ?>
                </span>
              </td>
              <td style="padding: 1rem; text-align: right;">
                <?php if ($canEdit): ?>
                  <button onclick="alert('Edit functionality coming soon')" style="padding: 0.5rem 1rem; background: white; color: var(--brand); border: 1px solid var(--brand); border-radius: 8px; font-size: 0.875rem; font-weight: 500; cursor: pointer; margin-right: 0.5rem;">
                    Edit
                  </button>
                <?php endif; ?>
                <span style="font-size: 0.75rem; color: var(--muted);">—</span>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php endif; ?>
</div>
