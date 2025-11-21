<?php
/**
 * Migration: Create practice_locations table
 *
 * Allows practices to manage multiple facility addresses/locations
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_header.php';

require_admin();

$success = false;
$error = null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
  try {
    $pdo->beginTransaction();

    // Check if table exists
    $tableExists = $pdo->query("
      SELECT EXISTS (
        SELECT FROM information_schema.tables
        WHERE table_name = 'practice_locations'
      )
    ")->fetchColumn();

    if (!$tableExists) {
      // Create practice_locations table
      $pdo->exec("
        CREATE TABLE practice_locations (
          id SERIAL PRIMARY KEY,
          user_id VARCHAR(32) NOT NULL,
          location_name VARCHAR(255) NOT NULL,
          address TEXT NOT NULL,
          city VARCHAR(100) NOT NULL,
          state VARCHAR(50) NOT NULL,
          zip VARCHAR(20) NOT NULL,
          phone VARCHAR(50),
          is_primary BOOLEAN DEFAULT FALSE,
          is_active BOOLEAN DEFAULT TRUE,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
      ");

      $pdo->exec("
        CREATE INDEX idx_practice_locations_user ON practice_locations(user_id)
      ");

      $pdo->exec("
        CREATE INDEX idx_practice_locations_primary ON practice_locations(user_id, is_primary)
      ");

      $pdo->exec("
        COMMENT ON TABLE practice_locations IS 'Multiple facility addresses for each practice'
      ");

      $message = "✓ Created practice_locations table with indexes";
    } else {
      // Table exists - check if all columns exist
      $columns = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'practice_locations'
      ")->fetchAll(PDO::FETCH_COLUMN);

      $requiredColumns = [
        'id', 'user_id', 'location_name', 'address', 'city', 'state',
        'zip', 'phone', 'is_primary', 'is_active', 'created_at', 'updated_at'
      ];

      $missingColumns = array_diff($requiredColumns, $columns);

      if (!empty($missingColumns)) {
        // Add missing columns
        foreach ($missingColumns as $col) {
          switch ($col) {
            case 'is_primary':
              $pdo->exec("ALTER TABLE practice_locations ADD COLUMN is_primary BOOLEAN DEFAULT FALSE");
              break;
            case 'is_active':
              $pdo->exec("ALTER TABLE practice_locations ADD COLUMN is_active BOOLEAN DEFAULT TRUE");
              break;
            case 'updated_at':
              $pdo->exec("ALTER TABLE practice_locations ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
              break;
            case 'location_name':
              $pdo->exec("ALTER TABLE practice_locations ADD COLUMN location_name VARCHAR(255) NOT NULL DEFAULT ''");
              break;
          }
        }
        $message = "✓ Added missing columns: " . implode(', ', $missingColumns);
      } else {
        $message = "✓ Table practice_locations already exists with all required columns";
      }
    }

    $pdo->commit();
    $success = true;

  } catch (Exception $e) {
    $pdo->rollBack();
    $error = $e->getMessage();
  }
}

// Get current table info
$tableInfo = null;
try {
  $tableExists = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'practice_locations'
    )
  ")->fetchColumn();

  if ($tableExists) {
    $tableInfo = $pdo->query("
      SELECT column_name, data_type, is_nullable, column_default
      FROM information_schema.columns
      WHERE table_name = 'practice_locations'
      ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  // Ignore error
}
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
  <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
    Create practice_locations Table
  </h1>
  <p style="color: var(--muted); font-size: 0.875rem; margin-bottom: 2rem;">
    This migration creates the practice_locations table for managing multiple facility addresses
  </p>

  <?php if ($success): ?>
    <div style="padding: 1rem; background: #d1fae5; border: 1px solid #10b981; border-radius: 6px; margin-bottom: 2rem;">
      <strong style="color: #065f46;"><?= $message ?></strong>
      <div style="margin-top: 1rem;">
        <a href="/portal/?page=practice-locations" class="btn" style="background: var(--brand); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block;">
          Go to Practice Locations →
        </a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div style="padding: 1rem; background: #fee; border: 1px solid #dc3545; border-radius: 6px; margin-bottom: 2rem; color: #991b1b;">
      <strong>Migration Failed:</strong><br><?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if (!$tableInfo && !$success): ?>
    <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem; margin-bottom: 2rem;">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem; color: #991b1b;">
        ⚠️ Table Missing
      </h2>
      <p style="color: var(--ink-light); margin-bottom: 1.5rem;">
        The <code style="background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 4px;">practice_locations</code> table does not exist.
        This table is required for physicians to manage multiple facility addresses.
      </p>

      <form method="POST">
        <button type="submit" name="run_migration" value="1"
                style="padding: 0.875rem 2rem; font-size: 1rem; font-weight: 600; background: var(--brand); color: white; border: none; border-radius: 6px; cursor: pointer;">
          Run Migration
        </button>
      </form>
    </div>
  <?php elseif ($tableInfo): ?>
    <!-- Current Schema -->
    <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem;">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">
        Current practice_locations Table Schema
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
            <?php foreach ($tableInfo as $col): ?>
              <tr style="border-bottom: 1px solid var(--border);">
                <td style="padding: 0.75rem;">
                  <code style="background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 4px;">
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

      <div style="margin-top: 1.5rem;">
        <a href="/portal/?page=practice-locations" class="btn" style="background: var(--brand); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block;">
          Go to Practice Locations →
        </a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
