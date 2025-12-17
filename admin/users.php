<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_admin();

// Sales reps cannot access admin settings
if (function_exists('deny_sales_rep')) deny_sales_rep();

require_once __DIR__ . '/../api/lib/email_notifications.php'; // Email notifications
require_once __DIR__ . '/config.php'; // For Google Maps API key

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$isSuperadmin = $adminRole === 'superadmin'; // Only parker@collagendirect.health
$isOwner = in_array($adminRole, ['owner','superadmin','admin','practice_admin']); // Full user management access
$isAdmin = in_array($adminRole, ['owner','superadmin','admin']); // Can edit employees and manufacturers
$isSales = $adminRole === 'sales'; // Sales role - can manage physicians/practices but not admin users
$isManufacturer = $adminRole === 'manufacturer'; // Manufacturer - one step below superadmin, full access except can't delete superadmin/employees
$tab = $_GET['tab'] ?? 'physicians';
$msg='';

/* Get active sales reps for assignment dropdown */
$activeReps = [];
try {
  $activeReps = $pdo->query("
    SELECT sr.id, sr.user_id, u.first_name, u.last_name
    FROM sales_reps sr
    JOIN users u ON u.id = sr.user_id
    WHERE sr.status = 'active'
    ORDER BY u.first_name, u.last_name
  ")->fetchAll();
} catch (Throwable $e) {
  // Table may not exist yet
}

/* Rep filter for list */
$repFilter = $_GET['rep'] ?? '';

/* Physician scope: only those mapped to this admin unless owner/superadmin/practice_admin */
/* IMPORTANT: Exclude superadmin from Physicians tab - they belong in admin portal, not as physicians */
/* Sales role can see all physicians (not just assigned) and can create new ones */
$physQuery = "
  SELECT u.id, u.first_name, u.last_name, u.email, u.account_type, u.status, u.created_at, u.role, u.practice_name,
         u.assigned_rep_id, u.phone, u.address, u.city, u.state, u.zip, u.npi, u.ptan, u.license, u.license_state, u.license_expiry,
         u.is_referral_only, u.has_dme_license, u.is_hybrid,
         u.agree_msa, u.agree_baa, u.sign_name, u.sign_title, u.sign_date, u.signed_ip,
         CASE WHEN sr.id IS NOT NULL THEN CONCAT(rep_user.first_name, ' ', rep_user.last_name) ELSE NULL END as rep_name
  FROM users u
  LEFT JOIN sales_reps sr ON sr.id = u.assigned_rep_id
  LEFT JOIN users rep_user ON rep_user.id = sr.user_id
";
if (!$isOwner && !$isSales && !$isManufacturer) {
  // Regular employees see only assigned physicians
  $physQuery .= " JOIN admin_physicians ap ON ap.physician_user_id = u.id WHERE ap.admin_id = :aid AND (u.role IS NULL OR u.role IN ('physician', 'practice_admin'))";
} else {
  // Owner, superadmin, admin, Sales, and Manufacturer see all physicians
  $physQuery .= " WHERE (u.role IS NULL OR u.role IN ('physician', 'practice_admin'))";
}
// Apply rep filter
if ($repFilter === 'unassigned') {
  $physQuery .= " AND u.assigned_rep_id IS NULL";
} elseif ($repFilter) {
  $physQuery .= " AND u.assigned_rep_id = :rep_id";
}
$physQuery .= " ORDER BY u.created_at DESC LIMIT 300";
$physStmt = $pdo->prepare($physQuery);
$params = [];
if (!$isOwner && !$isSales && !$isManufacturer) $params['aid'] = $admin['id'];
if ($repFilter && $repFilter !== 'unassigned') $params['rep_id'] = $repFilter;
$physStmt->execute($params);
$phys = $physStmt->fetchAll();

/* Employees list - only CollagenDirect staff */
$emps = $pdo->query("SELECT id, name, email, role, created_at FROM admin_users WHERE role IN ('employee', 'admin', 'sales', 'ops') ORDER BY created_at DESC LIMIT 200")->fetchAll();

/* Manufacturer list - separate from employees */
$manufacturers = $pdo->query("SELECT id, name, email, role, created_at FROM admin_users WHERE role = 'manufacturer' ORDER BY created_at DESC LIMIT 200")->fetchAll();

/* Practice Locations list - all locations for practices */
try {
  $locationsQuery = "
    SELECT pl.id, pl.user_id, pl.location_name, pl.address, pl.city, pl.state, pl.zip, pl.phone, pl.is_primary, pl.is_active, pl.created_at,
           u.first_name, u.last_name, u.practice_name
    FROM practice_locations pl
    JOIN users u ON u.id = pl.user_id
    WHERE u.role = 'practice_admin'
    ORDER BY u.practice_name, pl.is_primary DESC, pl.location_name
    LIMIT 500
  ";
  $locations = $pdo->query($locationsQuery)->fetchAll();
} catch (Throwable $e) {
  // Table might not exist yet - that's okay
  $locations = [];
  error_log("practice_locations table query failed: " . $e->getMessage());
}

/* Actions */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();
  $act = $_POST['action'] ?? '';

  // Manufacturer permissions: Can read/write everything except delete superadmins and employees
  if ($isManufacturer && in_array($act, ['delete_emp'])) {
    // Check if trying to delete superadmin or employee
    $targetId = (int)($_POST['emp_id'] ?? 0);
    $targetUser = $pdo->query("SELECT role FROM admin_users WHERE id = $targetId")->fetch();
    if ($targetUser && in_array($targetUser['role'], ['superadmin', 'employee', 'admin', 'sales', 'ops'])) {
      $msg = 'Manufacturers cannot delete superadmins or employees';
      header('Location: /admin/users.php?tab='.$tab.'&error='.urlencode($msg));
      exit;
    }
  }

  // Sales can manage physicians/practices but not employees, manufacturers, or other admins
  if ($isSales && in_array($act, ['create_employee', 'delete_emp', 'reset_emp_pw'])) {
    $msg = 'Sales role cannot manage employees, manufacturers, or admin users';
    header('Location: /admin/users.php?tab='.$tab.'&error='.urlencode($msg));
    exit;
  }

  if ($act==='create_employee' && ($isAdmin || $isManufacturer)) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $password = $_POST['password'];

    $pdo->prepare("INSERT INTO admin_users(name,email,role,password_hash) VALUES(?,?,?,?)")
        ->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT)]);

    // Send welcome email using SendGrid template
    $emailSent = send_physician_account_created_email($email, $name, $password);

    $msg = ($role === 'manufacturer' ? 'Manufacturer' : 'Employee') . ' created';
    if ($emailSent) {
      $msg .= ' - Welcome email sent';
    } else {
      $msg .= ' - Warning: Email failed to send';
      error_log("[users.php] Failed to send welcome email to $email");
    }
    $tab = ($role === 'manufacturer') ? 'manufacturer' : 'employees';
  }
  if ($act==='reset_emp_pw' && ($isAdmin || $isManufacturer)) {
    $pdo->prepare("UPDATE admin_users SET password_hash=? WHERE id=?")->execute([password_hash($_POST['newpw'], PASSWORD_DEFAULT), (int)$_POST['emp_id']]);
    $msg='Employee password updated'; $tab='employees';
  }
  if ($act==='delete_emp' && $isAdmin) {
    if ((int)$_POST['emp_id'] !== (int)$admin['id']) {
      $pdo->prepare("DELETE FROM admin_users WHERE id=?")->execute([(int)$_POST['emp_id']]);
      $msg='Employee deleted';
    } else { $msg='Cannot delete yourself'; }
    $tab='employees';
  }

  if ($act==='create_phys') {
    $providerType = $_POST['provider_type'] ?? 'practice';
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $securityRole = $_POST['security_role'] ?? 'physician'; // Practice Manager or Physician
    $accountTypeInput = $_POST['account_type'] ?? 'referral'; // referral, wholesale, or both
    $userId = bin2hex(random_bytes(16));

    // Rep assignment (superadmin/manufacturer only)
    $assignedRepId = null;
    $repAssignmentDate = null;
    $repAssignedBy = null;
    $repAssignedByUserId = null;
    if (($isSuperadmin || $isManufacturer) && !empty($_POST['assigned_rep_id'])) {
      $assignedRepId = $_POST['assigned_rep_id'];
      $repAssignmentDate = 'NOW()';
      $repAssignedBy = 'admin_assign';
      $repAssignedByUserId = $admin['id'];
    }

    // Physician credentials (optional now)
    $npi = preg_replace('/\D/', '', $_POST['npi'] ?? '');
    $ptan = trim($_POST['ptan'] ?? '');
    $license = trim($_POST['license'] ?? '');
    $licenseState = $_POST['license_state'] ?? null;
    $licenseExpiry = !empty($_POST['license_expiry']) ? $_POST['license_expiry'] : null;

    // Map account type to database fields
    $accountType = 'referral'; // Default
    $isReferralOnly = 0;
    $hasDmeLicense = 0;
    $isHybrid = 0;

    if ($accountTypeInput === 'referral') {
      $accountType = 'referral';
      $isReferralOnly = 1;
    } elseif ($accountTypeInput === 'wholesale') {
      $accountType = 'wholesale';
      $hasDmeLicense = 1;
    } elseif ($accountTypeInput === 'both') {
      $accountType = 'referral'; // Primary type
      $isHybrid = 1;
      $hasDmeLicense = 1;
    }

    if ($providerType === 'practice') {
      // Creating a practice owner (practice_admin)
      $practiceName = trim($_POST['practice_name'] ?? '');
      $address = trim($_POST['address'] ?? '');
      $city = trim($_POST['city'] ?? '');
      $state = $_POST['state'] ?? '';
      $zip = trim($_POST['zip'] ?? '');
      $phone = trim($_POST['phone'] ?? '');

      $pdo->prepare("
        INSERT INTO users(
          id, email, password_hash, first_name, last_name, practice_name,
          address, city, state, zip, phone,
          npi, ptan, license, license_state, license_expiry,
          role, user_type, account_type, status, can_manage_physicians,
          is_referral_only, has_dme_license, is_hybrid,
          assigned_rep_id, rep_assignment_date, rep_assigned_by, rep_assigned_by_user_id,
          created_at, updated_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'practice_admin','practice_admin',?,'active',TRUE,?,?,?,?,".($assignedRepId ? "NOW()" : "NULL").",?,?,NOW(),NOW())
      ")->execute([
        $userId, $email, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName, $practiceName,
        $address, $city, $state, $zip, $phone,
        $npi ?: null, $ptan ?: null, $license ?: null, $licenseState, $licenseExpiry,
        $accountType,
        $isReferralOnly, $hasDmeLicense, $isHybrid,
        $assignedRepId, $repAssignedBy, $repAssignedByUserId
      ]);
      $msg = 'Practice owner created';
      if ($assignedRepId) $msg .= ' and assigned to rep';
    } else {
      // Creating a physician and linking to practice
      $practiceId = $_POST['practice_id'] ?? '';

      $pdo->prepare("
        INSERT INTO users(
          id, email, password_hash, first_name, last_name,
          npi, ptan, license, license_state, license_expiry,
          role, user_type, account_type, status,
          is_referral_only, has_dme_license, is_hybrid,
          assigned_rep_id, rep_assignment_date, rep_assigned_by, rep_assigned_by_user_id,
          created_at, updated_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,'physician','physician',?,'active',?,?,?,?,".($assignedRepId ? "NOW()" : "NULL").",?,?,NOW(),NOW())
      ")->execute([
        $userId, $email, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName,
        $npi ?: null, $ptan ?: null, $license ?: null, $licenseState, $licenseExpiry,
        $accountType,
        $isReferralOnly, $hasDmeLicense, $isHybrid,
        $assignedRepId, $repAssignedBy, $repAssignedByUserId
      ]);

      // Link physician to practice via practice_physicians table
      if ($practiceId) {
        $pdo->prepare("
          INSERT INTO practice_physicians(
            practice_admin_id, physician_id, first_name, last_name, physician_email,
            physician_npi, physician_license, physician_license_state, physician_license_expiry,
            created_at
          ) VALUES (?,?,?,?,?,?,?,?,?,NOW())
        ")->execute([
          $practiceId, $userId, $firstName, $lastName, $email,
          $npi ?: null, $license ?: null, $licenseState, $licenseExpiry
        ]);
      }
      $msg = 'Physician created and linked to practice';
    }

    // Map to this admin if not owner/superadmin (for regular admins with limited scope)
    if (!$isOwner && ($admin['role'] ?? '') !== 'practice_admin') {
      $pdo->prepare("INSERT INTO admin_physicians(admin_id, physician_user_id) VALUES(?,?) ON CONFLICT DO NOTHING")->execute([$admin['id'], $userId]);
    }

    // Send welcome email via SendGrid template
    $emailSent = send_physician_account_created_email($email, "$firstName $lastName", $password);

    if ($emailSent) {
      $msg .= ' - Welcome email sent';
    } else {
      $msg .= ' - Warning: Email failed to send';
      error_log("[users.php] Failed to send welcome email to $email");
    }
  }
  if ($act==='update_phys') {
    $physId = $_POST['phys_id'];
    $accountTypeInput = $_POST['account_type'] ?? 'referral';

    // Map account type to database fields
    $accountType = 'referral';
    $isReferralOnly = false;
    $hasDmeLicense = false;
    $isHybrid = false;

    if ($accountTypeInput === 'referral') {
      $accountType = 'referral';
      $isReferralOnly = 1;
      $hasDmeLicense = 0;
      $isHybrid = 0;
    } elseif ($accountTypeInput === 'wholesale') {
      $accountType = 'wholesale';
      $isReferralOnly = 0;
      $hasDmeLicense = 1;
      $isHybrid = 0;
    } elseif ($accountTypeInput === 'both') {
      $accountType = 'referral';
      $isReferralOnly = 0;
      $isHybrid = 1;
      $hasDmeLicense = 1;
    }

    $pdo->prepare("
      UPDATE users SET
        first_name = ?,
        last_name = ?,
        email = ?,
        phone = ?,
        practice_name = ?,
        address = ?,
        city = ?,
        state = ?,
        zip = ?,
        account_type = ?,
        is_referral_only = ?,
        has_dme_license = ?,
        is_hybrid = ?,
        npi = ?,
        ptan = ?,
        license = ?,
        license_state = ?,
        license_expiry = ?,
        updated_at = NOW()
      WHERE id = ?
    ")->execute([
      trim($_POST['first_name'] ?? ''),
      trim($_POST['last_name'] ?? ''),
      trim($_POST['email'] ?? ''),
      trim($_POST['phone'] ?? ''),
      trim($_POST['practice_name'] ?? ''),
      trim($_POST['address'] ?? ''),
      trim($_POST['city'] ?? ''),
      $_POST['state'] ?? null,
      trim($_POST['zip'] ?? ''),
      $accountType,
      $isReferralOnly,
      $hasDmeLicense,
      $isHybrid,
      preg_replace('/\D/', '', $_POST['npi'] ?? '') ?: null,
      trim($_POST['ptan'] ?? '') ?: null,
      trim($_POST['license'] ?? '') ?: null,
      $_POST['license_state'] ?? null,
      !empty($_POST['license_expiry']) ? $_POST['license_expiry'] : null,
      $physId
    ]);
    $msg = 'User details updated successfully';
  }

  if ($act==='reset_phys_pw' && !$isManufacturer) {
    $pdo->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?")->execute([password_hash($_POST['newpw'], PASSWORD_DEFAULT), $_POST['phys_id']]);
    $msg='Physician password updated';
  }
  if ($act==='delete_phys' && !$isManufacturer) {
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$_POST['phys_id']]);
    $pdo->prepare("DELETE FROM admin_physicians WHERE physician_user_id=?")->execute([$_POST['phys_id']]);
    $msg='Physician deleted';
  }
  if ($act==='assign_rep' && ($isSuperadmin || $isManufacturer)) {
    $physId = $_POST['phys_id'] ?? '';
    $newRepId = $_POST['assigned_rep_id'] ?? '';

    if ($newRepId === '') {
      // Unassign rep
      $pdo->prepare("UPDATE users SET assigned_rep_id = NULL, rep_assignment_date = NULL, rep_assigned_by = NULL, rep_assigned_by_user_id = NULL, updated_at = NOW() WHERE id = ?")->execute([$physId]);
      $msg = 'Sales rep unassigned from this clinic';
    } else {
      // Assign rep
      $pdo->prepare("UPDATE users SET assigned_rep_id = ?, rep_assignment_date = NOW(), rep_assigned_by = 'admin_assign', rep_assigned_by_user_id = ?, updated_at = NOW() WHERE id = ?")->execute([$newRepId, $admin['id'], $physId]);
      $msg = 'Sales rep assigned to this clinic';
    }
  }
  if ($act==='map_phys' && !$isOwner && ($admin['role'] ?? '') !== 'practice_admin') {
    $pdo->prepare("INSERT INTO admin_physicians(admin_id, physician_user_id) VALUES(?,?) ON CONFLICT DO NOTHING")->execute([$admin['id'], $_POST['phys_id']]);
    $msg='Physician assigned to you';
  }
  if ($act==='assign_physicians' && $isOwner) {
    $empId = (int)$_POST['employee_id'];
    $physicians = $_POST['physicians'] ?? [];

    // Clear existing assignments
    $pdo->prepare("DELETE FROM admin_physicians WHERE admin_id = ?")->execute([$empId]);

    // Add new assignments
    $assigned = 0;
    foreach ($physicians as $physId) {
      $pdo->prepare("INSERT INTO admin_physicians(admin_id, physician_user_id) VALUES(?,?)")
          ->execute([$empId, $physId]);
      $assigned++;
    }

    $msg = "Assigned $assigned physician(s) to employee";
    $tab = 'employees';
  }

  // Practice Locations Management
  if ($act==='create_location' && ($isAdmin || $isManufacturer || $isSales)) {
    $userId = trim($_POST['user_id']);
    $locationName = trim($_POST['location_name']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $zip = trim($_POST['zip']);
    $phone = trim($_POST['phone'] ?? '');
    $isPrimary = isset($_POST['is_primary']) ? 1 : 0;

    // If this is set as primary, unset other primary locations for this user
    if ($isPrimary) {
      $pdo->prepare("UPDATE practice_locations SET is_primary = FALSE WHERE user_id = ?")->execute([$userId]);
    }

    $pdo->prepare("INSERT INTO practice_locations(user_id, location_name, address, city, state, zip, phone, is_primary, is_active, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,TRUE,NOW(),NOW())")
        ->execute([$userId, $locationName, $address, $city, $state, $zip, $phone, $isPrimary]);
    $msg = 'Practice location created';
    $tab = 'locations';
  }

  if ($act==='update_location' && ($isAdmin || $isManufacturer || $isSales)) {
    $locationId = (int)$_POST['location_id'];
    $locationName = trim($_POST['location_name']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $zip = trim($_POST['zip']);
    $phone = trim($_POST['phone'] ?? '');
    $isPrimary = isset($_POST['is_primary']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Get user_id for this location
    $loc = $pdo->prepare("SELECT user_id FROM practice_locations WHERE id = ?");
    $loc->execute([$locationId]);
    $userId = $loc->fetchColumn();

    // If this is set as primary, unset other primary locations for this user
    if ($isPrimary && $userId) {
      $pdo->prepare("UPDATE practice_locations SET is_primary = FALSE WHERE user_id = ? AND id != ?")->execute([$userId, $locationId]);
    }

    $pdo->prepare("UPDATE practice_locations SET location_name=?, address=?, city=?, state=?, zip=?, phone=?, is_primary=?, is_active=?, updated_at=NOW() WHERE id=?")
        ->execute([$locationName, $address, $city, $state, $zip, $phone, $isPrimary, $isActive, $locationId]);
    $msg = 'Practice location updated';
    $tab = 'locations';
  }

  if ($act==='delete_location' && ($isAdmin || $isManufacturer)) {
    // Soft delete if manufacturer, hard delete if superadmin
    if ($isManufacturer) {
      $pdo->prepare("UPDATE practice_locations SET deleted_at = NOW(), deleted_by = ? WHERE id = ?")
          ->execute([$admin['email'] ?? 'manufacturer', (int)$_POST['location_id']]);
      $msg = 'Practice location deleted';
    } else {
      $pdo->prepare("DELETE FROM practice_locations WHERE id=?")->execute([(int)$_POST['location_id']]);
      $msg = 'Practice location permanently deleted';
    }
    $tab = 'locations';
  }

  header('Location: /admin/users.php?tab='.$tab); exit;
}
?>
<?php include __DIR__ . '/_header.php'; ?>

<div class="flex items-center justify-between mb-4">
  <div class="text-xl font-semibold">Admin Settings</div>
</div>

<?php if ($msg): ?><div class="mb-3 text-sm bg-teal-50 border border-teal-200 text-teal-700 p-2 rounded"><?=$msg?></div><?php endif; ?>

<div class="mb-4">
  <a class="px-3 py-2 border rounded-t <?=($tab==='physicians'?'bg-white border-b-0':'')?>" href="/admin/users.php?tab=physicians">Providers</a>
  <a class="px-3 py-2 border rounded-t <?=($tab==='employees'?'bg-white border-b-0':'')?>" href="/admin/users.php?tab=employees">Employees</a>
  <?php if ($adminRole !== 'sales'): ?>
  <a class="px-3 py-2 border rounded-t <?=($tab==='manufacturer'?'bg-white border-b-0':'')?>" href="/admin/users.php?tab=manufacturer">Manufacturer</a>
  <?php endif; ?>
  <a class="px-3 py-2 border rounded-t <?=($tab==='locations'?'bg-white border-b-0':'')?>" href="/admin/users.php?tab=locations">Practice Locations</a>
</div>

<div class="bg-white border rounded-b rounded-r p-4">
<?php if ($tab==='physicians'): ?>
  <!-- Rep Filter -->
  <?php if (!empty($activeReps)): ?>
  <div class="mb-4 flex items-center gap-4">
    <label class="text-sm font-medium text-gray-700">Filter by Rep:</label>
    <select id="rep-filter" onchange="filterByRep(this.value)" class="border rounded px-3 py-1.5 text-sm">
      <option value="">All Providers</option>
      <option value="unassigned" <?= $repFilter === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
      <?php foreach ($activeReps as $rep): ?>
        <option value="<?= e($rep['id']) ?>" <?= $repFilter === $rep['id'] ? 'selected' : '' ?>><?= e($rep['first_name'] . ' ' . $rep['last_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($repFilter): ?>
      <a href="/admin/users.php?tab=physicians" class="text-sm text-gray-500 hover:underline">Clear filter</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Name</th>
            <th class="py-2">Email</th>
            <th class="py-2">Type</th>
            <th class="py-2">Assigned Rep</th>
            <th class="py-2">Status</th>
            <th class="py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($phys as $u): ?>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3">
              <div class="font-medium"><?=e(trim(($u['first_name']??'').' '.($u['last_name']??'')))?></div>
              <?php if ($u['practice_name']): ?>
                <div class="text-xs text-slate-500"><?=e($u['practice_name'])?></div>
              <?php endif; ?>
            </td>
            <td class="py-3"><?=e($u['email'] ?? '')?></td>
            <td class="py-3">
              <?php
              $displayType = $u['account_type'] ?? '';
              if (!empty($u['is_hybrid'])) {
                $displayType = 'Referral & Wholesale';
              } elseif ($displayType === 'wholesale') {
                $displayType = 'Wholesale';
              } else {
                $displayType = 'Referral';
              }
              echo e($displayType);
              ?>
            </td>
            <td class="py-3">
              <?php if ($u['rep_name']): ?>
                <span class="text-teal-600 font-medium text-xs"><?= e($u['rep_name']) ?></span>
              <?php else: ?>
                <span class="text-gray-400 text-xs">Unassigned</span>
              <?php endif; ?>
              <?php if ($isSuperadmin || $isManufacturer): ?>
                <button onclick="showRepAssignModal('<?= e($u['id']) ?>', '<?= e(addslashes(trim(($u['first_name']??'').' '.($u['last_name']??'')))) ?>', '<?= e($u['assigned_rep_id'] ?? '') ?>')" class="ml-1 text-blue-600 text-xs hover:underline">[change]</button>
              <?php endif; ?>
            </td>
            <td class="py-3">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= ($u['status'] ?? '') === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                <?=e($u['status'] ?? 'unknown')?>
              </span>
            </td>
            <td class="py-3 space-x-2">
              <button onclick="toggleUserDetails('user-<?=e($u['id'])?>')" class="text-blue-600 text-xs">Details</button>
              <?php if (!$isManufacturer): ?>
              <form method="post" class="inline"><?=csrf_field()?>
                <input type="hidden" name="action" value="reset_phys_pw">
                <input type="hidden" name="phys_id" value="<?=e($u['id'])?>">
                <input type="password" name="newpw" placeholder="New pw" class="border rounded px-2 py-0.5 text-xs w-20" required>
                <button class="text-brand text-xs">Reset</button>
              </form>
              <form method="post" class="inline" onsubmit="return confirm('Delete physician?')"><?=csrf_field()?>
                <input type="hidden" name="action" value="delete_phys">
                <input type="hidden" name="phys_id" value="<?=e($u['id'])?>">
                <button class="text-rose-600 text-xs">Delete</button>
              </form>
              <?php if (!$isOwner): ?>
              <form method="post" class="inline"><?=csrf_field()?>
                <input type="hidden" name="action" value="map_phys">
                <input type="hidden" name="phys_id" value="<?=e($u['id'])?>">
                <button class="text-slate-600 text-xs">Assign to me</button>
              </form>
              <?php endif; ?>
              <?php else: ?>
              <span class="text-slate-400 text-xs">View only</span>
              <?php endif; ?>
            </td>
          </tr>
          <!-- Expandable Details Row -->
          <tr id="user-<?=e($u['id'])?>" class="hidden bg-slate-50">
            <td colspan="6" class="p-4">
              <form method="post" class="grid grid-cols-2 gap-3 text-sm">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="update_phys">
                <input type="hidden" name="phys_id" value="<?=e($u['id'])?>">

                <div>
                  <label class="text-xs text-slate-600">First Name</label>
                  <input class="border rounded px-2 py-1 w-full" name="first_name" value="<?=e($u['first_name']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">Last Name</label>
                  <input class="border rounded px-2 py-1 w-full" name="last_name" value="<?=e($u['last_name']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">Email</label>
                  <input class="border rounded px-2 py-1 w-full" type="email" name="email" value="<?=e($u['email']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">Phone</label>
                  <input class="border rounded px-2 py-1 w-full" name="phone" value="<?=e($u['phone']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">Practice Name</label>
                  <input class="border rounded px-2 py-1 w-full" name="practice_name" value="<?=e($u['practice_name']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">Address</label>
                  <input class="border rounded px-2 py-1 w-full" name="address" value="<?=e($u['address']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">City</label>
                  <input class="border rounded px-2 py-1 w-full" name="city" value="<?=e($u['city']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">State</label>
                  <select class="border rounded px-2 py-1 w-full" name="state">
                    <option value="">Select State</option>
                    <?php
                    $states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];
                    foreach ($states as $st) {
                      $selected = ($u['state']??'') === $st ? 'selected' : '';
                      echo "<option value=\"$st\" $selected>$st</option>";
                    }
                    ?>
                  </select>
                </div>
                <div>
                  <label class="text-xs text-slate-600">Zip</label>
                  <input class="border rounded px-2 py-1 w-full" name="zip" value="<?=e($u['zip']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">Account Type</label>
                  <select class="border rounded px-2 py-1 w-full" name="account_type">
                    <option value="referral" <?=($u['is_referral_only']??false)?'selected':''?>>Referral</option>
                    <option value="wholesale" <?=($u['account_type']??'')==='wholesale'&&!($u['is_hybrid']??false)?'selected':''?>>Wholesale</option>
                    <option value="both" <?=($u['is_hybrid']??false)?'selected':''?>>Referral & Wholesale</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs text-slate-600">NPI</label>
                  <input class="border rounded px-2 py-1 w-full" name="npi" value="<?=e($u['npi']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">PTAN</label>
                  <input class="border rounded px-2 py-1 w-full" name="ptan" value="<?=e($u['ptan']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">License</label>
                  <input class="border rounded px-2 py-1 w-full" name="license" value="<?=e($u['license']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">License State</label>
                  <input class="border rounded px-2 py-1 w-full" name="license_state" value="<?=e($u['license_state']??'')?>">
                </div>
                <div>
                  <label class="text-xs text-slate-600">License Expiry</label>
                  <input class="border rounded px-2 py-1 w-full" type="date" name="license_expiry" value="<?=e($u['license_expiry']??'')?>">
                </div>
                <div class="col-span-2 flex gap-2">
                  <button class="bg-brand text-white rounded px-4 py-2 text-sm">Save Changes</button>
                  <button type="button" onclick="toggleUserDetails('user-<?=e($u['id'])?>')" class="bg-slate-200 rounded px-4 py-2 text-sm">Cancel</button>
                </div>
              </form>

              <!-- Signed Agreements Section -->
              <div class="mt-6 pt-6 border-t">
                <div class="font-semibold text-sm mb-3">Signed Agreements</div>
                <?php if (!empty($u['agree_msa']) && !empty($u['agree_baa'])): ?>
                  <div class="bg-green-50 border border-green-200 rounded p-3 mb-3">
                    <div class="flex items-center gap-2 text-green-700 font-medium text-sm mb-2">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                      Agreements Signed
                    </div>
                    <div class="text-xs text-green-600 space-y-1">
                      <div><strong>Signed By:</strong> <?=e($u['sign_name'] ?? 'N/A')?><?php if ($u['sign_title']): ?>, <?=e($u['sign_title'])?><?php endif; ?></div>
                      <div><strong>Date:</strong> <?=e($u['sign_date'] ? date('M j, Y g:i A', strtotime($u['sign_date'])) : 'N/A')?></div>
                      <?php if ($u['signed_ip']): ?><div><strong>IP Address:</strong> <?=e($u['signed_ip'])?></div><?php endif; ?>
                    </div>
                  </div>

                  <!-- Expandable Agreement Text -->
                  <div class="space-y-3">
                    <details class="border rounded">
                      <summary class="px-3 py-2 bg-slate-100 cursor-pointer text-sm font-medium hover:bg-slate-200">
                        View MD DME Product and Services Agreement
                      </summary>
                      <div class="p-3 text-xs text-slate-700 max-h-80 overflow-y-auto bg-white">
                        <p class="text-center mb-3"><strong>COLLAGENDIRECT / MD DME PRODUCT AND SERVICES AGREEMENT</strong><br>Version 2025-10-29</p>
                        <p class="mb-2">This Product and Services Agreement ("Agreement") is entered into by and between (1) MD DME, LLC ("MD DME"), a Texas limited liability company, and (2) the physician practice, clinic, or provider organization whose authorized representative is accepting this Agreement ("Client").</p>
                        <p class="mb-2"><strong>Effective Date.</strong> This Agreement becomes effective on the date the Client (or its authorized representative) registers or is provisioned within the CollagenDirect Provider Portal and affirmatively accepts this Agreement.</p>

                        <p class="font-semibold mt-3 mb-1">1. Relationship Between MD DME, Client, and CollagenDirect</p>
                        <p class="mb-2">CollagenDirect provides a secure ordering portal and workflow tools. CollagenDirect does not manufacture, dispense, fulfill, ship, bill, or collect payment for Products, and is not the supplier of record. MD DME is a licensed DME supplier solely responsible for fulfillment, shipment, documentation, and billing/collection for Products.</p>

                        <p class="font-semibold mt-3 mb-1">2. Product and Services Models</p>
                        <p class="mb-1"><strong>a. Option A – Physician-Billed Orders:</strong> MD DME supplies Products; Client bills payors through Client's own DME entity and insurance contracts.</p>
                        <p class="mb-2"><strong>b. Option B – MD DME-Billed Orders:</strong> MD DME fulfills, ships, and bills payors under MD DME's supplier credentials.</p>

                        <p class="font-semibold mt-3 mb-1">3. Client Responsibilities</p>
                        <p class="mb-2">Client is solely responsible for: clinical appropriateness and medical necessity; accuracy of coding and claim data; maintaining all source documents, medical records, and audit trails; maintaining accurate logs of all products provided to patients.</p>

                        <p class="font-semibold mt-3 mb-1">4. MD DME Responsibilities</p>
                        <p class="mb-2">MD DME will: provide Product specifications and training support; receive and review orders for completeness; pick, pack, and ship Products; ship within 24 hours (excluding weekends); provide tracking and proof of delivery.</p>

                        <p class="font-semibold mt-3 mb-1">5. Financial Terms</p>
                        <p class="mb-2">For Option A: Shipments 1st–15th → payment due 15th of following month. Shipments 16th–end → payment due 1st of following month. Late invoices accrue 2% APR. MD DME may suspend fulfillment for invoices 30+ days past due.</p>

                        <p class="font-semibold mt-3 mb-1">6–17. Additional Terms</p>
                        <p class="mb-2">Includes: Compliance requirements, 1-year term with auto-renewal, non-exclusivity, termination rights, confidentiality, HIPAA incorporation, limitation of liability, indemnification, Texas governing law, force majeure, assignment restrictions, and electronic execution provisions.</p>

                        <p class="text-center mt-3"><strong>END OF AGREEMENT</strong></p>
                      </div>
                    </details>

                    <details class="border rounded">
                      <summary class="px-3 py-2 bg-slate-100 cursor-pointer text-sm font-medium hover:bg-slate-200">
                        View Business Associate Agreement (BAA)
                      </summary>
                      <div class="p-3 text-xs text-slate-700 max-h-80 overflow-y-auto bg-white">
                        <p class="text-center mb-3"><strong>COLLAGEN DIRECT BUSINESS ASSOCIATE AGREEMENT</strong></p>
                        <p class="mb-2">This Business Associate Agreement ("Agreement") is entered into by and between the "Covered Entity" and CollagenDirect ("Business Associate"), effective as of the date the Covered Entity creates or is provisioned with an account on the CollagenDirect Provider Portal.</p>
                        <p class="mb-2">The Parties enter this Agreement to comply with HIPAA, HITECH, and the Privacy, Security, Breach Notification, and Enforcement Rules at 45 C.F.R. Parts 160 and 164.</p>

                        <p class="font-semibold mt-3 mb-1">1. Definitions</p>
                        <p class="mb-2">Includes standard HIPAA definitions: Administrative/Physical/Technical Safeguards, Breach, Business Associate, Covered Entity, Designated Record Set, HIPAA Rules, Individual, PHI, Privacy Rule, Security Rule, Security Incident, and Unsecured PHI.</p>

                        <p class="font-semibold mt-3 mb-1">2. Obligations of Business Associate</p>
                        <p class="mb-2">Business Associate will: not use/disclose PHI except as permitted; implement appropriate safeguards; mitigate harmful effects of violations; report breaches within 10 business days; ensure subcontractor compliance; make records available to Secretary; document disclosures for accounting; provide PHI access and amendments as required.</p>

                        <p class="font-semibold mt-3 mb-1">3. HIPAA Security Rule Requirements</p>
                        <p class="mb-2">Business Associate will implement Administrative, Physical, and Technical Safeguards; ensure subcontractor compliance; cooperate in breach mitigation; make policies available for compliance determination.</p>

                        <p class="font-semibold mt-3 mb-1">4. HITECH Requirements</p>
                        <p class="mb-2">Business Associate will not sell PHI; will track disclosures; limit uses to "minimum necessary"; report material breaches; acknowledges direct liability for civil/criminal penalties.</p>

                        <p class="font-semibold mt-3 mb-1">5. Permitted Uses and Disclosures</p>
                        <p class="mb-2">Business Associate may use PHI to: perform services and operate Portal; for proper management/administration; for data aggregation on behalf of Covered Entity's healthcare operations.</p>

                        <p class="font-semibold mt-3 mb-1">6. Obligations of Covered Entity</p>
                        <p class="mb-2">Covered Entity will: provide Notice of Privacy Practices; inform of permission changes; notify of use/disclosure restrictions; obtain required authorizations before disclosing PHI.</p>

                        <p class="font-semibold mt-3 mb-1">7. Term and Termination</p>
                        <p class="mb-2">Effective until PHI is returned/destroyed. Termination for cause with 30-day cure period. Upon termination, return or destroy all PHI.</p>

                        <p class="font-semibold mt-3 mb-1">8. Miscellaneous</p>
                        <p class="mb-2">Includes: regulatory references, amendment provisions, survival of obligations, interpretation rules, Texas governing law/venue (Bexar County), and electronic execution provisions.</p>

                        <p class="text-center mt-3"><strong>END OF AGREEMENT</strong></p>
                      </div>
                    </details>
                  </div>
                <?php else: ?>
                  <div class="bg-amber-50 border border-amber-200 rounded p-3">
                    <div class="flex items-center gap-2 text-amber-700 font-medium text-sm">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                      Agreements Not Yet Signed
                    </div>
                    <div class="text-xs text-amber-600 mt-1">
                      This provider has not completed the Terms of Service and Business Associate Agreement.
                    </div>
                  </div>
                <?php endif; ?>
              </div>

              <?php if (($u['role'] ?? '') === 'practice_admin'): ?>
              <!-- Practice Locations Section -->
              <div class="mt-6 pt-6 border-t">
                <div class="font-semibold text-sm mb-3">Practice Locations</div>

                <?php
                // Get locations for this practice
                $practiceLocations = [];
                try {
                  $locStmt = $pdo->prepare("
                    SELECT * FROM practice_locations
                    WHERE user_id = ? AND (deleted_at IS NULL OR deleted_at = '')
                    ORDER BY is_primary DESC, location_name ASC
                  ");
                  $locStmt->execute([$u['id']]);
                  $practiceLocations = $locStmt->fetchAll();
                } catch (Throwable $e) {
                  // Table might not exist yet
                }
                ?>

                <?php if (count($practiceLocations) > 0): ?>
                <div class="mb-3">
                  <table class="w-full text-xs">
                    <thead class="border-b">
                      <tr>
                        <th class="text-left py-1">Location</th>
                        <th class="text-left py-1">Address</th>
                        <th class="text-left py-1">Primary</th>
                        <th class="text-left py-1">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($practiceLocations as $loc): ?>
                      <tr class="border-b">
                        <td class="py-2"><?=e($loc['location_name'])?></td>
                        <td class="py-2 text-xs"><?=e($loc['city'])?>, <?=e($loc['state'])?></td>
                        <td class="py-2"><?=$loc['is_primary'] ? '✓' : ''?></td>
                        <td class="py-2">
                          <button onclick="editPracticeLocation(<?=htmlspecialchars(json_encode($loc), ENT_QUOTES, 'UTF-8')?>, '<?=e($u['id'])?>')" class="text-blue-600 text-xs">Edit</button>
                          <form method="post" class="inline ml-2" onsubmit="return confirm('Delete this location?')">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="delete_location">
                            <input type="hidden" name="location_id" value="<?=e($loc['id'])?>">
                            <button class="text-rose-600 text-xs">Delete</button>
                          </form>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php endif; ?>

                <!-- Add Location Form -->
                <div class="bg-slate-100 p-3 rounded">
                  <div class="text-xs font-semibold mb-2">Add Location</div>
                  <form method="post" class="grid grid-cols-2 gap-2" id="add-location-form-<?=e($u['id'])?>">
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="create_location" id="location-action-<?=e($u['id'])?>">
                    <input type="hidden" name="location_id" id="location-id-<?=e($u['id'])?>">
                    <input type="hidden" name="user_id" value="<?=e($u['id'])?>">

                    <input class="border rounded px-2 py-1 text-xs col-span-2" name="location_name" id="location-name-<?=e($u['id'])?>" placeholder="Location name" required>
                    <input class="border rounded px-2 py-1 text-xs col-span-2" name="address" id="location-address-<?=e($u['id'])?>" placeholder="Address" required>
                    <input class="border rounded px-2 py-1 text-xs" name="city" id="location-city-<?=e($u['id'])?>" placeholder="City" required>
                    <select class="border rounded px-2 py-1 text-xs" name="state" id="location-state-<?=e($u['id'])?>" required>
                      <option value="">State</option>
                      <?php foreach ($states as $st): ?>
                        <option value="<?=$st?>"><?=$st?></option>
                      <?php endforeach; ?>
                    </select>
                    <input class="border rounded px-2 py-1 text-xs" name="zip" id="location-zip-<?=e($u['id'])?>" placeholder="ZIP" required>
                    <input class="border rounded px-2 py-1 text-xs" name="phone" id="location-phone-<?=e($u['id'])?>" placeholder="Phone">
                    <div class="col-span-2">
                      <label class="flex items-center text-xs">
                        <input type="checkbox" name="is_primary" id="location-is-primary-<?=e($u['id'])?>" class="mr-2">
                        <span>Set as primary location</span>
                      </label>
                    </div>
                    <div class="col-span-2 flex gap-2">
                      <button class="bg-brand text-white rounded px-3 py-1 text-xs" id="location-submit-btn-<?=e($u['id'])?>">Add Location</button>
                      <button type="button" onclick="resetLocationForm('<?=e($u['id'])?>')" class="bg-slate-300 rounded px-3 py-1 text-xs" id="location-cancel-btn-<?=e($u['id'])?>" style="display:none;">Cancel</button>
                    </div>
                  </form>
                </div>
              </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div>
      <div class="font-semibold mb-2">Add Provider</div>
      <form method="post" class="bg-slate-50 border rounded p-3 text-sm" id="provider-form">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="create_phys">

        <!-- Provider Type Selection -->
        <div class="mb-3">
          <label class="flex items-center mb-2">
            <input type="radio" name="provider_type" value="practice" checked onchange="toggleProviderFields()">
            <span class="ml-2">Practice Owner</span>
          </label>
          <label class="flex items-center">
            <input type="radio" name="provider_type" value="physician" onchange="toggleProviderFields()">
            <span class="ml-2">Physician to Practice</span>
          </label>
        </div>

        <!-- Practice Selection (for physicians) -->
        <div id="practice-select-field" style="display:none;" class="mb-2">
          <select name="practice_id" class="border rounded px-2 py-1 w-full">
            <option value="">Select Practice</option>
            <?php
            $practices = $pdo->query("SELECT id, practice_name, CONCAT(first_name, ' ', last_name) as owner_name FROM users WHERE role='practice_admin' AND practice_name IS NOT NULL ORDER BY practice_name")->fetchAll();
            foreach ($practices as $p):
            ?>
              <option value="<?=e($p['id'])?>"><?=e($p['practice_name'])?> (<?=e($p['owner_name'])?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Practice Information (for practice owners only) -->
        <div id="practice-info-fields">
          <div class="mb-2">
            <input class="border rounded px-2 py-1 w-full" name="practice_name" placeholder="Practice name *" id="practice-name-input">
          </div>
          <div class="mb-2">
            <input class="border rounded px-2 py-1 w-full" name="address" placeholder="Address *" id="address-input" autocomplete="off">
          </div>
          <div class="grid grid-cols-2 gap-2 mb-2">
            <input class="border rounded px-2 py-1" name="city" placeholder="City *" id="city-input">
            <select class="border rounded px-2 py-1" name="state" id="state-input">
              <option value="">State *</option>
              <option value="AL">AL</option><option value="AK">AK</option><option value="AZ">AZ</option><option value="AR">AR</option><option value="CA">CA</option><option value="CO">CO</option><option value="CT">CT</option><option value="DE">DE</option><option value="FL">FL</option><option value="GA">GA</option><option value="HI">HI</option><option value="ID">ID</option><option value="IL">IL</option><option value="IN">IN</option><option value="IA">IA</option><option value="KS">KS</option><option value="KY">KY</option><option value="LA">LA</option><option value="ME">ME</option><option value="MD">MD</option><option value="MA">MA</option><option value="MI">MI</option><option value="MN">MN</option><option value="MS">MS</option><option value="MO">MO</option><option value="MT">MT</option><option value="NE">NE</option><option value="NV">NV</option><option value="NH">NH</option><option value="NJ">NJ</option><option value="NM">NM</option><option value="NY">NY</option><option value="NC">NC</option><option value="ND">ND</option><option value="OH">OH</option><option value="OK">OK</option><option value="OR">OR</option><option value="PA">PA</option><option value="RI">RI</option><option value="SC">SC</option><option value="SD">SD</option><option value="TN">TN</option><option value="TX">TX</option><option value="UT">UT</option><option value="VT">VT</option><option value="VA">VA</option><option value="WA">WA</option><option value="WV">WV</option><option value="WI">WI</option><option value="WY">WY</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-2 mb-2">
            <input class="border rounded px-2 py-1" name="zip" placeholder="Zip *" id="zip-input">
            <input class="border rounded px-2 py-1" name="phone" placeholder="Phone *" id="phone-input">
          </div>
        </div>

        <!-- Personal Information -->
        <div class="mb-2">
          <input class="border rounded px-2 py-1 w-full" name="first_name" placeholder="First name *" required>
        </div>
        <div class="mb-2">
          <input class="border rounded px-2 py-1 w-full" name="last_name" placeholder="Last name *" required>
        </div>
        <div class="mb-2">
          <input class="border rounded px-2 py-1 w-full" type="email" name="email" placeholder="Email *" required>
        </div>

        <!-- Account Type Dropdown -->
        <div class="mb-2">
          <label class="text-sm text-slate-600 block mb-1">Account Type *</label>
          <select class="border rounded px-2 py-1 w-full" name="account_type" required>
            <option value="">Select Account Type</option>
            <option value="referral">Referral</option>
            <option value="wholesale">Wholesale</option>
            <option value="both">Referral & Wholesale</option>
          </select>
        </div>

        <!-- Security Role Dropdown -->
        <div class="mb-2">
          <label class="text-sm text-slate-600 block mb-1">Security Role *</label>
          <select class="border rounded px-2 py-1 w-full" name="security_role" required>
            <option value="">Select Role</option>
            <option value="practice_manager">Practice Manager</option>
            <option value="physician">Physician</option>
          </select>
        </div>

        <!-- Rep Assignment (Superadmin/Manufacturer only) -->
        <?php if (($isSuperadmin || $isManufacturer) && !empty($activeReps)): ?>
        <div class="mb-2">
          <label class="text-sm text-slate-600 block mb-1">Assign to Sales Rep</label>
          <select class="border rounded px-2 py-1 w-full" name="assigned_rep_id">
            <option value="">Unassigned</option>
            <?php foreach ($activeReps as $rep): ?>
              <option value="<?= e($rep['id']) ?>"><?= e($rep['first_name'] . ' ' . $rep['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="text-xs text-slate-500 mt-1">Optional: Assign this provider to a sales rep</div>
        </div>
        <?php endif; ?>

        <!-- Physician Credentials (Optional) -->
        <div class="mb-2">
          <input class="border rounded px-2 py-1 w-full" name="npi" placeholder="NPI (10 digits)" maxlength="10">
        </div>
        <div class="mb-2">
          <input class="border rounded px-2 py-1 w-full" name="ptan" placeholder="PTAN (Optional)">
        </div>
        <div class="mb-2">
          <input class="border rounded px-2 py-1 w-full" name="license" placeholder="Medical License Number">
        </div>
        <div class="grid grid-cols-2 gap-2 mb-2">
          <select class="border rounded px-2 py-1" name="license_state">
            <option value="">License State</option>
            <option value="AL">AL</option><option value="AK">AK</option><option value="AZ">AZ</option><option value="AR">AR</option><option value="CA">CA</option><option value="CO">CO</option><option value="CT">CT</option><option value="DE">DE</option><option value="FL">FL</option><option value="GA">GA</option><option value="HI">HI</option><option value="ID">ID</option><option value="IL">IL</option><option value="IN">IN</option><option value="IA">IA</option><option value="KS">KS</option><option value="KY">KY</option><option value="LA">LA</option><option value="ME">ME</option><option value="MD">MD</option><option value="MA">MA</option><option value="MI">MI</option><option value="MN">MN</option><option value="MS">MS</option><option value="MO">MO</option><option value="MT">MT</option><option value="NE">NE</option><option value="NV">NV</option><option value="NH">NH</option><option value="NJ">NJ</option><option value="NM">NM</option><option value="NY">NY</option><option value="NC">NC</option><option value="ND">ND</option><option value="OH">OH</option><option value="OK">OK</option><option value="OR">OR</option><option value="PA">PA</option><option value="RI">RI</option><option value="SC">SC</option><option value="SD">SD</option><option value="TN">TN</option><option value="TX">TX</option><option value="UT">UT</option><option value="VT">VT</option><option value="VA">VA</option><option value="WA">WA</option><option value="WV">WV</option><option value="WI">WI</option><option value="WY">WY</option>
          </select>
          <input class="border rounded px-2 py-1" name="license_expiry" type="date" placeholder="License Expiry">
        </div>

        <div class="mb-2">
          <input class="border rounded px-2 py-1 w-full" type="password" name="password" placeholder="Temporary Password *" required>
        </div>
        <button class="bg-brand text-white rounded px-3 py-1">Create Provider</button>
      </form>

      <script>
      function toggleProviderFields() {
        const type = document.querySelector('input[name="provider_type"]:checked').value;
        const practiceSelectField = document.getElementById('practice-select-field');
        const practiceInfoFields = document.getElementById('practice-info-fields');
        const practiceSelect = document.querySelector('select[name="practice_id"]');

        // Get practice info field elements
        const practiceNameInput = document.getElementById('practice-name-input');
        const addressInput = document.getElementById('address-input');
        const cityInput = document.getElementById('city-input');
        const stateInput = document.getElementById('state-input');
        const zipInput = document.getElementById('zip-input');
        const phoneInput = document.getElementById('phone-input');

        if (type === 'physician') {
          // Show practice selection dropdown, hide practice info fields
          practiceSelectField.style.display = 'block';
          practiceInfoFields.style.display = 'none';
          practiceSelect.required = true;

          // Make practice info fields not required
          practiceNameInput.required = false;
          addressInput.required = false;
          cityInput.required = false;
          stateInput.required = false;
          zipInput.required = false;
          phoneInput.required = false;
        } else {
          // Hide practice selection, show practice info fields
          practiceSelectField.style.display = 'none';
          practiceInfoFields.style.display = 'block';
          practiceSelect.required = false;

          // Make practice info fields required
          practiceNameInput.required = true;
          addressInput.required = true;
          cityInput.required = true;
          stateInput.required = true;
          zipInput.required = true;
          phoneInput.required = true;
        }
      }
      </script>
    </div>
  </div>
<?php elseif ($tab==='employees'): ?>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="overflow-x-auto">
      <div class="font-semibold mb-2">Employees</div>
      <table class="w-full text-sm min-w-[600px]">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Name</th>
            <th class="py-2">Email</th>
            <th class="py-2">Role</th>
            <th class="py-2">Added</th>
            <th class="py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($emps as $e): ?>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3"><?=e($e['name'])?></td>
            <td class="py-3"><?=e($e['email'])?></td>
            <td class="py-3"><?=e($e['role'])?></td>
            <td class="py-3"><?=e($e['created_at'])?></td>
            <td class="py-3 space-x-2">
              <?php if ($isAdmin): ?>
              <form method="post" class="inline"><?=csrf_field()?>
                <input type="hidden" name="action" value="reset_emp_pw"><input type="hidden" name="emp_id" value="<?=$e['id']?>">
                <input type="password" name="newpw" class="border rounded px-2 py-0.5 text-xs" placeholder="New pw" required>
                <button class="text-brand text-xs">Reset PW</button>
              </form>
              <button onclick="showAssignDialog(<?=$e['id']?>, '<?=e($e['name'])?>')" class="text-blue-600 text-xs">Assign Physicians</button>
              <form method="post" class="inline" onsubmit="return confirm('Delete employee?')"><?=csrf_field()?>
                <input type="hidden" name="action" value="delete_emp"><input type="hidden" name="emp_id" value="<?=$e['id']?>">
                <button class="text-rose-600 text-xs">Delete</button>
              </form>
              <?php else: ?>
              <span class="text-slate-400 text-xs">View only</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($isAdmin): ?>
    <div>
      <div class="font-semibold mb-2">Add Employee</div>
      <form method="post" class="bg-slate-50 border rounded p-3">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="create_employee"/>
        <div class="grid grid-cols-2 gap-2">
          <input class="border rounded px-2 py-1 col-span-2" name="name" placeholder="Full name" required/>
          <input class="border rounded px-2 py-1 col-span-2" type="email" name="email" placeholder="Email" required/>
          <select class="border rounded px-2 py-1" name="role" required>
            <option value="">Select Role</option>
            <option value="admin">Admin</option>
            <option value="sales">Sales</option>
            <option value="ops">Ops</option>
          </select>
          <input class="border rounded px-2 py-1" type="password" name="password" placeholder="Temporary password" required/>
        </div>
        <button class="mt-2 bg-brand text-white rounded px-3 py-1">Create</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
<?php elseif ($tab==='manufacturer'): ?>
  <!-- Manufacturer Tab -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="overflow-x-auto">
      <div class="font-semibold mb-2">Manufacturer Representatives</div>
      <table class="w-full text-sm min-w-[600px]">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Name</th>
            <th class="py-2">Email</th>
            <th class="py-2">Role</th>
            <th class="py-2">Added</th>
            <th class="py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($manufacturers as $m): ?>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3"><?=e($m['name'])?></td>
            <td class="py-3"><?=e($m['email'])?></td>
            <td class="py-3"><?=e($m['role'])?></td>
            <td class="py-3"><?=e($m['created_at'])?></td>
            <td class="py-3 space-x-2">
              <?php if ($isAdmin): ?>
              <form method="post" class="inline"><?=csrf_field()?>
                <input type="hidden" name="action" value="reset_emp_pw"><input type="hidden" name="emp_id" value="<?=$m['id']?>">
                <input type="password" name="newpw" class="border rounded px-2 py-0.5 text-xs" placeholder="New pw" required>
                <button class="text-brand text-xs">Reset PW</button>
              </form>
              <form method="post" class="inline" onsubmit="return confirm('Delete manufacturer representative?')"><?=csrf_field()?>
                <input type="hidden" name="action" value="delete_emp"><input type="hidden" name="emp_id" value="<?=$m['id']?>">
                <button class="text-rose-600 text-xs">Delete</button>
              </form>
              <?php else: ?>
              <span class="text-slate-400 text-xs">View only</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($isAdmin || $isManufacturer): ?>
    <div>
      <div class="font-semibold mb-2">Add Manufacturer Representative</div>
      <form method="post" class="bg-slate-50 border rounded p-3">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="create_employee"/>
        <div class="grid grid-cols-2 gap-2">
          <input class="border rounded px-2 py-1 col-span-2" name="name" placeholder="Full name" required/>
          <input class="border rounded px-2 py-1 col-span-2" type="email" name="email" placeholder="Email" required/>
          <input type="hidden" name="role" value="manufacturer"/>
          <input class="border rounded px-2 py-1 col-span-2" type="password" name="password" placeholder="Temporary password" required/>
        </div>
        <button class="mt-2 bg-brand text-white rounded px-3 py-1">Create</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

<?php elseif ($tab==='locations'): ?>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 overflow-x-auto">
      <div class="flex justify-between items-center mb-2">
        <div class="font-semibold">Practice Locations</div>
        <input type="text" id="location-filter" placeholder="Filter by practice name..." class="border rounded px-3 py-1 text-sm" onkeyup="filterLocations()">
      </div>
      <table class="w-full text-sm min-w-[800px]" id="locations-table">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Practice</th>
            <th class="py-2">Location Name</th>
            <th class="py-2">Address</th>
            <th class="py-2">Phone</th>
            <th class="py-2">Primary</th>
            <th class="py-2">Status</th>
            <th class="py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($locations as $loc): ?>
          <tr class="border-b hover:bg-slate-50 location-row" data-practice="<?=e(strtolower($loc['practice_name'] ?? ''))?>">
            <td class="py-3"><?=e($loc['practice_name'] ?? '')?></td>
            <td class="py-3"><?=e($loc['location_name'])?></td>
            <td class="py-3 text-xs"><?=e($loc['address'])?>, <?=e($loc['city'])?>, <?=e($loc['state'])?> <?=e($loc['zip'])?></td>
            <td class="py-3"><?=e($loc['phone'] ?? '')?></td>
            <td class="py-3"><?=$loc['is_primary'] ? '✓' : ''?></td>
            <td class="py-3"><?=$loc['is_active'] ? '<span class="text-green-600">Active</span>' : '<span class="text-gray-400">Inactive</span>'?></td>
            <td class="py-3 space-x-2">
              <?php if ($isAdmin): ?>
              <button onclick="editLocation(<?=htmlspecialchars(json_encode($loc), ENT_QUOTES, 'UTF-8')?>)" class="text-brand text-xs">Edit</button>
              <form method="post" class="inline" onsubmit="return confirm('Delete location?')"><?=csrf_field()?>
                <input type="hidden" name="action" value="delete_location">
                <input type="hidden" name="location_id" value="<?=e($loc['id'])?>">
                <button class="text-rose-600 text-xs">Delete</button>
              </form>
              <?php else: ?>
              <span class="text-slate-400 text-xs">View only</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($isAdmin): ?>
    <div>
      <div class="font-semibold mb-2">Add Practice Location</div>
      <form method="post" class="bg-slate-50 border rounded p-3 text-sm" id="location-form">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="create_location" id="location-action">
        <input type="hidden" name="location_id" id="location-id">

        <div class="mb-2">
          <label class="text-xs text-slate-600 block mb-1">Practice</label>
          <select name="user_id" id="location-user-id" class="border rounded px-2 py-1 w-full" required>
            <option value="">Select Practice</option>
            <?php
            $practicesForLoc = $pdo->query("SELECT id, practice_name, CONCAT(first_name, ' ', last_name) as owner_name FROM users WHERE role='practice_admin' AND practice_name IS NOT NULL ORDER BY practice_name")->fetchAll();
            foreach ($practicesForLoc as $p):
            ?>
              <option value="<?=e($p['id'])?>"><?=e($p['practice_name'])?> (<?=e($p['owner_name'])?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <input class="border rounded px-2 py-1 w-full mb-2" name="location_name" id="location-name" placeholder="Location name" required>
        <input class="border rounded px-2 py-1 w-full mb-2" name="address" id="location-address" placeholder="Address" required>
        <div class="grid grid-cols-2 gap-2 mb-2">
          <input class="border rounded px-2 py-1" name="city" id="location-city" placeholder="City" required>
          <select class="border rounded px-2 py-1" name="state" id="location-state" required>
            <option value="">State</option>
            <option value="AL">AL</option><option value="AK">AK</option><option value="AZ">AZ</option><option value="AR">AR</option>
            <option value="CA">CA</option><option value="CO">CO</option><option value="CT">CT</option><option value="DE">DE</option>
            <option value="FL">FL</option><option value="GA">GA</option><option value="HI">HI</option><option value="ID">ID</option>
            <option value="IL">IL</option><option value="IN">IN</option><option value="IA">IA</option><option value="KS">KS</option>
            <option value="KY">KY</option><option value="LA">LA</option><option value="ME">ME</option><option value="MD">MD</option>
            <option value="MA">MA</option><option value="MI">MI</option><option value="MN">MN</option><option value="MS">MS</option>
            <option value="MO">MO</option><option value="MT">MT</option><option value="NE">NE</option><option value="NV">NV</option>
            <option value="NH">NH</option><option value="NJ">NJ</option><option value="NM">NM</option><option value="NY">NY</option>
            <option value="NC">NC</option><option value="ND">ND</option><option value="OH">OH</option><option value="OK">OK</option>
            <option value="OR">OR</option><option value="PA">PA</option><option value="RI">RI</option><option value="SC">SC</option>
            <option value="SD">SD</option><option value="TN">TN</option><option value="TX">TX</option><option value="UT">UT</option>
            <option value="VT">VT</option><option value="VA">VA</option><option value="WA">WA</option><option value="WV">WV</option>
            <option value="WI">WI</option><option value="WY">WY</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-2">
          <input class="border rounded px-2 py-1" name="zip" id="location-zip" placeholder="ZIP" required>
          <input class="border rounded px-2 py-1" name="phone" id="location-phone" placeholder="Phone">
        </div>

        <div class="mb-2">
          <label class="flex items-center text-xs">
            <input type="checkbox" name="is_primary" id="location-is-primary" class="mr-2">
            <span>Set as primary location</span>
          </label>
        </div>

        <div class="mb-2" id="location-active-field" style="display:none;">
          <label class="flex items-center text-xs">
            <input type="checkbox" name="is_active" id="location-is-active" class="mr-2" checked>
            <span>Active</span>
          </label>
        </div>

        <button type="submit" class="bg-brand text-white rounded px-3 py-1 w-full" id="location-submit-btn">Create Location</button>
        <button type="button" onclick="resetLocationForm()" class="mt-2 bg-gray-200 text-gray-700 rounded px-3 py-1 w-full text-sm" id="location-cancel-btn" style="display:none;">Cancel</button>
      </form>

      <script>
      function editLocation(loc) {
        document.getElementById('location-action').value = 'update_location';
        document.getElementById('location-id').value = loc.id;
        document.getElementById('location-user-id').value = loc.user_id;
        document.getElementById('location-name').value = loc.location_name;
        document.getElementById('location-address').value = loc.address;
        document.getElementById('location-city').value = loc.city;
        document.getElementById('location-state').value = loc.state;
        document.getElementById('location-zip').value = loc.zip;
        document.getElementById('location-phone').value = loc.phone || '';
        document.getElementById('location-is-primary').checked = loc.is_primary == 1;
        document.getElementById('location-is-active').checked = loc.is_active == 1;
        document.getElementById('location-active-field').style.display = 'block';
        document.getElementById('location-submit-btn').textContent = 'Update Location';
        document.getElementById('location-cancel-btn').style.display = 'block';
        document.getElementById('location-user-id').disabled = true; // Can't change practice for existing location
      }

      function resetLocationForm() {
        document.getElementById('location-form').reset();
        document.getElementById('location-action').value = 'create_location';
        document.getElementById('location-id').value = '';
        document.getElementById('location-active-field').style.display = 'none';
        document.getElementById('location-submit-btn').textContent = 'Create Location';
        document.getElementById('location-cancel-btn').style.display = 'none';
        document.getElementById('location-user-id').disabled = false;
      }
      </script>
    </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>

<!-- Physician Assignment Dialog -->
<dialog id="assign-dialog" class="rounded-lg shadow-lg p-0" style="max-width: 600px; width: 90%;">
  <form method="post" class="p-6">
    <?=csrf_field()?>
    <input type="hidden" name="action" value="assign_physicians">
    <input type="hidden" name="employee_id" id="assign-employee-id">

    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold">Assign Physicians to <span id="assign-employee-name"></span></h2>
      <button type="button" onclick="document.getElementById('assign-dialog').close()" class="text-slate-400 hover:text-slate-600">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>

    <div class="mb-4 text-sm text-slate-600">
      Select which physicians this employee can access. They will only see patients and orders from these physicians.
    </div>

    <div class="border rounded p-3 max-h-96 overflow-y-auto mb-4">
      <div class="mb-2">
        <label class="flex items-center">
          <input type="checkbox" id="select-all-physicians" onchange="toggleAllPhysicians()">
          <span class="ml-2 font-semibold">Select All</span>
        </label>
      </div>
      <div class="border-t pt-2">
        <?php
        // Get all physicians for assignment
        $allPhysicians = $pdo->query("
          SELECT id, first_name, last_name, email, practice_name, role
          FROM users
          WHERE role IN ('physician', 'practice_admin')
          ORDER BY first_name, last_name
        ")->fetchAll();

        foreach ($allPhysicians as $p):
        ?>
        <label class="flex items-center py-2 hover:bg-slate-50 px-2 rounded">
          <input type="checkbox" name="physicians[]" value="<?=e($p['id'])?>" class="physician-checkbox" data-employee-id="">
          <span class="ml-2 text-sm">
            <?=e($p['first_name'])?> <?=e($p['last_name'])?>
            <?php if ($p['practice_name']): ?>
              <span class="text-slate-500">(<?=e($p['practice_name'])?>)</span>
            <?php endif; ?>
            <span class="text-xs text-slate-400"><?=e($p['email'])?></span>
          </span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="flex justify-end gap-3">
      <button type="button" onclick="document.getElementById('assign-dialog').close()" class="btn">Cancel</button>
      <button type="submit" class="btn btn-primary">Save Assignments</button>
    </div>
  </form>
</dialog>

<script>
// Store physician assignments data
const physicianAssignments = <?php
  $assignments = [];
  $stmt = $pdo->query("
    SELECT admin_id, physician_user_id
    FROM admin_physicians
  ");
  while ($row = $stmt->fetch()) {
    if (!isset($assignments[$row['admin_id']])) {
      $assignments[$row['admin_id']] = [];
    }
    $assignments[$row['admin_id']][] = $row['physician_user_id'];
  }
  echo json_encode($assignments);
?>;

function showAssignDialog(employeeId, employeeName) {
  document.getElementById('assign-employee-id').value = employeeId;
  document.getElementById('assign-employee-name').textContent = employeeName;

  // Uncheck all first
  document.querySelectorAll('.physician-checkbox').forEach(cb => {
    cb.checked = false;
  });

  // Check currently assigned physicians
  const assigned = physicianAssignments[employeeId] || [];
  assigned.forEach(physId => {
    const checkbox = document.querySelector(`.physician-checkbox[value="${physId}"]`);
    if (checkbox) checkbox.checked = true;
  });

  // Update select all state
  updateSelectAllState();

  document.getElementById('assign-dialog').showModal();
}

function toggleAllPhysicians() {
  const selectAll = document.getElementById('select-all-physicians');
  document.querySelectorAll('.physician-checkbox').forEach(cb => {
    cb.checked = selectAll.checked;
  });
}

function updateSelectAllState() {
  const checkboxes = document.querySelectorAll('.physician-checkbox');
  const checked = document.querySelectorAll('.physician-checkbox:checked');
  const selectAll = document.getElementById('select-all-physicians');
  selectAll.checked = checkboxes.length === checked.length;
}

// Update select all state when individual checkboxes change
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.physician-checkbox').forEach(cb => {
    cb.addEventListener('change', updateSelectAllState);
  });
});

// Toggle user details accordion
function toggleUserDetails(rowId) {
  const row = document.getElementById(rowId);
  if (row.classList.contains('hidden')) {
    row.classList.remove('hidden');
    // Initialize autocomplete for newly visible address fields
    setTimeout(initAutocomplete, 100);
  } else {
    row.classList.add('hidden');
  }
}

// Google Places Autocomplete for address standardization
function initAutocomplete() {
  // Don't initialize if Google Maps isn't loaded
  if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
    return;
  }

  // Find all address input fields that haven't been initialized yet
  const addressInputs = document.querySelectorAll('input[name="address"]:not([data-autocomplete-initialized])');

  addressInputs.forEach((addressInput) => {
    // Mark as initialized to prevent duplicate initialization
    addressInput.setAttribute('data-autocomplete-initialized', 'true');

    const autocomplete = new google.maps.places.Autocomplete(addressInput, {
      types: ['address'],
      componentRestrictions: { country: 'us' }
    });

    autocomplete.addListener('place_changed', function() {
      const place = autocomplete.getPlace();
      if (!place.geometry) return;

      // Extract address components
      let street = '';
      let city = '';
      let state = '';
      let zip = '';

      for (const component of place.address_components) {
        const componentType = component.types[0];
        switch (componentType) {
          case 'street_number':
            street = component.long_name + ' ';
            break;
          case 'route':
            street += component.long_name;
            break;
          case 'locality':
            city = component.long_name;
            break;
          case 'administrative_area_level_1':
            state = component.short_name;
            break;
          case 'postal_code':
            zip = component.long_name;
            break;
        }
      }

      // Populate the form fields
      const form = addressInput.closest('form');
      if (form) {
        const cityInput = form.querySelector('input[name="city"]');
        const stateSelect = form.querySelector('select[name="state"]');
        const zipInput = form.querySelector('input[name="zip"]');

        if (street) addressInput.value = street;
        if (city && cityInput) cityInput.value = city;
        if (state && stateSelect) stateSelect.value = state;
        if (zip && zipInput) zipInput.value = zip;
      }
    });
  });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initAutocomplete);

// Filter locations by practice name
function filterLocations() {
  const filterValue = document.getElementById('location-filter').value.toLowerCase();
  const rows = document.querySelectorAll('.location-row');

  rows.forEach(row => {
    const practiceName = row.getAttribute('data-practice');
    if (practiceName.includes(filterValue)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

// Edit practice location (in accordion)
function editPracticeLocation(location, userId) {
  document.getElementById('location-action-' + userId).value = 'update_location';
  document.getElementById('location-id-' + userId).value = location.id;
  document.getElementById('location-name-' + userId).value = location.location_name;
  document.getElementById('location-address-' + userId).value = location.address;
  document.getElementById('location-city-' + userId).value = location.city;
  document.getElementById('location-state-' + userId).value = location.state;
  document.getElementById('location-zip-' + userId).value = location.zip;
  document.getElementById('location-phone-' + userId).value = location.phone || '';
  document.getElementById('location-is-primary-' + userId).checked = location.is_primary ? true : false;
  document.getElementById('location-submit-btn-' + userId).textContent = 'Update Location';
  document.getElementById('location-cancel-btn-' + userId).style.display = 'inline-block';
}

// Reset location form (in accordion)
function resetLocationForm(userId) {
  const form = document.getElementById('add-location-form-' + userId);
  form.reset();
  document.getElementById('location-action-' + userId).value = 'create_location';
  document.getElementById('location-id-' + userId).value = '';
  document.getElementById('location-submit-btn-' + userId).textContent = 'Add Location';
  document.getElementById('location-cancel-btn-' + userId).style.display = 'none';
}

// Filter by rep
function filterByRep(repId) {
  const url = new URL(window.location.href);
  url.searchParams.set('tab', 'physicians');
  if (repId) {
    url.searchParams.set('rep', repId);
  } else {
    url.searchParams.delete('rep');
  }
  window.location.href = url.toString();
}

// Show rep assignment modal
function showRepAssignModal(userId, userName, currentRepId) {
  document.getElementById('rep-assign-user-id').value = userId;
  document.getElementById('rep-assign-user-name').textContent = userName;
  document.getElementById('rep-assign-select').value = currentRepId || '';
  document.getElementById('rep-assign-modal').showModal();
}
</script>

<!-- Rep Assignment Modal -->
<?php if ($isSuperadmin || $isManufacturer): ?>
<dialog id="rep-assign-modal" class="rounded-lg shadow-xl w-full max-w-md p-0 backdrop:bg-black/50">
  <form method="post" class="p-6">
    <?=csrf_field()?>
    <input type="hidden" name="action" value="assign_rep">
    <input type="hidden" name="phys_id" id="rep-assign-user-id">

    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold">Assign Sales Rep</h3>
      <button type="button" onclick="document.getElementById('rep-assign-modal').close()" class="text-slate-400 hover:text-slate-600">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>

    <p class="text-sm text-gray-600 mb-4">
      Assign <strong id="rep-assign-user-name"></strong> to a sales rep:
    </p>

    <div class="mb-4">
      <select name="assigned_rep_id" id="rep-assign-select" class="border rounded px-3 py-2 w-full">
        <option value="">Unassigned</option>
        <?php foreach ($activeReps as $rep): ?>
          <option value="<?= e($rep['id']) ?>"><?= e($rep['first_name'] . ' ' . $rep['last_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="flex justify-end gap-3">
      <button type="button" onclick="document.getElementById('rep-assign-modal').close()" class="btn">Cancel</button>
      <button type="submit" class="btn btn-primary">Save Assignment</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<?php if (defined('GOOGLE_PLACES_API_KEY') && !empty(GOOGLE_PLACES_API_KEY)): ?>
<!-- Google Maps Places API for address autocomplete -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?=htmlspecialchars(GOOGLE_PLACES_API_KEY)?>&libraries=places&callback=initAutocomplete" async defer></script>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
