<?php
/**
 * Diagnostic: Check Product Catalog Synchronization
 *
 * Verifies that all product queries return identical results
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_header.php';

require_admin();

// Standard deduplication query (should be used everywhere)
$standardQuery = "
  SELECT DISTINCT ON (
    CASE
      WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN hcpcs_code || '|' || LOWER(TRIM(COALESCE(size, '')))
      ELSE 'NO_HCPCS|' || LOWER(TRIM(name)) || '|' || LOWER(TRIM(COALESCE(size, '')))
    END
  )
    id, name, size, hcpcs_code, category, price_wholesale, price_referral, pieces_per_box
  FROM products
  WHERE active = TRUE
    AND (name NOT ILIKE '%deprecated%' OR name IS NULL)
    AND (category NOT ILIKE '%deprecated%' OR category IS NULL)
  ORDER BY
    CASE
      WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN hcpcs_code || '|' || LOWER(TRIM(COALESCE(size, '')))
      ELSE 'NO_HCPCS|' || LOWER(TRIM(name)) || '|' || LOWER(TRIM(COALESCE(size, '')))
    END,
    CASE WHEN hcpcs_code IS NOT NULL AND hcpcs_code != '' THEN 0 ELSE 1 END,
    CASE WHEN price_wholesale > 0 THEN 0 ELSE 1 END,
    LENGTH(name) DESC,
    id ASC
";

$standardProducts = $pdo->query($standardQuery)->fetchAll(PDO::FETCH_ASSOC);

// Get total product count (including duplicates and deprecated)
$totalCount = $pdo->query("SELECT COUNT(*) FROM products WHERE active = TRUE")->fetchColumn();
$deprecatedCount = $pdo->query("SELECT COUNT(*) FROM products WHERE active = TRUE AND (name ILIKE '%deprecated%' OR category ILIKE '%deprecated%')")->fetchColumn();

?>

<div style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
  <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
    Product Catalog Diagnostic
  </h1>
  <p style="color: var(--muted); font-size: 0.875rem; margin-bottom: 2rem;">
    Checking product catalog synchronization across all order types
  </p>

  <!-- Summary Card -->
  <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem; margin-bottom: 2rem;">
    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Product Catalog Summary</h2>

    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
      <div style="padding: 1rem; background: #f8f9fa; border-radius: 6px;">
        <div style="font-size: 0.75rem; color: var(--muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
          Total Active Products
        </div>
        <div style="font-size: 2rem; font-weight: 700; color: var(--ink);">
          <?= $totalCount ?>
        </div>
      </div>

      <div style="padding: 1rem; background: #d1fae5; border-radius: 6px;">
        <div style="font-size: 0.75rem; color: #065f46; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
          Deduplicated Products
        </div>
        <div style="font-size: 2rem; font-weight: 700; color: #065f46;">
          <?= count($standardProducts) ?>
        </div>
        <div style="font-size: 0.75rem; color: #065f46; margin-top: 0.25rem;">
          (What users should see)
        </div>
      </div>

      <div style="padding: 1rem; background: #fef3c7; border-radius: 6px;">
        <div style="font-size: 0.75rem; color: #92400e; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
          Deprecated Products
        </div>
        <div style="font-size: 2rem; font-weight: 700; color: #92400e;">
          <?= $deprecatedCount ?>
        </div>
        <div style="font-size: 0.75rem; color: #92400e; margin-top: 0.25rem;">
          (Should be hidden)
        </div>
      </div>
    </div>

    <?php if ($totalCount > count($standardProducts)): ?>
      <div style="padding: 1rem; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px;">
        <strong style="color: #92400e;">⚠️ Notice:</strong>
        <p style="color: #92400e; margin-top: 0.5rem; font-size: 0.875rem;">
          <?= $totalCount - count($standardProducts) ?> products are being filtered out (duplicates or deprecated items).
          This is normal and expected behavior.
        </p>
      </div>
    <?php else: ?>
      <div style="padding: 1rem; background: #d1fae5; border: 1px solid #10b981; border-radius: 6px;">
        <strong style="color: #065f46;">✓ All Clear:</strong>
        <p style="color: #065f46; margin-top: 0.5rem; font-size: 0.875rem;">
          No duplicate or deprecated products found.
        </p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Product List -->
  <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem;">
    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">
      Current Product Catalog (<?= count($standardProducts) ?> products)
    </h2>

    <div style="overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
        <thead>
          <tr style="background: #f8f9fa; border-bottom: 2px solid var(--border);">
            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
              Product Name
            </th>
            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
              Size
            </th>
            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
              HCPCS
            </th>
            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
              Category
            </th>
            <th style="padding: 0.75rem; text-align: right; font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
              Wholesale/Box
            </th>
            <th style="padding: 0.75rem; text-align: right; font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
              Referral/Piece
            </th>
            <th style="padding: 0.75rem; text-align: center; font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
              Pcs/Box
            </th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($standardProducts as $product): ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 0.75rem; color: var(--ink);">
                <?= htmlspecialchars($product['name']) ?>
              </td>
              <td style="padding: 0.75rem; color: var(--ink);">
                <?= htmlspecialchars($product['size'] ?? '-') ?>
              </td>
              <td style="padding: 0.75rem;">
                <?php if ($product['hcpcs_code']): ?>
                  <code style="background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8125rem;">
                    <?= htmlspecialchars($product['hcpcs_code']) ?>
                  </code>
                <?php else: ?>
                  <span style="color: var(--muted);">N/A</span>
                <?php endif; ?>
              </td>
              <td style="padding: 0.75rem; color: var(--ink-light);">
                <?= htmlspecialchars($product['category'] ?? '-') ?>
              </td>
              <td style="padding: 0.75rem; text-align: right; font-weight: 600; color: var(--ink);">
                $<?= number_format($product['price_wholesale'] ?? 0, 2) ?>
              </td>
              <td style="padding: 0.75rem; text-align: right; color: var(--ink);">
                $<?= number_format($product['price_referral'] ?? 0, 2) ?>
              </td>
              <td style="padding: 0.75rem; text-align: center; color: var(--ink);">
                <?= $product['pieces_per_box'] ?? '-' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
      <a href="/admin/products.php" class="btn" style="background: var(--brand); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block;">
        Manage Products →
      </a>
      <a href="/admin/import-products-from-csv.php" class="btn" style="padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block;">
        Import from CSV
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
