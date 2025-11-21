<?php
/**
 * One-Time Import: Populate Products from Dressing Rule Matrix (11.21)
 *
 * This script reads the CSV file and populates the products table.
 * After running once, the admin UI becomes the source of truth.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_header.php';

require_admin();

// Path to CSV file
$csvPath = '/Users/parkerlee/Desktop/Support Documents/Dressing Rule Matrix (11.21).csv';

if (!file_exists($csvPath)) {
  die("<div class='main-content'><div class='alert alert-danger'>CSV file not found at: $csvPath</div></div>");
}
?>

<div class="main-content">
  <div style="max-width: 1200px; margin: 0 auto; padding: 2rem;">

    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
        Import Products from CSV
      </h1>
      <p style="color: var(--muted); font-size: 0.875rem;">
        One-time import from Dressing Rule Matrix (11.21). This will populate the products table.
      </p>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])): ?>

      <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem; margin-bottom: 2rem;">
        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Import Results</h2>

        <?php
        try {
          $pdo->beginTransaction();

          // Read CSV
          $file = fopen($csvPath, 'r');
          $headers = fgetcsv($file); // Skip header row

          $imported = 0;
          $updated = 0;
          $skipped = 0;
          $errors = [];

          echo "<div style='font-family: monospace; font-size: 0.875rem; background: #f8f9fa; padding: 1rem; border-radius: 4px; max-height: 400px; overflow-y: auto;'>";

          while (($row = fgetcsv($file)) !== false) {
            // Skip empty rows
            if (empty($row[4]) || empty($row[5])) {
              continue;
            }

            // Parse CSV columns (matching updated header structure)
            $brand = trim($row[0]);
            $can_be_primary = strtoupper(trim($row[1])) === 'YES';
            $can_be_secondary = strtoupper(trim($row[2])) === 'YES';
            $can_be_additional = strtoupper(trim($row[3])) === 'YES';
            $product_name = trim($row[4]);
            $size = trim($row[5]);
            $hcpcs_code = trim($row[6]) === 'N/A' ? null : trim($row[6]);
            $ref_number = trim($row[10]);
            $price_per_piece_str = trim($row[11]);
            $pieces_per_box = (int)trim($row[12]);
            $price_per_box_str = trim($row[13]);
            $price_referral_str = trim($row[14]);

            // Parse prices (remove $ and commas)
            $price_per_piece = (float)str_replace(['$', ',', ' '], '', $price_per_piece_str);
            $price_per_box = (float)str_replace(['$', ',', ' '], '', $price_per_box_str);
            $price_referral = (float)str_replace(['$', ',', ' '], '', $price_referral_str);

            // Build full product name: "Brand Product Size" (e.g., "AlgiHeal Calcium Alginate 2x2")
            $full_name = trim("$brand $product_name $size");

            // Generate SKU from ref number or construct one
            $sku = $ref_number ?: strtoupper(substr($brand, 0, 3) . '-' . substr($product_name, 0, 3) . '-' . $size);

            // Set category based on brand
            $category = strtolower($brand);

            try {
              // Check if product exists by name+size or SKU
              $checkStmt = $pdo->prepare("
                SELECT id FROM products
                WHERE (name = ? AND size = ?) OR sku = ?
              ");
              $checkStmt->execute([$full_name, $size, $sku]);
              $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

              if ($existing) {
                // UPDATE existing product
                $updateStmt = $pdo->prepare("
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
                    active = TRUE
                  WHERE id = ?
                ");

                $updateStmt->execute([
                  $full_name,
                  $size,
                  $sku,
                  $category,
                  $hcpcs_code,
                  $price_per_box, // price_wholesale is per BOX
                  $price_referral, // price_referral is per PIECE
                  $pieces_per_box,
                  $can_be_primary,
                  $can_be_secondary,
                  $can_be_additional,
                  $existing['id']
                ]);

                echo "✓ <span style='color: #f59e0b;'>UPDATED:</span> $full_name ($sku)<br>";
                $updated++;

              } else {
                // INSERT new product
                $insertStmt = $pdo->prepare("
                  INSERT INTO products (
                    name, size, sku, category, hcpcs_code,
                    price_wholesale, price_referral, pieces_per_box,
                    can_be_primary, can_be_secondary, can_be_additional,
                    active, created_at
                  ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, NOW()
                  )
                ");

                $insertStmt->execute([
                  $full_name,
                  $size,
                  $sku,
                  $category,
                  $hcpcs_code,
                  $price_per_box, // price_wholesale is per BOX
                  $price_referral, // price_referral is per PIECE
                  $pieces_per_box,
                  $can_be_primary,
                  $can_be_secondary,
                  $can_be_additional
                ]);

                echo "✓ <span style='color: #10b981;'>IMPORTED:</span> $full_name ($sku) - \$$price_per_box/box<br>";
                $imported++;
              }

            } catch (Exception $e) {
              $errors[] = "Error with $full_name: " . $e->getMessage();
              echo "✗ <span style='color: #dc3545;'>ERROR:</span> $full_name - " . $e->getMessage() . "<br>";
              $skipped++;
            }
          }

          fclose($file);
          echo "</div>";

          $pdo->commit();

          echo "<div style='margin-top: 1.5rem; padding: 1rem; background: #d1fae5; border: 1px solid #10b981; border-radius: 4px;'>";
          echo "<strong style='color: #065f46;'>✓ Import Complete!</strong><br>";
          echo "<div style='margin-top: 0.5rem; color: #065f46;'>";
          echo "• <strong>$imported</strong> products imported<br>";
          echo "• <strong>$updated</strong> products updated<br>";
          if ($skipped > 0) {
            echo "• <strong>$skipped</strong> products skipped (errors)<br>";
          }
          echo "</div></div>";

          if (!empty($errors)) {
            echo "<div style='margin-top: 1rem; padding: 1rem; background: #fee; border: 1px solid #dc3545; border-radius: 4px;'>";
            echo "<strong style='color: #991b1b;'>Errors:</strong><br>";
            foreach ($errors as $error) {
              echo "<div style='color: #991b1b; font-size: 0.875rem;'>• $error</div>";
            }
            echo "</div>";
          }

          echo "<div style='margin-top: 1.5rem;'>";
          echo "<a href='/admin/products.php' class='btn' style='background: var(--brand); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block;'>Go to Product Management →</a>";
          echo "</div>";

        } catch (Exception $e) {
          $pdo->rollBack();
          echo "<div style='padding: 1rem; background: #fee; border: 1px solid #dc3545; border-radius: 4px; color: #991b1b;'>";
          echo "<strong>Import Failed:</strong><br>" . $e->getMessage();
          echo "</div>";
        }
        ?>
      </div>

    <?php else: ?>

      <!-- Preview and Confirmation -->
      <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem; margin-bottom: 2rem;">
        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">CSV Preview</h2>

        <?php
        $file = fopen($csvPath, 'r');
        $headers = fgetcsv($file);
        $rows = [];
        $count = 0;
        while (($row = fgetcsv($file)) !== false && $count < 30) {
          if (!empty($row[4])) {
            $rows[] = $row;
            $count++;
          }
        }
        fclose($file);
        ?>

        <div style="overflow-x: auto; margin-bottom: 1.5rem;">
          <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
            <thead>
              <tr style="background: #f8f9fa; border-bottom: 2px solid var(--border);">
                <th style="padding: 0.75rem; text-align: left;">Product Name</th>
                <th style="padding: 0.75rem; text-align: left;">Size</th>
                <th style="padding: 0.75rem; text-align: left;">HCPCS</th>
                <th style="padding: 0.75rem; text-align: right;">Wholesale/Box</th>
                <th style="padding: 0.75rem; text-align: right;">Referral/Piece</th>
                <th style="padding: 0.75rem; text-align: center;">Pcs/Box</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr style="border-bottom: 1px solid var(--border);">
                  <td style="padding: 0.75rem;"><?= htmlspecialchars(trim($row[0] . ' ' . $row[4])) ?></td>
                  <td style="padding: 0.75rem;"><?= htmlspecialchars(trim($row[5])) ?></td>
                  <td style="padding: 0.75rem;"><code><?= htmlspecialchars(trim($row[6])) ?></code></td>
                  <td style="padding: 0.75rem; text-align: right; font-weight: 600;"><?= htmlspecialchars(trim($row[13])) ?></td>
                  <td style="padding: 0.75rem; text-align: right;"><?= htmlspecialchars(trim($row[14])) ?></td>
                  <td style="padding: 0.75rem; text-align: center;"><?= htmlspecialchars(trim($row[12])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div style="padding: 1rem; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 4px; margin-bottom: 1.5rem;">
          <strong style="color: #92400e;">⚠️ Warning:</strong>
          <p style="color: #92400e; margin-top: 0.5rem; font-size: 0.875rem;">
            This will import <?= count($rows) ?> products from the CSV. Existing products with matching names/SKUs will be updated.
            After importing, manage products via the <a href="/admin/products.php" style="color: #92400e; text-decoration: underline;">Product Management</a> page.
          </p>
        </div>

        <form method="POST">
          <button type="submit" name="confirm_import" value="1"
                  style="padding: 0.875rem 2rem; font-size: 1rem; font-weight: 600; background: var(--brand); color: white; border: none; border-radius: 6px; cursor: pointer;">
            Import <?= count($rows) ?> Products from CSV
          </button>
          <a href="/admin/products.php" style="margin-left: 1rem; padding: 0.875rem 2rem; font-size: 1rem; border: 1px solid var(--border); border-radius: 6px; text-decoration: none; color: var(--ink); display: inline-block;">
            Cancel
          </a>
        </form>
      </div>

    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
