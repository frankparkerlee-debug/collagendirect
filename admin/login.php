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
      // Regenerate session ID for security
      session_regenerate_id(true);
      $_SESSION['admin'] = ['id'=>$row['id'],'email'=>$row['email'],'name'=>$row['name'],'role'=>$row['role']];

      // Set persistent cookie (7 days)
      $params = session_get_cookie_params();
      setcookie(session_name(), session_id(), [
        'expires'  => time() + 60*60*24*7, // 7 days
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax'
      ]);

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
  <title>Admin Login — CollagenDirect</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --brand: #4DB8A8;
      --brand-dark: #3A9688;
      --brand-light: #E0F5F2;
      --ink: #1F2937;
      --ink-light: #6B7280;
      --muted: #9CA3AF;
      --bg-gray: #F9FAFB;
      --bg-sidebar: #F6F6F6;
      --border: #E5E7EB;
      --border-sidebar: #E8E8E9;
      --ring: rgba(77, 184, 168, 0.2);
      --radius: 0.5rem;
      --error: #EF4444;
      --error-light: #FEE2E2;
      --warning: #F59E0B;
      --warning-light: #FEF3C7;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
      background: #ffffff;
      color: var(--ink);
      -webkit-font-smoothing: antialiased;
    }
    .container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .form-card {
      background: white;
      border: 1px solid var(--border);
      border-radius: 0.75rem;
      padding: 2rem;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    h1 {
      font-size: 1.5rem;
      font-weight: 600;
      margin-bottom: 0.25rem;
      color: var(--ink);
    }
    .subtitle {
      font-size: 0.875rem;
      color: var(--muted);
      margin-bottom: 1.5rem;
    }
    .alert {
      padding: 0.75rem;
      border-radius: var(--radius);
      margin-bottom: 1rem;
      font-size: 0.875rem;
    }
    .alert-warning {
      background: var(--warning-light);
      border: 1px solid var(--warning);
      color: #92400E;
    }
    .alert-error {
      background: var(--error-light);
      border: 1px solid var(--error);
      color: #991B1B;
    }
    label {
      display: block;
      font-size: 0.875rem;
      font-weight: 500;
      margin-bottom: 0.5rem;
      color: var(--ink);
    }
    input {
      width: 100%;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 0.5rem 0.75rem;
      font-size: 0.875rem;
      margin-bottom: 1rem;
      font-family: inherit;
      transition: all 0.15s ease;
    }
    input:focus {
      outline: none;
      border-color: var(--brand);
      box-shadow: 0 0 0 3px var(--ring);
    }
    button {
      width: 100%;
      background: var(--brand);
      color: white;
      font-weight: 600;
      border-radius: var(--radius);
      padding: 0.625rem 1rem;
      font-size: 0.875rem;
      border: none;
      cursor: pointer;
      transition: background 0.15s ease;
      font-family: inherit;
    }
    button:hover {
      background: var(--brand-dark);
    }
  </style>
</head>
<body>
  <div class="container">
    <form method="post" class="form-card">
      <h1>Admin Login</h1>
      <p class="subtitle">Use your employee credentials.</p>
      <?php echo csrf_field(); ?>
      <?php if (!empty($bootnote)): ?>
        <div class="alert alert-warning"><?php echo e($bootnote); ?></div>
      <?php endif; ?>
      <?php if ($err): ?>
        <div class="alert alert-error"><?php echo e($err); ?></div>
      <?php endif; ?>
      <label>Email</label>
      <input type="email" name="email" required autofocus/>
      <label>Password</label>
      <input type="password" name="password" required/>
      <button type="submit">Sign In</button>
    </form>
  </div>
</body>
</html>
