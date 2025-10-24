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
    // Check admin_users table first
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    // If not found in admin_users, check users table for superadmin
    if (!$row) {
      $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, password_hash, role FROM users WHERE email = ? AND role = 'superadmin' LIMIT 1");
      $stmt->execute([$email]);
      $userRow = $stmt->fetch();
      if ($userRow) {
        // Convert users table row to admin format
        $row = [
          'id' => $userRow['id'],
          'email' => $userRow['email'],
          'name' => trim($userRow['first_name'] . ' ' . $userRow['last_name']),
          'role' => 'superadmin',
          'password_hash' => $userRow['password_hash']
        ];
      }
    }

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

      <!-- HIPAA Security & Trust Messaging -->
      <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border)">
        <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;margin-bottom:0.75rem">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#10b981">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
          </svg>
          <span style="font-weight:600;font-size:0.8125rem;color:#10b981">HIPAA Compliant & Secure</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;font-size:0.6875rem;color:var(--muted);text-align:center">
          <div style="display:flex;flex-direction:column;align-items:center;gap:0.25rem">
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24" style="color:#6b7280">
              <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"></path>
            </svg>
            <span>256-bit SSL</span>
          </div>
          <div style="display:flex;flex-direction:column;align-items:center;gap:0.25rem">
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24" style="color:#6b7280">
              <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM9 8V6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9z"></path>
            </svg>
            <span>Secure PHI</span>
          </div>
          <div style="display:flex;flex-direction:column;align-items:center;gap:0.25rem">
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24" style="color:#6b7280">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path>
            </svg>
            <span>Audit Logs</span>
          </div>
          <div style="display:flex;flex-direction:column;align-items:center;gap:0.25rem">
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24" style="color:#6b7280">
              <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"></path>
            </svg>
            <span>BAA Compliant</span>
          </div>
        </div>
        <p style="margin-top:0.75rem;text-align:center;font-size:0.6875rem;color:var(--muted);line-height:1.4">
          Protected with enterprise-grade security and full HIPAA compliance
        </p>
      </div>
    </form>
  </div>
</body>
</html>
