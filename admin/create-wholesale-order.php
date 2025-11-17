<?php
/**
 * Admin: Create Wholesale Order on Behalf of Practice
 * Standalone admin interface - does NOT redirect to portal
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
$practiceLocations = [];

if ($selectedPracticeId) {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$selectedPracticeId]);
  $selectedPractice = $stmt->fetch(PDO::FETCH_ASSOC);

  // Get practice locations
  $locStmt = $pdo->prepare("
    SELECT * FROM practice_locations
    WHERE user_id = ? AND is_active = TRUE
    ORDER BY is_primary DESC, location_name ASC
  ");
  $locStmt->execute([$selectedPracticeId]);
  $practiceLocations = $locStmt->fetchAll(PDO::FETCH_ASSOC);
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
?>

<div class="main-content">
  <div style="max-width: 1600px; margin: 0 auto; padding: 2rem;">

    <!-- Page Header -->
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
        Create Wholesale Order
      </h1>
      <p style="color: var(--ink-light); font-size: 0.875rem;">
        Create a wholesale order on behalf of a practice or physician
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

        <div id="order-form">
          <!-- Patient Information -->
          <div style="margin-bottom: 2rem;">
            <h4 style="font-size: 1rem; font-weight: 600; color: var(--ink); margin-bottom: 1rem;">
              Patient Information
            </h4>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem;">
              <div>
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
                  First Name *
                </label>
                <input type="text" id="patient-first-name" required
                       style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
              <div>
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
                  Last Name *
                </label>
                <input type="text" id="patient-last-name" required
                       style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem;">
              <div>
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
                  Date of Birth *
                </label>
                <input type="date" id="patient-dob" required
                       style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
              <div>
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
                  Phone
                </label>
                <input type="tel" id="patient-phone"
                       style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
            </div>

            <div style="margin-bottom: 1rem;">
              <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
                Address
              </label>
              <input type="text" id="patient-address"
                     style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
              <div>
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
                  City
                </label>
                <input type="text" id="patient-city"
                       style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
              <div>
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
                  State
                </label>
                <input type="text" id="patient-state" maxlength="2"
                       style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
              <div>
                <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
                  Zip
                </label>
                <input type="text" id="patient-zip" maxlength="10"
                       style="width: 100%; padding: 0.625rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius);">
              </div>
            </div>
          </div>

          <!-- Product Selection -->
          <div style="margin-bottom: 2rem;">
            <h4 style="font-size: 1rem; font-weight: 600; color: var(--ink); margin-bottom: 1rem;">
              Products
            </h4>

            <div style="overflow-x: auto;">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                  <tr style="border-bottom: 2px solid var(--border);">
                    <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--muted);">Product</th>
                    <th style="text-align: center; padding: 0.75rem; font-weight: 600; color: var(--muted);">Pieces/Box</th>
                    <th style="text-align: right; padding: 0.75rem; font-weight: 600; color: var(--muted);">Price/Box</th>
                    <th style="text-align: center; padding: 0.75rem; font-weight: 600; color: var(--muted);">Boxes</th>
                    <th style="text-align: right; padding: 0.75rem; font-weight: 600; color: var(--muted);">Total</th>
                  </tr>
                </thead>
                <tbody id="products-table">
                  <?php foreach ($products as $product): ?>
                    <?php
                      $piecesPerBox = $product['pieces_per_box'] ?? 1;
                      $defaultPricePerBox = $product['price_wholesale'];
                      $defaultPricePerPiece = $piecesPerBox > 0 ? $defaultPricePerBox / $piecesPerBox : 0;

                      // Check for custom pricing
                      $pricePerPiece = $defaultPricePerPiece;
                      if (isset($customPricing[$product['id']])) {
                        $pricePerPiece = (float)$customPricing[$product['id']]['custom_price'];
                      }
                      $pricePerBox = $pricePerPiece * $piecesPerBox;
                    ?>
                    <tr style="border-bottom: 1px solid var(--border);" class="product-row"
                        data-product-id="<?= htmlspecialchars($product['id']) ?>"
                        data-price-per-box="<?= $pricePerBox ?>"
                        data-pieces-per-box="<?= $piecesPerBox ?>">
                      <td style="padding: 1rem;">
                        <div style="font-weight: 500; color: var(--ink);">
                          <?= htmlspecialchars($product['name']) ?>
                        </div>
                      </td>
                      <td style="padding: 1rem; text-align: center;">
                        <?= $piecesPerBox ?>
                      </td>
                      <td style="padding: 1rem; text-align: right;">
                        $<?= number_format($pricePerBox, 2) ?>
                      </td>
                      <td style="padding: 1rem; text-align: center;">
                        <input type="number" min="0" value="0" class="boxes-input"
                               data-product-id="<?= htmlspecialchars($product['id']) ?>"
                               style="width: 80px; padding: 0.5rem; text-align: center; border: 1px solid var(--border); border-radius: var(--radius);"
                               onchange="updateOrderTotal()">
                      </td>
                      <td style="padding: 1rem; text-align: right; font-weight: 600;" class="product-total">
                        $0.00
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr style="border-top: 2px solid var(--border);">
                    <td colspan="4" style="padding: 1rem; text-align: right; font-weight: 600; font-size: 1rem;">
                      Order Total:
                    </td>
                    <td style="padding: 1rem; text-align: right; font-weight: 700; font-size: 1.125rem; color: var(--brand);" id="order-total">
                      $0.00
                    </td>
                  </tr>
                </tfoot>
              </table>
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

          <!-- Submit Button -->
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
function updateOrderTotal() {
  let total = 0;

  document.querySelectorAll('.product-row').forEach(row => {
    const boxesInput = row.querySelector('.boxes-input');
    const boxes = parseInt(boxesInput.value) || 0;
    const pricePerBox = parseFloat(row.dataset.pricePerBox);
    const productTotal = boxes * pricePerBox;

    row.querySelector('.product-total').textContent = '$' + productTotal.toFixed(2);
    total += productTotal;
  });

  document.getElementById('order-total').textContent = '$' + total.toFixed(2);
}

function submitOrder() {
  // Validate patient info
  const firstName = document.getElementById('patient-first-name').value.trim();
  const lastName = document.getElementById('patient-last-name').value.trim();
  const dob = document.getElementById('patient-dob').value;

  if (!firstName || !lastName || !dob) {
    showMessage('error', 'Please fill in required patient information (First Name, Last Name, Date of Birth)');
    return;
  }

  // Collect order items
  const items = [];
  document.querySelectorAll('.product-row').forEach(row => {
    const boxesInput = row.querySelector('.boxes-input');
    const boxes = parseInt(boxesInput.value) || 0;

    if (boxes > 0) {
      items.push({
        product_id: row.dataset.productId,
        boxes: boxes,
        price_per_box: parseFloat(row.dataset.pricePerBox)
      });
    }
  });

  if (items.length === 0) {
    showMessage('error', 'Please add at least one product to the order');
    return;
  }

  // Build order data
  const orderData = {
    practice_id: '<?= htmlspecialchars($selectedPracticeId) ?>',
    patient: {
      first_name: firstName,
      last_name: lastName,
      dob: dob,
      phone: document.getElementById('patient-phone').value.trim(),
      address: document.getElementById('patient-address').value.trim(),
      city: document.getElementById('patient-city').value.trim(),
      state: document.getElementById('patient-state').value.trim(),
      zip: document.getElementById('patient-zip').value.trim()
    },
    items: items,
    notes: document.getElementById('order-notes').value.trim(),
    created_by_admin: true,
    admin_id: '<?= htmlspecialchars($admin['id']) ?>'
  };

  // Submit to backend
  fetch('/api/admin/create-wholesale-order.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(orderData)
  })
  .then(response => response.json())
  .then(data => {
    if (data.ok) {
      showMessage('success', 'Order created successfully! Order ID: ' + data.order_id);

      // Reset form after 2 seconds
      setTimeout(() => {
        window.location.href = '/admin/wholesale-orders.php';
      }, 2000);
    } else {
      showMessage('error', 'Error creating order: ' + (data.error || 'Unknown error'));
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

  // Scroll to message
  container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
