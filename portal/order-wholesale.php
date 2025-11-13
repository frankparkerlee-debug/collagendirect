<?php
// /public/portal/order-wholesale.php — Simplified Wholesale/Cash-Pay Order Form
declare(strict_types=1);

/* ------------ DB + session/bootstrap ------------ */
require __DIR__ . '/../api/db.php';

// Check authentication
if (empty($_SESSION['user_id'])) {
  header('Location: /login?next=/portal/order-wholesale.php');
  exit;
}
$userId = (string)$_SESSION['user_id'];

// Load user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
  session_destroy();
  header('Location: /login');
  exit;
}

$userRole = $user['role'] ?? 'physician';

// Must have NPI
if (empty($user['npi'])) {
  $_SESSION['error_msg'] = 'You must add your NPI number before creating orders. Please update your profile.';
  header('Location: /portal/index.php?page=profile');
  exit;
}

require_once '_header.php';
?>

<div class="container-fluid py-4">
  <div class="row mb-4">
    <div class="col-md-8">
      <h2>Wholesale Order</h2>
      <p class="text-muted">Simplified ordering for practices billing their own DME license</p>
    </div>
    <div class="col-md-4 text-end">
      <a href="/portal/index.php?page=dashboard" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
      </a>
    </div>
  </div>

  <!-- Info Alert -->
  <div class="alert alert-info">
    <h5><i class="bi bi-info-circle"></i> Wholesale Ordering Benefits</h5>
    <ul class="mb-0">
      <li><strong>No Insurance Paperwork</strong> - No cards, AOB, or authorization required</li>
      <li><strong>Wholesale Pricing</strong> - Lower cost per piece, you bill at Medicare rates</li>
      <li><strong>Fast Processing</strong> - Simplified documentation, faster fulfillment</li>
      <li><strong>Your DME License</strong> - You bill patients/insurance and keep the margin</li>
    </ul>
  </div>

  <!-- Order Form -->
  <div class="card">
    <div class="card-body">
      <form id="wholesaleOrderForm">
        <!-- Patient Selection -->
        <div class="mb-4">
          <h5>1. Select Patient</h5>
          <div class="row">
            <div class="col-md-8">
              <label for="patient_id" class="form-label">Patient *</label>
              <select id="patient_id" name="patient_id" class="form-select" required>
                <option value="">Select a patient...</option>
              </select>
              <small class="text-muted">Or <a href="#" id="createNewPatientLink">create new patient</a></small>
            </div>
          </div>
        </div>

        <hr>

        <!-- Product Selection -->
        <div class="mb-4">
          <h5>2. Select Product</h5>
          <div class="row">
            <div class="col-md-6">
              <label for="product_id" class="form-label">Product *</label>
              <select id="product_id" name="product_id" class="form-select" required>
                <option value="">Select a product...</option>
              </select>
            </div>
            <div class="col-md-6">
              <div class="card bg-light">
                <div class="card-body">
                  <h6 class="card-title">Pricing</h6>
                  <div id="pricingDisplay">
                    <p class="text-muted">Select a product to see pricing</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <hr>

        <!-- Order Details -->
        <div class="mb-4">
          <h5>3. Order Details</h5>
          <div class="row">
            <div class="col-md-4">
              <label for="frequency_per_week" class="form-label">Changes per Week *</label>
              <input type="number" id="frequency_per_week" name="frequency_per_week" class="form-control" min="1" max="21" value="7" required>
              <small class="text-muted">How many times per week to change dressing</small>
            </div>
            <div class="col-md-4">
              <label for="duration_days" class="form-label">Duration (Days) *</label>
              <input type="number" id="duration_days" name="duration_days" class="form-control" min="1" max="90" value="30" required>
              <small class="text-muted">Total treatment duration</small>
            </div>
            <div class="col-md-4">
              <label for="qty_per_change" class="form-label">Pieces per Change *</label>
              <input type="number" id="qty_per_change" name="qty_per_change" class="form-control" min="1" max="10" value="1" required>
              <small class="text-muted">How many pieces per dressing change</small>
            </div>
          </div>

          <div class="row mt-3">
            <div class="col-md-12">
              <div class="card bg-success text-white">
                <div class="card-body">
                  <h5 class="card-title">Order Summary</h5>
                  <div id="orderSummary">
                    <p>Fill out the fields above to see order calculation</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <hr>

        <!-- Shipping -->
        <div class="mb-4">
          <h5>4. Shipping</h5>
          <div class="row">
            <div class="col-md-6">
              <label class="form-label">Deliver To *</label>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="delivery_to" id="delivery_patient" value="patient" checked>
                <label class="form-check-label" for="delivery_patient">
                  Patient Address
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="delivery_to" id="delivery_office" value="office">
                <label class="form-check-label" for="delivery_office">
                  Office Address
                </label>
              </div>
            </div>
          </div>

          <div class="row mt-3" id="shippingFields">
            <div class="col-md-12">
              <p class="text-muted">Shipping address will be auto-filled from patient record</p>
            </div>
          </div>
        </div>

        <hr>

        <!-- Physician Signature -->
        <div class="mb-4">
          <h5>5. Physician Signature</h5>
          <div class="row">
            <div class="col-md-6">
              <label for="sign_name" class="form-label">Full Name *</label>
              <input type="text" id="sign_name" name="sign_name" class="form-control"
                     value="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label for="sign_title" class="form-label">Title</label>
              <input type="text" id="sign_title" name="sign_title" class="form-control"
                     value="<?= htmlspecialchars($user['credentials'] ?? 'MD') ?>">
            </div>
          </div>
          <div class="row mt-3">
            <div class="col-md-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="ack_sig" name="ack_sig" required>
                <label class="form-check-label" for="ack_sig">
                  I certify that this order is medically necessary and that I am authorized to order medical supplies for this patient.
                </label>
              </div>
            </div>
          </div>
        </div>

        <hr>

        <!-- Submit Buttons -->
        <div class="d-flex justify-content-between">
          <button type="button" class="btn btn-secondary" onclick="window.location.href='/portal/index.php?page=dashboard'">
            Cancel
          </button>
          <div>
            <button type="submit" name="save_as_draft" value="1" class="btn btn-outline-primary me-2">
              Save as Draft
            </button>
            <button type="submit" name="save_as_draft" value="0" class="btn btn-success">
              <i class="bi bi-check-circle"></i> Submit Order
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  let products = [];
  let patients = [];
  let selectedProduct = null;

  // Load products (wholesale pricing)
  fetch('/portal/index.php?action=products')
    .then(r => r.json())
    .then(data => {
      if (data.ok && data.rows) {
        products = data.rows;
        const select = document.getElementById('product_id');
        products.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = `${p.name} ${p.size}`;
          opt.dataset.product = JSON.stringify(p);
          select.appendChild(opt);
        });
      }
    });

  // Load patients
  fetch('/portal/index.php?action=patients')
    .then(r => r.json())
    .then(data => {
      if (data.ok && data.rows) {
        patients = data.rows;
        const select = document.getElementById('patient_id');
        patients.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = `${p.first_name} ${p.last_name} (DOB: ${p.dob || 'N/A'})`;
          opt.dataset.patient = JSON.stringify(p);
          select.appendChild(opt);
        });
      }
    });

  // Product selection - show pricing
  document.getElementById('product_id').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt.dataset.product) {
      document.getElementById('pricingDisplay').innerHTML = '<p class="text-muted">Select a product to see pricing</p>';
      selectedProduct = null;
      return;
    }

    selectedProduct = JSON.parse(opt.dataset.product);

    // Note: We need to fetch wholesale pricing separately
    // For now, show placeholder
    document.getElementById('pricingDisplay').innerHTML = `
      <div><strong>${selectedProduct.name}</strong></div>
      <div class="mt-2">
        <small class="text-muted">Wholesale Price:</small> $<span id="wholesalePrice">--</span>/piece<br>
        <small class="text-muted">Pieces per Box:</small> <span id="piecesPerBox">--</span><br>
        <strong>Price per Box:</strong> $<span id="boxPrice">--</span>
      </div>
    `;

    // Fetch product details with wholesale pricing
    fetch(`/portal/index.php?action=product.details&id=${selectedProduct.id}`)
      .then(r => r.json())
      .then(data => {
        if (data.ok && data.product) {
          const p = data.product;
          document.getElementById('wholesalePrice').textContent = (p.price_wholesale || 0).toFixed(2);
          document.getElementById('piecesPerBox').textContent = p.pieces_per_box || 10;
          document.getElementById('boxPrice').textContent = ((p.price_wholesale || 0) * (p.pieces_per_box || 10)).toFixed(2);
          selectedProduct = p;
          calculateOrder();
        }
      });

    calculateOrder();
  });

  // Calculate order summary when inputs change
  ['frequency_per_week', 'duration_days', 'qty_per_change'].forEach(id => {
    document.getElementById(id).addEventListener('input', calculateOrder);
  });

  function calculateOrder() {
    if (!selectedProduct || !selectedProduct.price_wholesale) {
      document.getElementById('orderSummary').innerHTML = '<p>Select a product first</p>';
      return;
    }

    const freq = parseInt(document.getElementById('frequency_per_week').value) || 0;
    const duration = parseInt(document.getElementById('duration_days').value) || 0;
    const qtyPerChange = parseInt(document.getElementById('qty_per_change').value) || 1;
    const piecesPerBox = selectedProduct.pieces_per_box || 10;
    const wholesalePrice = selectedProduct.price_wholesale || 0;

    if (freq === 0 || duration === 0) {
      document.getElementById('orderSummary').innerHTML = '<p>Enter frequency and duration</p>';
      return;
    }

    const changesPerDay = freq / 7;
    const totalChanges = changesPerDay * duration;
    const piecesNeeded = Math.ceil(totalChanges * qtyPerChange);
    const boxesNeeded = Math.ceil(piecesNeeded / piecesPerBox);
    const totalCost = boxesNeeded * (wholesalePrice * piecesPerBox);

    document.getElementById('orderSummary').innerHTML = `
      <div class="row">
        <div class="col-md-6">
          <strong>Pieces Needed:</strong> ${piecesNeeded}<br>
          <strong>Boxes to Order:</strong> ${boxesNeeded} boxes
        </div>
        <div class="col-md-6 text-end">
          <strong>Unit Price:</strong> $${wholesalePrice.toFixed(2)}/piece<br>
          <strong>Total Cost:</strong> <span class="fs-4">$${totalCost.toFixed(2)}</span>
        </div>
      </div>
    `;
  }

  // Form submission
  document.getElementById('wholesaleOrderForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const saveAsDraft = e.submitter?.name === 'save_as_draft' && e.submitter?.value === '1' ? 1 : 0;

    const formData = new FormData(this);
    formData.append('action', 'order.create.wholesale');
    formData.append('save_as_draft', saveAsDraft);

    try {
      const response = await fetch('/portal/index.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.ok) {
        alert(saveAsDraft ? 'Order saved as draft' : 'Order submitted successfully!');
        window.location.href = '/portal/index.php?page=dashboard';
      } else {
        alert('Error: ' + (data.error || 'Failed to create order'));
      }
    } catch (err) {
      alert('Error submitting order: ' + err.message);
    }
  });

  // Create new patient link
  document.getElementById('createNewPatientLink').addEventListener('click', function(e) {
    e.preventDefault();
    window.location.href = '/portal/index.php?page=patient&new=1';
  });
});
</script>

<?php require_once '_footer.php'; ?>
