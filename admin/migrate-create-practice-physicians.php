<?php
/**
 * Migration: Create practice_physicians table
 *
 * Allows practice admins to manage multiple physicians and their credentials
 * for rotating physician signatures and details on orders
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
        WHERE table_name = 'practice_physicians'
      )
    ")->fetchColumn();

    if (!$tableExists) {
      // Create practice_physicians table
      $pdo->exec("
        CREATE TABLE practice_physicians (
          id SERIAL PRIMARY KEY,
          practice_user_id VARCHAR(32) NOT NULL,
          physician_name VARCHAR(255) NOT NULL,
          npi VARCHAR(20),
          license_number VARCHAR(50),
          address TEXT,
          city VARCHAR(100),
          state VARCHAR(50),
          zip VARCHAR(20),
          phone VARCHAR(50),
          signature_text TEXT,
          signature_image_path TEXT,
          is_active BOOLEAN DEFAULT TRUE,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (practice_user_id) REFERENCES users(id) ON DELETE CASCADE
        )
      ");

      $pdo->exec("
        CREATE INDEX idx_practice_physicians_practice ON practice_physicians(practice_user_id)
      ");

      $pdo->exec("
        CREATE INDEX idx_practice_physicians_active ON practice_physicians(practice_user_id, is_active)
      ");

      $pdo->exec("
        COMMENT ON TABLE practice_physicians IS 'Physician roster for practice admins to rotate signatures and credentials on orders'
      ");

      $pdo->exec("
        COMMENT ON COLUMN practice_physicians.npi IS 'National Provider Identifier'
      ");

      $pdo->exec("
        COMMENT ON COLUMN practice_physicians.signature_text IS 'Text representation of signature (e.g., Dr. John Smith, MD)'
      ");

      $pdo->exec("
        COMMENT ON COLUMN practice_physicians.signature_image_path IS 'Path to signature image file'
      ");

      $message = "✓ Created practice_physicians table with indexes and comments";
    } else {
      $message = "✓ Table practice_physicians already exists";
    }

    // Add physician_id to orders table if it doesn't exist
    $hasPhysicianId = $pdo->query("
      SELECT column_name
      FROM information_schema.columns
      WHERE table_name = 'orders' AND column_name = 'physician_id'
    ")->fetchColumn();

    if (!$hasPhysicianId) {
      $pdo->exec("
        ALTER TABLE orders
        ADD COLUMN physician_id INTEGER,
        ADD CONSTRAINT fk_orders_physician
          FOREIGN KEY (physician_id)
          REFERENCES practice_physicians(id)
          ON DELETE SET NULL
      ");

      $pdo->exec("
        CREATE INDEX idx_orders_physician ON orders(physician_id)
      ");

      $pdo->exec("
        COMMENT ON COLUMN orders.physician_id IS 'Reference to practice_physicians table for physician roster rotation'
      ");

      $message .= "\n✓ Added physician_id column to orders table";
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
$physicianCount = 0;
try {
  $tableExists = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'practice_physicians'
    )
  ")->fetchColumn();

  if ($tableExists) {
    $tableInfo = $pdo->query("
      SELECT column_name, data_type, is_nullable, column_default
      FROM information_schema.columns
      WHERE table_name = 'practice_physicians'
      ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);

    $physicianCount = $pdo->query("SELECT COUNT(*) FROM practice_physicians WHERE is_active = TRUE")->fetchColumn();
  }
} catch (Exception $e) {
  // Ignore error
}
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
  <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
    Create practice_physicians Table
  </h1>
  <p style="color: var(--muted); font-size: 0.875rem; margin-bottom: 2rem;">
    This migration creates the practice_physicians table for managing physician rosters and rotating signatures/credentials on orders
  </p>

  <?php if ($success): ?>
    <div style="padding: 1rem; background: #d1fae5; border: 1px solid #10b981; border-radius: 6px; margin-bottom: 2rem;">
      <strong style="color: #065f46;"><?= nl2br($message) ?></strong>
      <div style="margin-top: 1rem;">
        <a href="/portal/?page=physicians" class="btn" style="background: var(--brand); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block;">
          Go to Physician Roster →
        </a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div style="padding: 1rem; background: #fee; border: 1px solid #dc3545; border-radius: 6px; margin-bottom: 2rem; color: #991b1b;">
      <strong>Migration Failed:</strong><br><?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <!-- Feature Description -->
  <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem; margin-bottom: 2rem;">
    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">
      Physician Roster Management
    </h2>
    <p style="color: var(--ink-light); margin-bottom: 1rem;">
      This feature allows practice admins to maintain a roster of physicians (Dr. A, Dr. B, Dr. C, Dr. D, etc.)
      and rotate between them when creating orders.
    </p>
    <ul style="list-style: disc; margin-left: 1.5rem; color: var(--ink-light); line-height: 1.75;">
      <li>Store physician credentials: Name, NPI, License #, Address, Phone</li>
      <li>Select which physician when creating an order</li>
      <li>Auto-populate physician details into order forms</li>
      <li>Manage signature text and images for each physician</li>
      <li>Enable/disable physicians as needed</li>
    </ul>
  </div>

  <?php if (!$tableInfo && !$success): ?>
    <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem; margin-bottom: 2rem;">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem; color: #991b1b;">
        ⚠️ Table Missing
      </h2>
      <p style="color: var(--ink-light); margin-bottom: 1.5rem;">
        The <code style="background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 4px;">practice_physicians</code> table does not exist.
        This table is required for practice admins to manage physician rosters and rotate credentials on orders.
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
        Current practice_physicians Table Schema
      </h2>

      <div style="padding: 1rem; background: #d1fae5; border: 1px solid #10b981; border-radius: 6px; margin-bottom: 1.5rem; color: #065f46;">
        Total Active Physicians: <strong><?= $physicianCount ?></strong>
      </div>

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
        <a href="/portal/?page=physicians" class="btn" style="background: var(--brand); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block;">
          Go to Physician Roster →
        </a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
