<?php
// admin/login.php
declare(strict_types=1);
require __DIR__ . '/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
  id SERIAL PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role VARCHAR(50) DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $email = trim((string)($_POST['email'] ?? ''));
  $pass = (string)($_POST['password'] ?? '');
  if ($email && $pass) {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row && password_verify($pass, $row['password_hash'])) {
      $_SESSION['admin'] = ['id'=>$row['id'],'email'=>$row['email'],'name'=>$row['name'],'role'=>$row['role']];
      $next = $_GET['next'] ?? '/admin/index.php';
      header("Location: $next"); exit;
    } else { $err = 'Invalid credentials'; }
  } else { $err = 'Email and password required'; }
}

$cnt = (int)$pdo->query("SELECT COUNT(*) c FROM admin_users")->fetch()['c'];
if ($cnt === 0) {
  $bootstrapPass = password_hash('ChangeMe123!', PASSWORD_DEFAULT);
  try {
    $pdo->prepare("INSERT INTO admin_users(email,password_hash,name,role) VALUES(?,?,?,?) ON CONFLICT (email) DO NOTHING")
        ->execute(['admin@collagen.health',$bootstrapPass,'System Admin','owner']);
    $bootnote = "Bootstrap admin created: admin@collagen.health / ChangeMe123!  (change after login)";
  } catch (Exception $e) {
    // Already exists, that's okay
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login — CollagenHealth</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="min-h-screen flex items-center justify-center p-6">
    <form method="post" class="bg-white border rounded-xl shadow p-6 w-full max-w-sm">
      <h1 class="text-xl font-semibold mb-1">Admin Login</h1>
      <p class="text-sm text-slate-500 mb-4">Use your employee credentials.</p>
      <?php echo csrf_field(); ?>
      <?php if (!empty($bootnote)): ?>
        <div class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded p-2 mb-2"><?php echo e($bootnote); ?></div>
      <?php endif; ?>
      <?php if ($err): ?>
        <div class="text-sm bg-red-50 border border-red-200 text-red-700 rounded p-2 mb-3"><?php echo e($err); ?></div>
      <?php endif; ?>
      <label class="block text-sm mb-1">Email</label>
      <input class="w-full border rounded px-3 py-2 mb-3" type="email" name="email" required/>
      <label class="block text-sm mb-1">Password</label>
      <input class="w-full border rounded px-3 py-2 mb-4" type="password" name="password" required/>
      <button class="w-full bg-teal-500 hover:bg-teal-600 text-white font-semibold rounded px-3 py-2">Sign In</button>
    </form>
  </div>
</body>
</html>
