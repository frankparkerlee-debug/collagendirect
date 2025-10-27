<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_admin();
require_once __DIR__ . '/../api/lib/provider_welcome.php'; // Email notifications

$admin = current_admin();
$isOwner = in_array(($admin['role'] ?? ''), ['owner','superadmin','admin','practice_admin','manufacturer']); // Manufacturer has same rights as superadmin
$tab = $_GET['tab'] ?? 'physicians';
$msg='';

/* Physician scope: only those mapped to this admin unless owner/superadmin/practice_admin */
/* IMPORTANT: Exclude superadmin from Physicians tab - they belong in admin portal, not as physicians */
$physQuery = "
  SELECT u.id, u.first_name, u.last_name, u.email, u.account_type, u.status, u.created_at, u.role, u.practice_name
  FROM users u
";
if (!$isOwner) {
  $physQuery .= " JOIN admin_physicians ap ON ap.physician_user_id = u.id WHERE ap.admin_id = :aid AND (u.role IS NULL OR u.role IN ('physician', 'practice_admin'))";
} else {
  $physQuery .= " WHERE (u.role IS NULL OR u.role IN ('physician', 'practice_admin'))";
}
$physQuery .= " ORDER BY u.created_at DESC LIMIT 300";
$physStmt = $pdo->prepare($physQuery);
if (!$isOwner) $physStmt->execute(['aid'=>$admin['id']]);
else $physStmt->execute();
$phys = $physStmt->fetchAll();

/* Employees list - only CollagenDirect staff */
$emps = $pdo->query("SELECT id, name, email, role, created_at FROM admin_users WHERE role IN ('employee', 'admin') ORDER BY created_at DESC LIMIT 200")->fetchAll();

/* Manufacturer list - separate from employees */
$manufacturers = $pdo->query("SELECT id, name, email, role, created_at FROM admin_users WHERE role = 'manufacturer' ORDER BY created_at DESC LIMIT 200")->fetchAll();

/* Actions */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();
  $act = $_POST['action'] ?? '';

  if ($act==='create_employee' && $isOwner) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $password = $_POST['password'];

    $pdo->prepare("INSERT INTO admin_users(name,email,role,password_hash) VALUES(?,?,?,?)")
        ->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT)]);

    // Send welcome email
    send_provider_welcome_email($email, $name, $role, $password);

    $msg = ($role === 'manufacturer' ? 'Manufacturer' : 'Employee') . ' created and welcome email sent';
    $tab = ($role === 'manufacturer') ? 'manufacturer' : 'employees';
  }
  if ($act==='reset_emp_pw' && $isOwner) {
    $pdo->prepare("UPDATE admin_users SET password_hash=? WHERE id=?")->execute([password_hash($_POST['newpw'], PASSWORD_DEFAULT), (int)$_POST['emp_id']]);
    $msg='Employee password updated'; $tab='employees';
  }
  if ($act==='delete_emp' && $isOwner) {
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
    $userId = bin2hex(random_bytes(16));

    if ($providerType === 'practice') {
      // Creating a practice owner (practice_admin)
      $practiceName = trim($_POST['practice_name'] ?? '');
      $pdo->prepare("INSERT INTO users(id,email,password_hash,first_name,last_name,practice_name,role,account_type,status,created_at,updated_at) VALUES(?,?,?,?,?,?,'practice_admin','referral','active',NOW(),NOW())")
          ->execute([$userId, $email, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName, $practiceName]);
      $msg = 'Practice owner created';
    } else {
      // Creating a physician and linking to practice
      $practiceId = $_POST['practice_id'] ?? '';
      $pdo->prepare("INSERT INTO users(id,email,password_hash,first_name,last_name,role,account_type,status,created_at,updated_at) VALUES(?,?,?,?,?,'physician','referral','active',NOW(),NOW())")
          ->execute([$userId, $email, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName]);

      // Link physician to practice via practice_physicians table
      if ($practiceId) {
        $pdo->prepare("INSERT INTO practice_physicians(practice_admin_id,physician_id,first_name,last_name,physician_email,created_at) VALUES(?,?,?,?,?,NOW())")
            ->execute([$practiceId, $userId, $firstName, $lastName, $email]);
      }
      $msg = 'Physician created and linked to practice';
    }

    // Map to this admin if not owner/superadmin (for regular admins with limited scope)
    if (!$isOwner && ($admin['role'] ?? '') !== 'practice_admin') {
      $pdo->prepare("INSERT IGNORE INTO admin_physicians(admin_id, physician_user_id) VALUES(?,?)")->execute([$admin['id'], $userId]);
    }

    // Send welcome email via SendGrid
    send_provider_welcome_email($email, "$firstName $lastName", $providerType === 'practice' ? 'Practice Owner' : 'Physician', $password);
    $msg .= ' - Welcome email sent';
  }
  if ($act==='reset_phys_pw') {
    $pdo->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?")->execute([password_hash($_POST['newpw'], PASSWORD_DEFAULT), $_POST['phys_id']]);
    $msg='Physician password updated';
  }
  if ($act==='delete_phys') {
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$_POST['phys_id']]);
    $pdo->prepare("DELETE FROM admin_physicians WHERE physician_user_id=?")->execute([$_POST['phys_id']]);
    $msg='Physician deleted';
  }
  if ($act==='map_phys' && !$isOwner && ($admin['role'] ?? '') !== 'practice_admin') {
    $pdo->prepare("INSERT IGNORE INTO admin_physicians(admin_id, physician_user_id) VALUES(?,?)")->execute([$admin['id'], $_POST['phys_id']]);
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
      $pdo->prepare("INSERT INTO admin_physicians(admin_id, physician_user_id, created_at) VALUES(?,?,NOW())")
          ->execute([$empId, $physId]);
      $assigned++;
    }

    $msg = "Assigned $assigned physician(s) to employee";
    $tab = 'employees';
  }
  header('Location: /admin/users.php?tab='.$tab); exit;
}
?>
<?php include __DIR__ . '/_header.php'; ?>

<div class="flex items-center justify-between mb-4">
  <div class="text-xl font-semibold">Users</div>
</div>

<?php if ($msg): ?><div class="mb-3 text-sm bg-teal-50 border border-teal-200 text-teal-700 p-2 rounded"><?=$msg?></div><?php endif; ?>

<div class="mb-4">
  <a class="px-3 py-2 border rounded-t <?=($tab==='physicians'?'bg-white border-b-0':'')?>" href="/admin/users.php?tab=physicians">Providers</a>
  <a class="px-3 py-2 border rounded-t <?=($tab==='employees'?'bg-white border-b-0':'')?>" href="/admin/users.php?tab=employees">Employees</a>
  <a class="px-3 py-2 border rounded-t <?=($tab==='manufacturer'?'bg-white border-b-0':'')?>" href="/admin/users.php?tab=manufacturer">Manufacturer</a>
</div>

<div class="bg-white border rounded-b rounded-r p-4">
<?php if ($tab==='physicians'): ?>
  <div class="grid grid-cols-3 gap-6">
    <div class="col-span-2">
      <table class="w-full text-sm">
        <thead class="border-b">
          <tr class="text-left">
            <th class="py-2">Name</th>
            <th class="py-2">Email</th>
            <th class="py-2">Type</th>
            <th class="py-2">Status</th>
            <th class="py-2">Joined</th>
            <th class="py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($phys as $u): ?>
          <tr class="border-b hover:bg-slate-50">
            <td class="py-3"><?=e(trim(($u['first_name']??'').' '.($u['last_name']??'')))?></td>
            <td class="py-3"><?=e($u['email'] ?? '')?></td>
            <td class="py-3"><?=e($u['account_type'] ?? '')?></td>
            <td class="py-3"><?=e($u['status'] ?? '')?></td>
            <td class="py-3"><?=e($u['created_at'] ?? '')?></td>
            <td class="py-3 space-x-2">
              <form method="post" class="inline"><?=csrf_field()?>
                <input type="hidden" name="action" value="reset_phys_pw">
                <input type="hidden" name="phys_id" value="<?=e($u['id'])?>">
                <input type="password" name="newpw" placeholder="New pw" class="border rounded px-2 py-0.5 text-xs" required>
                <button class="text-brand text-xs">Reset PW</button>
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

        <!-- Practice Name (for practice owners) -->
        <div id="practice-name-field" class="mb-2">
          <input class="border rounded px-2 py-1 w-full" name="practice_name" placeholder="Practice name">
        </div>

        <input class="border rounded px-2 py-1 w-full mb-2" name="first_name" placeholder="First name" required>
        <input class="border rounded px-2 py-1 w-full mb-2" name="last_name" placeholder="Last name" required>
        <input class="border rounded px-2 py-1 w-full mb-2" type="email" name="email" placeholder="Email" required>
        <input class="border rounded px-2 py-1 w-full mb-2" type="password" name="password" placeholder="Temp password" required>
        <button class="bg-brand text-white rounded px-3 py-1">Create</button>
      </form>

      <script>
      function toggleProviderFields() {
        const type = document.querySelector('input[name="provider_type"]:checked').value;
        const practiceSelectField = document.getElementById('practice-select-field');
        const practiceNameField = document.getElementById('practice-name-field');
        const practiceSelect = document.querySelector('select[name="practice_id"]');
        const practiceNameInput = document.querySelector('input[name="practice_name"]');

        if (type === 'physician') {
          practiceSelectField.style.display = 'block';
          practiceNameField.style.display = 'none';
          practiceSelect.required = true;
          practiceNameInput.required = false;
        } else {
          practiceSelectField.style.display = 'none';
          practiceNameField.style.display = 'block';
          practiceSelect.required = false;
          practiceNameInput.required = true;
        }
      }
      </script>
    </div>
  </div>
<?php elseif ($tab==='employees'): ?>
  <div class="grid grid-cols-2 gap-6">
    <div>
      <div class="font-semibold mb-2">Employees</div>
      <table class="w-full text-sm">
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
              <?php if ($isOwner): ?>
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
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
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
  </div>
<?php else: ?>
  <!-- Manufacturer Tab -->
  <div class="grid grid-cols-2 gap-6">
    <div>
      <div class="font-semibold mb-2">Manufacturer Representatives</div>
      <table class="w-full text-sm">
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
              <?php if ($isOwner): ?>
              <form method="post" class="inline"><?=csrf_field()?>
                <input type="hidden" name="action" value="reset_emp_pw"><input type="hidden" name="emp_id" value="<?=$m['id']?>">
                <input type="password" name="newpw" class="border rounded px-2 py-0.5 text-xs" placeholder="New pw" required>
                <button class="text-brand text-xs">Reset PW</button>
              </form>
              <form method="post" class="inline" onsubmit="return confirm('Delete manufacturer representative?')"><?=csrf_field()?>
                <input type="hidden" name="action" value="delete_emp"><input type="hidden" name="emp_id" value="<?=$m['id']?>">
                <button class="text-rose-600 text-xs">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
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
</script>

<?php include __DIR__ . '/_footer.php'; ?>
