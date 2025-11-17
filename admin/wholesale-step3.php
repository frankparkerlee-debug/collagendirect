<?php
/**
 * Step 3: Review and Submit Order
 */

// Determine order type
$orderType = $_SESSION['admin_order_type'] ?? 'patient_orders';
$isOfficeStock = ($orderType === 'office_stock');

// Build order summary
$orderSummary = [];
$grandTotal = 0;

if ($isOfficeStock) {
  // Office stock: products stored at index 0, no patients
  $officeProducts = $savedProducts[0] ?? [];
  foreach ($officeProducts as $prod) {
    if (!empty($prod['product_id']) && !empty($prod['boxes'])) {
      $product = $productDataForJS[$prod['product_id']] ?? null;
      if ($product) {
        $boxes = (int)$prod['boxes'];
        $total = $boxes * $product['price_per_box'];
        $grandTotal += $total;

        $orderSummary[] = [
          'patient' => null, // No patient for office stock
          'product' => $product,
          'boxes' => $boxes,
          'total' => $total
        ];
      }
    }
  }
} else {
  // Patient orders: loop through each patient
  foreach ($patients as $patIndex => $patient) {
    $patientProducts = $savedProducts[$patIndex] ?? [];
    $patientTotal = 0;

    foreach ($patientProducts as $prod) {
      if (!empty($prod['product_id']) && !empty($prod['boxes'])) {
        $product = $productDataForJS[$prod['product_id']] ?? null;
        if ($product) {
          $boxes = (int)$prod['boxes'];
          $total = $boxes * $product['price_per_box'];
          $patientTotal += $total;

          $orderSummary[] = [
            'patient' => $patient,
            'product' => $product,
            'boxes' => $boxes,
            'total' => $total
          ];
        }
      }
    }

    $grandTotal += $patientTotal;
  }
}
?>

<div style="margin-bottom: 2rem;">
  <h4 style="font-size: 1rem; font-weight: 600; color: var(--ink); margin-bottom: 1.5rem;">Order Summary</h4>

  <?php if (!empty($orderSummary)): ?>
    <!-- Shipping Info -->
    <div style="background: var(--bg-gray); border-radius: var(--radius); padding: 1rem; margin-bottom: 1.5rem;">
      <div style="font-weight: 600; color: var(--ink); margin-bottom: 0.5rem;">Shipping:</div>
      <?php if (($shipping['type'] ?? '') === 'practice'): ?>
        <div style="font-size: 0.875rem; color: var(--muted);">
          Ship to Practice/Office
          <?php
          foreach ($practiceLocations as $loc) {
            if ($loc['id'] === ($shipping['location_id'] ?? '')) {
              echo '<div style="margin-top: 0.5rem;">' . htmlspecialchars($loc['location_name']) . '<br>';
              echo htmlspecialchars($loc['address']) . '<br>';
              echo htmlspecialchars($loc['city']) . ', ' . htmlspecialchars($loc['state']) . ' ' . htmlspecialchars($loc['zip']) . '</div>';
              break;
            }
          }
          ?>
        </div>
      <?php else: ?>
        <div style="font-size: 0.875rem; color: var(--muted);">Ship to Individual Patients</div>
      <?php endif; ?>
    </div>

    <!-- Order Items -->
    <div style="overflow-x: auto; margin-bottom: 1.5rem;">
      <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
        <thead>
          <tr style="border-bottom: 2px solid var(--border);">
            <?php if (!$isOfficeStock): ?>
              <th style="text-align: left; padding: 0.75rem;">Patient</th>
            <?php endif; ?>
            <th style="text-align: left; padding: 0.75rem;">Product</th>
            <th style="text-align: center; padding: 0.75rem;">Boxes</th>
            <th style="text-align: right; padding: 0.75rem;">Price/Box</th>
            <th style="text-align: right; padding: 0.75rem;">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orderSummary as $item): ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <?php if (!$isOfficeStock): ?>
                <td style="padding: 0.75rem;">
                  <?= htmlspecialchars($item['patient']['first_name'] . ' ' . $item['patient']['last_name']) ?>
                </td>
              <?php endif; ?>
              <td style="padding: 0.75rem;"><?= htmlspecialchars($item['product']['name']) ?></td>
              <td style="padding: 0.75rem; text-align: center;"><?= $item['boxes'] ?></td>
              <td style="padding: 0.75rem; text-align: right;">$<?= number_format($item['product']['price_per_box'], 2) ?></td>
              <td style="padding: 0.75rem; text-align: right; font-weight: 600;">$<?= number_format($item['total'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="border-top: 2px solid var(--border);">
            <td colspan="<?= $isOfficeStock ? '3' : '4' ?>" style="padding: 1rem; text-align: right; font-weight: 700;">Grand Total:</td>
            <td style="padding: 1rem; text-align: right; font-weight: 700; font-size: 1.125rem; color: var(--brand);">
              $<?= number_format($grandTotal, 2) ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Notes -->
    <div style="margin-bottom: 1.5rem;">
      <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
        Additional Notes (Optional)
      </label>
      <textarea id="order-notes" rows="3"
                style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);"></textarea>
    </div>

    <!-- Actions -->
    <div style="display: flex; justify-content: space-between; padding-top: 1.5rem; border-top: 1px solid var(--border);">
      <form method="POST" style="display: inline;">
        <input type="hidden" name="action" value="back_to_products">
        <button type="submit"
                style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: 1px solid var(--border); border-radius: var(--radius); background: white; color: var(--ink); cursor: pointer;">
          ← Back to Products
        </button>
      </form>
      <button type="button" onclick="submitOrder()"
              style="padding: 0.75rem 1.5rem; font-size: 0.875rem; font-weight: 500; border: none; border-radius: var(--radius); background: var(--brand); color: white; cursor: pointer;">
        Create Order
      </button>
    </div>

    <div id="message-container" style="display: none; margin-top: 1rem;"></div>

  <?php else: ?>
    <p style="text-align: center; color: var(--muted); padding: 2rem;">No products assigned. Please go back and assign products to patients.</p>
  <?php endif; ?>
</div>

<script>
function submitOrder() {
  const notes = document.getElementById('order-notes').value.trim();

  // Prepare order data
  const orderData = {
    practice_id: practiceId,
    order_type: '<?= htmlspecialchars($orderType) ?>',
    patients: <?= json_encode($patients) ?>,
    products: <?= json_encode($savedProducts) ?>,
    shipping: <?= json_encode($shipping) ?>,
    notes: notes,
    created_by_admin: true,
    admin_id: adminId
  };

  // Submit to backend
  fetch('/api/admin/create-wholesale-order.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(orderData)
  })
  .then(response => {
    if (!response.ok) {
      return response.text().then(text => {
        try {
          const data = JSON.parse(text);
          throw new Error(data.message || data.error || 'Server error');
        } catch (e) {
          throw new Error('Server returned error: ' + response.status + ' - ' + text.substring(0, 200));
        }
      });
    }
    return response.json();
  })
  .then(data => {
    if (data.ok) {
      showMessage('success', `Order created successfully! ${data.orders_created} order${data.orders_created !== 1 ? 's' : ''} created.`);
      setTimeout(() => {
        // Clear session and redirect
        fetch('?practice_id=' + practiceId + '&clear_session=1')
          .then(() => window.location.href = '/admin/wholesale-orders.php');
      }, 2000);
    } else {
      showMessage('error', 'Error creating order: ' + (data.message || data.error || 'Unknown error'));
    }
  })
  .catch(error => {
    showMessage('error', 'Error: ' + error.message);
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
</script>
