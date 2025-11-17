<?php
/**
 * Practice-Specific Pricing Management
 * Allows admins to set custom wholesale pricing for specific practices
 */

require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/db.php';

$selectedPractice = $_GET['practice_id'] ?? '';
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'save_pricing') {
    try {
      $userId = $_POST['user_id'] ?? '';
      $catalogDiscount = !empty($_POST['catalog_discount']) ? (float)$_POST['catalog_discount'] : null;
      $productPrices = $_POST['product_prices'] ?? [];
      $productDiscounts = $_POST['product_discounts'] ?? [];

      if (empty($userId)) {
        throw new Exception('Please select a practice');
      }

      $pdo->beginTransaction();

      // If catalog discount is set, apply to all products
      if ($catalogDiscount !== null && $catalogDiscount > 0) {
        // Get all products
        $stmt = $pdo->query("SELECT id, price_wholesale FROM products WHERE active = TRUE");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $product) {
          $defaultPrice = $product['price_wholesale'];
          $customPrice = $defaultPrice * (1 - ($catalogDiscount / 100));

          // Upsert pricing
          $stmt = $pdo->prepare("
            INSERT INTO practice_pricing (user_id, product_id, custom_price, discount_percentage, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON CONFLICT (user_id, product_id)
            DO UPDATE SET custom_price = EXCLUDED.custom_price, discount_percentage = EXCLUDED.discount_percentage, updated_at = NOW(), created_by = EXCLUDED.created_by
          ");
          $stmt->execute([$userId, $product['id'], $customPrice, $catalogDiscount, $admin['id']]);
        }
        $message = "Catalog-wide {$catalogDiscount}% discount applied to all products";
      } else {
        // Apply individual product pricing
        foreach ($productPrices as $productId => $customPrice) {
          $productId = (int)$productId;
          $customPrice = (float)$customPrice;
          $discount = isset($productDiscounts[$productId]) ? (float)$productDiscounts[$productId] : null;

          if ($customPrice > 0) {
            // Upsert pricing
            $stmt = $pdo->prepare("
              INSERT INTO practice_pricing (user_id, product_id, custom_price, discount_percentage, created_by, created_at, updated_at)
              VALUES (?, ?, ?, ?, ?, NOW(), NOW())
              ON CONFLICT (user_id, product_id)
              DO UPDATE SET custom_price = EXCLUDED.custom_price, discount_percentage = EXCLUDED.discount_percentage, updated_at = NOW(), created_by = EXCLUDED.created_by
            ");
            $stmt->execute([$userId, $productId, $customPrice, $discount, $admin['id']]);
          } else {
            // Remove pricing if custom price is empty/zero
            $stmt = $pdo->prepare("DELETE FROM practice_pricing WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
          }
        }
        $message = 'Custom pricing saved successfully';
      }

      $pdo->commit();
    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $error = $e->getMessage();
    }
  } elseif ($action === 'delete') {
    try {
      $id = (int)($_POST['id'] ?? 0);
      if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM practice_pricing WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Custom pricing removed successfully';
      }
    } catch (Exception $e) {
      $error = $e->getMessage();
    }
  } elseif ($action === 'clear_all') {
    try {
      $userId = $_POST['user_id'] ?? '';
      if (!empty($userId)) {
        $stmt = $pdo->prepare("DELETE FROM practice_pricing WHERE user_id = ?");
        $stmt->execute([$userId]);
        $message = 'All custom pricing cleared for this practice';
      }
    } catch (Exception $e) {
      $error = $e->getMessage();
    }
  }
}

// Fetch all practices (for dropdown)
$stmt = $pdo->query("
  SELECT id, practice_name, first_name, last_name, user_type
  FROM users
  WHERE user_type IN ('practice_admin', 'physician', 'dme_wholesale')
  ORDER BY practice_name ASC, last_name ASC
");
$practices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all products
$stmt = $pdo->query("
  SELECT id, name, size, price_wholesale, pieces_per_box, category
  FROM products
  WHERE active = TRUE
  ORDER BY name ASC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing pricing for selected practice
$existingPricing = [];
if ($selectedPractice) {
  $stmt = $pdo->prepare("
    SELECT product_id, custom_price, discount_percentage
    FROM practice_pricing
    WHERE user_id = ?
  ");
  $stmt->execute([$selectedPractice]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
    $existingPricing[$row['product_id']] = $row;
  }
}

// Get practice details
$practiceDetails = null;
if ($selectedPractice) {
  foreach ($practices as $p) {
    if ($p['id'] === $selectedPractice) {
      $practiceDetails = $p;
      break;
    }
  }
}
?>

<div class="main-content">
  <div class="container" style="max-width: 1600px; margin: 0 auto; padding: 2rem;">

    <!-- Page Header -->
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
        Practice Pricing
      </h1>
      <p style="color: var(--ink-light); font-size: 0.875rem;">
        Set custom wholesale pricing for practices - apply catalog-wide discounts or individual product pricing
      </p>
    </div>

    <?php if ($message): ?>
      <div style="background: var(--success-light); border: 1px solid var(--success); border-radius: var(--radius); padding: 1rem; margin-bottom: 1.5rem; color: var(--success);">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div style="background: var(--error-light); border: 1px solid var(--error); border-radius: var(--radius); padding: 1rem; margin-bottom: 1.5rem; color: var(--error);">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Practice Selection -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
      <h3 style="font-size: 1.125rem; font-weight: 600; color: var(--ink); margin-bottom: 1.5rem;">
        Select Practice
      </h3>

      <form method="GET" id="practice-form">
        <div style="display: flex; gap: 1rem; align-items: flex-end;">
          <div style="flex: 1; max-width: 500px;">
            <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
              Practice / Provider
            </label>
            <select name="practice_id" onchange="this.form.submit()" style="width: 100%; padding: 0.625rem; font-size: 0.875rem;">
              <option value="">-- Select Practice --</option>
              <?php foreach ($practices as $practice): ?>
                <option value="<?= htmlspecialchars($practice['id']) ?>" <?= $selectedPractice === $practice['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($practice['practice_name'] ?? ($practice['first_name'] . ' ' . $practice['last_name'])) ?>
                  (<?= htmlspecialchars($practice['user_type']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </form>
    </div>

    <?php if ($selectedPractice && $practiceDetails): ?>
      <!-- Pricing Management -->
      <form method="POST">
        <input type="hidden" name="action" value="save_pricing">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($selectedPractice) ?>">

        <!-- Catalog-Wide Discount -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem; background: linear-gradient(135deg, var(--brand-light) 0%, var(--brand-lighter) 100%); border: 2px solid var(--brand);">
          <h3 style="font-size: 1.125rem; font-weight: 600; color: var(--brand); margin-bottom: 1rem;">
            Catalog-Wide Discount
          </h3>
          <p style="font-size: 0.875rem; color: var(--ink-light); margin-bottom: 1.5rem;">
            Apply the same discount percentage to all products. This will override any individual product pricing below.
          </p>

          <div style="display: flex; gap: 1rem; align-items: flex-end;">
            <div style="flex: 0 0 200px;">
              <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
                Discount Percentage
              </label>
              <div style="position: relative;">
                <input type="number" name="catalog_discount" id="catalog-discount" step="0.01" min="0" max="100" placeholder="0.00"
                       style="width: 100%; padding-right: 2rem;" onchange="toggleCatalogMode(this.value)">
                <span style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-weight: 600;">%</span>
              </div>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Apply catalog-wide discount? This will override all individual product pricing.')">
              Apply to All Products
            </button>
          </div>
        </div>

        <!-- Individual Product Pricing -->
        <div class="card" style="padding: 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 600; color: var(--ink);">
              Product Pricing (<?= count($products) ?> products)
            </h3>
            <div style="display: flex; gap: 1rem;">
              <?php if (!empty($existingPricing)): ?>
                <button type="button" onclick="if(confirm('Clear all custom pricing for this practice?')) { document.getElementById('clear-form').submit(); }"
                        class="btn" style="color: var(--error);">
                  Clear All Pricing
                </button>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary">
                Save Individual Pricing
              </button>
            </div>
          </div>

          <div style="overflow-x: auto;">
            <table>
              <thead>
                <tr>
                  <th style="width: 40%;">Product</th>
                  <th>Default Price</th>
                  <th>Custom Price/pc</th>
                  <th>Discount %</th>
                  <th>Custom Price/box</th>
                  <th>Savings</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($products as $product): ?>
                  <?php
                    $piecesPerBox = $product['pieces_per_box'] ?? 10;
                    $defaultPricePerPiece = $product['price_wholesale'] ?? 0;
                    $defaultPricePerBox = $defaultPricePerPiece * $piecesPerBox;

                    $existing = $existingPricing[$product['id']] ?? null;
                    $customPricePerPiece = $existing ? $existing['custom_price'] : '';
                    $discount = $existing ? $existing['discount_percentage'] : '';
                  ?>
                  <tr data-product-id="<?= $product['id'] ?>">
                    <td>
                      <div style="font-weight: 500; color: var(--ink);">
                        <?= htmlspecialchars($product['name']) ?>
                      </div>
                      <div style="font-size: 0.75rem; color: var(--muted);">
                        <?= htmlspecialchars($product['size']) ?> - <?= $piecesPerBox ?> pieces/box
                      </div>
                    </td>
                    <td>
                      <div style="font-weight: 500;">$<?= number_format($defaultPricePerBox, 2) ?>/box</div>
                      <div style="font-size: 0.75rem; color: var(--muted);">$<?= number_format($defaultPricePerPiece, 2) ?>/pc</div>
                    </td>
                    <td>
                      <input type="number"
                             name="product_prices[<?= $product['id'] ?>]"
                             class="custom-price-input"
                             data-product-id="<?= $product['id'] ?>"
                             data-default-price="<?= $defaultPricePerPiece ?>"
                             data-pieces-per-box="<?= $piecesPerBox ?>"
                             step="0.01"
                             min="0"
                             placeholder="<?= number_format($defaultPricePerPiece, 2) ?>"
                             value="<?= $customPricePerPiece !== '' ? number_format($customPricePerPiece, 2) : '' ?>"
                             style="width: 100px; text-align: right;"
                             onchange="calculateDiscount(this)">
                    </td>
                    <td>
                      <div style="position: relative;">
                        <input type="number"
                               name="product_discounts[<?= $product['id'] ?>]"
                               class="discount-input"
                               data-product-id="<?= $product['id'] ?>"
                               step="0.01"
                               min="0"
                               max="100"
                               placeholder="0"
                               value="<?= $discount !== '' ? number_format($discount, 2) : '' ?>"
                               style="width: 80px; text-align: right; padding-right: 1.5rem;"
                               onchange="calculatePrice(this)">
                        <span style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 0.75rem;">%</span>
                      </div>
                    </td>
                    <td class="custom-box-price" style="font-weight: 600; color: var(--brand);">
                      <?php if ($customPricePerPiece): ?>
                        $<?= number_format($customPricePerPiece * $piecesPerBox, 2) ?>
                      <?php else: ?>
                        <span style="color: var(--muted);">-</span>
                      <?php endif; ?>
                    </td>
                    <td class="savings">
                      <?php if ($customPricePerPiece): ?>
                        <?php
                          $customPricePerBox = $customPricePerPiece * $piecesPerBox;
                          $savings = $defaultPricePerBox - $customPricePerBox;
                          $savingsPercent = $defaultPricePerBox > 0 ? ($savings / $defaultPricePerBox) * 100 : 0;
                        ?>
                        <?php if ($savings > 0): ?>
                          <span style="color: var(--success); font-weight: 500;">
                            -$<?= number_format($savings, 2) ?> (<?= number_format($savingsPercent, 1) ?>%)
                          </span>
                        <?php elseif ($savings < 0): ?>
                          <span style="color: var(--error); font-weight: 500;">
                            +$<?= number_format(abs($savings), 2) ?>
                          </span>
                        <?php else: ?>
                          <span style="color: var(--muted);">-</span>
                        <?php endif; ?>
                      <?php else: ?>
                        <span style="color: var(--muted);">-</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </form>

      <!-- Hidden form for clearing all pricing -->
      <form method="POST" id="clear-form" style="display: none;">
        <input type="hidden" name="action" value="clear_all">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($selectedPractice) ?>">
      </form>
    <?php endif; ?>

  </div>
</div>

<script>
function calculateDiscount(priceInput) {
  const productId = priceInput.dataset.productId;
  const defaultPrice = parseFloat(priceInput.dataset.defaultPrice);
  const customPrice = parseFloat(priceInput.value) || 0;
  const piecesPerBox = parseInt(priceInput.dataset.piecesPerBox) || 10;

  const discountInput = document.querySelector(`.discount-input[data-product-id="${productId}"]`);
  const boxPriceCell = priceInput.closest('tr').querySelector('.custom-box-price');
  const savingsCell = priceInput.closest('tr').querySelector('.savings');

  if (customPrice > 0 && defaultPrice > 0) {
    const discount = ((defaultPrice - customPrice) / defaultPrice) * 100;
    discountInput.value = discount.toFixed(2);

    // Update box price
    const customBoxPrice = customPrice * piecesPerBox;
    boxPriceCell.innerHTML = `$${customBoxPrice.toFixed(2)}`;

    // Update savings
    const defaultBoxPrice = defaultPrice * piecesPerBox;
    const savings = defaultBoxPrice - customBoxPrice;
    const savingsPercent = (savings / defaultBoxPrice) * 100;

    if (savings > 0) {
      savingsCell.innerHTML = `<span style="color: var(--success); font-weight: 500;">-$${savings.toFixed(2)} (${savingsPercent.toFixed(1)}%)</span>`;
    } else if (savings < 0) {
      savingsCell.innerHTML = `<span style="color: var(--error); font-weight: 500;">+$${Math.abs(savings).toFixed(2)}</span>`;
    } else {
      savingsCell.innerHTML = '<span style="color: var(--muted);">-</span>';
    }
  } else {
    discountInput.value = '';
    boxPriceCell.innerHTML = '<span style="color: var(--muted);">-</span>';
    savingsCell.innerHTML = '<span style="color: var(--muted);">-</span>';
  }
}

function calculatePrice(discountInput) {
  const productId = discountInput.dataset.productId;
  const discount = parseFloat(discountInput.value) || 0;

  const priceInput = document.querySelector(`.custom-price-input[data-product-id="${productId}"]`);
  const defaultPrice = parseFloat(priceInput.dataset.defaultPrice);
  const piecesPerBox = parseInt(priceInput.dataset.piecesPerBox) || 10;

  if (discount > 0 && defaultPrice > 0) {
    const customPrice = defaultPrice * (1 - (discount / 100));
    priceInput.value = customPrice.toFixed(2);

    // Update box price and savings
    const boxPriceCell = priceInput.closest('tr').querySelector('.custom-box-price');
    const savingsCell = priceInput.closest('tr').querySelector('.savings');

    const customBoxPrice = customPrice * piecesPerBox;
    boxPriceCell.innerHTML = `$${customBoxPrice.toFixed(2)}`;

    const defaultBoxPrice = defaultPrice * piecesPerBox;
    const savings = defaultBoxPrice - customBoxPrice;
    const savingsPercent = (savings / defaultBoxPrice) * 100;

    savingsCell.innerHTML = `<span style="color: var(--success); font-weight: 500;">-$${savings.toFixed(2)} (${savingsPercent.toFixed(1)}%)</span>`;
  }
}

function toggleCatalogMode(value) {
  const hasValue = value && parseFloat(value) > 0;
  const productInputs = document.querySelectorAll('.custom-price-input, .discount-input');

  productInputs.forEach(input => {
    input.disabled = hasValue;
    if (hasValue) {
      input.style.opacity = '0.5';
      input.style.cursor = 'not-allowed';
    } else {
      input.style.opacity = '1';
      input.style.cursor = 'text';
    }
  });
}

// Initialize on page load if catalog discount is set
document.addEventListener('DOMContentLoaded', function() {
  const catalogDiscount = document.getElementById('catalog-discount');
  if (catalogDiscount && catalogDiscount.value) {
    toggleCatalogMode(catalogDiscount.value);
  }
});
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
