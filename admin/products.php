<?php
/**
 * Product Management - Single Source of Truth
 *
 * Add, edit, delete, and manage all products from this admin UI.
 * After initial CSV import, this becomes the authoritative source.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_admin();

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'create') {
      $stmt = $pdo->prepare("
        INSERT INTO products (
          name, size, sku, category, hcpcs_code,
          price_wholesale, price_referral, pieces_per_box,
          can_be_primary, can_be_secondary, can_be_additional,
          active, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, NOW())
      ");

      $stmt->execute([
        $_POST['name'],
        $_POST['size'],
        $_POST['sku'],
        $_POST['category'],
        $_POST['hcpcs_code'] ?: null,
        (float)$_POST['price_wholesale'],
        (float)$_POST['price_referral'],
        (int)$_POST['pieces_per_box'],
        isset($_POST['can_be_primary']),
        isset($_POST['can_be_secondary']),
        isset($_POST['can_be_additional'])
      ]);

      $_SESSION['success_msg'] = 'Product created successfully';
      header('Location: /admin/products.php');
      exit;

    } elseif ($action === 'update') {
      $stmt = $pdo->prepare("
        UPDATE products SET
          name = ?,
          size = ?,
          sku = ?,
          category = ?,
          hcpcs_code = ?,
          price_wholesale = ?,
          price_referral = ?,
          pieces_per_box = ?,
          can_be_primary = ?,
          can_be_secondary = ?,
          can_be_additional = ?,
          active = ?
        WHERE id = ?
      ");

      $stmt->execute([
        $_POST['name'],
        $_POST['size'],
        $_POST['sku'],
        $_POST['category'],
        $_POST['hcpcs_code'] ?: null,
        (float)$_POST['price_wholesale'],
        (float)$_POST['price_referral'],
        (int)$_POST['pieces_per_box'],
        isset($_POST['can_be_primary']),
        isset($_POST['can_be_secondary']),
        isset($_POST['can_be_additional']),
        isset($_POST['active']),
        (int)$_POST['product_id']
      ]);

      $_SESSION['success_msg'] = 'Product updated successfully';
      header('Location: /admin/products.php');
      exit;

    } elseif ($action === 'delete') {
      $productId = (int)$_POST['product_id'];

      // Soft delete - mark as inactive instead of deleting
      $stmt = $pdo->prepare("UPDATE products SET active = FALSE WHERE id = ?");
      $stmt->execute([$productId]);

      $_SESSION['success_msg'] = 'Product deactivated successfully';
      header('Location: /admin/products.php');
      exit;
    }

  } catch (Exception $e) {
    $_SESSION['error_msg'] = 'Error: ' . $e->getMessage();
    header('Location: /admin/products.php');
    exit;
  }
}

require_once __DIR__ . '/_header.php';

// Get all products (including inactive for admin view)
$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === '1';
$whereClause = $showInactive ? '' : 'WHERE active = TRUE';

$products = $pdo->query("
  SELECT * FROM products
  $whereClause
  ORDER BY
    CASE WHEN active = TRUE THEN 0 ELSE 1 END,
    category ASC,
    name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get product being edited
$editProduct = null;
if (isset($_GET['edit'])) {
  $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
  $stmt->execute([(int)$_GET['edit']]);
  $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Check if products table is empty
$productCount = $pdo->query("SELECT COUNT(*) FROM products WHERE active = TRUE")->fetchColumn();
?>

<div class="main-content">
  <div style="max-width: 1600px; margin: 0 auto; padding: 2rem;">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
      <div>
        <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
          Product Management
        </h1>
        <p style="color: var(--muted); font-size: 0.875rem;">
          Single source of truth for all product pricing and configuration
        </p>
      </div>
      <div style="display: flex; gap: 1rem;">
        <?php if ($productCount === 0): ?>
          <a href="/admin/import-products-from-csv.php"
             style="padding: 0.75rem 1.5rem; background: #3b82f6; color: white; border-radius: 6px; text-decoration: none; font-weight: 500;">
            📥 Import from CSV
          </a>
        <?php endif; ?>
        <a href="?show_inactive=<?= $showInactive ? '0' : '1' ?>"
           style="padding: 0.75rem 1.5rem; background: white; border: 1px solid var(--border); color: var(--ink); border-radius: 6px; text-decoration: none; font-weight: 500;">
          <?= $showInactive ? '👁️ Hide Inactive' : '👁️‍🗨️ Show Inactive' ?>
        </a>
        <button onclick="document.getElementById('add-modal').style.display='flex'"
                style="padding: 0.75rem 1.5rem; background: var(--brand); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
          + Add Product
        </button>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_msg'])): ?>
      <div style="padding: 1rem; background: #d1fae5; border: 1px solid #10b981; border-radius: 6px; margin-bottom: 1.5rem; color: #065f46;">
        ✓ <?= htmlspecialchars($_SESSION['success_msg']) ?>
      </div>
      <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
      <div style="padding: 1rem; background: #fee; border: 1px solid #dc3545; border-radius: 6px; margin-bottom: 1.5rem; color: #991b1b;">
        ✗ <?= htmlspecialchars($_SESSION['error_msg']) ?>
      </div>
      <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <!-- Empty State -->
    <?php if ($productCount === 0 && !$showInactive): ?>
      <div style="background: white; border: 2px dashed var(--border); border-radius: 8px; padding: 3rem; text-align: center;">
        <div style="font-size: 4rem; margin-bottom: 1rem;">📦</div>
        <h2 style="font-size: 1.5rem; font-weight: 600; color: var(--ink); margin-bottom: 0.5rem;">No Products Yet</h2>
        <p style="color: var(--muted); margin-bottom: 2rem;">Import products from your CSV file to get started</p>
        <a href="/admin/import-products-from-csv.php"
           style="padding: 1rem 2rem; background: var(--brand); color: white; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 1.125rem; display: inline-block;">
          📥 Import from Dressing Rule Matrix CSV
        </a>
      </div>
    <?php else: ?>

      <!-- Products Table -->
      <div style="background: white; border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">
        <div style="overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
            <thead>
              <tr style="background: #f8f9fa; border-bottom: 2px solid var(--border);">
                <th style="padding: 0.875rem; text-align: left; font-weight: 600;">Product Name</th>
                <th style="padding: 0.875rem; text-align: left; font-weight: 600;">Size</th>
                <th style="padding: 0.875rem; text-align: left; font-weight: 600;">SKU</th>
                <th style="padding: 0.875rem; text-align: left; font-weight: 600;">HCPCS</th>
                <th style="padding: 0.875rem; text-align: right; font-weight: 600;">Wholesale/Box</th>
                <th style="padding: 0.875rem; text-align: right; font-weight: 600;">Referral/Piece</th>
                <th style="padding: 0.875rem; text-align: center; font-weight: 600;">Pcs/Box</th>
                <th style="padding: 0.875rem; text-align: center; font-weight: 600;">Primary</th>
                <th style="padding: 0.875rem; text-align: center; font-weight: 600;">Secondary</th>
                <th style="padding: 0.875rem; text-align: center; font-weight: 600;">Additional</th>
                <th style="padding: 0.875rem; text-align: center; font-weight: 600;">Status</th>
                <th style="padding: 0.875rem; text-align: right; font-weight: 600;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $product): ?>
                <tr style="border-bottom: 1px solid var(--border); <?= $product['active'] ? '' : 'opacity: 0.5; background: #f9fafb;' ?>">
                  <td style="padding: 0.875rem; font-weight: 500;"><?= htmlspecialchars($product['name']) ?></td>
                  <td style="padding: 0.875rem;"><?= htmlspecialchars($product['size'] ?? '-') ?></td>
                  <td style="padding: 0.875rem;"><code style="font-size: 0.75rem; background: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 3px;"><?= htmlspecialchars($product['sku'] ?? '-') ?></code></td>
                  <td style="padding: 0.875rem;"><code><?= htmlspecialchars($product['hcpcs_code'] ?? '-') ?></code></td>
                  <td style="padding: 0.875rem; text-align: right; font-weight: 600; color: var(--brand);">$<?= number_format($product['price_wholesale'] ?? 0, 2) ?></td>
                  <td style="padding: 0.875rem; text-align: right;">$<?= number_format($product['price_referral'] ?? 0, 2) ?></td>
                  <td style="padding: 0.875rem; text-align: center;"><?= $product['pieces_per_box'] ?? 10 ?></td>
                  <td style="padding: 0.875rem; text-align: center;"><?= $product['can_be_primary'] ? '✓' : '—' ?></td>
                  <td style="padding: 0.875rem; text-align: center;"><?= $product['can_be_secondary'] ? '✓' : '—' ?></td>
                  <td style="padding: 0.875rem; text-align: center;"><?= $product['can_be_additional'] ? '✓' : '—' ?></td>
                  <td style="padding: 0.875rem; text-align: center;">
                    <span style="padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; <?= $product['active'] ? 'background: #d1fae5; color: #065f46;' : 'background: #fee; color: #991b1b;' ?>">
                      <?= $product['active'] ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td style="padding: 0.875rem; text-align: right;">
                    <a href="?edit=<?= $product['id'] ?>"
                       style="padding: 0.375rem 0.75rem; background: #3b82f6; color: white; border-radius: 4px; text-decoration: none; font-size: 0.75rem; margin-right: 0.5rem;">
                      Edit
                    </a>
                    <?php if ($product['active']): ?>
                      <button onclick="confirmDelete(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>')"
                              style="padding: 0.375rem 0.75rem; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.75rem;">
                        Deactivate
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div style="padding: 1rem; background: #f8f9fa; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
          <div style="color: var(--muted); font-size: 0.875rem;">
            Total: <strong><?= count($products) ?></strong> products
            <?php if (!$showInactive): ?>
              (<?= $productCount ?> active)
            <?php endif; ?>
          </div>
          <a href="/admin/import-products-from-csv.php" style="color: var(--brand); text-decoration: none; font-size: 0.875rem; font-weight: 500;">
            Re-import from CSV →
          </a>
        </div>
      </div>

    <?php endif; ?>

  </div>
</div>

<!-- Add Product Modal -->
<div id="add-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
  <div style="background: white; border-radius: 8px; padding: 2rem; max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto;">
    <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">Add New Product</h2>

    <form method="POST">
      <input type="hidden" name="action" value="create">

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Product Name *</label>
          <input type="text" name="name" required style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Size *</label>
          <input type="text" name="size" required style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">SKU *</label>
          <input type="text" name="sku" required style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Category</label>
          <input type="text" name="category" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">HCPCS Code</label>
          <input type="text" name="hcpcs_code" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Pieces per Box *</label>
          <input type="number" name="pieces_per_box" required value="10" min="1" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Wholesale Price per Box *</label>
          <input type="number" name="price_wholesale" required step="0.01" min="0" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Referral Price per Piece *</label>
          <input type="number" name="price_referral" required step="0.01" min="0" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Product Categories</label>
        <div style="display: flex; gap: 2rem;">
          <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
            <input type="checkbox" name="can_be_primary"> Can be Primary Dressing
          </label>
          <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
            <input type="checkbox" name="can_be_secondary"> Can be Secondary Dressing
          </label>
          <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
            <input type="checkbox" name="can_be_additional"> Can be Additional Supply
          </label>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
        <button type="button" onclick="document.getElementById('add-modal').style.display='none'"
                style="padding: 0.75rem 1.5rem; background: white; border: 1px solid var(--border); color: var(--ink); border-radius: 6px; cursor: pointer;">
          Cancel
        </button>
        <button type="submit"
                style="padding: 0.75rem 1.5rem; background: var(--brand); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
          Create Product
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Product Modal -->
<?php if ($editProduct): ?>
<div id="edit-modal" style="display: flex; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
  <div style="background: white; border-radius: 8px; padding: 2rem; max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto;">
    <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">Edit Product</h2>

    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Product Name *</label>
          <input type="text" name="name" required value="<?= htmlspecialchars($editProduct['name']) ?>" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Size *</label>
          <input type="text" name="size" required value="<?= htmlspecialchars($editProduct['size'] ?? '') ?>" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">SKU *</label>
          <input type="text" name="sku" required value="<?= htmlspecialchars($editProduct['sku'] ?? '') ?>" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Category</label>
          <input type="text" name="category" value="<?= htmlspecialchars($editProduct['category'] ?? '') ?>" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">HCPCS Code</label>
          <input type="text" name="hcpcs_code" value="<?= htmlspecialchars($editProduct['hcpcs_code'] ?? '') ?>" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Pieces per Box *</label>
          <input type="number" name="pieces_per_box" required value="<?= $editProduct['pieces_per_box'] ?? 10 ?>" min="1" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Wholesale Price per Box *</label>
          <input type="number" name="price_wholesale" required value="<?= $editProduct['price_wholesale'] ?? 0 ?>" step="0.01" min="0" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Referral Price per Piece *</label>
          <input type="number" name="price_referral" required value="<?= $editProduct['price_referral'] ?? 0 ?>" step="0.01" min="0" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;">
        </div>
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Product Categories</label>
        <div style="display: flex; gap: 2rem;">
          <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
            <input type="checkbox" name="can_be_primary" <?= $editProduct['can_be_primary'] ? 'checked' : '' ?>> Can be Primary Dressing
          </label>
          <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
            <input type="checkbox" name="can_be_secondary" <?= $editProduct['can_be_secondary'] ? 'checked' : '' ?>> Can be Secondary Dressing
          </label>
          <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
            <input type="checkbox" name="can_be_additional" <?= $editProduct['can_be_additional'] ? 'checked' : '' ?>> Can be Additional Supply
          </label>
        </div>
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
          <input type="checkbox" name="active" <?= $editProduct['active'] ? 'checked' : '' ?>> Active (visible to users)
        </label>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
        <a href="/admin/products.php"
           style="padding: 0.75rem 1.5rem; background: white; border: 1px solid var(--border); color: var(--ink); border-radius: 6px; text-decoration: none;">
          Cancel
        </a>
        <button type="submit"
                style="padding: 0.75rem 1.5rem; background: var(--brand); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
          Save Changes
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function confirmDelete(productId, productName) {
  if (confirm(`Are you sure you want to deactivate "${productName}"?\n\nThis will hide it from all product lists but preserve historical data.`)) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="product_id" value="${productId}">
    `;
    document.body.appendChild(form);
    form.submit();
  }
}
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
