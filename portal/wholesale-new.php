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

// Fetch practice locations
$locationsStmt = $pdo->prepare("
  SELECT * FROM practice_locations
  WHERE user_id = ? AND is_active = TRUE
  ORDER BY is_primary DESC, location_name ASC
");
$locationsStmt->execute([$userId]);
$locations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);

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

  <?php
  // Load session data for all steps
  // Store patients from POST (from Step 1 submission)
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patients'])) {
    $_SESSION['wholesale_patients'] = $_POST['patients'];

    // Also store products if coming back from Step 3
    if (isset($_POST['products'])) {
      $_SESSION['wholesale_products'] = $_POST['products'];
    }
  }

  // Retrieve patients and products from session
  $patients = $_SESSION['wholesale_patients'] ?? [];
  $savedProducts = $_SESSION['wholesale_products'] ?? [];
  ?>

  <?php if ($step == '1'): ?>
    <!-- STEP 1: Patient Information -->
    <div style="margin-bottom: 2rem;">
      <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">Wholesale Order</h2>
      <p style="color: var(--muted); font-size: 0.875rem;">Select delivery location and add patients for this order.</p>
    </div>

    <?php if (!empty($locations)): ?>
    <!-- Delivery Location Selection -->
    <div style="background: white; border: 2px solid var(--brand); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 2rem;">
      <label for="delivery-location" style="display: block; font-weight: 600; margin-bottom: 1rem; color: var(--ink);">
        Delivery Location *
      </label>
      <select id="delivery-location" name="location_id" class="form-control" style="max-width: 600px;" required>
        <option value="">Select delivery location...</option>
        <?php foreach ($locations as $loc): ?>
          <option value="<?= $loc['id'] ?>" <?= $loc['is_primary'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($loc['location_name']) ?> - <?= htmlspecialchars($loc['address']) ?>, <?= htmlspecialchars($loc['city']) ?>, <?= htmlspecialchars($loc['state']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div style="font-size: 0.875rem; color: var(--muted); margin-top: 0.5rem;">
        Don't see your location? <a href="?page=practice-locations" style="color: var(--brand); text-decoration: underline;">Manage locations</a>
      </div>
    </div>
    <?php endif; ?>

    <div style="margin-bottom: 1.5rem;">
      <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--ink); margin-bottom: 0.5rem;">Patient Information</h3>
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

      <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <button type="button" class="btn" onclick="addPatientRow()" style="flex: 0 0 auto;">
          <span style="font-size: 1.25rem;">+</span> Add Patient
        </button>
        <button type="button" class="btn" onclick="addOfficeStockRow()" style="flex: 0 0 auto; background: #3b82f6;">
          📦 Office Stock
        </button>
      </div>

      <div class="form-actions">
        <a href="?page=wholesale" class="btn-secondary btn">Cancel</a>
        <button type="submit" class="btn btn-primary" id="next-btn" disabled>Next: Select Products →</button>
      </div>
    </form>

    <script>
    const USER_ID = <?= json_encode($userId) ?>;
    let patientRowCount = 0;
    let patientSearchTimeouts = {};

    function addOfficeStockRow() {
      const tbody = document.getElementById('patient-rows');
      const index = patientRowCount++;

      const row = document.createElement('tr');
      row.dataset.index = index;
      row.dataset.isOfficeStock = 'true';
      row.style.borderBottom = '1px solid var(--border)';
      row.style.background = '#eff6ff'; // Light blue tint for office stock
      row.innerHTML = `
        <td style="padding: 0.75rem;" colspan="4">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-weight: 600; color: #3b82f6;">📦 Office Stock</span>
            <span style="color: var(--muted); font-size: 0.875rem;">(Products for office inventory)</span>
          </div>
          <input type="hidden" name="patients[${index}][first_name]" value="Office">
          <input type="hidden" name="patients[${index}][last_name]" value="Stock">
          <input type="hidden" name="patients[${index}][phone]" value="N/A">
          <input type="hidden" name="patients[${index}][address]" value="Office">
          <input type="hidden" name="patients[${index}][is_office_stock]" value="1">
        </td>
        <td style="padding: 0.75rem;">
          <select name="patients[${index}][delivery_mode]"
                  class="form-control"
                  required
                  style="min-width: 160px;">
            <option value="ship_to_office" selected>Ship to Office</option>
          </select>
        </td>
        <td style="padding: 0.75rem; text-align: center;">
          <button type="button"
                  class="remove-btn"
                  onclick="removePatientRow(${index})"
                  title="Remove office stock"
                  style="background: #dc3545; color: white; border: none; border-radius: 4px; width: 32px; height: 32px; cursor: pointer; font-size: 1.25rem; line-height: 1;">×</button>
        </td>
      `;

      tbody.appendChild(row);
      validatePatientForm();
    }

    function addPatientRow(existingData = null) {
      const tbody = document.getElementById('patient-rows');
      const index = patientRowCount++;

      const row = document.createElement('tr');
      row.dataset.index = index;
      row.style.borderBottom = '1px solid var(--border)';
      row.style.position = 'relative';
      row.innerHTML = `
        <td style="padding: 0.75rem; position: relative;">
          <input type="text"
                 name="patients[${index}][first_name]"
                 class="form-control patient-name-input"
                 data-row-index="${index}"
                 placeholder="First name"
                 required
                 autocomplete="off"
                 value="${existingData?.first_name || ''}"
                 style="min-width: 120px;">
          <div id="patient-search-${index}" class="patient-search-results" style="display: none; position: absolute; z-index: 1000; background: white; border: 1px solid var(--border); border-radius: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-height: 200px; overflow-y: auto; width: calc(200% + 20px); margin-top: 2px;"></div>
        </td>
        <td style="padding: 0.75rem;">
          <input type="text"
                 name="patients[${index}][last_name]"
                 class="form-control patient-name-input"
                 data-row-index="${index}"
                 placeholder="Last name"
                 required
                 autocomplete="off"
                 value="${existingData?.last_name || ''}"
                 style="min-width: 120px;">
        </td>
        <td style="padding: 0.75rem;">
          <input type="tel"
                 name="patients[${index}][phone]"
                 class="form-control phone-input"
                 data-row-index="${index}"
                 placeholder="(555) 123-4567"
                 required
                 value="${existingData?.phone || ''}"
                 style="min-width: 140px;">
          <div class="validation-msg" style="color: #dc3545; font-size: 0.75rem; margin-top: 0.25rem; display: none;"></div>
        </td>
        <td style="padding: 0.75rem;">
          <input type="text"
                 id="address-${index}"
                 name="patients[${index}][address]"
                 class="form-control address-input"
                 data-row-index="${index}"
                 placeholder="Start typing address..."
                 required
                 value="${existingData?.address || ''}"
                 style="min-width: 250px;">
          <div class="validation-msg" style="color: #dc3545; font-size: 0.75rem; margin-top: 0.25rem; display: none;"></div>
        </td>
        <td style="padding: 0.75rem;">
          <select name="patients[${index}][delivery_mode]"
                  class="form-control"
                  required
                  style="min-width: 160px;">
            <option value="">Select...</option>
            <option value="ship_to_patient" ${existingData?.delivery_mode === 'ship_to_patient' ? 'selected' : ''}>Ship to Patient</option>
            <option value="ship_to_office" ${existingData?.delivery_mode === 'ship_to_office' ? 'selected' : ''}>Ship to Office</option>
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

      // Initialize patient search on name inputs
      const nameInputs = row.querySelectorAll('.patient-name-input');
      nameInputs.forEach(input => {
        input.addEventListener('input', function(e) {
          const rowIndex = e.target.dataset.rowIndex;
          const searchContainer = document.getElementById(`patient-search-${rowIndex}`);

          // Clear timeout if exists
          if (patientSearchTimeouts[rowIndex]) {
            clearTimeout(patientSearchTimeouts[rowIndex]);
          }

          // Get both first and last name for better matching
          const row = e.target.closest('tr');
          const firstName = row.querySelector('[name*="[first_name]"]').value.trim();
          const lastName = row.querySelector('[name*="[last_name]"]').value.trim();
          const searchQuery = (firstName + ' ' + lastName).trim();

          if (searchQuery.length < 2) {
            searchContainer.style.display = 'none';
            return;
          }

          // Debounce search
          patientSearchTimeouts[rowIndex] = setTimeout(() => {
            fetch(`/api/search-patients.php?q=${encodeURIComponent(searchQuery)}&user_id=${USER_ID}`)
              .then(response => response.json())
              .then(patients => {
                if (patients.length === 0) {
                  searchContainer.style.display = 'none';
                  return;
                }

                // Build search results HTML
                let html = '';
                patients.forEach(patient => {
                  html += `
                    <div class="patient-search-result"
                         data-patient='${JSON.stringify(patient).replace(/'/g, '&apos;')}'
                         style="padding: 0.75rem; border-bottom: 1px solid var(--border); cursor: pointer;">
                      <div style="font-weight: 600;">${patient.first_name} ${patient.last_name}</div>
                      <div style="font-size: 0.875rem; color: var(--muted);">${patient.phone || ''}</div>
                      <div style="font-size: 0.875rem; color: var(--muted);">${patient.address || ''}</div>
                      ${patient.last_order ? `<div style="font-size: 0.875rem; color: #059669; margin-top: 0.25rem;">Previous order: ${patient.last_order}</div>` : ''}
                    </div>
                  `;
                });

                searchContainer.innerHTML = html;
                searchContainer.style.display = 'block';

                // Add click handlers to each result
                searchContainer.querySelectorAll('.patient-search-result').forEach(resultDiv => {
                  resultDiv.addEventListener('click', function() {
                    const patient = JSON.parse(this.dataset.patient);
                    const parentRow = document.querySelector(`tr[data-index="${rowIndex}"]`);

                    // Populate form fields
                    parentRow.querySelector('[name*="[first_name]"]').value = patient.first_name;
                    parentRow.querySelector('[name*="[last_name]"]').value = patient.last_name;
                    parentRow.querySelector('[name*="[phone]"]').value = patient.phone;
                    parentRow.querySelector('[name*="[address]"]').value = patient.address;

                    // Hide search results
                    searchContainer.style.display = 'none';

                    // Validate fields
                    validatePhoneField(parentRow.querySelector('.phone-input'));
                    validateAddressField(parentRow.querySelector('.address-input'));
                  });

                  resultDiv.addEventListener('mouseenter', function() {
                    this.style.background = '#f3f4f6';
                  });
                  resultDiv.addEventListener('mouseleave', function() {
                    this.style.background = 'white';
                  });
                });
              })
              .catch(error => {
                console.error('Patient search error:', error);
                searchContainer.style.display = 'none';
              });
          }, 300); // 300ms debounce
        });

        // Hide search results when clicking outside
        input.addEventListener('blur', function(e) {
          const rowIndex = e.target.dataset.rowIndex;
          const searchContainer = document.getElementById(`patient-search-${rowIndex}`);
          // Delay to allow click on search result
          setTimeout(() => {
            searchContainer.style.display = 'none';
          }, 200);
        });
      });

      // Add phone validation
      phoneInput.addEventListener('blur', function(e) {
        validatePhoneField(e.target);
      });

      // Add address validation
      const addressInput = row.querySelector('.address-input');
      addressInput.addEventListener('blur', function(e) {
        validateAddressField(e.target);
      });

      validatePatientForm();
    }

    // Validate phone field (must have 10 digits)
    function validatePhoneField(input) {
      const digits = input.value.replace(/\D/g, '');
      const validationMsg = input.nextElementSibling;

      if (digits.length > 0 && digits.length < 10) {
        validationMsg.textContent = 'Phone number must be 10 digits';
        validationMsg.style.display = 'block';
        input.style.borderColor = '#dc3545';
        return false;
      } else {
        validationMsg.style.display = 'none';
        input.style.borderColor = '';
        return true;
      }
    }

    // Validate address field (must be complete)
    function validateAddressField(input) {
      const value = input.value.trim();
      const validationMsg = input.nextElementSibling;

      // Check if address looks complete (has street, city, state, zip)
      const hasComma = value.includes(',');
      const hasNumber = /\d/.test(value);
      const hasState = /\b[A-Z]{2}\b/.test(value); // Two uppercase letters
      const hasZip = /\d{5}/.test(value); // 5-digit zip

      if (value.length > 0 && (!hasComma || !hasNumber || !hasState || !hasZip)) {
        validationMsg.textContent = 'Please enter a complete address with city, state, and ZIP';
        validationMsg.style.display = 'block';
        input.style.borderColor = '#dc3545';
        return false;
      } else {
        validationMsg.style.display = 'none';
        input.style.borderColor = '';
        return true;
      }
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

    // Load patients from session if coming back from Step 2
    window.addEventListener('DOMContentLoaded', function() {
      const sessionPatients = <?= json_encode($_SESSION['wholesale_patients'] ?? []) ?>;

      if (sessionPatients && Object.keys(sessionPatients).length > 0) {
        // Load existing patients from session
        Object.values(sessionPatients).forEach(patientData => {
          if (patientData.is_office_stock === '1') {
            addOfficeStockRow();
          } else {
            addPatientRow(patientData);
          }
        });
      } else {
        // Add first patient row automatically if no session data
        addPatientRow();
      }
    });
    </script>

  <?php elseif ($step == '2'): ?>
    <!-- STEP 2: Product Assignment - Patient-Centric with Grouped Products -->
    <?php
    if (empty($patients)):
    ?>
      <div style="text-align: center; padding: 3rem;">
        <p style="color: var(--muted); margin-bottom: 1rem;">No patients found. Please add patients first.</p>
        <a href="?page=wholesale&step=1" class="btn btn-primary">← Back to Patient Entry</a>
      </div>
    <?php else:
      // Group products by class (brand + product name, without size or HCPCS)
      // e.g., "AlgiHeal AG Silver Alginate Dressing 2x2 (A6196)" -> "AlgiHeal AG Silver Alginate Dressing"
      $productGroups = [];

      foreach ($products as $product) {
        $fullName = $product['name'];

        // Remove HCPCS code in parentheses (e.g., "(A6196)")
        $nameWithoutHCPCS = preg_replace('/\s*\([A-Z0-9\/]+\)\s*$/i', '', $fullName);

        // Remove size dimensions (e.g., "2x2", "4.33x4.33", "6x6", "9"x9"", "8oz Bottle", "1.0g", "Medium", "100ML", "250ML")
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

        // Extract size from product name
        let sizeText = '';
        let fullName = product.name;

        // Remove HCPCS code first (e.g., "(A6196)")
        fullName = fullName.replace(/\s*\([A-Z0-9\/]+\)\s*$/i, '');

        // Extract different size patterns
        let sizeMatch;

        // Pattern 1: Dimensions like "2x2", "4.33x4.33", "6x6"
        sizeMatch = fullName.match(/(\d+(?:\.\d+)?)\s*x\s*(\d+(?:\.\d+)?)$/i);
        if (sizeMatch) {
          sizeText = sizeMatch[0];
        }
        // Pattern 2: Quoted dimensions like 9"x9"
        else if ((sizeMatch = fullName.match(/(\d+"x\d+")$/i))) {
          sizeText = sizeMatch[0];
        }
        // Pattern 3: Gauze sizes like 4"x4"(2"x2")
        else if ((sizeMatch = fullName.match(/(\d+"x\d+"\(\d+(?:\.\d+)?"x\d+(?:\.\d+)?"\))$/i))) {
          sizeText = sizeMatch[0];
        }
        // Pattern 4: Volume like "8oz Bottle"
        else if ((sizeMatch = fullName.match(/(\d+(?:\.\d+)?[a-z]+\s+Bottle)$/i))) {
          sizeText = sizeMatch[0];
        }
        // Pattern 5: Weight with rope like "2g Rope"
        else if ((sizeMatch = fullName.match(/(\d+(?:\.\d+)?g\s+Rope)$/i))) {
          sizeText = sizeMatch[0];
        }
        // Pattern 6: Weight like "1.0g"
        else if ((sizeMatch = fullName.match(/(\d+(?:\.\d+)?g)$/i))) {
          sizeText = sizeMatch[0];
        }
        // Pattern 7: Volume like "100ML", "250ML"
        else if ((sizeMatch = fullName.match(/(\d+ML)$/i))) {
          sizeText = sizeMatch[0];
        }
        // Pattern 8: Size like "Medium"
        else if ((sizeMatch = fullName.match(/(Medium|Large|Small)$/i))) {
          sizeText = sizeMatch[0];
        }
        // Fallback: Show what's after the category name
        else {
          sizeText = fullName.replace(selectedCategory, '').trim() || 'Standard';
        }

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

      const selectedProductId = sizeSelect.value;

      if (!selectedProductId) {
        quantityContainer.style.display = 'none';
        return;
      }

      const product = productData[selectedProductId];
      // price_wholesale is already per BOX (not per piece)
      const pricePerBox = parseFloat(product.price_wholesale || 0);
      const piecesPerBox = parseInt(product.pieces_per_box || 10);

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
          // price_wholesale is already per BOX (not per piece)
          const pricePerBox = parseFloat(product.price_wholesale || 0);
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

    // Initialize - add product rows for each patient
    <?php foreach ($patients as $patIndex => $patient): ?>
      // Check if we have saved products for this patient
      const patient<?= $patIndex ?>Products = savedProducts[<?= $patIndex ?>] || {};

      if (Object.keys(patient<?= $patIndex ?>Products).length > 0) {
        // Load saved products
        Object.keys(patient<?= $patIndex ?>Products).forEach(rowIndex => {
          const savedProduct = patient<?= $patIndex ?>Products[rowIndex];
          addProductRow(<?= $patIndex ?>);

          // Now populate the fields
          setTimeout(() => {
            const row = document.querySelector(`[data-patient-index="<?= $patIndex ?>"][data-row-index="${rowIndex}"]`);
            if (row && savedProduct.product_id && savedProduct.boxes) {
              const product = productData[savedProduct.product_id];
              if (product) {
                // Find the category for this product
                let categoryName = null;
                for (const [category, products] of Object.entries(productCatalog)) {
                  if (products.some(p => p.id === savedProduct.product_id)) {
                    categoryName = category;
                    break;
                  }
                }

                if (categoryName) {
                  // Set the product category
                  const productSelect = row.querySelector('.product-selector');
                  productSelect.value = categoryName;
                  updateSizeOptions(<?= $patIndex ?>, rowIndex);

                  // Set the size (product ID)
                  setTimeout(() => {
                    const sizeSelect = row.querySelector('.size-selector');
                    sizeSelect.value = savedProduct.product_id;
                    updateProductSelection(<?= $patIndex ?>, rowIndex);

                    // Set the quantity
                    setTimeout(() => {
                      const quantityInput = row.querySelector('.quantity-input');
                      quantityInput.value = savedProduct.boxes;
                      calculatePatientTotal(<?= $patIndex ?>);
                    }, 50);
                  }, 50);
                }
              }
            }
          }, 50);
        });
      } else {
        // No saved products, add one empty row
        addProductRow(<?= $patIndex ?>);
      }
    <?php endforeach; ?>
    </script>

    <?php endif; ?>

  <?php elseif ($step == '3'): ?>
    <!-- STEP 3: Review & Submit -->
    <?php
    // Get saved products from session
    $savedProducts = $_SESSION['wholesale_products'] ?? [];

    // Get practice address for office stock deliveries
    $practiceAddress = [];
    if (!empty($_SESSION['user_id'])) {
      // First get the current user's practice_name
      $userStmt = $pdo->prepare("SELECT practice_name, address, city, state, zip, phone, role FROM users WHERE id = ?");
      $userStmt->execute([$_SESSION['user_id']]);
      $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

      if ($currentUser) {
        // If this user has an address, use it
        if (!empty($currentUser['address'])) {
          $practiceAddress = $currentUser;
        }
        // Otherwise, find the practice admin with the same practice_name
        else if (!empty($currentUser['practice_name'])) {
          $adminStmt = $pdo->prepare("
            SELECT practice_name, address, city, state, zip, phone
            FROM users
            WHERE practice_name = ?
              AND role = 'practice_admin'
              AND address IS NOT NULL
              AND address != ''
            LIMIT 1
          ");
          $adminStmt->execute([$currentUser['practice_name']]);
          $practiceAddress = $adminStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
      }
    }

    // Calculate totals
    $orderItems = [];
    $grandTotal = 0;

    foreach ($patients as $patIndex => $patient) {
      $patientProducts = $savedProducts[$patIndex] ?? [];
      foreach ($patientProducts as $rowIndex => $productData) {
        if (isset($productData['product_id']) && isset($productData['boxes'])) {
          $productId = $productData['product_id'];
          $boxes = (int)$productData['boxes'];

          // Find product details
          $stmt = $pdo->prepare("SELECT id, name, price_wholesale, pieces_per_box FROM products WHERE id = ?");
          $stmt->execute([$productId]);
          $product = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($product && $boxes > 0) {
            // price_wholesale is already per BOX, not per piece
            $pricePerBox = (float)($product['price_wholesale'] ?? 0);
            $lineTotal = $pricePerBox * $boxes;

            $orderItems[] = [
              'patient_index' => $patIndex,
              'patient' => $patient,
              'product' => $product,
              'boxes' => $boxes,
              'price_per_box' => $pricePerBox,
              'line_total' => $lineTotal
            ];

            $grandTotal += $lineTotal;
          }
        }
      }
    }
    ?>

    <div style="max-width: 900px; margin: 0 auto;">
      <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem; color: var(--ink);">Review Order</h2>

      <?php if (empty($orderItems)): ?>
        <div class="alert alert-warning" style="margin-bottom: 2rem;">
          <strong>No products selected.</strong> Please go back to Step 2 and add products to your order.
        </div>
        <div class="form-actions">
          <a href="?page=wholesale&step=2" class="btn btn-secondary">← Back to Products</a>
        </div>
      <?php else: ?>
        <!-- Order Summary -->
        <div class="card" style="margin-bottom: 1.5rem;">
          <div class="card-body">
            <h3 style="font-size: 1.125rem; margin-bottom: 1rem; color: var(--ink);">Order Summary</h3>

            <table class="table" style="width: 100%; border-collapse: collapse;" id="review-table">
              <thead>
                <tr style="border-bottom: 2px solid var(--border);">
                  <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem;">Patient</th>
                  <th style="padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">Delivery</th>
                  <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem;">Address</th>
                  <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem;">Product</th>
                  <th style="padding: 0.75rem; text-align: right; font-weight: 600; font-size: 0.875rem;">Boxes</th>
                  <th style="padding: 0.75rem; text-align: right; font-weight: 600; font-size: 0.875rem;">Price/Box</th>
                  <th style="padding: 0.75rem; text-align: right; font-weight: 600; font-size: 0.875rem;">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $currentPatient = null;
                $rowIndex = 0;
                foreach ($orderItems as $item):
                  $patient = $item['patient'];
                  $product = $item['product'];
                  $showPatientInfo = ($currentPatient !== $item['patient_index']);
                  $currentPatient = $item['patient_index'];

                  $patientName = htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']);

                  // Format address and delivery type
                  $deliveryType = '';
                  $address = '';

                  // Check if this should ship to office (multiple ways it can be indicated)
                  $shipToOffice = (
                    ($patient['delivery_mode'] ?? '') === 'ship_to_office' || // Regular patient shipping to office
                    ($patient['delivery_preference'] ?? '') === 'office_stock' ||
                    ($patient['is_office_stock'] ?? '') == '1' || // Use == for loose comparison
                    ($patient['is_office_stock'] ?? false) === true ||
                    strtolower($patient['first_name'] ?? '') === 'office'
                  );

                  if ($shipToOffice) {
                    $deliveryType = 'Office';
                    // Use practice address for office deliveries
                    $addressParts = array_filter([
                      $practiceAddress['address'] ?? '',
                      $practiceAddress['city'] ?? '',
                      $practiceAddress['state'] ?? '',
                      $practiceAddress['zip'] ?? ''
                    ]);
                    if (!empty($addressParts)) {
                      $address = htmlspecialchars(implode(', ', $addressParts));
                    } else {
                      $address = '<em style="color: var(--muted);">Office Address (Not Set)</em>';
                    }
                  } else {
                    $deliveryType = 'Patient';
                    $addressParts = array_filter([
                      $patient['address'] ?? '',
                      $patient['city'] ?? '',
                      $patient['state'] ?? '',
                      $patient['zip'] ?? ''
                    ]);
                    $address = htmlspecialchars(implode(', ', $addressParts));
                  }

                  $rowIndex++;
                ?>
                  <tr style="border-bottom: 1px solid var(--border);" data-row="<?= $rowIndex ?>" data-price="<?= $item['price_per_box'] ?>">
                    <?php if ($showPatientInfo): ?>
                      <td style="padding: 0.75rem; font-size: 0.875rem; vertical-align: top;">
                        <strong><?= $patientName ?></strong><br>
                        <small style="color: var(--muted);"><?= htmlspecialchars($patient['phone'] ?? '') ?></small>
                      </td>
                      <td style="padding: 0.75rem; text-align: center; font-size: 0.875rem; vertical-align: top;">
                        <span style="display: inline-block; padding: 0.25rem 0.5rem; background: <?= $deliveryType === 'Office' ? '#e0f2fe' : '#f0fdf4' ?>; color: <?= $deliveryType === 'Office' ? '#0369a1' : '#15803d' ?>; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                          <?= $deliveryType ?>
                        </span>
                      </td>
                      <td style="padding: 0.75rem; font-size: 0.875rem; vertical-align: top;">
                        <?= $address ?>
                      </td>
                    <?php else: ?>
                      <td colspan="3" style="padding: 0.75rem; font-size: 0.875rem;"></td>
                    <?php endif; ?>
                    <td style="padding: 0.75rem; font-size: 0.875rem;">
                      <?= htmlspecialchars($product['name']) ?>
                    </td>
                    <td style="padding: 0.75rem; text-align: right; font-size: 0.875rem;">
                      <input
                        type="number"
                        class="quantity-edit"
                        value="<?= $item['boxes'] ?>"
                        min="1"
                        max="999"
                        data-row="<?= $rowIndex ?>"
                        style="width: 60px; padding: 0.25rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; text-align: right;"
                        onchange="updateRowTotal(this)"
                      >
                    </td>
                    <td style="padding: 0.75rem; text-align: right; font-size: 0.875rem;" class="price-per-box">
                      $<?= number_format($item['price_per_box'], 2) ?>
                    </td>
                    <td style="padding: 0.75rem; text-align: right; font-size: 0.875rem; font-weight: 600;" class="row-total">
                      $<?= number_format($item['line_total'], 2) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <tr style="border-top: 2px solid var(--border);">
                  <td colspan="6" style="padding: 1rem; text-align: right; font-weight: 600; font-size: 1rem;">
                    Grand Total:
                  </td>
                  <td style="padding: 1rem; text-align: right; font-weight: 700; font-size: 1.125rem; color: var(--primary);" id="grand-total-display">
                    $<?= number_format($grandTotal, 2) ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Order Notes -->
        <div class="card" style="margin-bottom: 1.5rem;">
          <div class="card-body">
            <label for="order-notes" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Order Notes (Optional)</label>
            <textarea
              id="order-notes"
              name="order_notes"
              rows="3"
              class="form-control"
              placeholder="Add any special instructions or notes for this order..."
              style="width: 100%; resize: vertical;"
            ></textarea>
          </div>
        </div>

        <!-- Submit Section -->
        <form id="submit-order-form" method="POST" action="/api/portal/wholesale-order.create.php">
          <input type="hidden" name="order_data" id="order-data-input" value="">

          <div class="card" style="margin-bottom: 1.5rem; background: #f8f9fa;">
            <div class="card-body">
              <div style="display: flex; align-items: start; gap: 0.75rem;">
                <input type="checkbox" id="confirm-order" required style="margin-top: 0.25rem;">
                <label for="confirm-order" style="flex: 1; font-size: 0.875rem;">
                  I confirm that this wholesale order is accurate and authorize the creation of <strong id="order-count"><?= count($orderItems) ?></strong> order(s)
                  totaling <strong id="confirm-total">$<?= number_format($grandTotal, 2) ?></strong>.
                  This will be added to the practice account balance. By checking this box, I agree to our
                  <a href="?page=esignature-policy" target="_blank" style="color: var(--primary); text-decoration: underline;">Electronic Signature Policy</a>.
                </label>
              </div>
            </div>
          </div>

          <div id="submit-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem;"></div>
          <div id="submit-success" class="alert alert-success" style="display: none; margin-bottom: 1rem;"></div>

          <div class="form-actions">
            <a href="?page=wholesale&step=2" class="btn btn-secondary">← Back to Products</a>
            <button type="submit" id="submit-btn" class="btn btn-primary">
              Submit Wholesale Order
            </button>
          </div>
        </form>

        <script>
        // Prepare order data
        let orderData = <?= json_encode([
          'patients' => $patients,
          'products' => $savedProducts,
          'items' => $orderItems,
          'grand_total' => $grandTotal
        ]) ?>;

        // Function to update row total when quantity changes
        function updateRowTotal(input) {
          const row = input.closest('tr');
          const quantity = parseInt(input.value) || 1;
          const pricePerBox = parseFloat(row.dataset.price);
          const rowTotal = quantity * pricePerBox;

          // Update the row total display
          row.querySelector('.row-total').textContent = '$' + rowTotal.toFixed(2);

          // Recalculate grand total
          let grandTotal = 0;
          document.querySelectorAll('.quantity-edit').forEach(qtyInput => {
            const qtyRow = qtyInput.closest('tr');
            const qty = parseInt(qtyInput.value) || 1;
            const price = parseFloat(qtyRow.dataset.price);
            grandTotal += qty * price;
          });

          // Update grand total display
          document.getElementById('grand-total-display').textContent = '$' + grandTotal.toFixed(2);
          document.getElementById('confirm-total').textContent = '$' + grandTotal.toFixed(2);

          // Update orderData
          updateOrderData();
        }

        // Function to rebuild orderData from current quantities
        function updateOrderData() {
          const updatedItems = [];
          let grandTotal = 0;

          orderData.items.forEach((item, index) => {
            const qtyInput = document.querySelector(`.quantity-edit[data-row="${index + 1}"]`);
            if (qtyInput) {
              const newQuantity = parseInt(qtyInput.value) || item.boxes;
              const newLineTotal = newQuantity * item.price_per_box;

              updatedItems.push({
                ...item,
                boxes: newQuantity,
                line_total: newLineTotal
              });

              grandTotal += newLineTotal;
            } else {
              updatedItems.push(item);
              grandTotal += item.line_total;
            }
          });

          orderData.items = updatedItems;
          orderData.grand_total = grandTotal;
        }

        // Handle form submission
        document.getElementById('submit-order-form').addEventListener('submit', async function(e) {
          e.preventDefault();

          const submitBtn = document.getElementById('submit-btn');
          const errorDiv = document.getElementById('submit-error');
          const successDiv = document.getElementById('submit-success');

          // Disable button and show loading
          submitBtn.disabled = true;
          submitBtn.textContent = 'Submitting...';
          errorDiv.style.display = 'none';
          successDiv.style.display = 'none';

          try {
            // Add order notes to data
            orderData.notes = document.getElementById('order-notes').value;

            // Submit the order
            const response = await fetch('/api/portal/wholesale-order.create.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify(orderData)
            });

            const result = await response.json();

            if (result.ok) {
              // Success - show message and redirect
              successDiv.textContent = `Success! Created ${result.orders_created || 0} wholesale order(s).`;
              successDiv.style.display = 'block';

              // Clear session data
              <?php
                unset($_SESSION['wholesale_patients']);
                unset($_SESSION['wholesale_products']);
              ?>

              // Redirect to orders page after 2 seconds
              setTimeout(() => {
                window.location.href = '?page=orders';
              }, 2000);
            } else {
              // Error - show detailed error message
              let errorMsg = result.error || 'Failed to create order';
              if (result.details) {
                errorMsg += ': ' + result.details;
              }
              errorDiv.textContent = 'Error: ' + errorMsg;
              errorDiv.style.display = 'block';
              submitBtn.disabled = false;
              submitBtn.textContent = 'Submit Wholesale Order';

              // Log full error for debugging
              console.error('API Error Response:', result);
            }
          } catch (error) {
            console.error('Order submission error:', error);
            errorDiv.textContent = 'Error: Failed to submit order. Please try again. ' + error.message;
            errorDiv.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Wholesale Order';
          }
        });
        </script>
      <?php endif; ?>
    </div>

  <?php endif; ?>
</div>
