<?php
/**
 * Migration: Add price_referral column to products table
 *
 * This column stores the Medicare allowable rate per piece for referral orders
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_header.php';

require_admin();

$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
  try {
    $pdo->beginTransaction();

    // Check if column already exists
    $checkCol = $pdo->query("
      SELECT column_name
      FROM information_schema.columns
      WHERE table_name = 'products' AND column_name = 'price_referral'
    ")->fetchColumn();

    if (!$checkCol) {
      // Add price_referral column
      $pdo->exec("
        ALTER TABLE products
        ADD COLUMN price_referral DECIMAL(10,2) DEFAULT 0.00
      ");

      $pdo->exec("
        COMMENT ON COLUMN products.price_referral IS 'Medicare allowable rate per piece for referral orders'
      ");

      $success = true;
      $message = "✓ Added price_referral column to products table";
    } else {
      $success = true;
      $message = "✓ Column price_referral already exists - no migration needed";
    }

    $pdo->commit();

  } catch (Exception $e) {
    $pdo->rollBack();
    $error = $e->getMessage();
  }
}

// Get current schema
$schema = $pdo->query("
  SELECT column_name, data_type, is_nullable, column_default
  FROM information_schema.columns
  WHERE table_name = 'products'
  ORDER BY ordinal_position
")->fetchAll(PDO::FETCH_ASSOC);

$hasPriceReferral = false;
foreach ($schema as $col) {
  if ($col['column_name'] === 'price_referral') {
    $hasPriceReferral = true;
    break;
  }
}
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
  <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
    Add price_referral Column
  </h1>
  <p style="color: var(--muted); font-size: 0.875rem; margin-bottom: 2rem;">
    This migration adds the price_referral column to store Medicare allowable rates per piece
  </p>

  <?php if ($success): ?>
    <div style="padding: 1rem; background: #d1fae5; border: 1px solid #10b981; border-radius: 6px; margin-bottom: 2rem;">
      <strong style="color: #065f46;"><?= $message ?></strong>
      <div style="margin-top: 1rem;">
        <a href="/admin/products.php" class="btn" style="background: var(--brand); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block;">
          Go to Products →
        </a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div style="padding: 1rem; background: #fee; border: 1px solid #dc3545; border-radius: 6px; margin-bottom: 2rem; color: #991b1b;">
      <strong>Migration Failed:</strong><br><?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if (!$hasPriceReferral && !$success): ?>
    <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem; margin-bottom: 2rem;">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem; color: #991b1b;">
        ⚠️ Missing Column Detected
      </h2>
      <p style="color: var(--ink-light); margin-bottom: 1.5rem;">
        The <code style="background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 4px;">price_referral</code> column is missing from the products table.
        This column is required for storing Medicare allowable rates for referral orders.
      </p>

      <form method="POST">
        <button type="submit" name="run_migration" value="1"
                style="padding: 0.875rem 2rem; font-size: 1rem; font-weight: 600; background: var(--brand); color: white; border: none; border-radius: 6px; cursor: pointer;">
          Run Migration
        </button>
      </form>
    </div>
  <?php endif; ?>

  <!-- Current Schema -->
  <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem;">
    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">
      Current Products Table Schema
    </h2>

    <div style="overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
        <thead>
          <tr style="background: #f8f9fa; border-bottom: 2px solid var(--border);">
            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 0.75rem;">
              Column Name
            </th>
            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 0.75rem;">
              Data Type
            </th>
            <th style="padding: 0.75rem; text-align: center; font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 0.75rem;">
              Nullable
            </th>
            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 0.75rem;">
              Default
            </th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($schema as $col): ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 0.75rem;">
                <code style="background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 4px; <?= $col['column_name'] === 'price_referral' ? 'color: #10b981; font-weight: 600;' : '' ?>">
                  <?= htmlspecialchars($col['column_name']) ?>
                </code>
              </td>
              <td style="padding: 0.75rem; color: var(--ink-light);">
                <?= htmlspecialchars($col['data_type']) ?>
              </td>
              <td style="padding: 0.75rem; text-align: center;">
                <?= $col['is_nullable'] === 'YES' ? '✓' : '✗' ?>
              </td>
              <td style="padding: 0.75rem; color: var(--muted); font-size: 0.8125rem;">
                <?= $col['column_default'] ? htmlspecialchars($col['column_default']) : '-' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
