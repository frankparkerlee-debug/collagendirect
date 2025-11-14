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
      <div id="patient-list">
        <!-- Patient entries will be added here dynamically -->
      </div>

      <button type="button" class="btn" onclick="addPatient()">
        <span style="font-size: 1.25rem;">+</span> Add Patient
      </button>

      <div class="form-actions">
        <a href="?page=wholesale" class="btn-secondary btn">Cancel</a>
        <button type="submit" class="btn btn-primary" id="next-btn" disabled>Next: Select Products →</button>
      </div>
    </form>

    <script>
    let patientCount = 0;

    function addPatient() {
      const patientList = document.getElementById('patient-list');
      const index = patientCount++;

      const patientDiv = document.createElement('div');
      patientDiv.className = 'patient-card';
      patientDiv.dataset.index = index;
      patientDiv.innerHTML = `
        <div class="patient-card-header">
          <span class="patient-name">Patient ${index + 1}</span>
          <button type="button" class="remove-btn" onclick="removePatient(${index})" title="Remove patient">×</button>
        </div>

        <div class="form-group">
          <label>First Name *</label>
          <input type="text" name="patients[${index}][first_name]" class="form-control" required>
        </div>

        <div class="form-group">
          <label>Last Name *</label>
          <input type="text" name="patients[${index}][last_name]" class="form-control" required>
        </div>

        <div class="form-group">
          <label>Phone Number *</label>
          <input type="tel" name="patients[${index}][phone]" class="form-control phone-input" required placeholder="(555) 123-4567">
        </div>

        <div class="form-group">
          <label>Address *</label>
          <input type="text" id="address-${index}" name="patients[${index}][address]" class="form-control address-input" required placeholder="Start typing address...">
        </div>

        <div class="form-group">
          <label>Delivery Method *</label>
          <select name="patients[${index}][delivery_mode]" class="form-control" required>
            <option value="">Select delivery method</option>
            <option value="ship_to_patient">Ship to Patient</option>
            <option value="ship_to_office">Ship to Office</option>
          </select>
        </div>
      `;

      patientList.appendChild(patientDiv);

      // Initialize phone formatting
      const phoneInput = patientDiv.querySelector('.phone-input');
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
          // Address is already filled in the input, just log for debugging
          console.log('Address selected:', addressData);
        });
      }

      validateForm();
    }

    function removePatient(index) {
      const patientCard = document.querySelector(`.patient-card[data-index="${index}"]`);
      if (patientCard) {
        patientCard.remove();
        validateForm();
      }
    }

    function validateForm() {
      const patients = document.querySelectorAll('.patient-card');
      const nextBtn = document.getElementById('next-btn');
      nextBtn.disabled = patients.length === 0;
    }

    // Add first patient automatically
    addPatient();
    </script>

  <?php elseif ($step == '2'): ?>
    <!-- STEP 2: Product Assignment with Column-Based Selection -->
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
    <?php else: ?>

    <div style="margin-bottom: 2rem;">
      <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">Assign Products</h2>
      <p style="color: var(--muted); font-size: 0.875rem;">Enter the number of boxes for each product per patient. Leave blank to skip.</p>
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

      <div style="overflow-x: auto; margin-bottom: 2rem;">
        <table class="product-table">
          <thead>
            <tr>
              <th style="min-width: 200px;">Product</th>
              <th style="width: 120px;">Price/Box</th>
              <th style="width: 100px;">Pcs/Box</th>
              <?php foreach ($patients as $index => $patient): ?>
                <th style="width: 120px; text-align: center;">
                  <?= htmlspecialchars($patient['first_name'] ?? '') ?><br>
                  <span style="font-weight: normal; text-transform: none; font-size: 0.7rem;">Boxes</span>
                </th>
              <?php endforeach; ?>
              <th style="width: 120px; text-align: right;">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $product):
              $pricePerPiece = (float)($product['price_wholesale'] ?? 0);
              $piecesPerBox = (int)($product['pieces_per_box'] ?? 10);
              $pricePerBox = $pricePerPiece * $piecesPerBox;
            ?>
            <tr data-product-id="<?= $product['id'] ?>" data-price-per-box="<?= $pricePerBox ?>">
              <td>
                <div style="font-weight: 500;"><?= htmlspecialchars($product['name']) ?> <?= htmlspecialchars($product['size']) ?></div>
              </td>
              <td style="color: var(--muted);">$<?= number_format($pricePerBox, 2) ?></td>
              <td style="color: var(--muted); text-align: center;"><?= $piecesPerBox ?></td>
              <?php foreach ($patients as $patIndex => $patient): ?>
                <td style="text-align: center;">
                  <input
                    type="number"
                    name="products[<?= $product['id'] ?>][patients][<?= $patIndex ?>]"
                    class="quantity-input"
                    min="0"
                    max="100"
                    placeholder="0"
                    onchange="calculateTotals()"
                  >
                </td>
              <?php endforeach; ?>
              <td style="text-align: right; font-weight: 600;" class="row-total">$0.00</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Cost Summary -->
      <div class="cost-summary">
        <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Order Summary</h3>
        <div id="summary-details">
          <p style="color: var(--muted);">Select product quantities to see cost breakdown</p>
        </div>
        <div class="cost-summary-total">
          <div class="cost-summary-row">
            <span>Grand Total:</span>
            <span id="grand-total" style="color: var(--brand);">$0.00</span>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <a href="?page=wholesale&step=1" class="btn btn-secondary">← Back to Patients</a>
        <button type="submit" class="btn btn-primary" id="proceed-btn" disabled>Next: Review Order →</button>
      </div>
    </form>

    <script>
    function calculateTotals() {
      let grandTotal = 0;
      let hasAnyQuantity = false;
      const summaryDetails = [];

      // Calculate row totals
      document.querySelectorAll('.product-table tbody tr').forEach(row => {
        const pricePerBox = parseFloat(row.dataset.pricePerBox);
        let rowTotal = 0;
        let rowBoxCount = 0;

        row.querySelectorAll('.quantity-input').forEach(input => {
          const boxes = parseInt(input.value) || 0;
          rowTotal += boxes * pricePerBox;
          rowBoxCount += boxes;
        });

        if (rowBoxCount > 0) {
          hasAnyQuantity = true;
          const productName = row.querySelector('td:first-child div').textContent.trim();
          summaryDetails.push({
            name: productName,
            boxes: rowBoxCount,
            total: rowTotal
          });
        }

        row.querySelector('.row-total').textContent = '$' + rowTotal.toFixed(2);
        grandTotal += rowTotal;
      });

      // Update summary
      const summaryDiv = document.getElementById('summary-details');
      if (summaryDetails.length === 0) {
        summaryDiv.innerHTML = '<p style="color: var(--muted);">Select product quantities to see cost breakdown</p>';
      } else {
        let html = '<div style="display: flex; flex-direction: column; gap: 0.5rem;">';
        summaryDetails.forEach(item => {
          html += `
            <div class="cost-summary-row" style="font-size: 0.875rem;">
              <span>${item.name}</span>
              <span style="color: var(--muted);">${item.boxes} boxes × $${item.total.toFixed(2)}</span>
            </div>
          `;
        });
        html += '</div>';
        summaryDiv.innerHTML = html;
      }

      document.getElementById('grand-total').textContent = '$' + grandTotal.toFixed(2);
      document.getElementById('proceed-btn').disabled = !hasAnyQuantity;
    }

    // Initialize
    calculateTotals();
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
