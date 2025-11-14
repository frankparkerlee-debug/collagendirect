<?php
/**
 * Practice-Specific Pricing Management
 * Allows admins to set custom wholesale pricing for specific practices
 */

require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($action === 'save') {
    try {
      $userId = $_POST['user_id'] ?? '';
      $productId = (int)($_POST['product_id'] ?? 0);
      $customPrice = (float)($_POST['custom_price'] ?? 0);
      $discountPercentage = !empty($_POST['discount_percentage']) ? (float)$_POST['discount_percentage'] : null;
      $notes = $_POST['notes'] ?? '';

      if (empty($userId) || $productId <= 0 || $customPrice <= 0) {
        throw new Exception('Please fill in all required fields');
      }

      // Check if pricing already exists
      $stmt = $pdo->prepare("SELECT id FROM practice_pricing WHERE user_id = ? AND product_id = ?");
      $stmt->execute([$userId, $productId]);
      $existing = $stmt->fetch();

      if ($existing) {
        // Update existing
        $stmt = $pdo->prepare("
          UPDATE practice_pricing
          SET custom_price = ?, discount_percentage = ?, notes = ?, updated_at = NOW(), created_by = ?
          WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$customPrice, $discountPercentage, $notes, $admin['id'], $userId, $productId]);
        $message = 'Custom pricing updated successfully';
      } else {
        // Insert new
        $stmt = $pdo->prepare("
          INSERT INTO practice_pricing (user_id, product_id, custom_price, discount_percentage, notes, created_by)
          VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $productId, $customPrice, $discountPercentage, $notes, $admin['id']]);
        $message = 'Custom pricing added successfully';
      }
    } catch (Exception $e) {
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
  }
}

// Fetch all practice pricing
$stmt = $pdo->query("
  SELECT
    pp.id,
    pp.user_id,
    pp.product_id,
    pp.custom_price,
    pp.discount_percentage,
    pp.notes,
    pp.created_at,
    pp.updated_at,
    u.practice_name,
    u.first_name,
    u.last_name,
    u.user_type,
    p.name AS product_name,
    p.size AS product_size,
    p.price_wholesale AS default_price,
    p.pieces_per_box
  FROM practice_pricing pp
  JOIN users u ON u.id = pp.user_id
  JOIN products p ON p.id = pp.product_id
  ORDER BY u.practice_name ASC, p.name ASC
");
$pricingRules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all practices (for dropdown)
$stmt = $pdo->query("
  SELECT id, practice_name, first_name, last_name, user_type
  FROM users
  WHERE user_type IN ('practice_admin', 'physician', 'dme_hybrid', 'dme_wholesale')
  ORDER BY practice_name ASC, last_name ASC
");
$practices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all products (for dropdown)
$stmt = $pdo->query("
  SELECT id, name, size, price_wholesale, pieces_per_box
  FROM products
  WHERE active = TRUE
  ORDER BY name ASC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
  <div class="container" style="max-width: 1400px; margin: 0 auto; padding: 2rem;">

    <!-- Page Header -->
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
        Practice Pricing
      </h1>
      <p style="color: var(--ink-light); font-size: 0.875rem;">
        Set custom wholesale pricing for specific practices and providers
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

    <!-- Add New Pricing Form -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
      <h3 style="font-size: 1.125rem; font-weight: 600; color: var(--ink); margin-bottom: 1.5rem;">
        Add Custom Pricing
      </h3>

      <form method="POST" action="?action=save">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">

          <!-- Practice Selection -->
          <div>
            <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
              Practice / Provider *
            </label>
            <select name="user_id" required style="width: 100%;">
              <option value="">-- Select Practice --</option>
              <?php foreach ($practices as $practice): ?>
                <option value="<?= htmlspecialchars($practice['id']) ?>">
                  <?= htmlspecialchars($practice['practice_name'] ?? ($practice['first_name'] . ' ' . $practice['last_name'])) ?>
                  (<?= htmlspecialchars($practice['user_type']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Product Selection -->
          <div>
            <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
              Product *
            </label>
            <select name="product_id" id="product-select" required style="width: 100%;">
              <option value="">-- Select Product --</option>
              <?php foreach ($products as $product): ?>
                <?php
                  $pricePerPiece = $product['price_wholesale'] ?? 0;
                  $piecesPerBox = $product['pieces_per_box'] ?? 10;
                  $pricePerBox = $pricePerPiece * $piecesPerBox;
                ?>
                <option value="<?= $product['id'] ?>"
                        data-default-price="<?= number_format($pricePerPiece, 2) ?>"
                        data-pieces-per-box="<?= $piecesPerBox ?>">
                  <?= htmlspecialchars($product['name']) ?>
                  <?= htmlspecialchars($product['size']) ?>
                  (Default: $<?= number_format($pricePerBox, 2) ?>/box)
                </option>
              <?php endforeach; ?>
            </select>
            <small style="color: var(--muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">
              Default wholesale price shown in parentheses
            </small>
          </div>

          <!-- Custom Price -->
          <div>
            <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
              Custom Price (per piece) *
            </label>
            <input type="number" name="custom_price" id="custom-price" step="0.01" min="0.01" required placeholder="0.00" style="width: 100%;">
            <small style="color: var(--muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">
              Price per individual piece
            </small>
          </div>

          <!-- Discount Percentage (Optional) -->
          <div>
            <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
              Discount % (Optional)
            </label>
            <input type="number" name="discount_percentage" id="discount-percentage" step="0.01" min="0" max="100" placeholder="0.00" style="width: 100%;">
            <small style="color: var(--muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">
              For reporting purposes only
            </small>
          </div>

        </div>

        <!-- Notes -->
        <div style="margin-bottom: 1.5rem;">
          <label style="display: block; font-weight: 500; color: var(--ink); margin-bottom: 0.5rem; font-size: 0.875rem;">
            Notes (Optional)
          </label>
          <textarea name="notes" rows="2" placeholder="Reason for custom pricing, agreement details, etc." style="width: 100%; resize: vertical;"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">
          <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
          </svg>
          Add Custom Pricing
        </button>
      </form>
    </div>

    <!-- Existing Pricing Rules -->
    <div class="card" style="padding: 1.5rem;">
      <h3 style="font-size: 1.125rem; font-weight: 600; color: var(--ink); margin-bottom: 1.5rem;">
        Active Pricing Rules (<?= count($pricingRules) ?>)
      </h3>

      <?php if (empty($pricingRules)): ?>
        <div style="text-align: center; padding: 3rem 1rem; color: var(--muted);">
          <svg style="width: 48px; height: 48px; margin: 0 auto 1rem; display: block; color: var(--muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <p style="font-size: 0.875rem;">No custom pricing rules configured yet</p>
          <p style="font-size: 0.75rem; margin-top: 0.5rem;">All practices use default wholesale pricing</p>
        </div>
      <?php else: ?>
        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th>Practice / Provider</th>
                <th>Product</th>
                <th>Default Price</th>
                <th>Custom Price</th>
                <th>Discount %</th>
                <th>Savings</th>
                <th>Notes</th>
                <th>Updated</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pricingRules as $rule): ?>
                <?php
                  $piecesPerBox = $rule['pieces_per_box'] ?? 10;
                  $defaultPricePerPiece = $rule['default_price'] ?? 0;
                  $customPricePerPiece = $rule['custom_price'];
                  $defaultPricePerBox = $defaultPricePerPiece * $piecesPerBox;
                  $customPricePerBox = $customPricePerPiece * $piecesPerBox;
                  $savings = $defaultPricePerBox - $customPricePerBox;
                  $savingsPercent = $defaultPricePerBox > 0 ? ($savings / $defaultPricePerBox) * 100 : 0;
                ?>
                <tr>
                  <td>
                    <div style="font-weight: 500; color: var(--ink);">
                      <?= htmlspecialchars($rule['practice_name'] ?? ($rule['first_name'] . ' ' . $rule['last_name'])) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--muted);">
                      <?= htmlspecialchars($rule['user_type']) ?>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight: 500; color: var(--ink);">
                      <?= htmlspecialchars($rule['product_name']) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--muted);">
                      <?= htmlspecialchars($rule['product_size']) ?> (<?= $piecesPerBox ?> pcs/box)
                    </div>
                  </td>
                  <td>
                    <div>$<?= number_format($defaultPricePerBox, 2) ?>/box</div>
                    <div style="font-size: 0.75rem; color: var(--muted);">$<?= number_format($defaultPricePerPiece, 2) ?>/pc</div>
                  </td>
                  <td style="font-weight: 600; color: var(--brand);">
                    <div>$<?= number_format($customPricePerBox, 2) ?>/box</div>
                    <div style="font-size: 0.75rem; color: var(--muted);">$<?= number_format($customPricePerPiece, 2) ?>/pc</div>
                  </td>
                  <td>
                    <?= $rule['discount_percentage'] ? number_format($rule['discount_percentage'], 1) . '%' : '-' ?>
                  </td>
                  <td>
                    <?php if ($savings > 0): ?>
                      <span style="color: var(--success); font-weight: 500;">
                        -$<?= number_format($savings, 2) ?>
                        (<?= number_format($savingsPercent, 1) ?>%)
                      </span>
                    <?php elseif ($savings < 0): ?>
                      <span style="color: var(--error); font-weight: 500;">
                        +$<?= number_format(abs($savings), 2) ?>
                      </span>
                    <?php else: ?>
                      <span style="color: var(--muted);">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($rule['notes']): ?>
                      <div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($rule['notes']) ?>">
                        <?= htmlspecialchars($rule['notes']) ?>
                      </div>
                    <?php else: ?>
                      <span style="color: var(--muted);">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="font-size: 0.75rem; color: var(--muted);">
                      <?= date('M j, Y', strtotime($rule['updated_at'] ?? $rule['created_at'])) ?>
                    </div>
                  </td>
                  <td>
                    <form method="POST" action="?action=delete" style="display: inline;" onsubmit="return confirm('Remove this custom pricing rule?');">
                      <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                      <button type="submit" class="btn" style="color: var(--error); padding: 0.25rem 0.625rem; font-size: 0.75rem;">
                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Remove
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
// Calculate discount percentage when custom price is entered
document.getElementById('product-select').addEventListener('change', function() {
  const selected = this.options[this.selectedIndex];
  const defaultPrice = parseFloat(selected.dataset.defaultPrice) || 0;

  document.getElementById('custom-price').addEventListener('input', function() {
    const customPrice = parseFloat(this.value) || 0;
    if (defaultPrice > 0 && customPrice > 0) {
      const discount = ((defaultPrice - customPrice) / defaultPrice) * 100;
      if (discount > 0) {
        document.getElementById('discount-percentage').value = discount.toFixed(2);
      }
    }
  });
});
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
