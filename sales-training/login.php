<?php
session_start();

// Database connection for sales rep authentication
require_once __DIR__ . '/../admin/db.php';

$error = '';
$success = '';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    session_start();
    $success = 'You have been logged out successfully.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (empty($password)) {
        $error = 'Please enter your password.';
    } else {
        // Check if this is a CollagenDirect employee
        if (preg_match('/@collagendirect\.health$/i', $email)) {
            // Employee login - validate against admin_users table
            try {
                $stmt = $pdo->prepare("
                    SELECT id, email, password_hash, name, role, status
                    FROM admin_users
                    WHERE LOWER(email) = ? AND status = 'active'
                ");
                $stmt->execute([$email]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && password_verify($password, $admin['password_hash'])) {
                    // Valid employee login
                    $_SESSION['user_email'] = $admin['email'];
                    $_SESSION['user_name'] = $admin['name'];
                    $_SESSION['user_type'] = 'employee';
                    $_SESSION['login_time'] = time();
                    $_SESSION['is_sales_rep'] = false;

                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (Exception $e) {
                $error = 'Login failed. Please try again.';
            }
        } else {
            // Sales rep / distributor login - check users table + sales_reps status
            try {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.email, u.password_hash, u.first_name, u.last_name,
                           sr.id as sales_rep_id, sr.status as rep_status, sr.company_name
                    FROM users u
                    INNER JOIN sales_reps sr ON sr.user_id = u.id
                    WHERE LOWER(u.email) = ?
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $error = 'No sales rep account found with this email. Contact your manager for access.';
                } elseif (!password_verify($password, $user['password_hash'])) {
                    $error = 'Invalid email or password.';
                } elseif ($user['rep_status'] !== 'active') {
                    // Provide specific messages based on status
                    switch ($user['rep_status']) {
                        case 'pending':
                            $error = 'Your account is pending approval. Please wait for your manager to approve your access.';
                            break;
                        case 'suspended':
                            $error = 'Your account has been suspended. Please contact your manager.';
                            break;
                        case 'terminated':
                            $error = 'Your account has been terminated. Contact support if you believe this is an error.';
                            break;
                        default:
                            $error = 'Your account is not active. Please contact your manager.';
                    }
                } else {
                    // Valid sales rep login
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                    $_SESSION['user_type'] = 'sales_rep';
                    $_SESSION['sales_rep_id'] = $user['sales_rep_id'];
                    $_SESSION['company_name'] = $user['company_name'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['is_sales_rep'] = true;

                    header('Location: index.php');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Login failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Sales Training Portal</title>
  <meta name="robots" content="noindex, nofollow">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: { teal: '#47c6be', blue: '#2a78ff', navy: '#0a2540' }
          }
        }
      }
    }
  </script>
  <style>
    body { font-feature-settings: 'cv11', 'ss01'; -webkit-font-smoothing: antialiased; }
  </style>
</head>
<body class="bg-gradient-to-br from-brand-navy via-slate-900 to-brand-navy min-h-screen flex items-center justify-center">

  <div class="w-full max-w-md px-6">

    <!-- Logo/Header -->
    <div class="text-center mb-8">
      <img src="/assets/collagendirect.png" alt="CollagenDirect" class="h-12 w-auto mx-auto mb-4">
      <h1 class="text-3xl font-black text-white mb-2">Sales Training Portal</h1>
      <p class="text-slate-400">For CollagenDirect Team & Partners</p>
    </div>

    <!-- Login Card -->
    <div class="bg-white rounded-3xl shadow-2xl p-8">

      <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <p class="text-sm text-red-800"><?php echo htmlspecialchars($error); ?></p>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
          <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <p class="text-sm text-emerald-800"><?php echo htmlspecialchars($success); ?></p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="POST" action="login.php">
        <!-- Email Field -->
        <div class="mb-4">
          <label for="email" class="block text-sm font-semibold text-gray-900 mb-2">
            Email Address
          </label>
          <input
            type="email"
            id="email"
            name="email"
            placeholder="you@example.com"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-brand-teal focus:outline-none transition"
            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
            required
          >
        </div>

        <!-- Password Field -->
        <div class="mb-6">
          <label for="password" class="block text-sm font-semibold text-gray-900 mb-2">
            Password
          </label>
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-brand-teal focus:outline-none transition"
            required
          >
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          class="w-full bg-gradient-to-r from-brand-teal to-emerald-500 text-white font-bold py-3 rounded-xl hover:shadow-lg hover:scale-105 transition-all"
        >
          Sign In
        </button>
      </form>

      <!-- Divider -->
      <div class="my-6 border-t border-gray-200"></div>

      <!-- Help Text -->
      <div class="text-center">
        <p class="text-sm text-gray-600 mb-2">Need access to the training portal?</p>
        <p class="text-xs text-gray-500 mb-3">Sales reps and distributors: Use the same login as your sales portal.</p>
        <a href="mailto:sales-support@collagendirect.health" class="text-sm text-brand-teal hover:text-brand-navy font-semibold transition">
          Contact Sales Support
        </a>
      </div>

    </div>

    <!-- Footer -->
    <div class="text-center mt-8 text-slate-400 text-sm">
      <p>&copy; 2025 CollagenDirect. Confidential & Proprietary.</p>
    </div>

  </div>

</body>
</html>
