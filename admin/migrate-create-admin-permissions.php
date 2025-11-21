<?php
/**
 * Migration: Create admin_permissions table
 *
 * Granular permissions system for admin users with checkboxes per feature
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_header.php';

require_admin();

$success = false;
$error = null;
$message = '';

// Define available permissions
$availablePermissions = [
  // User Management
  'users.view' => 'View Users',
  'users.create' => 'Create Users',
  'users.edit' => 'Edit Users',
  'users.delete' => 'Delete Users',

  // Product Management
  'products.view' => 'View Products',
  'products.create' => 'Create Products',
  'products.edit' => 'Edit Products',
  'products.delete' => 'Delete Products',

  // Order Management
  'orders.view' => 'View Orders',
  'orders.create' => 'Create Orders',
  'orders.edit' => 'Edit Orders',
  'orders.delete' => 'Delete Orders',

  // Billing & Revenue
  'billing.view' => 'View Billing',
  'billing.edit' => 'Edit Billing',
  'revenue.view' => 'View Revenue Reports',

  // Shipments
  'shipments.view' => 'View Shipments',
  'shipments.edit' => 'Edit Shipments',

  // Pricing
  'pricing.view' => 'View Pricing',
  'pricing.edit' => 'Edit Pricing',

  // Messages
  'messages.view' => 'View Messages',
  'messages.send' => 'Send Messages',

  // Practice Management
  'practices.view' => 'View Practices',
  'practices.edit' => 'Edit Practices',

  // Reports & Analytics
  'reports.view' => 'View Reports',
  'reports.export' => 'Export Reports',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
  try {
    $pdo->beginTransaction();

    // Check if table exists
    $tableExists = $pdo->query("
      SELECT EXISTS (
        SELECT FROM information_schema.tables
        WHERE table_name = 'admin_permissions'
      )
    ")->fetchColumn();

    if (!$tableExists) {
      // Create admin_permissions table
      $pdo->exec("
        CREATE TABLE admin_permissions (
          id SERIAL PRIMARY KEY,
          admin_user_id INTEGER NOT NULL,
          permission_key VARCHAR(100) NOT NULL,
          granted BOOLEAN DEFAULT TRUE,
          granted_by INTEGER,
          granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
          FOREIGN KEY (granted_by) REFERENCES admin_users(id) ON DELETE SET NULL,
          UNIQUE(admin_user_id, permission_key)
        )
      ");

      $pdo->exec("
        CREATE INDEX idx_admin_permissions_user ON admin_permissions(admin_user_id)
      ");

      $pdo->exec("
        CREATE INDEX idx_admin_permissions_key ON admin_permissions(permission_key)
      ");

      $pdo->exec("
        COMMENT ON TABLE admin_permissions IS 'Granular permissions for admin users with checkbox per feature'
      ");

      // Grant all permissions to existing superadmin users
      $superadmins = $pdo->query("SELECT id FROM admin_users WHERE role = 'superadmin'")->fetchAll(PDO::FETCH_COLUMN);
      $insertStmt = $pdo->prepare("INSERT INTO admin_permissions (admin_user_id, permission_key, granted) VALUES (?, ?, TRUE)");

      foreach ($superadmins as $adminId) {
        foreach (array_keys($availablePermissions) as $permKey) {
          $insertStmt->execute([$adminId, $permKey]);
        }
      }

      $message = "✓ Created admin_permissions table and granted all permissions to superadmin users";
    } else {
      $message = "✓ Table admin_permissions already exists";
    }

    // Add permissions_override column to admin_users if it doesn't exist
    $hasPermissionsCol = $pdo->query("
      SELECT column_name
      FROM information_schema.columns
      WHERE table_name = 'admin_users' AND column_name = 'use_custom_permissions'
    ")->fetchColumn();

    if (!$hasPermissionsCol) {
      $pdo->exec("
        ALTER TABLE admin_users
        ADD COLUMN use_custom_permissions BOOLEAN DEFAULT FALSE
      ");

      $pdo->exec("
        COMMENT ON COLUMN admin_users.use_custom_permissions IS 'If true, use granular permissions from admin_permissions table. If false, use role-based permissions.'
      ");

      $message .= "\n✓ Added use_custom_permissions column to admin_users table";
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
$permissionCount = 0;
try {
  $tableExists = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'admin_permissions'
    )
  ")->fetchColumn();

  if ($tableExists) {
    $tableInfo = $pdo->query("
      SELECT column_name, data_type, is_nullable, column_default
      FROM information_schema.columns
      WHERE table_name = 'admin_permissions'
      ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);

    $permissionCount = $pdo->query("SELECT COUNT(*) FROM admin_permissions")->fetchColumn();
  }
} catch (Exception $e) {
  // Ignore error
}
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
  <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">
    Create Admin Permissions System
  </h1>
  <p style="color: var(--muted); font-size: 0.875rem; margin-bottom: 2rem;">
    This migration creates the granular permissions system with checkboxes per feature for each admin user
  </p>

  <?php if ($success): ?>
    <div style="padding: 1rem; background: #d1fae5; border: 1px solid #10b981; border-radius: 6px; margin-bottom: 2rem;">
      <strong style="color: #065f46;"><?= nl2br($message) ?></strong>
      <div style="margin-top: 1rem;">
        <a href="/admin/users.php" class="btn" style="background: var(--brand); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block;">
          Go to Admin Users →
        </a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div style="padding: 1rem; background: #fee; border: 1px solid #dc3545; border-radius: 6px; margin-bottom: 2rem; color: #991b1b;">
      <strong>Migration Failed:</strong><br><?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <!-- Available Permissions -->
  <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem; margin-bottom: 2rem;">
    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">
      Available Permissions (<?= count($availablePermissions) ?>)
    </h2>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem;">
      <?php foreach ($availablePermissions as $key => $label): ?>
        <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 6px;">
          <div style="font-weight: 600; color: var(--ink); margin-bottom: 0.25rem;"><?= $label ?></div>
          <code style="font-size: 0.75rem; color: var(--muted);"><?= $key ?></code>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$tableInfo && !$success): ?>
    <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 2rem; margin-bottom: 2rem;">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem; color: #991b1b;">
        ⚠️ Table Missing
      </h2>
      <p style="color: var(--ink-light); margin-bottom: 1.5rem;">
        The <code style="background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 4px;">admin_permissions</code> table does not exist.
        This table is required for granular permissions management with checkboxes per feature.
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
        Current admin_permissions Table Schema
      </h2>

      <div style="padding: 1rem; background: #d1fae5; border: 1px solid #10b981; border-radius: 6px; margin-bottom: 1.5rem; color: #065f46;">
        Total Permissions Granted: <strong><?= $permissionCount ?></strong>
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
        <a href="/admin/users.php" class="btn" style="background: var(--brand); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block;">
          Go to Admin Users →
        </a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
