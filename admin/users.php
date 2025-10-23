<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_admin();

$admin = current_admin();
$isOwner = in_array(($admin['role'] ?? ''), ['owner','superadmin','admin','practice_admin']); // practice_admin sees all physicians too
$tab = $_GET['tab'] ?? 'physicians';
$msg='';

/* Physician scope: only those mapped to this admin unless owner/superadmin/practice_admin */
$physQuery = "
  SELECT u.id, u.first_name, u.last_name, u.email, u.account_type, u.status, u.created_at
  FROM users u
";
if (!$isOwner) {
  $physQuery .= " JOIN admin_physicians ap ON ap.physician_user_id = u.id WHERE ap.admin_id = :aid ";
} else {
  $physQuery .= " WHERE 1=1 ";
}
$physQuery .= " ORDER BY u.created_at DESC LIMIT 300";
$physStmt = $pdo->prepare($physQuery);
if (!$isOwner) $physStmt->execute(['aid'=>$admin['id']]);
else $physStmt->execute();
$phys = $physStmt->fetchAll();

/* Employees list */
$emps = $pdo->query("SELECT id, name, email, role, created_at FROM admin_users ORDER BY created_at DESC LIMIT 200")->fetchAll();

/* Actions */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();
  $act = $_POST['action'] ?? '';

  if ($act==='create_employee' && $isOwner) {
    $pdo->prepare("INSERT INTO admin_users(name,email,role,password_hash) VALUES(?,?,?,?)")
        ->execute([trim($_POST['name']), trim($_POST['email']), trim($_POST['role']), password_hash($_POST['password'], PASSWORD_DEFAULT)]);
    $msg='Employee created'; $tab='employees';
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
    $pdo->prepare("INSERT INTO users(id,email,password_hash,first_name,last_name,account_type,status,created_at,updated_at) VALUES(?,?,?,?,?,'referral','active',NOW(),NOW())")
        ->execute([bin2hex(random_bytes(16)), trim($_POST['email']), password_hash($_POST['password'], PASSWORD_DEFAULT), trim($_POST['first_name']), trim($_POST['last_name'])]);
    // Map to this admin if not owner/practice_admin (only for regular admins with limited scope)
    if (!$isOwner && ($admin['role'] ?? '') !== 'practice_admin') {
      $uid = $pdo->lastInsertId(); // if not available (VARCHAR PK), fetch by email
      $uidRow = $pdo->prepare("SELECT id FROM users WHERE email=? ORDER BY created_at DESC LIMIT 1"); $uidRow->execute([trim($_POST['email'])]);
      $uid = $uidRow->fetch()['id'] ?? null;
      if ($uid) $pdo->prepare("INSERT IGNORE INTO admin_physicians(admin_id, physician_user_id) VALUES(?,?)")->execute([$admin['id'], $uid]);
    }
    $msg='Physician created';
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
  header('Location: /admin/users.php?tab='.$tab); exit;
}
?>
<?php include __DIR__ . '/_header.php'; ?>

<div class="flex items-center justify-between mb-4">
  <div class="text-xl font-semibold">Users</div>
</div>

<?php if ($msg): ?><div class="mb-3 text-sm bg-teal-50 border border-teal-200 text-teal-700 p-2 rounded"><?=$msg?></div><?php endif; ?>

<div class="mb-4">
  <a class="px-3 py-2 border rounded-t <?=($tab==='physicians'?'bg-white border-b-0':'')?>" href="/admin/users.php?tab=physicians">Physicians</a>
  <a class="px-3 py-2 border rounded-t <?=($tab==='employees'?'bg-white border-b-0':'')?>" href="/admin/users.php?tab=employees">Employees</a>
</div>

<div class="bg-white border rounded-b rounded-r p-4">
<?php if ($tab==='physicians'): ?>
  <div class="grid grid-cols-3 gap-6">
    <div class="col-span-2">
      <table class="w-full text-sm">
        <thead class="text-left text-slate-500"><tr><th class="py-2">Name</th><th>Email</th><th>Type</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($phys as $u): ?>
          <tr class="border-t">
            <td class="py-2"><?=e(trim(($u['first_name']??'').' '.($u['last_name']??'')))?></td>
            <td><?=e($u['email'] ?? '')?></td>
            <td><?=e($u['account_type'] ?? '')?></td>
            <td><?=e($u['status'] ?? '')?></td>
            <td><?=e($u['created_at'] ?? '')?></td>
            <td class="space-x-2">
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
      <div class="font-semibold mb-2">Add Physician</div>
      <form method="post" class="bg-slate-50 border rounded p-3 text-sm">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="create_phys"/>
        <input class="border rounded px-2 py-1 w-full mb-2" name="first_name" placeholder="First name" required>
        <input class="border rounded px-2 py-1 w-full mb-2" name="last_name" placeholder="Last name" required>
        <input class="border rounded px-2 py-1 w-full mb-2" type="email" name="email" placeholder="Email" required>
        <input class="border rounded px-2 py-1 w-full mb-2" type="password" name="password" placeholder="Temp password" required>
        <button class="bg-brand text-white rounded px-3 py-1">Create</button>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-cols-2 gap-6">
    <div>
      <div class="font-semibold mb-2">Employees</div>
      <table class="w-full text-sm">
        <thead class="text-left text-slate-500"><tr><th class="py-2">Name</th><th>Email</th><th>Role</th><th>Added</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($emps as $e): ?>
          <tr class="border-t">
            <td class="py-2"><?=e($e['name'])?></td><td><?=e($e['email'])?></td><td><?=e($e['role'])?></td><td><?=e($e['created_at'])?></td>
            <td class="space-x-2">
              <?php if ($isOwner): ?>
              <form method="post" class="inline"><?=csrf_field()?>
                <input type="hidden" name="action" value="reset_emp_pw"><input type="hidden" name="emp_id" value="<?=$e['id']?>">
                <input type="password" name="newpw" class="border rounded px-2 py-0.5 text-xs" placeholder="New pw" required>
                <button class="text-brand text-xs">Reset PW</button>
              </form>
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
          <input class="border rounded px-2 py-1" name="role" placeholder="Role (admin, ops, sales)" value="admin"/>
          <input class="border rounded px-2 py-1" type="password" name="password" placeholder="Temporary password" required/>
        </div>
        <button class="mt-2 bg-brand text-white rounded px-3 py-1">Create</button>
      </form>
    </div>
  </div>
<?php endif; ?>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
