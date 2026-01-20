<?php
/**
 * Demo Portal Users Management
 * Create and manage demo user accounts for distributors
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

$admin = current_admin();

// Restrict access to superadmin, admin, and sales roles
if (!in_array($admin['role'] ?? '', ['superadmin', 'admin', 'sales'])) {
    header('Location: /admin/');
    exit;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $companyName = trim($_POST['company_name'] ?? '');
            $distributorCode = trim($_POST['distributor_code'] ?? '');

            if (!$email || !$password) {
                throw new Exception('Email and password are required');
            }

            if (strlen($password) < 8) {
                throw new Exception('Password must be at least 8 characters');
            }

            // Check for existing email
            $check = $pdo->prepare("SELECT id FROM demo_users WHERE LOWER(email) = LOWER(?)");
            $check->execute([$email]);
            if ($check->fetch()) {
                throw new Exception('A demo user with this email already exists');
            }

            $userId = bin2hex(random_bytes(16));
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO demo_users (id, email, password_hash, first_name, last_name, company_name, distributor_code, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([$userId, $email, $passwordHash, $firstName, $lastName, $companyName, $distributorCode]);

            $success = "Demo user created successfully. Login URL: https://collagendirect.health/demo-portal/login.php";

        } elseif ($action === 'toggle_active') {
            $userId = $_POST['user_id'] ?? '';
            if ($userId) {
                $pdo->prepare("UPDATE demo_users SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?")
                    ->execute([$userId]);
                $success = 'User status updated';
            }

        } elseif ($action === 'delete') {
            $userId = $_POST['user_id'] ?? '';
            if ($userId) {
                // Delete associated sessions first (cascades to demo data)
                $pdo->prepare("DELETE FROM demo_sessions WHERE demo_user_id = ?")->execute([$userId]);
                $pdo->prepare("DELETE FROM demo_users WHERE id = ?")->execute([$userId]);
                $success = 'Demo user deleted';
            }

        } elseif ($action === 'reset_password') {
            $userId = $_POST['user_id'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';

            if (!$userId || !$newPassword) {
                throw new Exception('User ID and new password are required');
            }

            if (strlen($newPassword) < 8) {
                throw new Exception('Password must be at least 8 characters');
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE demo_users SET password_hash = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$passwordHash, $userId]);
            $success = 'Password updated successfully';
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch all demo users
$users = [];
try {
    $stmt = $pdo->query("
        SELECT u.*,
               (SELECT COUNT(*) FROM demo_sessions WHERE demo_user_id = u.id) as session_count,
               (SELECT MAX(started_at) FROM demo_sessions WHERE demo_user_id = u.id) as last_session
        FROM demo_users u
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'Could not load demo users. The demo_users table may not exist yet. Please run the migration.';
}

// Get active session stats
$stats = ['total_users' => 0, 'active_sessions' => 0, 'total_sessions' => 0];
try {
    $stats['total_users'] = count($users);
    $statsStmt = $pdo->query("SELECT COUNT(*) FROM demo_sessions WHERE expires_at > NOW()");
    $stats['active_sessions'] = (int)$statsStmt->fetchColumn();
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM demo_sessions");
    $stats['total_sessions'] = (int)$totalStmt->fetchColumn();
} catch (Throwable $e) {
    // Stats unavailable
}

include __DIR__ . '/_header.php';
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
      <h1 style="font-size: 1.5rem; font-weight: 700; color: #1f2937;">Demo Portal Users</h1>
      <p style="color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem;">Manage distributor demo accounts</p>
    </div>
    <button onclick="showCreateModal()" class="btn btn-primary">
      <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
      </svg>
      Add Demo User
    </button>
  </div>

  <?php if ($success): ?>
  <div style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
    <?=e($success)?>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
    <?=e($error)?>
  </div>
  <?php endif; ?>

  <!-- Stats Cards -->
  <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="padding: 1.25rem;">
      <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Total Demo Users</div>
      <div style="font-size: 2rem; font-weight: 700; color: #1f2937;"><?=$stats['total_users']?></div>
    </div>
    <div class="card" style="padding: 1.25rem;">
      <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Active Sessions</div>
      <div style="font-size: 2rem; font-weight: 700; color: #059669;"><?=$stats['active_sessions']?></div>
    </div>
    <div class="card" style="padding: 1.25rem;">
      <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Total Sessions (All Time)</div>
      <div style="font-size: 2rem; font-weight: 700; color: #6b7280;"><?=$stats['total_sessions']?></div>
    </div>
  </div>

  <!-- Demo Portal Link -->
  <div class="card" style="padding: 1rem; margin-bottom: 1.5rem; background: #fef3c7; border-color: #fcd34d;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <svg style="width: 20px; height: 20px; color: #d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
      </svg>
      <div>
        <div style="font-weight: 600; color: #92400e;">Demo Portal URL</div>
        <a href="/demo-portal/login.php" target="_blank" style="color: #d97706; text-decoration: underline;">https://collagendirect.health/demo-portal/login.php</a>
      </div>
    </div>
  </div>

  <!-- Users Table -->
  <div class="card" style="overflow: hidden;">
    <table>
      <thead>
        <tr style="background: #f9fafb;">
          <th>User</th>
          <th>Company</th>
          <th>Distributor Code</th>
          <th>Sessions</th>
          <th>Last Active</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
        <tr>
          <td colspan="7" style="text-align: center; padding: 2rem; color: #6b7280;">
            No demo users yet. Click "Add Demo User" to create one.
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($users as $user): ?>
        <tr>
          <td>
            <div style="font-weight: 500;"><?=e($user['first_name'] . ' ' . $user['last_name'])?></div>
            <div style="font-size: 0.75rem; color: #6b7280;"><?=e($user['email'])?></div>
          </td>
          <td><?=e($user['company_name'] ?: '—')?></td>
          <td><code style="background: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.75rem;"><?=e($user['distributor_code'] ?: '—')?></code></td>
          <td><?=$user['session_count']?></td>
          <td>
            <?php if ($user['last_session']): ?>
              <?=date('M j, Y g:i A', strtotime($user['last_session']))?>
            <?php else: ?>
              <span style="color: #9ca3af;">Never</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($user['is_active']): ?>
              <span style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">Active</span>
            <?php else: ?>
              <span style="background: #fee2e2; color: #991b1b; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display: flex; gap: 0.5rem;">
              <form method="POST" style="display: inline;">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="user_id" value="<?=e($user['id'])?>">
                <button type="submit" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                  <?=$user['is_active'] ? 'Deactivate' : 'Activate'?>
                </button>
              </form>
              <button onclick="showResetPasswordModal('<?=e($user['id'])?>', '<?=e($user['email'])?>')" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                Reset Password
              </button>
              <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this demo user? All their demo sessions will also be deleted.')">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?=e($user['id'])?>">
                <button type="submit" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #dc2626;">
                  Delete
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create User Modal -->
<div id="createModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
  <div style="background: white; border-radius: 0.75rem; width: 100%; max-width: 480px; margin: 1rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
    <div style="padding: 1.25rem; border-bottom: 1px solid #e5e7eb;">
      <h2 style="font-size: 1.125rem; font-weight: 600;">Create Demo User</h2>
    </div>
    <form method="POST">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="create">
      <div style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div>
            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">First Name</label>
            <input type="text" name="first_name" style="width: 100%;">
          </div>
          <div>
            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Last Name</label>
            <input type="text" name="last_name" style="width: 100%;">
          </div>
        </div>
        <div>
          <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Email *</label>
          <input type="email" name="email" required style="width: 100%;">
        </div>
        <div>
          <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Password *</label>
          <input type="password" name="password" required minlength="8" style="width: 100%;">
          <p style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Minimum 8 characters</p>
        </div>
        <div>
          <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Company Name</label>
          <input type="text" name="company_name" style="width: 100%;">
        </div>
        <div>
          <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Distributor Code</label>
          <input type="text" name="distributor_code" placeholder="e.g., DIST-001" style="width: 100%;">
        </div>
      </div>
      <div style="padding: 1rem 1.25rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 0.75rem;">
        <button type="button" onclick="hideCreateModal()" class="btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
  <div style="background: white; border-radius: 0.75rem; width: 100%; max-width: 400px; margin: 1rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
    <div style="padding: 1.25rem; border-bottom: 1px solid #e5e7eb;">
      <h2 style="font-size: 1.125rem; font-weight: 600;">Reset Password</h2>
      <p style="font-size: 0.875rem; color: #6b7280;" id="resetPasswordEmail"></p>
    </div>
    <form method="POST">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="resetPasswordUserId">
      <div style="padding: 1.25rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">New Password *</label>
        <input type="password" name="new_password" required minlength="8" style="width: 100%;">
        <p style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Minimum 8 characters</p>
      </div>
      <div style="padding: 1rem 1.25rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 0.75rem;">
        <button type="button" onclick="hideResetPasswordModal()" class="btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function showCreateModal() {
  document.getElementById('createModal').style.display = 'flex';
}
function hideCreateModal() {
  document.getElementById('createModal').style.display = 'none';
}
function showResetPasswordModal(userId, email) {
  document.getElementById('resetPasswordUserId').value = userId;
  document.getElementById('resetPasswordEmail').textContent = email;
  document.getElementById('resetPasswordModal').style.display = 'flex';
}
function hideResetPasswordModal() {
  document.getElementById('resetPasswordModal').style.display = 'none';
}

// Close modals on backdrop click
document.getElementById('createModal').addEventListener('click', function(e) {
  if (e.target === this) hideCreateModal();
});
document.getElementById('resetPasswordModal').addEventListener('click', function(e) {
  if (e.target === this) hideResetPasswordModal();
});
</script>

<?php include __DIR__ . '/_footer.php'; ?>
