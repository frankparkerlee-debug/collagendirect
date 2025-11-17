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
            <div class="product-row" data-patient="<?= $patIndex ?>" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 0.75rem; align-items: end; padding: 0.75rem; background: var(--bg-gray); border-radius: var(--radius); margin-bottom: 0.5rem;">
              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Product</label>
                <select name="products[<?= $patIndex ?>][<?= $prodIndex ?>][product_id]" class="product-select" onchange="updateRowPrice(this)"
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
                <input type="number" name="products[<?= $patIndex ?>][<?= $prodIndex ?>][boxes]" value="<?= htmlspecialchars($prod['boxes'] ?? '1') ?>" min="1" class="boxes-input" onchange="updateRowPrice(this)"
                       style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Price/Box</label>
                <div class="price-per-box" style="padding: 0.5rem; font-weight: 600;">$<?= isset($productDataForJS[$prod['product_id'] ?? '']) ? number_format($productDataForJS[$prod['product_id']]['price_per_box'], 2) : '0.00' ?></div>
              </div>
              <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Total</label>
                <div class="row-total" style="padding: 0.5rem; font-weight: 700; color: var(--brand);">$<?= isset($productDataForJS[$prod['product_id'] ?? '']) ? number_format($productDataForJS[$prod['product_id']]['price_per_box'] * ($prod['boxes'] ?? 1), 2) : '0.00' ?></div>
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

function updateRowPrice(element) {
  const row = element.closest('.product-row');
  if (!row) return;

  const productSelect = row.querySelector('.product-select');
  const boxesInput = row.querySelector('.boxes-input');
  const pricePerBoxDiv = row.querySelector('.price-per-box');
  const rowTotalDiv = row.querySelector('.row-total');

  const productId = productSelect?.value;
  const boxes = parseInt(boxesInput?.value) || 0;

  if (productId && productCatalog[productId]) {
    const pricePerBox = productCatalog[productId].price_per_box;
    const total = pricePerBox * boxes;

    pricePerBoxDiv.textContent = '$' + pricePerBox.toFixed(2);
    rowTotalDiv.textContent = '$' + total.toFixed(2);
  } else {
    pricePerBoxDiv.textContent = '-';
    rowTotalDiv.textContent = '-';
  }

  // Update patient total
  updatePatientTotal(row.dataset.patient);
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
  row.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 0.75rem; align-items: end; padding: 0.75rem; background: var(--bg-gray); border-radius: var(--radius); margin-bottom: 0.5rem;';

  row.innerHTML = `
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Product</label>
      <select name="products[${patientIndex}][${prodIndex}][product_id]" class="product-select" onchange="updateRowPrice(this)"
              style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
        <option value="">Select product...</option>
        ${Object.values(productCatalog).map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
      </select>
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Boxes</label>
      <input type="number" name="products[${patientIndex}][${prodIndex}][boxes]" value="1" min="1" class="boxes-input" onchange="updateRowPrice(this)"
             style="width: 100%; padding: 0.5rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Price/Box</label>
      <div class="price-per-box" style="padding: 0.5rem; font-weight: 600;">-</div>
    </div>
    <div>
      <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Total</label>
      <div class="row-total" style="padding: 0.5rem; font-weight: 700; color: var(--brand);">-</div>
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
      if (document.querySelectorAll('#patient-<?= $patIndex ?>-products .product-row').length === 0) {
        addProductRow(<?= $patIndex ?>);
      } else {
        // Calculate initial totals for existing rows
        updatePatientTotal(<?= $patIndex ?>);
      }
    <?php endforeach; ?>
  <?php endif; ?>
});
</script>
